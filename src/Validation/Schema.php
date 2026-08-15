<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\ValidationAction;

final class Schema
{
    private string $type = 'mixed';
    /** @var array<string, Schema>|null */
    private ?array $fields = null;
    private ?Schema $itemSchema = null;
    /** @var list<mixed>|null */
    private ?array $enumValues = null;
    private mixed $literalValue = null;

    private bool $optional = false;
    private bool $nullable = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $strict = true;
    private bool $coerce = false;
    private bool $bail = false;
    private ?string $alias = null;
    /** @var array<string, string> */
    private array $messages = [];
    /** @var list<ValidationAction|callable> */
    private array $steps = [];

    private ?DatabaseManager $databaseManager = null;

    private function __construct()
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function mixed(): self
    {
        return new self();
    }

    public static function string(): self
    {
        $schema = new self();
        $schema->type = 'string';

        return $schema;
    }

    public static function int(): self
    {
        $schema = new self();
        $schema->type = 'int';

        return $schema;
    }

    public static function float(): self
    {
        $schema = new self();
        $schema->type = 'float';

        return $schema;
    }

    public static function bool(): self
    {
        $schema = new self();
        $schema->type = 'bool';

        return $schema;
    }

    public static function array(self $itemSchema): self
    {
        $schema = new self();
        $schema->type = 'array';
        $schema->itemSchema = $itemSchema;

        return $schema;
    }

    /**
     * @param array<string, Schema> $fields
     */
    public static function object(array $fields): self
    {
        $schema = new self();
        $schema->type = 'object';
        $schema->fields = $fields;

        return $schema;
    }

    /**
     * @param list<mixed> $values
     */
    public static function enum(mixed ...$values): self
    {
        if (count($values) === 1 && is_array($values[0])) {
            $values = $values[0];
        }

        $schema = new self();
        $schema->type = 'enum';
        $schema->enumValues = $values;

        return $schema;
    }

    public static function literal(mixed $value): self
    {
        $schema = new self();
        $schema->type = 'literal';
        $schema->literalValue = $value;

        return $schema;
    }

    public static function json(): self
    {
        $schema = new self();
        $schema->type = 'json';
        $schema->coerce = true;

        return $schema;
    }

    public function required(): self
    {
        return $this->cloneSchema(['optional' => false]);
    }

    public function optional(): self
    {
        return $this->cloneSchema(['optional' => true]);
    }

    public function nullable(): self
    {
        return $this->cloneSchema(['nullable' => true]);
    }

    public function default(mixed $value): self
    {
        return $this->cloneSchema(['hasDefault' => true, 'default' => $value]);
    }

    public function sometimes(): self
    {
        return $this->optional();
    }

    public function strict(): self
    {
        return $this->cloneWithOptions(['strict' => true, 'coerce' => false]);
    }

    public function lax(): self
    {
        return $this->cloneWithOptions(['strict' => false, 'coerce' => true]);
    }

    public function coerce(): self
    {
        return $this->cloneSchema(['coerce' => true]);
    }

    public function bail(): self
    {
        return $this->cloneSchema(['bail' => true]);
    }

    public function alias(string $inputKey): self
    {
        return $this->cloneSchema(['alias' => $inputKey]);
    }

    /**
     * @param array<string, string> $messages
     */
    public function messages(array $messages): self
    {
        return $this->cloneSchema(['messages' => array_merge($this->messages, $messages)]);
    }

    public function pipe(ValidationAction $action): self
    {
        $clone = $this->cloneSchema();
        $clone->steps[] = $action;

        return $clone;
    }

    public function transform(callable $callback): self
    {
        $clone = $this->cloneSchema();
        $clone->steps[] = new class ($callback) implements ValidationAction {
            public function __construct(private readonly mixed $callback) {}

            public function validate(mixed $value, ValidationContext $ctx): mixed
            {
                return ($this->callback)($value);
            }
        };

        return $clone;
    }

    public function refine(callable $callback, string $message = 'The :attribute field is invalid.'): self
    {
        $clone = $this->cloneSchema();
        $clone->steps[] = new class ($callback, $message) implements ValidationAction {
            public function __construct(
                private readonly mixed $callback,
                private readonly string $message,
            ) {}

            public function validate(mixed $value, ValidationContext $ctx): mixed
            {
                if (!(($this->callback)($value))) {
                    $ctx->addError('refine', $this->message);
                }

                return $value;
            }
        };

        return $clone;
    }

    public function min(int|float $limit): self
    {
        return $this->addStep('min', $limit, function (mixed $value, ValidationContext $ctx) use ($limit): mixed {
            $ok = match (true) {
                is_string($value) => mb_strlen($value) >= $limit,
                is_array($value) => count($value) >= $limit,
                is_numeric($value) => $value >= $limit,
                default => false,
            };

            if (!$ok) {
                $ctx->addError('min', 'The :attribute field must be at least :param0.', [$limit]);
            }

            return $value;
        });
    }

    public function max(int|float $limit): self
    {
        return $this->addStep('max', $limit, function (mixed $value, ValidationContext $ctx) use ($limit): mixed {
            $ok = match (true) {
                is_string($value) => mb_strlen($value) <= $limit,
                is_array($value) => count($value) <= $limit,
                is_numeric($value) => $value <= $limit,
                default => false,
            };

            if (!$ok) {
                $ctx->addError('max', 'The :attribute field must not be greater than :param0.', [$limit]);
            }

            return $value;
        });
    }

    public function gt(int|float $limit): self
    {
        return $this->numericComparison('gt', $limit, fn ($v) => $v > $limit);
    }

    public function gte(int|float $limit): self
    {
        return $this->numericComparison('gte', $limit, fn ($v) => $v >= $limit);
    }

    public function lt(int|float $limit): self
    {
        return $this->numericComparison('lt', $limit, fn ($v) => $v < $limit);
    }

    public function lte(int|float $limit): self
    {
        return $this->numericComparison('lte', $limit, fn ($v) => $v <= $limit);
    }

    private function numericComparison(string $rule, int|float $limit, callable $check): self
    {
        return $this->addStep($rule, $limit, function (mixed $value, ValidationContext $ctx) use ($rule, $limit, $check): mixed {
            if (!is_numeric($value) || !$check($value)) {
                $ctx->addError($rule, "The :attribute field must be {$rule} :param0.", [$limit]);
            }

            return $value;
        });
    }

    public function email(): self
    {
        return $this->addStep('email', 0, function (mixed $value, ValidationContext $ctx): mixed {
            if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $ctx->addError('email', 'The :attribute field must be a valid email address.');
            }

            return $value;
        });
    }

    public function url(): self
    {
        return $this->addStep('url', 0, function (mixed $value, ValidationContext $ctx): mixed {
            if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                $ctx->addError('url', 'The :attribute field must be a valid URL.');
            }

            return $value;
        });
    }

    public function uuid(): self
    {
        return $this->addStep('uuid', 0, function (mixed $value, ValidationContext $ctx): mixed {
            if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                $ctx->addError('uuid', 'The :attribute field must be a valid UUID.');
            }

            return $value;
        });
    }

    public function ip(): self
    {
        return $this->addStep('ip', 0, function (mixed $value, ValidationContext $ctx): mixed {
            if (!is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
                $ctx->addError('ip', 'The :attribute field must be a valid IP address.');
            }

            return $value;
        });
    }

    public function regex(string $pattern): self
    {
        return $this->addStep('regex', $pattern, function (mixed $value, ValidationContext $ctx) use ($pattern): mixed {
            if (!is_string($value) || preg_match($pattern, $value) !== 1) {
                $ctx->addError('regex', 'The :attribute field format is invalid.', [$pattern]);
            }

            return $value;
        });
    }

    /**
     * @param list<mixed> $values
     */
    public function in(array $values): self
    {
        return $this->addStep('in', $values, function (mixed $value, ValidationContext $ctx) use ($values): mixed {
            if (!in_array($value, $values, true)) {
                $ctx->addError('in', 'The selected :attribute is invalid.', [$values]);
            }

            return $value;
        });
    }

    /**
     * @param list<mixed> $values
     */
    public function notIn(array $values): self
    {
        return $this->addStep('not_in', $values, function (mixed $value, ValidationContext $ctx) use ($values): mixed {
            if (in_array($value, $values, true)) {
                $ctx->addError('not_in', 'The selected :attribute is invalid.', [$values]);
            }

            return $value;
        });
    }

    public function confirmed(): self
    {
        return $this->addStep('confirmed', 0, function (mixed $value, ValidationContext $ctx): mixed {
            $key = $ctx->getAttribute() . '_confirmation';
            $confirmation = $ctx->getData()[$key] ?? null;

            if ($value !== $confirmation) {
                $ctx->addError('confirmed', 'The :attribute confirmation does not match.');
            }

            return $value;
        });
    }

    public function unique(string $table, ?string $column = null, ?string $except = null): self
    {
        $column ??= 'id';

        return $this->addStep('unique', [$table, $column], function (mixed $value, ValidationContext $ctx) use ($table, $column, $except): mixed {
            $db = $ctx->getDatabaseManager();
            if ($db === null) {
                $ctx->addError('unique', 'Database manager is not available for unique validation.');
                return $value;
            }

            $query = $db->table($table)->where($column, '=', $value);
            if ($except !== null) {
                $query->where($column, '!=', $except);
            }

            if ($query->first() !== null) {
                $ctx->addError('unique', 'The :attribute has already been taken.');
            }

            return $value;
        });
    }

    public function exists(string $table, ?string $column = null): self
    {
        $column ??= 'id';

        return $this->addStep('exists', [$table, $column], function (mixed $value, ValidationContext $ctx) use ($table, $column): mixed {
            $db = $ctx->getDatabaseManager();
            if ($db === null) {
                $ctx->addError('exists', 'Database manager is not available for exists validation.');
                return $value;
            }

            if ($db->table($table)->where($column, '=', $value)->first() === null) {
                $ctx->addError('exists', 'The selected :attribute is invalid.');
            }

            return $value;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function safeParse(mixed $data, ?DatabaseManager $databaseManager = null, array $messages = []): ValidationResult
    {
        $errorBag = new ErrorBag();
        $ctx = new ValidationContext('value', is_array($data) ? $data : [], $errorBag, $databaseManager, $messages, $this->bail);
        $value = $this->process($data, $ctx, 'value');
        $errors = $errorBag->getErrors();

        return new ValidationResult(empty($errors), empty($errors) ? $value : null, $errors);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function parse(mixed $data, ?DatabaseManager $databaseManager = null, array $messages = []): mixed
    {
        return $this->safeParse($data, $databaseManager, $messages)->orFail();
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function process(mixed $value, ValidationContext $ctx, string $attribute, ?array $data = null): mixed
    {
        if ($value === null) {
            if ($this->nullable) {
                return null;
            }

            if ($this->hasDefault) {
                return $this->default;
            }

            if ($this->optional) {
                return $this->default ?? null;
            }

            $ctx->addError('required', 'The :attribute field is required.');
            return null;
        }

        if ($this->coerce) {
            $value = $this->coerceValue($value);
        }

        if (!$this->isValidType($value)) {
            $ctx->addError('type', "The :attribute field must be a valid {$this->type}.");
            return $value;
        }

        if ($this->type === 'object' && is_array($value)) {
            $value = $this->processObject($value, $ctx, $attribute);
        } elseif ($this->type === 'array' && is_array($value)) {
            $value = $this->processArray($value, $ctx, $attribute);
        }

        if ($ctx->hasErrors() && $this->bail) {
            return $value;
        }

        $childCtx = $ctx->withAttribute($attribute);
        foreach ($this->steps as $step) {
            if ($step instanceof ValidationAction) {
                $value = $step->validate($value, $childCtx);
            } elseif (is_callable($step)) {
                $value = $step($value, $childCtx);
            }

            if ($ctx->hasErrors() && $this->bail) {
                break;
            }
        }

        return $value;
    }

    private function coerceValue(mixed $value): mixed
    {
        if ($this->type === 'string' && !is_string($value)) {
            return (string) $value;
        }

        if ($this->type === 'int' && !is_int($value)) {
            if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                return (int) $value;
            }

            if (is_float($value) && (float) (int) $value === $value) {
                return (int) $value;
            }
        }

        if ($this->type === 'float' && !is_float($value)) {
            if (is_string($value) || is_int($value)) {
                $converted = filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($converted !== false) {
                    return $converted;
                }
            }
        }

        if ($this->type === 'bool' && !is_bool($value) && (is_string($value) || is_int($value))) {
            $converted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($converted !== null) {
                return $converted;
            }
        }

        if ($this->type === 'array' && is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if ($this->type === 'object' && is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if ($this->type === 'json' && is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    private function isValidType(mixed $value): bool
    {
        return match ($this->type) {
            'mixed' => true,
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value),
            'enum' => in_array($value, $this->enumValues ?? [], true),
            'literal' => $value === $this->literalValue,
            'json' => !is_string($value),
            default => false,
        };
    }

    private function processObject(array $data, ValidationContext $ctx, string $attribute): array
    {
        if ($this->fields === null) {
            return $data;
        }

        $knownKeys = [];
        foreach ($this->fields as $name => $schema) {
            $knownKeys[$name] = true;
            if ($schema->alias !== null) {
                $knownKeys[$schema->alias] = true;
            }
        }

        $unknown = array_diff_key($data, $knownKeys);
        if ($this->strict && !empty($unknown)) {
            $keys = implode(', ', array_keys($unknown));
            $ctx->addError('strict', "The :attribute object contains unknown keys: {$keys}.");
            return $data;
        }

        $result = [];
        foreach ($this->fields as $name => $schema) {
            $keys = array_filter([$schema->alias, $name]);
            $childAttribute = $attribute === 'value' ? $name : $attribute . '.' . $name;
            $childData = $data;
            $childValue = null;
            $found = false;

            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    $childValue = $data[$key];
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $result[$name] = $schema->process($childValue, $ctx->withAttribute($childAttribute, $childData), $childAttribute, $childData);
            } elseif ($schema->hasDefault) {
                $result[$name] = $schema->default;
            } elseif ($schema->nullable) {
                $result[$name] = $schema->default ?? null;
            } elseif ($schema->optional) {
                continue;
            } else {
                $schema->process(null, $ctx->withAttribute($childAttribute, $childData), $childAttribute, $childData);
                $result[$name] = null;
            }

            if ($this->bail && $ctx->hasErrors()) {
                break;
            }
        }

        return $result;
    }

    private function processArray(array $data, ValidationContext $ctx, string $attribute): array
    {
        if ($this->itemSchema === null) {
            return $data;
        }

        $result = [];
        foreach ($data as $index => $item) {
            $childAttribute = $attribute . '.' . $index;
            $result[] = $this->itemSchema->process($item, $ctx->withAttribute($childAttribute, $data), $childAttribute, $data);

            if ($this->bail && $ctx->hasErrors()) {
                break;
            }
        }

        return $result;
    }

    private function addStep(string $rule, mixed $param, callable $callback): self
    {
        $clone = $this->cloneSchema();
        $clone->steps[] = new class ($callback) implements ValidationAction {
            public function __construct(private readonly mixed $callback) {}

            public function validate(mixed $value, ValidationContext $ctx): mixed
            {
                return ($this->callback)($value, $ctx);
            }
        };

        return $clone;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cloneSchema(array $overrides = []): self
    {
        $clone = new self();
        $clone->type = $this->type;
        $clone->fields = $this->fields;
        $clone->itemSchema = $this->itemSchema;
        $clone->enumValues = $this->enumValues;
        $clone->literalValue = $this->literalValue;
        $clone->optional = $this->optional;
        $clone->nullable = $this->nullable;
        $clone->default = $this->default;
        $clone->hasDefault = $this->hasDefault;
        $clone->strict = $this->strict;
        $clone->coerce = $this->coerce;
        $clone->bail = $this->bail;
        $clone->alias = $this->alias;
        $clone->messages = $this->messages;
        $clone->steps = $this->steps;

        foreach ($overrides as $key => $value) {
            $clone->$key = $value;
        }

        return $clone;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cloneWithOptions(array $overrides): self
    {
        $clone = $this->cloneSchema($overrides);

        if ($clone->fields !== null) {
            $fields = [];
            foreach ($clone->fields as $name => $field) {
                $fields[$name] = $overrides['coerce'] ?? false ? $field->lax() : $field;
                if ($overrides['strict'] ?? false) {
                    $fields[$name] = $field->strict();
                }
            }
            $clone->fields = $fields;
        }

        if ($clone->itemSchema !== null) {
            $clone->itemSchema = $overrides['coerce'] ?? false ? $clone->itemSchema->lax() : $clone->itemSchema;
            if ($overrides['strict'] ?? false) {
                $clone->itemSchema = $clone->itemSchema->strict();
            }
        }

        return $clone;
    }
}
