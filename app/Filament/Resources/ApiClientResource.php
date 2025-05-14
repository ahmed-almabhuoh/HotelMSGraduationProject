<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiClientResource\Pages;
use App\Filament\Resources\ApiClientResource\RelationManagers\RequestLogsRelationManager;
use App\Models\ApiClient;
use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->label('Active'),
                Forms\Components\Select::make('permissions')
                    ->multiple()
                    ->options([
                        'rooms.index' => 'List Rooms',
                        'bookings.store' => 'Create Bookings',
                    ])
                    ->label('Allowed Endpoints'),
                Forms\Components\TextInput::make('token')
                    ->label('API Token')
                    ->disabled()
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
                    ->formatStateUsing(fn($state) => implode(', ', $state ?? []))
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
