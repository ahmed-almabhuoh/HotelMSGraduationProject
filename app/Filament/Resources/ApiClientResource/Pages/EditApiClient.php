<?php

namespace App\Filament\Resources\ApiClientResource\Pages;

use App\Filament\Resources\ApiClientResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditApiClient extends EditRecord
{
    protected static string $resource = ApiClientResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        // Safely access the client_details section and toggle_active action
        $clientDetails = $this->form->getComponent('client_details');
        if (!$clientDetails) {
            Log::error('Client Details component not found in form schema');
            return;
        }

        // Access the Actions component (which contains toggle_active)
        $actions = null;
        foreach ($clientDetails->getChildComponents() as $component) {
            if ($component instanceof \Filament\Forms\Components\Actions) {
                $actions = $component;
                break;
            }
        }

        if (!$actions) {
            Log::error('Actions component not found in Client Details');
            return;
        }

        $toggleAction = $actions->getChildComponent('toggle_active');
        if (!$toggleAction) {
            Log::error('Toggle Active action not found');
            return;
        }

        // Register the listener for toggle_active
        $toggleAction->afterStateUpdated(function ($state, $set, $get, $record) {
            $newState = !($record->is_active ?? true);
            $record->is_active = $newState;
            $record->save();
            Log::info('Toggled is_active', ['id' => $record->id, 'is_active' => $newState]);
            Notification::make()
                ->title($newState ? 'Client Activated' : 'Client Deactivated')
                ->success()
                ->send();
        });
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('API Client Updated')
            ->success()
            ->send();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}
