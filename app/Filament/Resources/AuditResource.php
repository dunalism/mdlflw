<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Models\Audit;
use App\Models\Module;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $navigationGroup = 'System Management';

    protected static ?int $navigationSort = 3;

    /**
     * =================================================================
     * INI ADALAH LOGIKA UTAMANYA
     * Kita menimpa metode ini untuk membuat query yang dinamis
     * berdasarkan peran pengguna yang sedang login.
     * =================================================================
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $userIsManager = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer');
        });

        // Jika pengguna adalah super_admin atau admin, tampilkan semua log.
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return parent::getEloquentQuery();
        }

        // Jika pengguna adalah manager, bangun daftar model yang diizinkan.
        if ($userIsManager) {
            $allowedModels = [];

            $modules = Module::all();
            foreach ($modules as $module) {
                /**
                 * =================================================================
                 * INI ADALAH PERBAIKANNYA
                 * Kita sekarang menggunakan Str::singular() pada nama tabel untuk
                 * memastikan nama izin yang diperiksa (misal: 'page_product')
                 * cocok dengan nama izin yang dibuat ('page_product').
                 * =================================================================
                 */
                $permissionName = 'page_'.Str::singular($module->table_name);

                if ($user->can($permissionName)) {
                    $modelName = Str::studly(Str::singular($module->table_name));
                    $allowedModels[] = 'App\\Models\\Dynamic\\'.$modelName;
                }
            }

            // Jika manajer tidak memiliki izin ke modul mana pun, kembalikan query kosong.
            if (empty($allowedModels)) {
                return parent::getEloquentQuery()->whereRaw('0 = 1');
            }

            // Filter query audit berdasarkan daftar model yang diizinkan.
            return parent::getEloquentQuery()->whereIn('auditable_type', $allowedModels);
        }

        // Untuk peran lain (misalnya Staf), jangan tampilkan apa pun.
        return parent::getEloquentQuery()->whereRaw('0 = 1');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('event')
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
                TextColumn::make('auditable_type')
                    ->label('Target')
                    ->formatStateUsing(
                        fn (string $state): string => (string) Str::of($state)
                            ->afterLast('\\')
                            ->snake()
                            ->replace('_', ' ')
                            ->title(),
                    ),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->multiple()
                    ->options(User::pluck('name', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'created' => 'created',
                        'updated' => 'updated',
                        'deleted' => 'deleted',
                    ]),
                Filter::make('role_filter') // Handle internal, bukan nama kolom
                    ->form([
                        // Kita bangun antarmuka filter kita sendiri di sini
                        Select::make('role_ids')
                            ->label('Roles')
                            ->multiple()
                            ->options(Role::pluck('name', 'id')->toArray())
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Logika kueri ini sekarang akan bekerja dengan benar
                        return $query->when(
                            $data['role_ids'] ?? null,
                            fn (Builder $query, $roles) => $query->whereHas(
                                'user.roles',
                                fn (Builder $roleQuery) => $roleQuery->whereIn('id', $roles),
                            ),
                        );
                    }),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Audit Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name'),
                    TextEntry::make('event')
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
                    TextEntry::make('created_at')->dateTime(),
                ]),
            Section::make('Target Record')
                ->columns(2)
                ->schema([
                    TextEntry::make('auditable_type')->label('Model Type'),
                    TextEntry::make('auditable_id')->label('Record ID'),
                ]),
            Section::make('Change Data')
                ->columns(2)
                ->schema([
                    KeyValueEntry::make('old_values')->label('Old Data'),
                    KeyValueEntry::make('new_values')->label('New Data'),
                ]),
            Section::make('Additional information')
                ->columns(2)
                ->schema([
                    TextEntry::make('ip_address')->label('IP Address'),
                    TextEntry::make('user_agent')->label('User Agent')->columnSpanFull(),
                    TextEntry::make('url')->label('URL')->columnSpanFull(),
                ]),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
            'index' => Pages\ListAudits::route('/'),
            'view' => Pages\ViewAudit::route('/{record}'),
        ];
    }
}
