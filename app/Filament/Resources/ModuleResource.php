<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Metode inilah yang mendefinisikan formulir Create dan Edit.
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Grup ini membuat layout 2/3 untuk form utama dan 1/3 untuk info tambahan.
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Detail Modul')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Module Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('modules', 'name', ignoreRecord: true)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (string $operation, $state, Forms\Set $set) => $operation === 'create'
                                            ? $set('table_name', Str::snake(Str::plural($state)))
                                            : null,
                                    ),

                                Forms\Components\TextInput::make('table_name')
                                    ->label('Table Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('modules', 'table_name', ignoreRecord: true)
                                    ->readonly()
                                    ->required(),
                                // =================================================================
                                // PERUBAHAN DIMULAI DI SINI
                                // Kita bungkus dropdown dan pratinjau dalam sebuah Grid
                                // =================================================================
                                Forms\Components\Grid::make(3) // Grid dengan 3 kolom
                                    ->schema([
                                        Forms\Components\Select::make('icon')
                                            ->label('Icon')
                                            ->options([
                                                'heroicon-o-cube' => 'Cube',
                                                'heroicon-o-users' => 'Users',
                                                'heroicon-o-shopping-cart' => 'Shopping Cart',
                                                'heroicon-o-briefcase' => 'Briefcase',
                                                'heroicon-o-document-text' => 'Document',
                                                'heroicon-o-chart-bar' => 'Chart Bar',
                                                'heroicon-o-calendar' => 'Calendar',
                                                'heroicon-o-envelope' => 'Envelope',
                                                'heroicon-o-bell' => 'Bell',
                                                'heroicon-o-globe-alt' => 'Globe',
                                                'heroicon-o-key' => 'Key',
                                                'heroicon-o-lock-closed' => 'Lock',
                                                'heroicon-o-rectangle-stack' => 'Rectangle',
                                                'heroicon-o-tag' => 'Tag',
                                                'heroicon-o-trash' => 'Trash',
                                                'heroicon-o-photo' => 'Photo',
                                            ])
                                            ->default('heroicon-o-cube')
                                            ->required()
                                            ->live() // <-- 2. Jadikan dropdown ini reaktif
                                            ->columnSpan(2), // <-- Dropdown mengambil 2 kolom

                                        // 3. Tambahkan Placeholder untuk menampilkan pratinjau ikon
                                        Forms\Components\Placeholder::make('icon_preview')
                                            ->label('Icon Preview')
                                            ->content(function (Forms\Get $get) {
                                                // Ambil nilai saat ini dari dropdown 'icon'
                                                $icon = $get('icon');
                                                if (! $icon) {
                                                    return null;
                                                }

                                                // Render komponen Blade ikon Filament secara dinamis
                                                return new HtmlString(
                                                    Blade::render(
                                                        '<x-filament::icon icon="'.$icon.'" class="h-10 w-10" />',
                                                    ),
                                                );
                                            })
                                            ->columnSpan(1), // <-- Pratinjau mengambil 1 kolom
                                    ]),
                            ])
                            ->columns(2), // Layout 2 kolom untuk section ini

                        // 4. Repeater untuk mendefinisikan kolom-kolom modul
                        Forms\Components\Section::make('Column Definition')->schema([
                            // 'Repeater' adalah komponen kunci di sini.
                            // Ini memungkinkan admin menambah/menghapus baris field secara dinamis.
                            // 'relationship()' memberitahu Filament bahwa data ini akan disimpan
                            // ke relasi 'fields' yang ada di Model 'Module'.
                            Forms\Components\Repeater::make('fields')
                                ->label('Columns')
                                ->rules([
                                    function (Get $get, $record) {
                                        return function (string $attribute, $value, Closure $fail) {
                                            $names = collect($value)->pluck('column_name')->filter();
                                            $duplicates = $names->duplicates();

                                            if ($duplicates->isNotEmpty()) {
                                                $fail('Duplicate column names found: '.$duplicates->implode(', '));
                                            }
                                        };
                                    },
                                ])
                                ->relationship()
                                ->schema([
                                    Forms\Components\TextInput::make('column_name')
                                        ->label('Column Name')
                                        ->required()
                                        ->unique(
                                            table: 'module_fields',
                                            column: 'column_name',
                                            ignoreRecord: true,
                                            modifyRuleUsing: fn ($rule, $context, Get $get) => $rule->where(
                                                'module_id',
                                                $get('../../id'),
                                            ),
                                        )
                                        ->rules([
                                            function (Get $get, $record) {
                                                return function (string $attribute, $value, Closure $fail) use (
                                                    $get,
                                                    $record,
                                                ) {
                                                    $moduleId = $get('../../id'); // Ambil ID modul dari parent repeater
                                                    if (! $moduleId || ! $value) {
                                                        return;
                                                    }

                                                    $query = DB::table('module_fields')
                                                        ->where('module_id', $moduleId)
                                                        ->where('column_name', $value);

                                                    if ($record && $record->id) {
                                                        $query->where('id', '!=', $record->id);
                                                    }
                                                };
                                            },
                                        ]),
                                    Forms\Components\Select::make('data_type')
                                        ->label('Data Type')
                                        ->options([
                                            'string' => 'String (Short Text)',
                                            'integer' => 'Integer (Number)',
                                            'text' => 'Text (Long Text)',
                                            'date' => 'Date',
                                            'boolean' => 'Boolean (Yes/No)',
                                            'wysiwyg' => 'WYSIWYG (Editor)',
                                            'options' => 'Options (Dropdown)',
                                            'image' => 'Image',
                                            'file' => 'File',
                                        ])
                                        ->required()
                                        ->live(),
                                    // Toggle (checkbox) untuk properti kolom
                                    Forms\Components\Toggle::make('is_required')
                                        ->label('Required')
                                        ->rules([
                                            function (Get $get, $record) {
                                                return function (string $attribute, $value, Closure $fail) use (
                                                    $get,
                                                    $record,
                                                ) {
                                                    // Hanya jalankan validasi jika toggle diaktifkan.
                                                    if ($value !== true) {
                                                        return;
                                                    }

                                                    $tableName = $get('../../table_name');
                                                    if (! $tableName) {
                                                        return;
                                                    }

                                                    // Skenario 1: Mengubah kolom yang sudah ada menjadi NOT NULL
                                                    if ($record) {
                                                        $columnName = $record->column_name;
                                                        if (
                                                            Schema::hasTable($tableName) &&
                                                            DB::table($tableName)->whereNull($columnName)->exists()
                                                        ) {
                                                            $fail(
                                                                'Cannot change to "Required" because there is already empty (NULL) value in this column.',
                                                            );
                                                        }
                                                    }
                                                    // Skenario 2: Menambah kolom baru sebagai NOT NULL
                                                    else {
                                                        // Jika tabel belum ada (saat membuat modul baru), lewati validasi.
                                                        if (! Schema::hasTable($tableName)) {
                                                            return;
                                                        }
                                                        // Jika tabel sudah ada dan berisi data, gagalkan.
                                                        if (DB::table($tableName)->exists()) {
                                                            $fail(
                                                                'Cannot add new column as "Required" because there is already existing data in this table.',
                                                            );
                                                        }
                                                    }
                                                };
                                            },
                                        ]),
                                    Forms\Components\Toggle::make('is_unique')->label('Unique'),
                                    // === LOGIKA BARU UNTUK SELECT/RELATIONSHIP ===
                                    Forms\Components\Radio::make('source_type')
                                        ->label('Options Source')
                                        ->options([
                                            'manual' => 'Manual Input',
                                            'module' => 'Module Relationship',
                                        ])
                                        ->default('manual')
                                        ->live()
                                        ->visible(fn (Get $get): bool => $get('data_type') === 'options'),

                                    Forms\Components\Textarea::make('options')
                                        ->required(
                                            fn (Get $get): bool => $get('data_type') === 'options' &&
                                                $get('source_type') === 'manual',
                                        )
                                        ->visible(
                                            fn (Get $get): bool => $get('data_type') === 'options' &&
                                                $get('source_type') === 'manual',
                                        )
                                        ->helperText(
                                            'Separate each option with a comma, for example: Option A, Option B, Option C.',
                                        )
                                        ->columnSpan(['lg' => 3]),
                                    Forms\Components\Select::make('related_module_id')
                                        ->label('Select Source Module')
                                        ->options(Module::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->required(
                                            fn (Get $get): bool => $get('data_type') === 'relationship' ||
                                                ($get('data_type') === 'options' && $get('source_type') === 'module'),
                                        )
                                        ->visible(
                                            fn (Get $get): bool => $get('data_type') === 'relationship' ||
                                                ($get('data_type') === 'options' && $get('source_type') === 'module'),
                                        )
                                        ->helperText(
                                            'The options contains the first string column of the selected module, make sure the column contains data.',
                                        )
                                        ->columnSpan(['lg' => 3]),
                                ])
                                ->columns(4) // Setiap baris repeater punya 4 kolom
                                ->addActionLabel('Add New Column'),
                        ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                // Grup ini adalah sidebar kanan
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')->schema([
                            Forms\Components\Placeholder::make('created_at')
                                ->label('Created')
                                ->content(fn (?Module $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                            Forms\Components\Placeholder::make('updated_at')
                                ->label('Last modified')
                                ->content(fn (?Module $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                        ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    // Metode ini mendefinisikan tabel daftar modul
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('table_name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('icon')->icon(fn (string $state): string => $state),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete Module')
                    ->modalDescription(
                        fn ($record) => new HtmlString(
                            'WARNING: Are you sure you want to delete the module <strong>'.
                                $record->name.
                                '</strong>? <br><br>This action will permanently delete the associated database table (<strong>'.
                                $record->table_name.
                                '</strong>) and <strong>ALL OF ITS DATA</strong>. This action cannot be undone.',
                        ),
                    )
                    ->modalSubmitActionLabel('Yes, delete module')
                    ->after(fn (Component $livewire) => $livewire->redirect(static::getUrl('index'), navigate: true)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('Delete Selected Modules')
                        ->modalDescription(
                            new HtmlString(
                                'WARNING: Are you sure you want to delete the selected modules? <br><br>'.
                                    'This action will permanently delete the associated database tables and <strong>ALL OF THEIR DATA</strong>. '.
                                    'This action cannot be undone.',
                            ),
                        )
                        ->modalSubmitActionLabel('Yes, delete selected modules')
                        ->after(
                            fn (Component $livewire) => $livewire->redirect(static::getUrl('index'), navigate: true),
                        ),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}
