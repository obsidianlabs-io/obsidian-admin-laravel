<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class CrudSchemaSearchFieldData
{
    public function __construct(
        public string $key,
        public string $type,
        public string $labelKey,
        public ?string $placeholderKey,
        public bool $clearable,
        public bool $filterable,
        public ?string $optionSource,
    ) {}

    /**
     * @param  array<string, mixed>  $field
     */
    public static function fromArray(array $field): self
    {
        $placeholderKey = trim((string) ($field['placeholderKey'] ?? ''));
        $optionSource = trim((string) ($field['optionSource'] ?? ''));

        return new self(
            key: trim((string) ($field['key'] ?? '')),
            type: trim((string) ($field['type'] ?? '')),
            labelKey: trim((string) ($field['labelKey'] ?? '')),
            placeholderKey: $placeholderKey !== '' ? $placeholderKey : null,
            clearable: (bool) ($field['clearable'] ?? false),
            filterable: (bool) ($field['filterable'] ?? false),
            optionSource: $optionSource !== '' ? $optionSource : null,
        );
    }

    /**
     * @return array{
     *   key: string,
     *   type: string,
     *   labelKey: string,
     *   placeholderKey?: string,
     *   clearable?: bool,
     *   filterable?: bool,
     *   optionSource?: string
     * }
     */
    public function toArray(): array
    {
        $payload = [
            'key' => $this->key,
            'type' => $this->type,
            'labelKey' => $this->labelKey,
        ];

        if ($this->placeholderKey !== null) {
            $payload['placeholderKey'] = $this->placeholderKey;
        }

        if ($this->clearable) {
            $payload['clearable'] = true;
        }

        if ($this->filterable) {
            $payload['filterable'] = true;
        }

        if ($this->optionSource !== null) {
            $payload['optionSource'] = $this->optionSource;
        }

        return $payload;
    }
}
