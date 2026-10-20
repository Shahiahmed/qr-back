<?php

namespace App\Filament\Resources\Establishments\RelationManagers;

use App\Models\MenuCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Moderation view of a venue's menu sections. No create — the owner builds the
 * menu; the panel only fixes or hides what is there. Every write goes through
 * the model, so InvalidatesPublicMenu drops the cached guest menu.
 */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Разделы меню';

    protected static ?string $modelLabel = 'раздел';

    protected static ?string $pluralModelLabel = 'разделы';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->categories()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ru')
                ->label('Название (RU)')
                ->required()
                ->maxLength(255),
            TextInput::make('name_kk')
                ->label('Название (KK)')
                ->maxLength(255),
            Toggle::make('is_visible')
                ->label('Виден гостям'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ru')
            ->modifyQueryUsing(fn ($query) => $query->withCount('dishes'))
            ->columns([
                TextColumn::make('position')
                    ->label('Порядок')
                    ->color('gray'),
                TextColumn::make('name_ru')
                    ->label('Раздел')
                    ->weight('bold')
                    ->description(fn (MenuCategory $record): ?string => $record->name_kk)
                    ->searchable(['name_ru', 'name_kk']),
                TextColumn::make('dishes_count')
                    ->label('Блюда')
                    ->badge()
                    ->color('success')
                    ->alignRight(),
                ToggleColumn::make('is_visible')
                    ->label('Виден гостям'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Вместе с разделом удалятся все его блюда. Отменить нельзя.'),
            ])
            ->paginated(false);
    }
}
