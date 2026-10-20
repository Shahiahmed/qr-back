<?php

namespace App\Filament\Resources\Establishments\RelationManagers;

use App\Models\Dish;
use App\Support\DishImage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Moderation view of a venue's dishes: read the whole menu without opening the
 * guest page (which 404s once access has expired), fix a typo or price, hide an
 * inappropriate item, flip the stop-list. No create — owners build menus.
 */
class DishesRelationManager extends RelationManager
{
    protected static string $relationship = 'dishes';

    protected static ?string $title = 'Блюда';

    protected static ?string $modelLabel = 'блюдо';

    protected static ?string $pluralModelLabel = 'блюда';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->dishes()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Only this venue's sections — never let a dish hop tenants.
            Select::make('menu_category_id')
                ->label('Раздел')
                ->options(fn (): array => $this->getOwnerRecord()->categories()->pluck('name_ru', 'id')->all())
                ->required(),
            // Stored in tiyn, entered in tenge — convert on the boundary (as in PlanForm).
            TextInput::make('price')
                ->label('Цена, ₸')
                ->numeric()
                ->required()
                ->minValue(0)
                ->formatStateUsing(fn (?int $state): int => (int) round(((int) $state) / 100))
                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
            TextInput::make('name_ru')
                ->label('Название (RU)')
                ->required()
                ->maxLength(255),
            TextInput::make('name_kk')
                ->label('Название (KK)')
                ->maxLength(255),
            Textarea::make('description_ru')
                ->label('Описание (RU)')
                ->rows(3),
            Textarea::make('description_kk')
                ->label('Описание (KK)')
                ->rows(3),
            Toggle::make('is_visible')
                ->label('Видно гостям'),
            Toggle::make('is_available')
                ->label('В наличии (выкл = стоп-лист)'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ru')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Фото')
                    ->state(fn (Dish $record): ?string => DishImage::url($record->image_path))
                    ->square()
                    ->imageSize(44),
                TextColumn::make('name_ru')
                    ->label('Блюдо')
                    ->weight('bold')
                    ->description(fn (Dish $record): ?string => $record->name_kk)
                    ->searchable(['name_ru', 'name_kk'])
                    ->sortable(),
                TextColumn::make('category.name_ru')
                    ->label('Раздел')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('price')
                    ->label('Цена')
                    ->state(fn (Dish $record): string => number_format(intdiv($record->price, 100), 0, '.', ' ').' ₸')
                    ->alignRight()
                    ->sortable(),
                ToggleColumn::make('is_visible')
                    ->label('Видно'),
                ToggleColumn::make('is_available')
                    ->label('В наличии'),
            ])
            ->defaultSort('menu_category_id')
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label('Раздел')
                    ->options(fn (): array => $this->getOwnerRecord()->categories()->pluck('name_ru', 'id')->all()),
                TernaryFilter::make('is_available')
                    ->label('Наличие')
                    ->trueLabel('В наличии')
                    ->falseLabel('В стоп-листе'),
                TernaryFilter::make('is_visible')
                    ->label('Видимость')
                    ->trueLabel('Видно гостям')
                    ->falseLabel('Скрыто'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Drop the photo file too, otherwise it orphans in the venue folder.
                    ->before(fn (Dish $record) => $record->image_path ? DishImage::remove($record) : null),
            ]);
    }
}
