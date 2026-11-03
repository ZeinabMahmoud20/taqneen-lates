<?php

namespace App\Filament\Resources\Citizens\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * النواب عن المواطن. النائب الحالي = «انتهت في» فاضي.
 * إضافة نائب حالي جديد بتنهي النائب الحالي القديم تلقائياً (جوه موديل Representative).
 */
class RepresentativesRelationManager extends RelationManager
{
    protected static string $relationship = 'representatives';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'النواب';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم النائب')
                    ->required()
                    ->maxLength(255),

                TextInput::make('relationship')
                    ->label('صلة القرابة')
                    ->maxLength(100),

                DatePicker::make('started_at')
                    ->label('بداية النيابة'),

                DatePicker::make('ended_at')
                    ->label('نهاية النيابة')
                    ->helperText('سيبه فاضي لو ده النائب الحالي.'),

                Repeater::make('phones')
                    ->label('أرقام هاتف النائب')
                    ->relationship()
                    ->schema([
                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        TextInput::make('label')
                            ->label('ملاحظة')
                            ->maxLength(50),

                        Toggle::make('is_primary')
                            ->label('أساسي'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel('إضافة رقم')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('phones'))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم'),

                TextColumn::make('relationship')
                    ->label('صلة القرابة')
                    ->placeholder('—'),

                TextColumn::make('phones.phone')
                    ->label('التليفون')
                    ->listWithLineBreaks()
                    ->placeholder('—'),

                TextColumn::make('started_at')
                    ->label('من')
                    ->date('Y-m-d')
                    ->placeholder('—'),

                TextColumn::make('ended_at')
                    ->label('إلى')
                    ->date('Y-m-d')
                    ->placeholder('النائب الحالي')
                    ->badge()
                    ->color(fn ($state): string => $state === null ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()->label('إضافة نائب'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
