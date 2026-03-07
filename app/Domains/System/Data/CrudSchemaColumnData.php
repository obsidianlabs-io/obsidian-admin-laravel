<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class CrudSchemaColumnData
{
    public function __construct(
        public string $key,
        public string $type,
        public string $titleKey,
        public string $align,
        public ?int $width,
        public ?int $minWidth,
        public ?string $emptyLabelKey,
    ) {}

    /**
     * @param  array<string, mixed>  $column
     */
    public static function fromArray(array $column): self
    {
        $emptyLabelKey = trim((string) ($column['emptyLabelKey'] ?? ''));

        return new self(
            key: trim((string) ($column['key'] ?? '')),
            type: trim((string) ($column['type'] ?? '')),
            titleKey: trim((string) ($column['titleKey'] ?? '')),
            align: trim((string) ($column['align'] ?? 'left')),
            width: is_numeric($column['width'] ?? null) ? (int) $column['width'] : null,
            minWidth: is_numeric($column['minWidth'] ?? null) ? (int) $column['minWidth'] : null,
            emptyLabelKey: $emptyLabelKey !== '' ? $emptyLabelKey : null,
        );
    }

    /**
     * @return array{
     *   key: string,
     *   type: string,
     *   titleKey: string,
     *   align: string,
     *   width?: int,
     *   minWidth?: int,
     *   emptyLabelKey?: string
     * }
     */
    public function toArray(): array
    {
        $payload = [
            'key' => $this->key,
            'type' => $this->type,
            'titleKey' => $this->titleKey,
            'align' => $this->align,
        ];

        if ($this->width !== null) {
            $payload['width'] = $this->width;
        }

        if ($this->minWidth !== null) {
            $payload['minWidth'] = $this->minWidth;
        }

        if ($this->emptyLabelKey !== null) {
            $payload['emptyLabelKey'] = $this->emptyLabelKey;
        }

        return $payload;
    }
}
