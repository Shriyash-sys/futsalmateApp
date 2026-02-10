<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Vendor;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\VendorResource\Pages;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Vendors';

    protected static ?string $modelLabel = 'Vendor';

    protected static ?string $pluralModelLabel = 'Vendors';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Vendor Name'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(table: 'vendors', column: 'email', ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->unique(table: 'vendors', column: 'phone', ignoreRecord: true)
                    ->maxLength(15),
                TextInput::make('owner_name')
                    ->maxLength(255)
                    ->label('Owner Name'),
                Textarea::make('address')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->password()
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->minLength(8)
                    ->label(fn(string $context): string => $context === 'create' ? 'Password' : 'New Password (leave blank to keep current)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Vendor Name'),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user_type')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_name')
                    ->searchable()
                    ->sortable()
                    ->label('Owner'),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn($record) => $record->address)
                    ->toggleable(),
                TextColumn::make('court_count')
                    ->counts('court')
                    ->label('Total Courts')
                    ->sortable(),
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
                TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->nullable(),
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
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
