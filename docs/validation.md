# Validation

Tondbād provides a standalone validation engine and `FormRequest` classes for controller input.

## Validation in controllers

```php
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

public function store(Request $request, Response $response): void
{
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
        'age' => 'nullable|integer|min:18',
    ]);

    $response->json($data);
}
```

On failure a `ValidationException` is thrown and converted to a `422` response.

## Built-in rules

| Rule | Description |
|---|---|
| `required` | Field must be present and not empty |
| `nullable` | Allows null/empty values; other rules skip null |
| `sometimes` | Validates only if field is present |
| `bail` | Stops on first failure |
| `email` | Valid email address |
| `string` | Value must be a string |
| `int` / `integer` / `numeric` | Numeric value |
| `min:n` / `max:n` | Length or numeric minimum/maximum |
| `confirmed` | Field `{name}_confirmation` must match |
| `same:field` | Must equal another field |
| `different:field` | Must differ from another field |
| `in:a,b,c` | Must be one of the listed values |
| `not_in:a,b,c` | Must not be any of the listed values |
| `json` | Valid JSON string |
| `uuid` | Valid UUID string |
| `unique:table,column` | Column value must not exist in the database |
| `exists:table,column` | Column value must exist in the database |
| `alpha` / `alpha_num` / `alpha_dash` | Character-only strings |
| `array` | Value must be an array |
| `boolean` | Boolean-ish value |
| `date` / `date_format:Y-m-d` | Date validation |
| `digits:n` / `digits_between:min,max` | Exact or range digit count |
| `ip` | Valid IP address |
| `regex:pattern` | Matches the given regex |
| `url` | Valid URL |
| `after:date` / `before:date` | Date comparisons |

## Custom rules

Implement `Rule`:

```php
<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class PhoneRule implements Rule
{
    public function getName(): string
    {
        return 'phone';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && preg_match('/^\+?[1-9]\d{1,14}$/', $value) === 1;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute field is not a valid phone number.';
    }
}
```

Register the rule on a validator instance:

```php
use TondbadSwoole\Validation\Validator;

$validator = new Validator($request->all(), ['phone' => 'phone']);
$validator->extend('phone', new PhoneRule());

$data = $validator->validated();
```

Then use it:

```php
$validator = new Validator($request->all(), [
    'phone' => 'required|phone',
]);
$validator->extend('phone', new PhoneRule());
```

## Custom error messages

```php
$data = $request->validate(
    ['email' => 'required|email'],
    ['email.required' => 'Please provide an email address.']
);
```

## Form requests

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use TondbadSwoole\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ];
    }
}
```

Use it as a controller parameter:

```php
public function store(StoreUserRequest $request, Response $response): void
{
    $data = $request->validated();

    $response->json($data);
}
```

`FormRequest` validates on construction and throws `ValidationException` when rules fail. Override `authorize()` to reject requests before validation runs.

## Validation rules format

Rules can be a pipe-separated string or an array:

```php
['email' => ['required', 'email']]
```
