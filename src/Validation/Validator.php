<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;
use TondbadSwoole\Validation\Rules\After;
use TondbadSwoole\Validation\Rules\Alpha;
use TondbadSwoole\Validation\Rules\AlphaDash;
use TondbadSwoole\Validation\Rules\AlphaNum;
use TondbadSwoole\Validation\Rules\ArrayRule;
use TondbadSwoole\Validation\Rules\Before;
use TondbadSwoole\Validation\Rules\Boolean;
use TondbadSwoole\Validation\Rules\Confirmed;
use TondbadSwoole\Validation\Rules\Date;
use TondbadSwoole\Validation\Rules\DateFormat;
use TondbadSwoole\Validation\Rules\Different;
use TondbadSwoole\Validation\Rules\Digits;
use TondbadSwoole\Validation\Rules\DigitsBetween;
use TondbadSwoole\Validation\Rules\Email;
use TondbadSwoole\Validation\Rules\Exists;
use TondbadSwoole\Validation\Rules\In;
use TondbadSwoole\Validation\Rules\IntRule;
use TondbadSwoole\Validation\Rules\Ip;
use TondbadSwoole\Validation\Rules\Json;
use TondbadSwoole\Validation\Rules\Max;
use TondbadSwoole\Validation\Rules\Min;
use TondbadSwoole\Validation\Rules\NotIn;
use TondbadSwoole\Validation\Rules\Numeric;
use TondbadSwoole\Validation\Rules\Regex;
use TondbadSwoole\Validation\Rules\Required;
use TondbadSwoole\Validation\Rules\Same;
use TondbadSwoole\Validation\Rules\StringRule;
use TondbadSwoole\Validation\Rules\Unique;
use TondbadSwoole\Validation\Rules\Url;
use TondbadSwoole\Validation\Rules\Uuid;

class Validator
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * @var array<string, Rule>
     */
    private array $ruleRegistry = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly ?DatabaseManager $databaseManager = null,
    ) {
        $this->registerDefaultRules();
    }

    /**
     * Register a custom rule or override a built-in one.
     */
    public function extend(string $name, Rule $rule): self
    {
        $this->ruleRegistry[trim($name)] = $rule;

        return $this;
    }

    public function fails(): bool
    {
        $this->validate();

        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validated = [];

        foreach ($this->rules as $attribute => $rules) {
            if (array_key_exists($attribute, $this->data)) {
                $validated[$attribute] = $this->data[$attribute];
            }
        }

        return $validated;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $this->validate();

        return $this->errors;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors();
    }

    private function validate(): void
    {
        if (!empty($this->errors)) {
            return;
        }

        foreach ($this->rules as $attribute => $rules) {
            $this->validateAttribute($attribute, $rules);
        }
    }

    /**
     * @param string|list<string> $rules
     */
    private function validateAttribute(string $attribute, mixed $rules): void
    {
        if (!is_array($rules)) {
            $rules = explode('|', (string) $rules);
        }

        $value = $this->data[$attribute] ?? null;
        $hasAttribute = array_key_exists($attribute, $this->data);

        if (in_array('sometimes', $rules, true) && !$hasAttribute) {
            return;
        }

        $bail = false;

        foreach ($rules as $rule) {
            $rule = trim((string) $rule);

            if ($rule === '') {
                continue;
            }

            if ($rule === 'sometimes') {
                continue;
            }

            if ($rule === 'bail') {
                $bail = true;

                continue;
            }

            if ($rule === 'nullable' && ($value === null || $value === '')) {
                break;
            }

            if ($rule === 'nullable') {
                continue;
            }

            if (!$this->passesRule($attribute, $value, $rule)) {
                $this->addError($attribute, $rule);

                if ($bail) {
                    break;
                }
            }
        }
    }

    private function passesRule(string $attribute, mixed $value, string $rule): bool
    {
        [$name, $parameters] = $this->parseRule($rule);

        $ruleInstance = $this->ruleRegistry[$name] ?? null;

        if ($ruleInstance === null) {
            return false;
        }

        return $ruleInstance->passes($value, $attribute, $parameters, $this->data, $this->databaseManager);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $params] = explode(':', $rule, 2);

            return [trim($name), array_map('trim', explode(',', $params))];
        }

        return [trim($rule), []];
    }

    private function addError(string $attribute, string $rule): void
    {
        $this->errors[$attribute][] = $this->resolveMessage($attribute, $rule);
    }

    private function resolveMessage(string $attribute, string $rule): string
    {
        $key = "{$attribute}.{$rule}";

        if (isset($this->messages[$key])) {
            return $this->replacePlaceholders($this->messages[$key], $attribute, $this->parseRule($rule)[1]);
        }

        [$name, $parameters] = $this->parseRule($rule);

        if (isset($this->messages[$name])) {
            return $this->replacePlaceholders($this->messages[$name], $attribute, $parameters);
        }

        $ruleInstance = $this->ruleRegistry[$name] ?? null;

        $message = $ruleInstance !== null
            ? $ruleInstance->message($attribute, $parameters)
            : 'The :attribute field is invalid.';

        return $this->replacePlaceholders($message, $attribute, $parameters);
    }

    /**
     * @param list<string> $parameters
     */
    private function replacePlaceholders(string $message, string $attribute, array $parameters): string
    {
        $replacements = [
            ':attribute' => str_replace(['_', '.'], ' ', $attribute),
        ];

        foreach ($parameters as $index => $parameter) {
            $replacements[":param{$index}"] = $parameter;
        }

        $aliases = [':min', ':max', ':value', ':format', ':other'];
        foreach ($parameters as $index => $parameter) {
            if (isset($aliases[$index])) {
                $replacements[$aliases[$index]] = $parameter;
            }
        }

        return strtr($message, $replacements);
    }

    private function registerDefaultRules(): void
    {
        $rules = [
            new Required(),
            new Email(),
            new StringRule(),
            new IntRule(),
            new Numeric(),
            new Boolean(),
            new ArrayRule(),
            new Min(),
            new Max(),
            new In(),
            new NotIn(),
            new Regex(),
            new Confirmed(),
            new Same(),
            new Different(),
            new Unique(),
            new Exists(),
            new Date(),
            new DateFormat(),
            new Before(),
            new After(),
            new Uuid(),
            new Ip(),
            new Url(),
            new Alpha(),
            new AlphaNum(),
            new AlphaDash(),
            new Json(),
            new Digits(),
            new DigitsBetween(),
        ];

        foreach ($rules as $rule) {
            $this->ruleRegistry[$rule->getName()] = $rule;
        }

        $this->ruleRegistry['integer'] = $this->ruleRegistry['int'] ?? new IntRule();
        $this->ruleRegistry['boolean'] = $this->ruleRegistry['bool'] ?? new Boolean();
    }
}
