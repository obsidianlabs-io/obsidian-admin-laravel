<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Theme;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateThemeConfigRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'themeScheme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'auto'])],
            'themeColor' => ['sometimes', 'string', 'regex:/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'themeRadius' => ['sometimes', 'integer', 'between:0,16'],
            'headerHeight' => ['sometimes', 'integer', 'between:48,80'],
            'siderWidth' => ['sometimes', 'integer', 'between:180,320'],
            'siderCollapsedWidth' => ['sometimes', 'integer', 'between:48,120'],
            'layoutMode' => ['sometimes', 'string', Rule::in((array) config('theme.allowed_layout_modes', [
                'vertical',
                'horizontal',
                'vertical-mix',
                'vertical-hybrid-header-first',
                'top-hybrid-sidebar-first',
                'top-hybrid-header-first',
            ]))],
            'scrollMode' => ['sometimes', 'string', Rule::in((array) config('theme.allowed_scroll_modes', ['wrapper', 'content']))],
            'darkSider' => ['sometimes', 'boolean'],
            'themeSchemaVisible' => ['sometimes', 'boolean'],
            'headerFullscreenVisible' => ['sometimes', 'boolean'],
            'tabVisible' => ['sometimes', 'boolean'],
            'tabFullscreenVisible' => ['sometimes', 'boolean'],
            'breadcrumbVisible' => ['sometimes', 'boolean'],
            'footerVisible' => ['sometimes', 'boolean'],
            'footerHeight' => ['sometimes', 'integer', 'between:32,96'],
            'multilingualVisible' => ['sometimes', 'boolean'],
            'globalSearchVisible' => ['sometimes', 'boolean'],
            'themeConfigVisible' => ['sometimes', 'boolean'],
            'pageAnimate' => ['sometimes', 'boolean'],
            'pageAnimateMode' => ['sometimes', 'string', Rule::in((array) config('theme.allowed_page_animate_modes', [
                'fade',
                'fade-slide',
                'fade-bottom',
                'fade-scale',
                'zoom-fade',
                'zoom-out',
                'none',
            ]))],
            'fixedHeaderAndTab' => ['sometimes', 'boolean'],
        ];
    }
}
