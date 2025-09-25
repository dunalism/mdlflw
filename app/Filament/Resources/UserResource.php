<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique('users', 'email', ignoreRecord: true),
            Hidden::make('email_verified_at')->default(Carbon::now()),
            TextInput::make('password')
                ->password()
                ->maxLength(255)
                ->minLength(8)
                // Hanya hash jika diisi. Jika kosong, jangan lakukan apa-apa.
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                // Jangan simpan ke database jika field ini kosong (saat edit).
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context): bool => $context === 'create')
                ->default('password'),
            Select::make('roles')
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    // Gunakan modifyQueryUsing untuk menyaring daftar peran
                    modifyQueryUsing: function (Builder $query) {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();
                        if (! $user->hasRole('super_admin')) {
                            $query->where('name', '!=', 'super_admin')->where('name', '!=', 'admin');
                        }
                        if ($user->hasRole('manager')) {
                            $query
                                ->where('name', '!=', 'super_admin')
                                ->where('name', '!=', 'admin')
                                ->where('name', '!=', 'manager');
                        }
                    },
                )
                ->preload()
                ->placeholder('Select a role'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles')
                    ->label('Role')
                    ->getStateUsing(fn ($record) => $record->roles->pluck('name')->join(', '))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M d, Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('roles')->label('Filter by Role')->relationship('roles', 'name')->preload()])

            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->action(function (Collection $records) {
                        $deletableRecords = $records->filter(function ($record) {
                            // Untuk setiap record, kita periksa menggunakan UserPolicy::delete()
                            /** @var \App\Models\User $user */
                            $user = Auth::user();

                            return $user->can('delete', $record);
                        });

                        if ($deletableRecords->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('Restricted Action')
                                ->body('You do not have permission to delete the selected user.')
                                ->send();

                            return;
                        }

                        $deletedCount = $deletableRecords->count();
                        $deletableRecords->each->delete();

                        if ($deletedCount < $records->count()) {
                            Notification::make()
                                ->warning()
                                ->title('Some Users Not Deleted')
                                ->body(
                                    'Some of the selected users could not be deleted because you do not have permission to delete them.',
                                )
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title('Users Deleted')
                                ->body('The selected users have been deleted.')
                                ->send();
                        }
                    }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'superadmin@example.com');

        if ($user->email !== $superAdminEmail) {
            $query->where('email', '!=', $superAdminEmail);
        }

        if (! $user->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', function (Builder $query) {
                $query->where('name', 'super_admin');
            });
        }

        return $query->with('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
