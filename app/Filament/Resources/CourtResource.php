<?php

namespace App\Filament\Resources;

use App\Models\Court;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\CourtResource\Pages;

class CourtResource extends Resource
{
    protected static ?string $model = Court::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Courts';

    protected static ?string $modelLabel = 'Court';

    protected static ?string $pluralModelLabel = 'Courts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('court_name')
                    ->required()
                    ->maxLength(255)
                    ->label('Court Name'),
                TextInput::make('location')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->required()
                    ->maxLength(255)
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('latitude')
                    ->numeric()
                    ->step(0.0000001),
                TextInput::make('longitude')
                    ->numeric()
                    ->step(0.0000001),
                TextInput::make('opening_time')
                    ->type('time')
                    ->label('Opening Time'),
                TextInput::make('closing_time')
                    ->type('time')
                    ->label('Closing Time'),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ])
                    ->required()
                    ->default('active'),
                Select::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                FileUpload::make('image')
                    ->image()
                    ->directory('courts')
                    ->visibility('public')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('court_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('opening_time')
                    ->time()
                    ->label('Opens At'),
                TextColumn::make('closing_time')
                    ->time()
                    ->label('Closes At'),
                TextColumn::make('vendor.name')
                    ->searchable()
                    ->sortable()
                    ->label('Vendor'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                ImageColumn::make('image')
                    ->circular()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ]),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourts::route('/'),
            'create' => Pages\CreateCourt::route('/create'),
            'edit' => Pages\EditCourt::route('/{record}/edit'),
        ];
    }
}



