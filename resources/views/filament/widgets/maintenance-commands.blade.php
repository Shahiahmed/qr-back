<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-wrench-screwdriver"
        icon-color="warning"
    >
        <x-slot name="heading">Обслуживание</x-slot>
        <x-slot name="description">
            Команды, которые проект запускает вручную после деплоя. Выполняются на сервере в момент нажатия.
        </x-slot>

        {{-- Live health readout --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($this->getChecks() as $check)
                <x-filament::badge :color="$check['color']">
                    {{ $check['label'] }}: {{ $check['value'] }}
                </x-filament::badge>
            @endforeach
        </div>

        {{-- Command buttons --}}
        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button
                color="primary"
                icon="heroicon-o-circle-stack"
                wire:click="runMigrate"
                wire:confirm="Применить все ожидающие миграции к базе данных?"
                wire:target="runMigrate"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="runMigrate">Применить миграции</span>
                <span wire:loading wire:target="runMigrate">Выполняется…</span>
            </x-filament::button>

            <x-filament::button
                color="gray"
                icon="heroicon-o-link"
                wire:click="runStorageLink"
                wire:target="runStorageLink"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="runStorageLink">Симлинк storage</span>
                <span wire:loading wire:target="runStorageLink">Выполняется…</span>
            </x-filament::button>

            <x-filament::button
                color="gray"
                icon="heroicon-o-trash"
                wire:click="runClearCache"
                wire:confirm="Очистить кэш конфига, роутов, представлений и приложения?"
                wire:target="runClearCache"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="runClearCache">Очистить кэш</span>
                <span wire:loading wire:target="runClearCache">Выполняется…</span>
            </x-filament::button>

            @if ($this->canSeedPlans())
                <x-filament::button
                    color="warning"
                    icon="heroicon-o-sparkles"
                    wire:click="runSeedPlans"
                    wire:confirm="Создать стартовые тарифы (Бесплатный · На 6 месяцев · На год)?"
                    wire:target="runSeedPlans"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="runSeedPlans">Засеять тарифы</span>
                    <span wire:loading wire:target="runSeedPlans">Выполняется…</span>
                </x-filament::button>
            @endif
        </div>

        {{-- Output of the last command --}}
        @if ($lastOutput !== null)
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Вывод: <code>{{ $lastLabel }}</code>
                </p>
                <pre class="mt-1 max-h-64 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $lastOutput }}</pre>
            </div>
        @endif

        <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
            «Засеять тарифы» показывается только на пустом каталоге — чтобы не затереть цены, изменённые в «Тарифах».
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
