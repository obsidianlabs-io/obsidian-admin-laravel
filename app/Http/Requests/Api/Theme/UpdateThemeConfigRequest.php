<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Theme;

use App\DTOs\Theme\UpdateThemeConfigInputDTO;
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

    public function toDTO(): UpdateThemeConfigInputDTO
    {
        $validated = $this->validated();

        return new UpdateThemeConfigInputDTO(
            themeScheme: array_key_exists('themeScheme', $validated) ? (string) $validated['themeScheme'] : null,
            themeColor: array_key_exists('themeColor', $validated) ? (string) $validated['themeColor'] : null,
            themeRadius: array_key_exists('themeRadius', $validated) ? (int) $validated['themeRadius'] : null,
            headerHeight: array_key_exists('headerHeight', $validated) ? (int) $validated['headerHeight'] : null,
            siderWidth: array_key_exists('siderWidth', $validated) ? (int) $validated['siderWidth'] : null,
            siderCollapsedWidth: array_key_exists('siderCollapsedWidth', $validated) ? (int) $validated['siderCollapsedWidth'] : null,
            layoutMode: array_key_exists('layoutMode', $validated) ? (string) $validated['layoutMode'] : null,
            scrollMode: array_key_exists('scrollMode', $validated) ? (string) $validated['scrollMode'] : null,
            darkSider: array_key_exists('darkSider', $validated) ? filter_var($validated['darkSider'], FILTER_VALIDATE_BOOLEAN) : null,
            themeSchemaVisible: array_key_exists('themeSchemaVisible', $validated)
                ? filter_var($validated['themeSchemaVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            headerFullscreenVisible: array_key_exists('headerFullscreenVisible', $validated)
                ? filter_var($validated['headerFullscreenVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            tabVisible: array_key_exists('tabVisible', $validated) ? filter_var($validated['tabVisible'], FILTER_VALIDATE_BOOLEAN) : null,
            tabFullscreenVisible: array_key_exists('tabFullscreenVisible', $validated)
                ? filter_var($validated['tabFullscreenVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            breadcrumbVisible: array_key_exists('breadcrumbVisible', $validated)
                ? filter_var($validated['breadcrumbVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            footerVisible: array_key_exists('footerVisible', $validated)
                ? filter_var($validated['footerVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            footerHeight: array_key_exists('footerHeight', $validated) ? (int) $validated['footerHeight'] : null,
            multilingualVisible: array_key_exists('multilingualVisible', $validated)
                ? filter_var($validated['multilingualVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            globalSearchVisible: array_key_exists('globalSearchVisible', $validated)
                ? filter_var($validated['globalSearchVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            themeConfigVisible: array_key_exists('themeConfigVisible', $validated)
                ? filter_var($validated['themeConfigVisible'], FILTER_VALIDATE_BOOLEAN)
                : null,
            pageAnimate: array_key_exists('pageAnimate', $validated)
                ? filter_var($validated['pageAnimate'], FILTER_VALIDATE_BOOLEAN)
                : null,
            pageAnimateMode: array_key_exists('pageAnimateMode', $validated) ? (string) $validated['pageAnimateMode'] : null,
            fixedHeaderAndTab: array_key_exists('fixedHeaderAndTab', $validated)
                ? filter_var($validated['fixedHeaderAndTab'], FILTER_VALIDATE_BOOLEAN)
                : null,
        );
    }
}
