<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Resources;

use App\Domains\System\Models\Language as LanguageModel;
use App\Domains\System\Models\LanguageTranslation;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LanguageTranslation */
class LanguageTranslationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $this->language;

        return [
            'id' => $this->id,
            'locale' => $language instanceof LanguageModel ? (string) $language->code : '',
            'localeName' => $language instanceof LanguageModel ? (string) $language->name : '',
            'translationKey' => (string) ($this->translation_key ?? ''),
            'translationValue' => (string) ($this->translation_value ?? ''),
            'description' => (string) ($this->description ?? ''),
            'status' => (string) $this->status,
            'version' => (string) ($this->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'createTime' => ApiDateTime::formatForRequest($this->created_at, $request),
            'updateTime' => ApiDateTime::formatForRequest($this->updated_at, $request),
        ];
    }
}
