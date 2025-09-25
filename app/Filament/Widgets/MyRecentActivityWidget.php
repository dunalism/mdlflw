<?php

namespace App\Filament\Widgets;

use App\Models\Audit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MyRecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3; // Tampilkan setelah widget lain

    protected int|string|array $columnSpan = 'full';

    /**
     * Tentukan query untuk tabel widget ini.
     */
    public function table(Table $table): Table
    {
        return $table
            // Ambil query dasar dari model Audit
            ->query(function (): Builder {
                return Audit::query()
                    // Filter: Hanya untuk pengguna yang sedang login
                    ->where('user_id', Auth::id())
                    // Filter: Hanya untuk aksi 'created' atau 'updated'
                    ->whereIn('event', ['created', 'updated'])
                    // Urutkan dari yang paling baru
                    ->latest()
                    // Batasi hanya 5 record terakhir
                    ->limit(5);
            })
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('event')->badge(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Target')
                    // Format nama model menjadi lebih mudah dibaca
                    ->formatStateUsing(
                        fn (string $state): string => (string) Str::of($state)
                            ->afterLast('\\')
                            ->snake()
                            ->replace('_', ' ')
                            ->title(),
                    ),

                Tables\Columns\TextColumn::make('created_at')->label('Created')->since(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Widget ini hanya akan terlihat oleh pengguna dengan peran 'staff'.
     */
    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $userIsStaff = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'staff') ||
                str_contains($roleName, 'staf') ||
                str_contains($roleName, 'editor') ||
                str_contains($roleName, 'karyawan') ||
                str_contains($roleName, 'employee');
        });

        return $userIsStaff;
    }
}
