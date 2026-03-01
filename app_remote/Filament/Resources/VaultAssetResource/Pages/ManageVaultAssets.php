<?php

namespace App\Filament\Resources\VaultAssetResource\Pages;

use App\Filament\Resources\VaultAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVaultAssets extends ManageRecords
{
    protected static string $resource = VaultAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
