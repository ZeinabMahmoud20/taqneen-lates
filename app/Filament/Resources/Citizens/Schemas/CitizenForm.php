<?php

namespace App\Filament\Resources\Citizens\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CitizenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المواطن')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('national_id')
                            ->label('الرقم القومي (اختياري)')
                            ->length(14)
                            ->regex('/^\d{14}$/')
                            ->unique(ignoreRecord: true),

                        Select::make('center_id')
                            ->label('المركز')
                            ->relationship('center', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('اسم المركز')
                                    ->required()
                                    ->unique('centers', 'name'),
                            ]),

                        Textarea::make('address')
                            ->label('العنوان')
                            ->rows(2),
                    ]),

                Section::make('أرقام هاتف المواطن')
                    ->schema([
                        Repeater::make('phones')
                            ->label('الأرقام')
                            ->relationship()
                            ->schema([
                                TextInput::make('phone')
                                    ->label('رقم الهاتف')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20),

                                TextInput::make('label')
                                    ->label('ملاحظة (موبايل / منزل / واتساب)')
                                    ->maxLength(50),

                                Toggle::make('is_primary')
                                    ->label('الرقم الأساسي'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('إضافة رقم'),
                    ]),
            ]);
    }
}
