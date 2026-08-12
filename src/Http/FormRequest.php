<?php

declare(strict_types=1);

namespace TondbadSwoole\Http;

use OpenSwoole\Http\Request as SwooleRequest;
use TondbadSwoole\Validation\ValidationException;

abstract class FormRequest extends Request
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $validatedData = null;

    public function __construct(SwooleRequest $request)
    {
        parent::__construct($request);

        if (!$this->authorize()) {
            throw new \RuntimeException('This action is unauthorized.');
        }

        $this->validatedData = $this->validate($this->rules());
    }

    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData ?? [];
    }
}
