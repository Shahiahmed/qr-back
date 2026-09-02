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

        {{--
            Command cards, grouped by purpose. Each row is a self-describing tile
            (icon + name + one-line hint + its button) so the operator never has
            to guess what a button does before pressing it.
        --}}
        @php
            $groups = [
                [
                    'title' => 'Деплой',
                    'hint' => 'После обновления кода на сервере',
                    'commands' => [
                        [
                            'method' => 'runMigrate',
                            'label' => 'Применить миграции',
                            'desc' => 'Накатить новые таблицы и колонки на базу',
                            'icon' => 'heroicon-o-circle-stack',
                            'color' => 'primary',
                            'confirm' => 'Применить все ожидающие миграции к базе данных?',
                        ],
                        [
                            'method' => 'runStorageLink',
                            'label' => 'Симлинк storage',
                            'desc' => 'Связать public/storage — иначе картинки отдают 404',
                            'icon' => 'heroicon-o-link',
                            'color' => 'gray',
                            'confirm' => null,
                        ],
                        [
                            'method' => 'runClearCache',
                            'label' => 'Очистить кэш',
                            'desc' => 'Сбросить кэш конфига, роутов и представлений',
                            'icon' => 'heroicon-o-trash',
                            'color' => 'gray',
                            'confirm' => 'Очистить кэш конфига, роутов, представлений и приложения?',
                        ],
                    ],
                ],
                [
                    'title' => 'Данные',
                    'hint' => 'Разовое заполнение и починка',
                    'commands' => [
                        [
                            'method' => 'runSeedPlans',
                            'label' => 'Засеять тарифы',
                            'desc' => 'Создать стартовые тарифы (Бесплатный · 6 месяцев · Год)',
                            'icon' => 'heroicon-o-sparkles',
                            'color' => 'warning',
                            'confirm' => $this->canSeedPlans()
                                ? 'Создать стартовые тарифы (Бесплатный · На 6 месяцев · На год)?'
                                : 'В каталоге уже есть тарифы. Сидер ПЕРЕЗАПИШЕТ цены дефолтных тарифов (Бесплатный · На 6 месяцев · На год) на значения по умолчанию. Ваши правки цен слетят. Продолжить?',
                        ],
                        [
                            'method' => 'runSeedPromo',
                            'label' => 'Засеять акцию',
                            'desc' => 'Заполнить текст акции по умолчанию (акция останется выключенной)',
                            'icon' => 'heroicon-o-megaphone',
                            'color' => 'warning',
                            'confirm' => $this->promoHasContent()
                                ? 'Текст акции уже заполнен. Сидер добавит значения только в ПУСТЫЕ поля — заполненное не тронет и акцию не включит. Продолжить?'
                                : 'Заполнить текст акции значениями по умолчанию (скидка 30%)? Акция при этом останется выключенной — включите её вручную на странице «Акция».',
                        ],
                        [
                            'method' => 'runBackfillReferralCodes',
                            'label' => 'Выдать реф. коды',
                            'desc' => 'Создать реферальные коды тем, кто регистрировался до реф. программы',
                            'icon' => 'heroicon-o-gift',
                            'color' => 'success',
                            'confirm' => 'Выдать реферальные коды всем аккаунтам без кода? Существующие коды не изменятся.',
                        ],
                    ],
                ],
            ];
        @endphp

        <div class="mt-6 space-y-6">
            @foreach ($groups as $group)
                <div>
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $group['title'] }}</h3>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $group['hint'] }}</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group['commands'] as $cmd)
                            <div class="flex flex-col justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                        'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $cmd['color'] === 'primary',
                                        'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400' => $cmd['color'] === 'warning',
                                        'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' => $cmd['color'] === 'success',
                                        'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $cmd['color'] === 'gray',
                                    ])>
                                        <x-filament::icon :icon="$cmd['icon']" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $cmd['label'] }}</p>
                                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $cmd['desc'] }}</p>
                                    </div>
                                </div>

                                <x-filament::button
                                    :color="$cmd['color']"
                                    size="sm"
                                    class="w-full"
                                    wire:click="{{ $cmd['method'] }}"
                                    :wire:confirm="$cmd['confirm']"
                                    wire:target="{{ $cmd['method'] }}"
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove wire:target="{{ $cmd['method'] }}">Запустить</span>
                                    <span wire:loading wire:target="{{ $cmd['method'] }}">Выполняется…</span>
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Output of the last command --}}
        @if ($lastOutput !== null)
            <div class="mt-6">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Вывод: <code>{{ $lastLabel }}</code>
                </p>
                <pre class="mt-1 max-h-64 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $lastOutput }}</pre>
            </div>
        @endif

        <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">
            «Засеять тарифы» на непустом каталоге перезапишет цены дефолтных тарифов на значения по умолчанию — запускайте только если хотите сбросить их.
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
