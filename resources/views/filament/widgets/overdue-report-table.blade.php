@php
    use Filament\Support\Facades\FilamentView;
    use Filament\Widgets\View\WidgetsRenderHook;
@endphp

<x-filament-widgets::widget class="fi-wi-table">
    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_START, scopes: static::class) }}

    <div class="mb-3 flex justify-end">
        <x-filament::button
            type="button"
            icon="heroicon-o-printer"
            class="report-print-button"
            onclick="{{ $this->getPrintScript() }}"
        >
            طباعة التقرير
        </x-filament::button>
    </div>

    {{ $this->table }}

    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_END, scopes: static::class) }}
</x-filament-widgets::widget>
