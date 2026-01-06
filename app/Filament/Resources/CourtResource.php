<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourtResource\Pages;
use App\Models\Court;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

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
                Tables\Columns\TextColumn::make('court_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->searchable()
                    ->sortable()
                    ->label('Vendor'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ]),
                Tables\Filters\SelectFilter::make('vendor_id')
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



