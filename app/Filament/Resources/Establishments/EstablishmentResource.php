<?php

namespace App\Filament\Resources\Establishments;

use App\Filament\Resources\Establishments\Pages\EditEstablishment;
use App\Filament\Resources\Establishments\Pages\ListEstablishments;
use App\Filament\Resources\Establishments\Schemas\EstablishmentForm;
use App\Filament\Resources\Establishments\Tables\EstablishmentsTable;
use App\Models\Establishment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EstablishmentResource extends Resource
{
    protected static ?string $model = Establishment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Заведения';

    protected static ?string $modelLabel = 'заведение';

    protected static ?string $pluralModelLabel = 'Заведения';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return EstablishmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EstablishmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
    ];
    }

    public static function getPages(): array
    {
        // No create page — venues are made by owners in their own cabinet, the
        // panel only moderates existing ones.
        return [
            'index' => ListEstablishments::route('/'),
            'edit' => EditEstablishment::route('/{record}/edit'),
        ];
    }
}
