<?php

namespace App\Filament\Resources\ContactRequests;

use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Models\ContactRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Заявки с сайта';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'Заявки с сайта';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return ContactRequestsTable::configure($table);
    }

    /** Badge with the count of unhandled leads, so admins notice new ones. */
    public static function getNavigationBadge(): ?string
    {
        $count = ContactRequest::query()
            ->where('status', ContactRequest::STATUS_NEW)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
        ];
    }
}
