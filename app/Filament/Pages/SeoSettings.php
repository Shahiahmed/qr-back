<?php

namespace App\Filament\Pages;

use App\Models\SeoSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin page to edit the site-wide SEO settings (a singleton). Bilingual
 * title/description/keywords, a shared OG image and a canonical host override.
 * The landing is always indexable — there is deliberately no noindex switch, so
 * a stray click can never drop the live site out of search. Saving writes the
 * singleton and drops the public
 * cache; the landing revalidates within the hour without a redeploy.
 *
 * Auto-discovered by AdminPanelProvider (discoverPages). The panel is already
 * gated to admins; we re-check is_admin on mount as defence in depth.
 *
 * @property-read Schema $form
 */
class SeoSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'SEO';

    protected static ?string $title = 'SEO и метаданные';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.seo-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $this->form->fill(SeoSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Основное')
                    ->description('Картинка для ссылок в соцсетях и канонический домен.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('og_image_path')
                            ->label('OG-картинка')
                            ->helperText('Показывается при отправке ссылки в WhatsApp / Telegram / соцсети. Рекомендуемый размер 1200×630.')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1.91:1'])
                            ->disk('public')
                            ->directory('seo')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->columnSpanFull(),
                        TextInput::make('canonical_host')
                            ->label('Канонический домен')
                            ->helperText('Обычно оставьте пустым. Пример: qmenu.kz')
                            ->placeholder('qmenu.kz')
                            ->maxLength(120),
                    ]),

                Section::make('Русский (RU)')
                    ->description('Пусто = взять текст по умолчанию из кода лендинга.')
                    ->schema([
                        TextInput::make('title_ru')
                            ->label('Заголовок (title)')
                            ->helperText('До ~60 символов — то, что видно во вкладке и в результатах поиска.')
                            ->maxLength(70),
                        Textarea::make('description_ru')
                            ->label('Описание (description)')
                            ->helperText('До ~160 символов — подпись под ссылкой в выдаче.')
                            ->rows(3)
                            ->maxLength(300),
                        Textarea::make('keywords_ru')
                            ->label('Ключевые слова')
                            ->helperText('Через запятую. Влияние на выдачу минимальное, но пусть будут.')
                            ->rows(2)
                            ->maxLength(300),
                    ]),

                Section::make('Қазақша (KK)')
                    ->description('Бос = кодтағы әдепкі мәтін алынады.')
                    ->schema([
                        TextInput::make('title_kk')
                            ->label('Тақырып (title)')
                            ->maxLength(70),
                        Textarea::make('description_kk')
                            ->label('Сипаттама (description)')
                            ->rows(3)
                            ->maxLength(300),
                        Textarea::make('keywords_kk')
                            ->label('Кілт сөздер')
                            ->rows(2)
                            ->maxLength(300),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless((bool) auth()->user()?->is_admin, 403);

        $data = $this->form->getState();

        // Trusted panel context; the singleton row has no owner-facing write
        // path. fill() is safe — every column is fillable on the model.
        SeoSetting::current()->fill($data)->save();

        Notification::make()
            ->title('SEO сохранено')
            ->body('Изменения появятся на сайте в течение часа.')
            ->success()
            ->send();
    }
}
