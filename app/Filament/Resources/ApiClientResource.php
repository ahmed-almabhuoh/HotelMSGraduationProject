<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiClientResource\Pages;
use App\Filament\Resources\ApiClientResource\RelationManagers\RequestLogsRelationManager;
use App\Models\ApiClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Details')
                    ->description('Basic information about the API client.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., Booking System')
                            ->helperText('A unique name for the external system.'),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->placeholder('Describe the purpose of this API client.')
                            ->helperText('Optional description of the client’s purpose (max 500 characters).')
                            ->rows(4),
                        Forms\Components\Hidden::make('is_active')
                            ->default(true),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('toggle_active')
                                ->label(fn($record) => $record && $record->is_active ? 'Deactivate' : 'Activate')
                                ->icon(fn($record) => $record && $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                                ->color(fn($record) => $record && $record->is_active ? 'danger' : 'success')
                                ->requiresConfirmation()
                                ->modalHeading('Confirm Status Change')
                                ->modalDescription('Are you sure you want to change the active status of this API client? Deactivation will revoke access immediately.')
                                ->modalSubmitActionLabel('Confirm')
                                ->action(function ($record, $set) {
                                    $newState = !($record->is_active ?? true);
                                    $set('is_active', $newState);
                                    Notification::make()
                                        ->title($newState ? 'Client Activated' : 'Client Deactivated')
                                        ->success()
                                        ->send();
                                })
                                ->hidden(fn($livewire) => $livewire instanceof Pages\CreateApiClient),
                        ]),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Permissions')
                    ->description('Select the API endpoints this client can access.')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->options([
                                'rooms.index' => 'List Rooms (View available rooms)',
                                'bookings.store' => 'Create Bookings (Submit new bookings)',
                            ])
                            ->label('Allowed Endpoints')
                            ->required()
                            ->helperText('Choose the actions this client can perform via the API.')
                            ->validationMessages([
                                'required' => 'At least one permission must be selected.',
                            ]),
                    ]),
                Forms\Components\Section::make('Token Management')
                    ->description('Manage the API token for this client.')
                    ->schema([
                        Forms\Components\TextInput::make('token')
                            ->label('API Token')
                            ->disabled()
                            ->default(fn($record) => $record ? $record->token : '')
                            ->visible(fn($livewire) => $livewire instanceof Pages\EditApiClient)
                            ->helperText('This token is used for API authentication. Keep it secure.')
                            ->extraAttributes(['type' => 'text'])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('copy_token')
                                    ->icon('heroicon-o-clipboard')
                                    ->disabled(fn($state) => empty($state))
                                    ->action(function ($state) {
                                        if (empty($state)) {
                                            Notification::make()
                                                ->title('No Token Available')
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        Notification::make()
                                            ->title('Token Copied')
                                            ->success()
                                            ->send();
                                    })
                                    ->extraAttributes([
                                        'onclick' => 'if (this.closest(\'.fi-ta-text\').querySelector(\'input\').value) { navigator.clipboard.writeText(this.closest(\'.fi-ta-text\').querySelector(\'input\').value); }',
                                    ])
                            ),
                    ])
                    ->visible(fn($livewire) => $livewire instanceof Pages\EditApiClient),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('permissions')
                    ->formatStateUsing(function ($state) {
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            if (is_array($decoded)) {
                                return implode(', ', $decoded);
                            }
                            return $state;
                        }
                        return is_array($state) ? implode(', ', $state) : '';
                    })
                    ->label('Permissions'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('regenerate_token')
                    ->label('Regenerate Token')
                    ->action(function (ApiClient $record) {
                        $record->tokens()->delete();
                        $record->token = $record->createToken($record->name)->plainTextToken;
                        $record->save();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RequestLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiClients::route('/'),
            'create' => Pages\CreateApiClient::route('/create'),
            'edit' => Pages\EditApiClient::route('/{record}/edit'),
        ];
    }
}
