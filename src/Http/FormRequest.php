<?php

declare(strict_types=1);

namespace TondbadSwoole\Http;

use OpenSwoole\Http\Request as SwooleRequest;
use ReflectionClass;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Attributes\Field;
use TondbadSwoole\Validation\DtoFactory;
use TondbadSwoole\Validation\ValidationException;

abstract class FormRequest extends Request
{
    protected mixed $validatedData = null;

    public function __construct(SwooleRequest $request)
    {
        parent::__construct($request);

        if (!$this->authorize()) {
            throw new \RuntimeException('This action is unauthorized.');
        }

        $rules = $this->rules();

        if ($rules !== []) {
            $this->validatedData = $this->validate($rules);

            return;
        }

        $this->hydrateFromAttributes();
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>|$this
     */
    public function validated(): mixed
    {
        return $this->validatedData ?? [];
    }

    private function hydrateFromAttributes(): void
    {
        $schema = DtoFactory::schema(static::class, false);
        $result = $schema->safeParse($this->all(), $this->resolveDatabaseManager());

        if (!$result->valid) {
            throw ValidationException::fromErrors($result->errors);
        }

        $data = $result->data;
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $attributes = $property->getAttributes(Field::class);
            if ($attributes === []) {
                continue;
            }

            /** @var Field $field */
            $field = $attributes[0]->newInstance();
            $name = $property->getName();
            $key = $field->alias ?? $name;

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif (array_key_exists($key, $data)) {
                $value = $data[$key];
            } elseif ($field->default !== null) {
                $value = $field->default;
            } elseif ($property->hasDefaultValue()) {
                $value = $property->getDefaultValue();
            } else {
                $value = null;
            }

            $property->setAccessible(true);
            $property->setValue($this, $value);
        }

        $this->validatedData = $this;
    }

    private function resolveDatabaseManager(): ?DatabaseManager
    {
        if (!function_exists('app') || app() === null) {
            return null;
        }

        try {
            return app()->container->make(DatabaseManager::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
