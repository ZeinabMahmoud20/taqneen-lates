<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                // ماينفعش المستخدم يغيّر دور حسابه بنفسه (علشان ماحدش يقفل على نفسه)
                Select::make('role')
                    ->label('الدور')
                    ->options(UserRole::class)
                    ->required()
                    ->default(UserRole::Leadership)
                    ->disabled(fn (?Model $record): bool => $record?->getKey() === auth()->id())
                    ->helperText(fn (?Model $record): ?string => $record?->getKey() === auth()->id()
                        ? 'مش مسموح تغيّر دور حسابك بنفسك. اطلب من مشرف عام تاني.'
                        : null),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    // مطلوبة عند الإنشاء فقط، وعند التعديل بنسيبها فاضية لو مش عايزين نغيرها
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),

                Toggle::make('is_active')
                    ->label('الحساب مفعّل')
                    ->default(true)
                    ->disabled(fn (?Model $record): bool => $record?->getKey() === auth()->id()),
            ]);
    }
}
