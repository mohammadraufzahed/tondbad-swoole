<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class Metadata
{
    /** @param array<array{0: string, 1: string}> $values */
    public function __construct(private array $values = [])
    {
    }

    public static function fromArray(array $headers): self
    {
        $values = [];

        foreach ($headers as $key => $header) {
            $key = (string) $key;

            if (is_array($header)) {
                foreach ($header as $value) {
                    $values[] = [$key, (string) $value];
                }
            } else {
                $values[] = [$key, (string) $header];
            }
        }

        return new self($values);
    }

    public function with(string $key, string $value): self
    {
        $values = $this->values;
        $values[] = [$key, $value];

        return new self($values);
    }

    /** @return string[] */
    public function get(string $key): array
    {
        $key = strtolower($key);

        return array_values(array_filter(
            array_map(
                fn (array $item) => strtolower($item[0]) === $key ? $item[1] : null,
                $this->values,
            ),
            fn (?string $value) => $value !== null,
        ));
    }

    public function first(string $key): ?string
    {
        $values = $this->get($key);

        return $values[0] ?? null;
    }

    /** @return array<array{0: string, 1: string}> */
    public function all(): array
    {
        return $this->values;
    }
}
