<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Details')
                    ->schema([
                        Forms\Components\TextInput::make('room_number')
                            ->required()
                            ->maxLength(10)
                            ->regex('/^[A-Z0-9-]+$/')
                            ->unique(Room::class, 'room_number', ignoreRecord: true)
                            ->helperText('Use format like A-101 or 305'),
                        Forms\Components\Select::make('type')
                            ->options([
                                'single' => 'Single',
                                'double' => 'Double',
                                'suite' => 'Suite',
                                'deluxe' => 'Deluxe',
                            ])
                            ->required()
                            ->reactive()
                            ->helperText('Select the room category'),
                        Forms\Components\TextInput::make('price_per_night')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Price per night in USD'),
                        Forms\Components\Toggle::make('is_available')
                            ->required()
                            ->default(true)
                            ->helperText('Toggle room availability'),
                        Forms\Components\Select::make('max_occupancy')
                            ->required()
                            ->options(array_combine(range(1, 20), range(1, 20)))
                            ->helperText('Select maximum number of guests'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->required()
                            ->maxLength(65535)
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull()
                            ->helperText('Describe the room features and amenities'),
                        Forms\Components\TagsInput::make('amenities')
                            ->suggestions([
                                'wifi',
                                'tv',
                                'minibar',
                                'balcony',
                                'ocean-view',
                                'air-conditioning',
                            ])
                            ->placeholder('Add amenities...')
                            ->helperText('Add room amenities as tags'),
                        Forms\Components\FileUpload::make('image_path')
                            ->image()
                            ->directory('room-images')
                            ->maxSize(5120)
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('600')
                            ->imagePreviewHeight('250')
                            ->columnSpanFull()
                            ->helperText('Upload a room image (max 5MB, auto-resized to 800x600)'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Suite Features')
                    ->schema([
                        Forms\Components\TextInput::make('suite_size')
                            ->label('Suite Size (sq ft)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Enter suite size in square feet'),
                        Forms\Components\Toggle::make('has_jacuzzi')
                            ->label('Has Jacuzzi')
                            ->helperText('Does the suite include a jacuzzi?'),
                    ])
                    ->columns(2)
                    ->visible(fn($get) => $get('type') === 'suite')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_night')
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_occupancy')
                    ->label('Max Guests')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->disk('public'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'single' => 'Single',
                        'double' => 'Double',
                        'suite' => 'Suite',
                        'deluxe' => 'Deluxe',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
