<?php

namespace App\Filament\Resources\SubscriptionRequests;

use App\Filament\Resources\SubscriptionRequests\Pages\ListSubscriptionRequests;
use App\Filament\Resources\SubscriptionRequests\Tables\SubscriptionRequestsTable;
use App\Models\SubscriptionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriptionRequestResource extends Resource
{
    protected static ?string $model = SubscriptionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Заявки на подписку';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'Заявки на подписку';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return SubscriptionRequestsTable::configure($table);
    }

    /** Badge with the count of pending requests, so admins notice new ones. */
    public static function getNavigationBadge(): ?string
    {
        $count = SubscriptionRequest::query()
            ->whereIn('status', SubscriptionRequest::PENDING)
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
            'index' => ListSubscriptionRequests::route('/'),
        ];
    }
}
