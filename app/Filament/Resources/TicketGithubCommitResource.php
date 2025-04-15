<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketGithubCommitResource\Pages;
use App\Models\TicketGithubCommit;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TicketGithubCommitResource extends Resource
{
    protected static ?string $model = TicketGithubCommit::class;

    protected static ?string $navigationIcon = 'heroicon-o-code';

    protected static ?string $navigationGroup = 'Tickets';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'sha';

    public static function getModelLabel(): string
    {
        return __('GitHub Commit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('GitHub Commits');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ticket_id')
                    ->relationship('ticket', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('sha')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->maxLength(65535),
                Forms\Components\DateTimePicker::make('committed_at')
                    ->required(),
                Forms\Components\TextInput::make('branch')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sha')
                    ->searchable()
                    ->sortable()
                    ->limit(7),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('committed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch')
                    ->searchable()
                    ->sortable(),
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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTicketGithubCommits::route('/'),
            'create' => Pages\CreateTicketGithubCommit::route('/create'),
            'edit' => Pages\EditTicketGithubCommit::route('/{record}/edit'),
            'view' => Pages\ViewTicketGithubCommit::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
