<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AuditResource;
use App\Models\Audit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RecentAuditsWidget extends BaseWidget
{
    protected static ?int $sort = 2; // Urutan di dasbor

    protected int|string|array $columnSpan = 'full'; // Lebar penuh

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditResource::getEloquentQuery()->limit(5)) // Gunakan query dari AuditResource agar filter manajer juga berlaku!
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(
                        fn (string $state): string => match ($state) {
                            'created' => 'success',
                            'updated' => 'warning',
                            'deleted' => 'danger',
                            default => 'gray',
                        },
                    ),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Target')
                    ->formatStateUsing(
                        fn (string $state): string => (string) Str::of($state)
                            ->afterLast('\\')
                            ->snake()
                            ->replace('_', ' ')
                            ->title(),
                    ),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->since(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->url(
                    fn (Audit $record): string => AuditResource::getUrl('view', ['record' => $record]),
                ),
            ])
            ->recordUrl(fn (Audit $record): string => AuditResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userIsManager = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer');
        });

        return $user->hasAnyRole(['super_admin', 'admin']) || $user->can('view_filtered_audits') || $userIsManager;
    }
}
