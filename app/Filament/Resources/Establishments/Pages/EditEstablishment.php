<?php

namespace App\Filament\Resources\Establishments\Pages;

use App\Filament\Actions\ExtendTrialAction;
use App\Filament\Resources\Establishments\EstablishmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEstablishment extends EditRecord
{
    protected static string $resource = EstablishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExtendTrialAction::make(),
            DeleteAction::make(),
        ];
    }
}
