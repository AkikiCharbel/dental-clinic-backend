<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?string $old): void {
                                if (filled($old) || $state === null) {
                                    return;
                                }
                                $set('slug', Str::slug($state));
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Textarea::make('address')
                            ->maxLength(500)
                            ->rows(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Subscription')
                    ->schema([
                        Forms\Components\Select::make('subscription_status')
                            ->options(fn (): array => collect(SubscriptionStatus::cases())
                                ->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => $status->label()])
                                ->all())
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('subscription_plan')
                            ->options(fn (): array => collect(SubscriptionPlan::cases())
                                ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->value => $plan->label()])
                                ->all())
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('subscription_ends_at')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Localization')
                    ->schema([
                        Forms\Components\TextInput::make('default_currency')
                            ->default('USD')
                            ->maxLength(3)
                            ->required(),
                        Forms\Components\TextInput::make('timezone')
                            ->default('UTC')
                            ->required(),
                        Forms\Components\TextInput::make('locale')
                            ->default('en')
                            ->maxLength(10)
                            ->required(),
                        Forms\Components\TextInput::make('country_code')
                            ->maxLength(2),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Setting Value')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('features')
                            ->keyLabel('Feature')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive tenants cannot access the system'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('subscription_plan')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionPlan $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->options(fn (): array => collect(SubscriptionStatus::cases())
                        ->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => $status->label()])
                        ->all()),
                Tables\Filters\SelectFilter::make('subscription_plan')
                    ->options(fn (): array => collect(SubscriptionPlan::cases())
                        ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->value => $plan->label()])
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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

    public static function getNavigationBadge(): ?string
    {
        /** @var int $count */
        $count = static::getModel()::count();

        return (string) $count;
    }
}
