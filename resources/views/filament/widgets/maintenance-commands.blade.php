{{--
    Styling note: the panel has no custom Filament theme (deploy.sh runs no npm
    build), so Tailwind utilities that Filament's own CSS doesn't ship render
    unstyled. Everything here is therefore plain CSS scoped under `.qm-mt`,
    built on the colour variables Filament injects at runtime (--primary-500,
    --gray-200 …) — it follows the panel palette and dark mode with no build.
--}}
@php
    $checks = $this->getChecks();
    $issues = collect($checks)->whereIn('color', ['warning', 'danger'])->count();

    $checkIcons = [
        'migrations' => 'heroicon-o-circle-stack',
        'storage' => 'heroicon-o-link',
        'plans' => 'heroicon-o-banknotes',
        'promo' => 'heroicon-o-megaphone',
        'referral' => 'heroicon-o-gift',
        'webp' => 'heroicon-o-photo',
    ];

    // `check` ties a tile to the health row it fixes, so it can say "нужно".
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
                    'tone' => 'primary',
                    'check' => 'migrations',
                    'confirm' => 'Применить все ожидающие миграции к базе данных?',
                ],
                [
                    'method' => 'runStorageLink',
                    'label' => 'Симлинк storage',
                    'desc' => 'Связать public/storage — иначе картинки отдают 404',
                    'icon' => 'heroicon-o-link',
                    'tone' => 'info',
                    'check' => 'storage',
                    'confirm' => null,
                ],
                [
                    'method' => 'runClearCache',
                    'label' => 'Очистить кэш',
                    'desc' => 'Сбросить кэш конфига, роутов и представлений',
                    'icon' => 'heroicon-o-arrow-path',
                    'tone' => 'gray',
                    'check' => null,
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
                    'desc' => 'Стартовые тарифы: Бесплатный · 6 месяцев · Год',
                    'icon' => 'heroicon-o-sparkles',
                    'tone' => 'warning',
                    'check' => 'plans',
                    'confirm' => $this->canSeedPlans()
                        ? 'Создать стартовые тарифы (Бесплатный · На 6 месяцев · На год)?'
                        : 'В каталоге уже есть тарифы. Сидер ПЕРЕЗАПИШЕТ цены дефолтных тарифов (Бесплатный · На 6 месяцев · На год) на значения по умолчанию. Ваши правки цен слетят. Продолжить?',
                ],
                [
                    'method' => 'runSeedPromo',
                    'label' => 'Засеять акцию',
                    'desc' => 'Текст акции по умолчанию, сама акция останется выключенной',
                    'icon' => 'heroicon-o-megaphone',
                    'tone' => 'warning',
                    'check' => 'promo',
                    'confirm' => $this->promoHasContent()
                        ? 'Текст акции уже заполнен. Сидер добавит значения только в ПУСТЫЕ поля — заполненное не тронет и акцию не включит. Продолжить?'
                        : 'Заполнить текст акции значениями по умолчанию (скидка 30%)? Акция при этом останется выключенной — включите её вручную на странице «Акция».',
                ],
                [
                    'method' => 'runBackfillReferralCodes',
                    'label' => 'Выдать реф. коды',
                    'desc' => 'Коды для тех, кто зарегистрировался до реф. программы',
                    'icon' => 'heroicon-o-gift',
                    'tone' => 'success',
                    'check' => 'referral',
                    'confirm' => 'Выдать реферальные коды всем аккаунтам без кода? Существующие коды не изменятся.',
                ],
            ],
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <style>
        .qm-mt { --qm-border: var(--gray-200); --qm-card: #fff; --qm-muted: var(--gray-500); --qm-text: var(--gray-950); }
        .dark .qm-mt { --qm-border: color-mix(in oklab, #fff 10%, transparent); --qm-card: color-mix(in oklab, #fff 4%, transparent); --qm-muted: var(--gray-400); --qm-text: #fff; }

        .qm-mt { display: flex; flex-direction: column; gap: 1.75rem; }
        .qm-mt svg { width: 1.25rem; height: 1.25rem; }

        /* Summary banner */
        .qm-mt-summary { display: flex; align-items: center; gap: .875rem; padding: .875rem 1rem; border-radius: .875rem; border: 1px solid; }
        .qm-mt-summary[data-ok="1"] { background: color-mix(in oklab, var(--success-500) 8%, transparent); border-color: color-mix(in oklab, var(--success-500) 25%, transparent); }
        .qm-mt-summary[data-ok="0"] { background: color-mix(in oklab, var(--warning-500) 9%, transparent); border-color: color-mix(in oklab, var(--warning-500) 30%, transparent); }
        .qm-mt-summary-icon { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; flex-shrink: 0; border-radius: 999px; color: #fff; }
        .qm-mt-summary[data-ok="1"] .qm-mt-summary-icon { background: var(--success-500); }
        .qm-mt-summary[data-ok="0"] .qm-mt-summary-icon { background: var(--warning-500); }
        .qm-mt-summary-title { font-size: .9375rem; font-weight: 600; color: var(--qm-text); }
        .qm-mt-summary-text { font-size: .8125rem; color: var(--qm-muted); margin-top: .125rem; }

        /* Health grid */
        .qm-mt-checks { display: grid; gap: .625rem; grid-template-columns: 1fr; }
        @media (min-width: 30rem) { .qm-mt-checks { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 64rem) { .qm-mt-checks { grid-template-columns: repeat(3, 1fr); } }
        .qm-mt-check { display: flex; align-items: center; gap: .75rem; padding: .75rem .875rem; border-radius: .75rem; border: 1px solid var(--qm-border); background: var(--qm-card); }
        .qm-mt-check-icon { position: relative; display: grid; place-items: center; width: 2rem; height: 2rem; flex-shrink: 0; border-radius: .5rem; background: color-mix(in oklab, var(--gray-500) 10%, transparent); color: var(--qm-muted); }
        .qm-mt-check-icon svg { width: 1.0625rem; height: 1.0625rem; }
        .qm-mt-dot { position: absolute; top: -.1875rem; right: -.1875rem; width: .625rem; height: .625rem; border-radius: 999px; box-shadow: 0 0 0 2px var(--qm-dot-ring, #fff); }
        .dark .qm-mt-dot { --qm-dot-ring: var(--gray-900); }
        .qm-mt-check-label { font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--qm-muted); }
        .qm-mt-check-value { font-size: .8125rem; font-weight: 600; color: var(--qm-text); line-height: 1.3; }
        .qm-mt-check[data-tone="success"] .qm-mt-dot { background: var(--success-500); }
        .qm-mt-check[data-tone="warning"] .qm-mt-dot { background: var(--warning-500); }
        .qm-mt-check[data-tone="danger"] .qm-mt-dot { background: var(--danger-500); }
        .qm-mt-check[data-tone="gray"] .qm-mt-dot { background: var(--gray-400); }
        .qm-mt-check[data-tone="warning"] { border-color: color-mix(in oklab, var(--warning-500) 35%, transparent); }
        .qm-mt-check[data-tone="danger"] { border-color: color-mix(in oklab, var(--danger-500) 40%, transparent); }
        .qm-mt-check[data-tone="danger"] .qm-mt-check-value { color: var(--danger-600); }
        .dark .qm-mt-check[data-tone="danger"] .qm-mt-check-value { color: var(--danger-400); }

        /* Command groups */
        .qm-mt-group-head { display: flex; align-items: baseline; flex-wrap: wrap; gap: .25rem .625rem; margin-bottom: .75rem; }
        .qm-mt-group-title { font-size: .875rem; font-weight: 600; color: var(--qm-text); }
        .qm-mt-group-hint { font-size: .75rem; color: var(--qm-muted); }
        .qm-mt-cards { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); }
        .qm-mt-card { position: relative; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem; padding: 1rem; border-radius: .875rem; border: 1px solid var(--qm-border); background: var(--qm-card); transition: border-color .15s, box-shadow .15s; }
        .qm-mt-card:hover { border-color: color-mix(in oklab, var(--qm-tone) 40%, var(--qm-border)); box-shadow: 0 6px 20px -12px color-mix(in oklab, var(--qm-tone) 45%, transparent); }
        .qm-mt-card[data-needed="1"] { border-color: color-mix(in oklab, var(--warning-500) 45%, transparent); }
        .qm-mt-card-top { display: flex; align-items: flex-start; gap: .75rem; }
        .qm-mt-card-icon { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; flex-shrink: 0; border-radius: .625rem; color: var(--qm-tone); background: color-mix(in oklab, var(--qm-tone) 12%, transparent); }
        .qm-mt-card-label { font-size: .875rem; font-weight: 600; color: var(--qm-text); }
        .qm-mt-card-desc { margin-top: .1875rem; font-size: .75rem; line-height: 1.5; color: var(--qm-muted); }
        .qm-mt-needed { position: absolute; top: .75rem; right: .75rem; padding: .125rem .5rem; border-radius: 999px; font-size: .6875rem; font-weight: 600; color: var(--warning-700); background: color-mix(in oklab, var(--warning-500) 16%, transparent); }
        .dark .qm-mt-needed { color: var(--warning-300); }
        .qm-mt-card[data-tone="primary"] { --qm-tone: var(--primary-500); }
        .qm-mt-card[data-tone="info"] { --qm-tone: var(--info-500); }
        .qm-mt-card[data-tone="warning"] { --qm-tone: var(--warning-500); }
        .qm-mt-card[data-tone="success"] { --qm-tone: var(--success-500); }
        .qm-mt-card[data-tone="gray"] { --qm-tone: var(--gray-500); }
        .qm-mt-card .fi-btn { width: 100%; }

        /* Command log — terminal look in both themes */
        .qm-mt-log { border-radius: .875rem; overflow: hidden; border: 1px solid var(--gray-800); background: var(--gray-950); }
        .qm-mt-log[data-failed="1"] { border-color: color-mix(in oklab, var(--danger-500) 60%, transparent); }
        .qm-mt-log-bar { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; background: var(--gray-900); border-bottom: 1px solid var(--gray-800); }
        .qm-mt-log-lights { display: flex; gap: .3125rem; }
        .qm-mt-log-lights i { width: .625rem; height: .625rem; border-radius: 999px; background: var(--gray-700); }
        .qm-mt-log[data-failed="0"] .qm-mt-log-lights i:last-child { background: var(--success-500); }
        .qm-mt-log[data-failed="1"] .qm-mt-log-lights i:first-child { background: var(--danger-500); }
        .qm-mt-log-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; color: var(--gray-400); }
        .qm-mt-log-close { display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: .375rem; color: var(--gray-400); cursor: pointer; }
        .qm-mt-log-close:hover { background: var(--gray-800); color: #fff; }
        .qm-mt-log-close svg { width: 1rem; height: 1rem; }
        .qm-mt-log pre { margin: 0; max-height: 16rem; overflow: auto; padding: .875rem 1rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; line-height: 1.6; color: var(--gray-200); white-space: pre-wrap; word-break: break-word; }

        .qm-mt-footnote { display: flex; gap: .5rem; font-size: .75rem; line-height: 1.5; color: var(--qm-muted); }
        .qm-mt-footnote svg { width: 1rem; height: 1rem; flex-shrink: 0; margin-top: .0625rem; color: var(--warning-500); }

        @media (prefers-reduced-motion: reduce) { .qm-mt-card { transition: none; } }
    </style>

    <x-filament::section icon="heroicon-o-wrench-screwdriver" icon-color="warning">
        <x-slot name="heading">Обслуживание</x-slot>
        <x-slot name="description">
            Команды, которые проект запускает вручную после деплоя. Выполняются на сервере в момент нажатия.
        </x-slot>

        <div class="qm-mt">
            {{-- One-line verdict, so a healthy server reads at a glance --}}
            <div class="qm-mt-summary" data-ok="{{ $issues === 0 ? 1 : 0 }}">
                <span class="qm-mt-summary-icon">
                    <x-filament::icon :icon="$issues === 0 ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle'" />
                </span>
                <div>
                    <p class="qm-mt-summary-title">
                        {{ $issues === 0 ? 'Сервер в порядке' : 'Требует внимания: '.$issues }}
                    </p>
                    <p class="qm-mt-summary-text">
                        {{ $issues === 0
                            ? 'Все проверки зелёные — запускать ничего не нужно.'
                            : 'Карточки с меткой «Нужно» ниже исправляют найденное.' }}
                    </p>
                </div>
            </div>

            {{-- Live health readout --}}
            <div class="qm-mt-checks">
                @foreach ($checks as $key => $check)
                    <div class="qm-mt-check" data-tone="{{ $check['color'] }}">
                        <span class="qm-mt-check-icon">
                            <x-filament::icon :icon="$checkIcons[$key] ?? 'heroicon-o-cog-6-tooth'" />
                            <span class="qm-mt-dot"></span>
                        </span>
                        <div style="min-width: 0">
                            <p class="qm-mt-check-label">{{ $check['label'] }}</p>
                            <p class="qm-mt-check-value">{{ $check['value'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Command tiles, grouped by purpose --}}
            @foreach ($groups as $group)
                <div>
                    <div class="qm-mt-group-head">
                        <h3 class="qm-mt-group-title">{{ $group['title'] }}</h3>
                        <span class="qm-mt-group-hint">{{ $group['hint'] }}</span>
                    </div>

                    <div class="qm-mt-cards">
                        @foreach ($group['commands'] as $cmd)
                            @php
                                $needed = $cmd['check'] !== null
                                    && in_array($checks[$cmd['check']]['color'] ?? null, ['warning', 'danger'], true);
                            @endphp
                            <div class="qm-mt-card" data-tone="{{ $cmd['tone'] }}" data-needed="{{ $needed ? 1 : 0 }}">
                                @if ($needed)
                                    <span class="qm-mt-needed">Нужно</span>
                                @endif

                                <div class="qm-mt-card-top">
                                    <span class="qm-mt-card-icon">
                                        <x-filament::icon :icon="$cmd['icon']" />
                                    </span>
                                    <div style="min-width: 0; padding-right: {{ $needed ? '3.5rem' : '0' }}">
                                        <p class="qm-mt-card-label">{{ $cmd['label'] }}</p>
                                        <p class="qm-mt-card-desc">{{ $cmd['desc'] }}</p>
                                    </div>
                                </div>

                                <x-filament::button
                                    :color="$needed ? 'warning' : 'gray'"
                                    :outlined="! $needed"
                                    size="sm"
                                    icon="heroicon-m-play"
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

            {{-- Output of the last command --}}
            @if ($lastOutput !== null)
                <div class="qm-mt-log" data-failed="{{ $lastFailed ? 1 : 0 }}">
                    <div class="qm-mt-log-bar">
                        <span class="qm-mt-log-lights"><i></i><i></i><i></i></span>
                        <span class="qm-mt-log-title">php artisan {{ $lastLabel }}</span>
                        <button type="button" class="qm-mt-log-close" wire:click="clearOutput" aria-label="Скрыть вывод">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    </div>
                    <pre>{{ $lastOutput }}</pre>
                </div>
            @endif

            <p class="qm-mt-footnote">
                <x-filament::icon icon="heroicon-m-information-circle" />
                <span>«Засеять тарифы» на непустом каталоге перезапишет цены дефолтных тарифов на значения по умолчанию — запускайте, только если хотите их сбросить.</span>
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
