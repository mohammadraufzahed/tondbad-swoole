<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

final class StubGenerator
{
    public function __construct(
        private readonly string $namespacePrefix,
        private readonly string $outputDir,
        private readonly string $implNamespace = 'App\\Grpc\\Services',
        private readonly string $implSuffix = 'Impl',
    ) {
    }

    /** @return array<string, string> path => content */
    public function generate(ProtoFile $file): array
    {
        $files = [];

        foreach ($file->services as $service) {
            $stubNamespace = $this->stubNamespace($service->package);
            $files[$this->filePath($stubNamespace, $service->shortName . 'GrpcAdapter')] = $this->generateAdapter($service, $stubNamespace);
            $files[$this->filePath($stubNamespace, $service->shortName . 'Client')] = $this->generateClient($service, $stubNamespace);
        }

        return $files;
    }

    private function stubNamespace(string $package): string
    {
        if ($package === '') {
            return $this->namespacePrefix;
        }

        return $this->namespacePrefix . '\\' . implode('\\', array_map('ucfirst', explode('.', $package)));
    }

    private function filePath(string $namespace, string $className): string
    {
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);

        return $this->outputDir . DIRECTORY_SEPARATOR . $relative . DIRECTORY_SEPARATOR . $className . '.php';
    }

    private function generateAdapter(ProtoService $service, string $namespace): string
    {
        $implClass = $this->implNamespace . '\\' . $service->shortName . $this->implSuffix;
        $imports = [
            'TondbadSwoole\\Grpc\\BindableService',
            'TondbadSwoole\\Grpc\\MethodDescriptor',
            'TondbadSwoole\\Grpc\\Request',
            'TondbadSwoole\\Grpc\\ServiceDefinition',
            'TondbadSwoole\\Grpc\\ServiceInvoker',
            $implClass,
        ];

        foreach ($service->methods as $method) {
            $imports[] = $method->inputPhpClass;
            $imports[] = $method->outputPhpClass;
        }

        $imports = array_unique($imports);
        sort($imports);

        $importLines = implode(
            "\n",
            array_map(fn (string $class) => 'use ' . $class . ';', $imports),
        );

        $methodLines = [];
        foreach ($service->methods as $method) {
            $inputShort = $this->shortClass($method->inputPhpClass);
            $outputShort = $this->shortClass($method->outputPhpClass);

            $methodLines[] = <<<PHP
                new MethodDescriptor(
                    name: '{$method->name}',
                    inputClass: {$inputShort}::class,
                    outputClass: {$outputShort}::class,
                    handler: function (Request \$request): {$outputShort} {
                        return ServiceInvoker::invoke(\$this->impl, '{$method->name}', \$request);
                    },
                ),
            PHP;
        }

        $methodsString = implode("\n", $methodLines);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$importLines}

class {$service->shortName}GrpcAdapter implements BindableService
{
    public function __construct(private readonly {$this->shortClass($implClass)} \$impl) {}

    public function bindService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: '{$service->name}',
            package: '{$service->package}',
            methods: [
{$methodsString}
            ],
        );
    }
}

PHP;
    }

    private function generateClient(ProtoService $service, string $namespace): string
    {
        $imports = [
            'TondbadSwoole\\Grpc\\Channel',
            'TondbadSwoole\\Grpc\\Stream',
        ];

        foreach ($service->methods as $method) {
            $imports[] = $method->inputPhpClass;
            $imports[] = $method->outputPhpClass;
        }

        $imports = array_unique($imports);
        sort($imports);

        $importLines = implode(
            "\n",
            array_map(fn (string $class) => 'use ' . $class . ';', $imports),
        );

        $methodLines = [];
        foreach ($service->methods as $method) {
            $inputShort = $this->shortClass($method->inputPhpClass);
            $outputShort = $this->shortClass($method->outputPhpClass);
            $methodName = lcfirst($method->name);

            if ($method->serverStreaming) {
                $methodLines[] = <<<PHP
    public function {$methodName}({$inputShort} \$request, array \$metadata = []): Stream
    {
        return \$this->channel->stream('/{$service->name}/{$method->name}', \$request, {$outputShort}::class, \$metadata);
    }
PHP;
            } else {
                $methodLines[] = <<<PHP
    public function {$methodName}({$inputShort} \$request, array \$metadata = []): {$outputShort}
    {
        return \$this->channel->invoke('/{$service->name}/{$method->name}', \$request, {$outputShort}::class, \$metadata);
    }
PHP;
            }
        }

        $methodsString = implode("\n\n", $methodLines);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$importLines}

class {$service->shortName}Client
{
    public function __construct(private readonly Channel \$channel) {}

{$methodsString}
}

PHP;
    }

    private function shortClass(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
