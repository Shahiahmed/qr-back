<?php

namespace App\Filament\Pages;

use App\Models\ReferralSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin page to tune the referral program (a singleton): the master switch and
 * both reward amounts. Amounts are stored in tiyn but edited in tenge — the same
 * ÷100 / ×100 boundary the plan price uses.
 *
 * Auto-discovered by AdminPanelProvider (discoverPages). The panel is already
 * gated to admins; we re-check is_admin on mount/save as defence in depth.
 *
 * @property-read Schema $form
 */
class ReferralSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Реферальная программа';

    protected static ?string $title = 'Реферальная программа';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.referral-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $this->form->fill(ReferralSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Программа')
                    ->description('Скидка начисляется в кошелёк и вычитается из цены тарифа при оформлении подписки на меню.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Программа включена')
                            ->helperText('Выключите, чтобы приостановить начисления без правки кода.')
                            ->default(true)
                            ->columnSpanFull(),
                        // Stored in tiyn; edited in tenge. Convert on the boundary so
                        // nothing downstream ever sees a fraction (money is int tiyn).
                        TextInput::make('referrer_reward')
                            ->label('Скидка пригласившему, ₸')
                            ->helperText('Начисляется, когда приглашённый впервые оплатил платный тариф.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->formatStateUsing(fn (?int $state): int => (int) round(((int) $state) / 100))
                            ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                        TextInput::make('referred_reward')
                            ->label('Скидка приглашённому, ₸')
                            ->helperText('Начисляется сразу при регистрации по ссылке.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->formatStateUsing(fn (?int $state): int => (int) round(((int) $state) / 100))
                            ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $data = $this->form->getState();

        // Trusted panel context; the singleton has no owner-facing write path.
        // fill() is safe — every column is fillable on the model.
        ReferralSetting::current()->fill($data)->save();

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
