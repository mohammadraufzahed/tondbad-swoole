<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Exception;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Http\Attributes\Authenticate as AuthenticateAttribute;
use TondbadSwoole\Http\FormRequest;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\Attributes\Authorize as AuthorizeAttribute;
use TondbadSwoole\Routing\Attributes\Body;
use TondbadSwoole\Routing\Attributes\Controller as ControllerAttribute;
use TondbadSwoole\Routing\Attributes\CurrentUser;
use TondbadSwoole\Routing\Attributes\Guard as GuardAttribute;
use TondbadSwoole\Routing\Attributes\Header;
use TondbadSwoole\Routing\Attributes\Interceptor as InterceptorAttribute;
use TondbadSwoole\Routing\Attributes\Param;
use TondbadSwoole\Routing\Attributes\Pipe as PipeAttribute;
use TondbadSwoole\Routing\Attributes\Query;
use TondbadSwoole\Routing\Attributes\Req;
use TondbadSwoole\Routing\Attributes\RequireMfa as RequireMfaAttribute;
use TondbadSwoole\Routing\Attributes\Res;
use TondbadSwoole\Routing\Contracts\Guard;
use TondbadSwoole\Routing\Contracts\Interceptor;
use TondbadSwoole\Routing\Contracts\Pipe;
use TondbadSwoole\Routing\Contracts\UrlRoutable;

class HandlerInvoker
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array|callable $handler
     * @param array<string, string> $vars
     */
    public function invoke(array|callable $handler, Request $request, Response $response, array $vars): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            $this->ensureAuthorized($class, $method);
            $this->ensureAuthorize($class, $method);
            $this->ensureMfa($class, $method);
            $this->ensureGuards($class, $method, $request);

            $instance = $this->container->make($class);
            $reflection = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveDependencies($reflection, $request, $response, $vars);

            $next = function () use ($reflection, $instance, $dependencies): mixed {
                return $reflection->invokeArgs($instance, $dependencies);
            };

            $this->applyInterceptors($class, $method, $request, $response, $next);

            return;
        }

        $reflection = new ReflectionFunction($handler);
        $dependencies = $this->resolveDependencies($reflection, $request, $response, $vars);
        $reflection->invokeArgs($dependencies);
    }

    /**
     * @param class-string|null $class
     */
    private function ensureAuthorized(?string $class, ?string $method): void
    {
        if ($class === null || $method === null) {
            return;
        }

        $attributes = array_merge(
            (new \ReflectionClass($class))->getAttributes(AuthenticateAttribute::class),
            (new ReflectionMethod($class, $method))->getAttributes(AuthenticateAttribute::class),
        );

        if (count($attributes) === 0) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $auth = $attribute->guard === null ? auth() : auth($attribute->guard);

        if (!$auth->check()) {
            throw new AuthorizationException();
        }
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function ensureAuthorize(string $class, string $method): void
    {
        $attributes = array_merge(
            (new \ReflectionClass($class))->getAttributes(AuthorizeAttribute::class),
            (new ReflectionMethod($class, $method))->getAttributes(AuthorizeAttribute::class),
        );

        if (count($attributes) === 0) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $guard = $attribute->guard ?? null;
        $auth = $guard === null ? auth() : auth($guard);

        $gate = gate();

        if ($gate === null) {
            throw new AuthorizationException('Gate service is not available.');
        }

        $gate->authorize($attribute->ability, $attribute->arguments);
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function ensureMfa(string $class, string $method): void
    {
        $attributes = array_merge(
            (new \ReflectionClass($class))->getAttributes(RequireMfaAttribute::class),
            (new ReflectionMethod($class, $method))->getAttributes(RequireMfaAttribute::class),
        );

        if (count($attributes) === 0) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $guard = $attribute->guard ?? null;
        $session = auth($guard)->session();

        if ($session === null || !($session->claims['mfa_verified'] ?? false)) {
            throw new AuthorizationException('Multi-factor authentication is required.');
        }
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function ensureGuards(string $class, string $method, Request $request): void
    {
        $classReflection = new \ReflectionClass($class);

        $controllerAttributes = $classReflection->getAttributes(ControllerAttribute::class);

        if ($controllerAttributes !== []) {
            /** @var ControllerAttribute $controller */
            $controller = $controllerAttributes[0]->newInstance();

            foreach ($controller->guards() as $guardClass) {
                $this->assertGuardCan($request, $guardClass);
            }
        }

        $attributes = array_merge(
            $classReflection->getAttributes(GuardAttribute::class),
            (new ReflectionMethod($class, $method))->getAttributes(GuardAttribute::class),
        );

        foreach ($attributes as $attribute) {
            /** @var GuardAttribute $instance */
            $instance = $attribute->newInstance();
            $this->assertGuardCan($request, $instance->guard);
        }
    }

    /**
     * @param class-string $guardClass
     */
    private function assertGuardCan(Request $request, string $guardClass): void
    {
        $guard = $this->container->make($guardClass);

        if (!$guard instanceof Guard) {
            throw new Exception("Guard class '{$guardClass}' must implement Guard contract");
        }

        if (!$guard->can($request)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function applyInterceptors(string $class, string $method, Request $request, Response $response, callable $next): void
    {
        $attributes = array_merge(
            (new \ReflectionClass($class))->getAttributes(InterceptorAttribute::class),
            (new ReflectionMethod($class, $method))->getAttributes(InterceptorAttribute::class),
        );

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $interceptor = $this->container->make($instance->interceptor);

            if (!$interceptor instanceof Interceptor) {
                throw new Exception("Interceptor class '{$instance->interceptor}' must implement Interceptor contract");
            }

            $previous = $next;
            $next = function () use ($interceptor, $request, $response, $previous): mixed {
                return $interceptor->intercept($request, $response, $previous);
            };
        }

        $next();
    }

    /**
     * @return list<mixed>
     */
    private function resolveDependencies(ReflectionFunctionAbstract $reflection, Request $request, Response $response, array $vars): array
    {
        $dependencies = [];
        $methodPipes = $reflection->getAttributes(PipeAttribute::class);

        foreach ($reflection->getParameters() as $param) {
            $dependencies[] = $this->resolveParameter($param, $request, $response, $vars, $methodPipes);
        }

        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $param, Request $request, Response $response, array $vars, array $methodPipes = []): mixed
    {
        $value = $this->resolveRawParameter($param, $request, $response, $vars);

        return $this->applyPipes($param, $value, $methodPipes);
    }

    private function resolveRawParameter(ReflectionParameter $param, Request $request, Response $response, array $vars): mixed
    {
        $type = $param->getType();
        $name = $param->getName();

        $attributes = $param->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof Param) {
                $key = $instance->name() ?? $name;

                if (!array_key_exists($key, $vars)) {
                    if ($param->isDefaultValueAvailable()) {
                        return $param->getDefaultValue();
                    }

                    if ($param->allowsNull()) {
                        return null;
                    }

                    throw new Exception("Missing route parameter '{$key}'");
                }

                return $this->castValue($vars[$key], $type);
            }

            if ($instance instanceof Query) {
                $key = $instance->name() ?? $name;

                return $this->castValue($request->query($key), $type);
            }

            if ($instance instanceof Header) {
                $key = $instance->name() ?? $name;

                return $request->header($key);
            }

            if ($instance instanceof Body) {
                return $this->resolveBodyValue($param, $instance->name(), $request);
            }

            if ($instance instanceof Req) {
                return $request;
            }

            if ($instance instanceof Res) {
                return $response;
            }

            if ($instance instanceof CurrentUser) {
                $guard = $instance->guard ?? null;
                $user = auth($guard)->user();

                if ($user === null) {
                    if ($param->allowsNull()) {
                        return null;
                    }

                    throw new AuthorizationException();
                }

                $type = $param->getType();

                if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Authenticatable::class)) {
                    return $user;
                }

                return $user->getAuthIdentifier();
            }
        }

        if ($type instanceof ReflectionNamedType) {
            $value = $this->tryResolveNamedType($param, $type, $request, $response, $vars);

            if ($value !== null || ($value === null && $type->allowsNull())) {
                return $value;
            }
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionedType) {
                if (!$unionedType instanceof ReflectionNamedType) {
                    continue;
                }

                try {
                    $value = $this->tryResolveNamedType($param, $unionedType, $request, $response, $vars);

                    if ($value !== null || ($unionedType->getName() === 'null')) {
                        return $value;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        if (array_key_exists($name, $vars)) {
            return $this->castValue($vars[$name], $type);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw new Exception("Cannot resolve parameter '{$name}'");
    }

    /**
     * @param list<\ReflectionAttribute<PipeAttribute>> $methodPipes
     */
    private function applyPipes(ReflectionParameter $param, mixed $value, array $methodPipes = []): mixed
    {
        $attributes = array_merge($methodPipes, $param->getAttributes(PipeAttribute::class));

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $pipe = $this->container->make($instance->pipe);

            if (!$pipe instanceof Pipe) {
                throw new Exception("Pipe class '{$instance->pipe}' must implement Pipe contract");
            }

            $value = $pipe->transform($value, $param->getType());
        }

        return $value;
    }

    private function resolveBodyValue(ReflectionParameter $param, ?string $name, Request $request): mixed
    {
        $type = $param->getType();
        $typeName = null;

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
        }

        $data = $request->all();

        if ($name !== null) {
            $data = $data[$name] ?? null;
        }

        if ($typeName === null || $typeName === 'array' || $typeName === 'mixed') {
            return $data;
        }

        if ($typeName === Request::class || $typeName === SwooleRequest::class) {
            return $request;
        }

        if (!class_exists($typeName)) {
            return $this->castValue($data, $type);
        }

        return $this->buildDto($typeName, is_array($data) ? $data : []);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildDto(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->getConstructor() === null) {
            $instance = $reflection->newInstanceWithoutConstructor();

            foreach ($data as $key => $value) {
                if ($reflection->hasProperty($key)) {
                    $property = $reflection->getProperty($key);
                    $property->setAccessible(true);
                    $property->setValue($instance, $value);
                }
            }

            return $instance;
        }

        return $reflection->newInstance(...$data);
    }

    private function tryResolveNamedType(
        ReflectionParameter $param,
        ReflectionNamedType $type,
        Request $request,
        Response $response,
        array $vars
    ): mixed {
        $typeName = $type->getName();
        $name = $param->getName();

        if ($typeName === Request::class) {
            return $request;
        }

        if ($typeName === Response::class) {
            return $response;
        }

        if ($typeName === SwooleRequest::class) {
            return $request->getSwooleRequest();
        }

        if ($typeName === SwooleResponse::class) {
            return $response->getSwooleResponse();
        }

        if (is_subclass_of($typeName, FormRequest::class)) {
            return new $typeName($request->getSwooleRequest());
        }

        if (is_subclass_of($typeName, UrlRoutable::class) && array_key_exists($name, $vars)) {
            $model = new $typeName();

            return $model->resolveRouteBinding($vars[$name]);
        }

        if (array_key_exists($name, $vars)) {
            return $this->castValue($vars[$name], $type);
        }

        if (!$type->isBuiltin()) {
            return $this->container->make($typeName);
        }

        return null;
    }

    private function castValue(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            return match ($typeName) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => is_bool($value) ? $value : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value),
                'string' => (string) $value,
                'array' => is_array($value) ? $value : [$value],
                default => $value,
            };
        }

        return $value;
    }
}
