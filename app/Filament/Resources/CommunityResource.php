<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Community;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\CourtResource\Pages;


class CommunityResource extends Resource
{
    protected static ?string $model = Community::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Communities';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('team_name')
                    ->required()
                    ->maxLength(255)
                    ->label('Team Name'),

                TextInput::make('preferred_courts')
                    ->maxLength(255)
                    ->nullable()
                    ->label('Preferred Courts'),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50)
                    ->nullable()
                    ->label('Phone Number'),

                Textarea::make('description')
                    ->maxLength(1000)
                    ->nullable()
                    ->label('Description'),

                TagsInput::make('preferred_days')
                    ->separator(',')
                    ->suggestions(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
                    ->nullable()
                    ->label('Preferred Days'),

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->label('Owner'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team_name')
                    ->searchable()
                    ->sortable()
                    ->label('Team Name'),

                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('User'),
                    
                TextColumn::make('phone')
                    ->searchable()
                    ->label('Phone'),
                    
                TextColumn::make('preferred_courts')
                    ->searchable()
                    ->label('Preferred Courts'),
                
                TextColumn::make('description')
                    ->limit(50)
                    ->sortable()
                    ->label('Description'),

                TextColumn::make('preferred_days')
                    ->searchable()
                    ->sortable()
                    ->label('Preferred Days'),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CommunityResource\Pages\ListCommunities::route('/'),
            'create' => \App\Filament\Resources\CommunityResource\Pages\CreateCommunity::route('/create'),
            'edit' => \App\Filament\Resources\CommunityResource\Pages\EditCommunity::route('/{record}/edit'),
        ];
    }
}
