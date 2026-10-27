<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    // No create action: leads come from the landing form, admins only process them.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
