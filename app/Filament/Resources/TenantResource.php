<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Administration';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash(),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Textarea::make('address')
                            ->rows(2),
                        TextInput::make('country_code')
                            ->maxLength(3),
                    ])
                    ->columns(2),

                Section::make('Subscription')
                    ->schema([
                        Select::make('subscription_status')
                            ->options(SubscriptionStatus::options())
                            ->required(),
                        Select::make('subscription_plan')
                            ->options(SubscriptionPlan::options())
                            ->required(),
                        DateTimePicker::make('trial_ends_at'),
                        DateTimePicker::make('subscription_ends_at'),
                    ])
                    ->columns(2),

                Section::make('Localization')
                    ->schema([
                        TextInput::make('default_currency')
                            ->default('USD')
                            ->maxLength(3),
                        TextInput::make('timezone')
                            ->default('UTC')
                            ->maxLength(50),
                        TextInput::make('locale')
                            ->default('en')
                            ->maxLength(10),
                    ])
                    ->columns(3),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true),
                    ]),

                Section::make('Settings')
                    ->schema([
                        KeyValue::make('settings')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Setting Value'),
                        KeyValue::make('features')
                            ->keyLabel('Feature')
                            ->valueLabel('Enabled'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->badge()
                    ->color(fn (SubscriptionStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('subscription_plan')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->options(SubscriptionStatus::options()),
                Tables\Filters\SelectFilter::make('subscription_plan')
                    ->options(SubscriptionPlan::options()),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
