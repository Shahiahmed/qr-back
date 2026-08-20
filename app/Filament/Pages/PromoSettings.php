<?php

namespace App\Filament\Pages;

use App\Models\PromoSetting;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
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
 * Admin page to edit the landing promo pop-up (a singleton). A master switch, an
 * optional schedule window, and bilingual (RU + KK) badge / title / body / CTA
 * label with a shared CTA url. Saving writes the singleton and drops the public
 * cache; the landing revalidates within the hour without a redeploy.
 *
 * Auto-discovered by AdminPanelProvider (discoverPages). The panel is already
 * gated to admins; we re-check is_admin on mount/save as defence in depth.
 *
 * @property-read Schema $form
 */
class PromoSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Акция (поп-ап)';

    protected static ?string $title = 'Акция на лендинге';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.promo-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $this->form->fill(PromoSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Показ')
                    ->description('Включите, чтобы поп-ап появился на сайте. Даты необязательны: без них акция активна сразу и бессрочно.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Показывать поп-ап')
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label('Начало показа')
                            ->helperText('Пусто = сразу.')
                            ->seconds(false)
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('Конец показа')
                            ->helperText('Пусто = без ограничения.')
                            ->seconds(false)
                            ->native(false),
                        TextInput::make('cta_url')
                            ->label('Ссылка кнопки')
                            ->helperText('Куда ведёт кнопка. Пусто = кнопки нет.')
                            ->placeholder('https://qr-menu.kz/ru/register')
                            ->url()
                            ->maxLength(300)
                            ->columnSpanFull(),
                    ]),

                Section::make('Русский (RU)')
                    ->schema([
                        TextInput::make('badge_ru')
                            ->label('Плашка')
                            ->helperText('Короткая, напр. «−20%» или «Акция».')
                            ->maxLength(40),
                        TextInput::make('title_ru')
                            ->label('Заголовок')
                            ->helperText('Без заголовка поп-ап не показывается.')
                            ->maxLength(120),
                        Textarea::make('body_ru')
                            ->label('Текст')
                            ->rows(3)
                            ->maxLength(400),
                        TextInput::make('cta_label_ru')
                            ->label('Текст кнопки')
                            ->placeholder('Получить скидку')
                            ->maxLength(60),
                    ]),

                Section::make('Қазақша (KK)')
                    ->schema([
                        TextInput::make('badge_kk')
                            ->label('Белгі')
                            ->maxLength(40),
                        TextInput::make('title_kk')
                            ->label('Тақырып')
                            ->maxLength(120),
                        Textarea::make('body_kk')
                            ->label('Мәтін')
                            ->rows(3)
                            ->maxLength(400),
                        TextInput::make('cta_label_kk')
                            ->label('Батырма мәтіні')
                            ->placeholder('Жеңілдік алу')
                            ->maxLength(60),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $data = $this->form->getState();

        // Trusted panel context; the singleton row has no owner-facing write
        // path. fill() is safe — every column is fillable on the model.
        PromoSetting::current()->fill($data)->save();

        Notification::make()
            ->title('Акция сохранена')
            ->body('Изменения появятся на сайте в течение часа.')
            ->success()
            ->send();
    }
}
