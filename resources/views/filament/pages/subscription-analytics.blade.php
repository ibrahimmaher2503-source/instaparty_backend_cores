<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getHeaderWidgetsColumns()"
        :widgets="$this->getHeaderWidgets()"
        :widget-data="$this->getWidgetData()"
    />
</x-filament-panels::page>
