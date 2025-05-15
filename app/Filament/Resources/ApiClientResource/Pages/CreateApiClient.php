<?php

namespace App\Filament\Resources\ApiClientResource\Pages;

use App\Filament\Resources\ApiClientResource;
use App\Models\ApiClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\CreateRecord;

class CreateApiClient extends CreateRecord
{
    // protected static string $resource = ApiClientResource::class;
    protected static string $resource = ApiClientResource::class;

    protected function handleRecordCreation(array $data): ApiClient
    {
        // Ensure permissions is an array
        $data['permissions'] = is_array($data['permissions']) ? $data['permissions'] : [];
        $apiClient = ApiClient::create($data);
        $token = $apiClient->createToken($apiClient->name)->plainTextToken;
        $apiClient->token = $token;
        $apiClient->save();

        Notification::make()
            ->title('API Client Created')
            ->body('Token: ' . $token . ' (Copy this token; it will not be shown again.)')
            ->success()
            ->send();

        return $apiClient;
    }
}
