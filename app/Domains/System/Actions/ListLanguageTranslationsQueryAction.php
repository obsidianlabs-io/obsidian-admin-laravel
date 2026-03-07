<?php

declare(strict_types=1);

namespace App\Domains\System\Actions;

use App\Domains\System\Models\LanguageTranslation;
use App\DTOs\Language\ListLanguageTranslationsInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListLanguageTranslationsQueryAction
{
    /**
     * @return Builder<LanguageTranslation>
     */
    public function handle(ListLanguageTranslationsInputDTO $input): Builder
    {
        $query = LanguageTranslation::query()
            ->with('language:id,code,name,status')
            ->select(['id', 'language_id', 'translation_key', 'translation_value', 'description', 'status', 'created_at', 'updated_at']);

        if ($input->locale !== '') {
            $query->whereHas('language', static function (Builder $builder) use ($input): void {
                $builder->where('code', $input->locale);
            });
        }

        if ($input->keyword !== '') {
            $query->where(static function (Builder $builder) use ($input): void {
                $builder->where('translation_key', 'like', '%'.$input->keyword.'%')
                    ->orWhere('translation_value', 'like', '%'.$input->keyword.'%');
            });
        }

        if ($input->status !== '') {
            $query->where('status', $input->status);
        }

        return $query;
    }
}
