<?php

namespace App\Filament\Pages;

use App\Exports\DynamicModuleExport;
use App\Filament\Traits\HandlesDynamicStatusDisplay;
use App\Models\Module;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Cloudinary\Cloudinary;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DatePicker as FormDatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;
use pxlrbt\FilamentExcel\Exports\ExcelExport; // <-- Import RichEditor

// <-- Import Halaman View

class ListDynamicEntries extends Page implements HasForms, HasTable
{
    use HandlesDynamicStatusDisplay;
    use InteractsWithForms;
    use InteractsWithTable; // <-- 3. Gunakan Trait

    protected static string $view = 'filament.pages.list-dynamic-entries';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $route = '/dynamic-modules';

    public ?Module $module = null;

    public ?string $modelClass = null;

    public ?string $permissionBase = null;

    /**
     * =================================================================
     * Melindungi Halaman dengan Otorisasi Dinamis
     * =================================================================
     */
    public function authorize($ability, $arguments = []): void
    {
        // Ambil nama modul dari parameter kueri URL
        $moduleName = request()->query('module');

        // Bangun nama izin yang diharapkan
        $permissionName = 'page_' . Str::snake(Str::studly(Str::singular($moduleName)));

        if (!Gate::allows($permissionName)) {
            throw new AuthorizationException();
        }
    }

    public function mount(): void
    {
        $moduleName = request()->query('module');
        if (!$moduleName) {
            abort(404);
        }

        $this->module = Module::with('fields')->where('table_name', $moduleName)->firstOrFail();
        $modelName = Str::studly(Str::singular($this->module->table_name));
        $this->modelClass = 'App\\Models\\Dynamic\\' . $modelName;
        $this->permissionBase = Str::singular($this->module->table_name);
    }

    public function table(Table $table): Table
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            // ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                EditAction::make()
                    ->form($this->getFormSchema())
                    ->visible(fn(): bool => $user?->can('update_' . $this->permissionBase) ?? false),
                DeleteAction::make()->visible(fn(): bool => $user?->can('delete_' . $this->permissionBase) ?? false),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(
                        fn(): bool => $user?->can('delete_' . $this->permissionBase) ?? false,
                    ),

                    // =================================================================
                    // INI ADALAH IMPLEMENTASI YANG BENAR
                    // Kita membuat dan mengonfigurasi objek ExcelExport secara terpisah
                    // =================================================================

                    BulkAction::make('export_excel')
                        ->label('Export to Excel')
                        ->icon('heroicon-o-table-cells')
                        ->action(function (Collection $records) {
                            $exportableFields = $this->getExportableFields();
                            $filename = Str::slug($this->module->name) . '-' . now()->format('Y-m-d') . '.xlsx';

                            return Excel::download(new DynamicModuleExport($records, $exportableFields), $filename);
                        }),

                    // --- AKSI EKSPOR PDF ---
                    BulkAction::make('export_pdf')
                        ->label('Export to PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            $html = view('exports.dynamic-data', [
                                'moduleName' => $this->module->name,
                                'headers' => $this->getExportHeaders('pdf'),
                                'fields' => $this->getExportableFields('pdf'),
                                'records' => $records,
                            ])->render();

                            $pdf = Pdf::loadHTML($html);

                            return response()->streamDownload(
                                fn() => print $pdf->output(),
                                Str::slug($this->module->name) . '-' . now()->format('Y-m-d') . '.pdf',
                            );
                        }),

                    BulkAction::make('export_word')
                        ->label('Export to Word')
                        ->icon('heroicon-o-document-text')
                        ->action(function (Collection $records) {
                            $phpWord = new PhpWord();
                            $phpWord->setDefaultFontName('Arial');
                            $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));

                            $section = $phpWord->addSection();
                            $section->addTitle('Data Ekspor untuk: ' . $this->module->name, 1);
                            $section->addText('Tanggal Ekspor: ' . now()->format('d F Y H:i'));
                            $section->addTextBreak(1); // Spasi

                            $table = $section->addTable([
                                'borderColor' => '000000',
                                'borderSize' => 6,
                                'cellMargin' => 50,
                            ]);

                            // Tambahkan baris header
                            $table->addRow();
                            foreach ($this->getExportHeaders('word') as $header) {
                                $table->addCell(1750, ['bgColor' => 'F2F2F2'])->addText($header, ['bold' => true]);
                            }

                            // Tambahkan baris data
                            $exportableFields = $this->getExportableFields('word');
                            foreach ($records as $record) {
                                $table->addRow();
                                foreach ($exportableFields as $field) {
                                    $value = $record->{$field->column_name};
                                    if ($field->data_type === 'boolean') {
                                        $value = $value ? 'Ya' : 'Tidak';
                                    }
                                    $table->addCell(1750)->addText(htmlspecialchars($value));
                                }
                            }

                            $writer = IOFactory::createWriter($phpWord, 'Word2007');
                            $filename = Str::slug($this->module->name) . '-' . now()->format('Y-m-d') . '.docx';

                            return response()->streamDownload(fn() => $writer->save('php://output'), $filename);
                        }),
                ]),
            ])
            ->recordUrl(
                fn(Model $record): ?string => $user?->can('view_' . $this->permissionBase)
                    ? ViewDynamicEntry::getUrl([
                        'module' => $this->module->table_name,
                        'record' => $record->id,
                    ])
                    : null,
            );
    }

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$this->module) {
            return [];
        }

        return [
            CreateAction::make()
                ->label('Add ' . Str::singular($this->module->name))
                ->form($this->getFormSchema())
                ->using(function (array $data): Model {
                    return $this->modelClass::create($data);
                })
                ->successNotificationTitle($this->module->name . ' baru berhasil ditambahkan')
                ->visible(fn(): bool => $user?->can('create_' . $this->permissionBase) ?? false),
        ];
    }

    protected function getFormSchema(): array
    {
        if (!$this->module) {
            return [];
        }

        return $this->module->fields
            ->map(function ($field) {
                $component = null;
                switch ($field->data_type) {
                    case 'string':
                        $component = TextInput::make($field->column_name);
                        break;
                    case 'integer':
                        $component = TextInput::make($field->column_name)->numeric();
                        break;
                    case 'text':
                        $component = Textarea::make($field->column_name)->columnSpanFull();
                        break;
                    case 'date':
                        $component = DatePicker::make($field->column_name);
                        break;
                    case 'boolean':
                        $component = Toggle::make($field->column_name);
                        break;
                    case 'wysiwyg':
                        $component = RichEditor::make($field->column_name)
                            ->columnSpanFull()
                            ->disableToolbarButtons(['attachFiles']);
                        break;
                    case 'options':
                        $options = [];
                        $columnStringExists = false;
                        $placeholder = '';

                        if ($field->source_type === 'module' && $field->relatedModule) {
                            $relatedModule = $field->relatedModule;
                            $displayColumn =
                                $relatedModule->fields()->where('data_type', 'string')->first()?->column_name ?? 'id';

                            if ($displayColumn == 'id') {
                                $options = [
                                    'Please add a string type column in the module: (' .
                                    $relatedModule->table_name .
                                    ')',
                                ];
                                $placeholder =
                                    'Please add a string type column in the module: (' .
                                    $relatedModule->table_name .
                                    ')';
                            } else {
                                $columnStringExists = true;
                                $options = DB::table($relatedModule->table_name)
                                    ->pluck($displayColumn, $displayColumn)
                                    ->map(function ($label) use ($displayColumn, $relatedModule) {
                                        if (empty($label)) {
                                            $label =
                                                'Please add data to: (' .
                                                $displayColumn .
                                                '), in the module: (' .
                                                $relatedModule->name .
                                                ')';
                                        }

                                        return $label;
                                    })
                                    ->toArray();

                                $optionsis = DB::table($relatedModule->table_name)
                                    ->pluck($displayColumn, $displayColumn)
                                    ->map(function ($label) use ($displayColumn, $relatedModule) {
                                        if (empty($label)) {
                                            $label = '';
                                        }

                                        return $label;
                                    })
                                    ->toArray();

                                if (in_array('', $optionsis) || $optionsis == null) {
                                    $placeholder =
                                        'Please add data to: (' .
                                        $displayColumn .
                                        '), in the module: (' .
                                        $relatedModule->name .
                                        ')';
                                }
                            }
                        } else {
                            $manualOptions = array_map('trim', explode(',', $field->options ?? ''));
                            $options = array_combine($manualOptions, $manualOptions);
                        }
                        $component = Select::make($field->column_name)
                            ->options($options)
                            ->disabled(!$columnStringExists && $field->source_type === 'module')
                            ->helperText($placeholder);
                        break;
                    case 'image': // <-- Handle image type
                        $component = FileUpload::make($field->column_name)
                            ->image()
                            ->imageEditor()
                            ->visibility('public')
                            ->columnSpanFull()
                            ->storeFiles(false)
                            ->maxSize(3072)
                            ->mutateDehydratedStateUsing(function ($state, ?Model $record) use ($field): ?string {
                                // Skenario 1: File baru diunggah
                                if ($state instanceof TemporaryUploadedFile) {
                                    $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));
                                    $uploadResult = $cloudinary->uploadApi()->upload($state->getRealPath(), [
                                        'folder' => 'modules/' . $this->module->table_name,
                                        'public_id' => Str::random(20),
                                        'upload_preset' => 'modules',
                                    ]);

                                    return $uploadResult['secure_url']; // Simpan URL gambar
                                }
                                // Skenario 2: Edit tanpa upload baru
                                if (empty($state) && $record) {
                                    return $record->{$field->column_name};
                                }

                                // Skenario 3: Create tanpa upload, atau state adalah path lama
                                return $state;
                            })
                            ->helperText('maximum image size is 3MB.');
                        break;
                    case 'file': // <-- Handle file type
                        $component = FileUpload::make($field->column_name)
                            ->visibility('public')
                            ->columnSpanFull()
                            ->storeFiles(false)
                            ->maxSize(5120)
                            ->mutateDehydratedStateUsing(function ($state, ?Model $record) use ($field): ?string {
                                // Skenario 1: File baru diunggah
                                if ($state instanceof TemporaryUploadedFile) {
                                    $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));
                                    $uploadResult = $cloudinary->uploadApi()->upload($state->getRealPath(), [
                                        'folder' => 'modules/' . $this->module->table_name,
                                        'public_id' => Str::random(20),
                                        'upload_preset' => 'modules',
                                        'resource_type' => 'raw', // penting untuk PDF, ZIP, dll
                                    ]);

                                    return $uploadResult['secure_url']; // Simpan URL gambar
                                }
                                // Skenario 2: Edit tanpa upload baru
                                if (empty($state) && $record) {
                                    return $record->{$field->column_name};
                                }

                                // Skenario 3: Create tanpa upload, atau state adalah path lama
                                return $state;
                            })
                            ->helperText('maximum file size is 5MB.');
                        break;
                    default:
                        $component = TextInput::make($field->column_name);
                }

                $component->label(Str::headline($field->column_name));

                if ($field->is_required) {
                    // Terapkan 'required' secara berbeda untuk file/gambar
                    if (in_array($field->data_type, ['image', 'file'])) {
                        // Hanya wajib saat membuat ('create'), tidak saat mengedit ('edit')
                        $component->required(fn(string $operation): bool => $operation === 'create');
                    } else {
                        // Perilaku normal untuk semua tipe kolom lainnya
                        $component->required();
                    }
                }

                if ($field->is_unique) {
                    $component->unique(
                        table: $this->module->table_name,
                        column: $field->column_name,
                        ignoreRecord: true, // Ini penting agar validasi bekerja saat mengedit
                    );
                }

                return $component;
            })
            ->all();
    }

    protected function getTableQuery(): Builder
    {
        if ($this->modelClass) {
            return $this->modelClass::query();
        }

        return User::query()->whereRaw('0 = 1');
    }

    protected function getTableColumns(): array
    {
        if (!$this->module) {
            return [];
        }
        $columns = [];
        foreach ($this->module->fields as $field) {
            if (in_array($field->data_type, ['text', 'wysiwyg'])) {
                continue;
            }
            $tableColumn = null;

            if ($this->isStatusColumn($field->column_name) && $field->data_type === 'options') {
                $tableColumn = TextColumn::make($field->column_name)
                    ->label(Str::headline($field->column_name))
                    ->badge()
                    ->color(fn(?string $state): string => $this->getStatusColor($state) ?? 'primary')
                    ->searchable();
            } else {
                // Logika lama untuk kolom non-status
                switch ($field->data_type) {
                    case 'boolean':
                        $tableColumn = IconColumn::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->boolean();
                        break;
                    case 'image':
                        $tableColumn = ImageColumn::make($field->column_name)
                            ->disk('cloudinary')
                            ->label(Str::headline($field->column_name));
                        break;
                    case 'file': // <-- LOGIKA BARU UNTUK MENAMPILKAN IKON FILE
                        $tableColumn = IconColumn::make($field->column_name)
                            ->icon(fn(?string $state): string => $this->getFileIcon($state))
                            ->color('primary')
                            ->label(Str::headline($field->column_name))
                            ->url(fn(?string $state): ?string => $state, shouldOpenInNewTab: true);
                        break;
                    default:
                        $tableColumn = TextColumn::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->searchable()
                            ->sortable();
                        break;
                }
            }

            if ($tableColumn) {
                array_push(
                    $columns,
                    $tableColumn,
                    TextColumn::make('created_at')
                        ->dateTime('M d, Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->dateTime('M d, Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                );
            }
        }

        return $columns;
    }

    protected function getTableFilters(): array
    {
        if (!$this->module) {
            return [];
        }

        $filters = [];

        foreach ($this->module->fields as $field) {
            $filter = null;
            $dataType = strtolower($field->data_type);

            switch ($dataType) {
                case 'options':
                    if (!empty($field->options)) {
                        $options = array_map('trim', explode(',', $field->options));
                        $filter = SelectFilter::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->options(array_combine($options, $options));
                    }
                    break;

                case 'date':
                    $labelBase = Str::headline($field->column_name); // Contoh: "Tanggal Lahir" dari "tanggal_lahir"
                    $filter = Filter::make($field->column_name)
                        ->form([
                            FormDatePicker::make('created_from')
                                ->label($labelBase . ' (from)')
                                ->live()
                                ->maxDate(fn(Get $get): ?string => $get('created_until'))
                                // 4. Tambahkan aturan validasi di sisi server sebagai pengaman
                                ->rule(
                                    'before_or_equal:created_until',
                                    // Hanya jalankan aturan ini jika 'created_until' sudah diisi
                                    fn(Get $get): bool => filled($get('created_until')),
                                ),
                            FormDatePicker::make('created_until')
                                ->label($labelBase . ' (until)')
                                ->live()
                                ->minDate(fn(Get $get): ?string => $get('created_from'))
                                ->rule(
                                    'after_or_equal:created_from',
                                    // Hanya jalankan aturan ini jika 'created_from' sudah diisi
                                    fn(Get $get): bool => filled($get('created_from')),
                                ),
                        ])
                        ->query(function (Builder $query, array $data) use ($field): Builder {
                            return $query
                                ->when(
                                    $data['created_from'],
                                    fn(Builder $query, $date): Builder => $query->whereDate(
                                        $field->column_name,
                                        '>=',
                                        $date,
                                    ),
                                )
                                ->when(
                                    $data['created_until'],
                                    fn(Builder $query, $date): Builder => $query->whereDate(
                                        $field->column_name,
                                        '<=',
                                        $date,
                                    ),
                                );
                        });
                    break;

                case 'boolean':
                    $filter = SelectFilter::make($field->column_name)
                        ->label(Str::headline($field->column_name))
                        ->options([
                            1 => 'Yes',
                            0 => 'No',
                        ]);
                    break;
            }

            if ($filter) {
                $filters[] = $filter;
            }
        }

        $filters[] = Filter::make('created_at')
            ->form([
                // 2. Jadikan komponen ini 'live' agar perubahannya bisa dideteksi
                FormDatePicker::make('created_from')->label('Created from')->live(),
                FormDatePicker::make('created_until')
                    ->label('Created until')
                    // 3. Secara dinamis set tanggal minimum berdasarkan input 'created_from'
                    ->minDate(fn(Get $get): ?string => $get('created_from'))
                    // 4. Tambahkan aturan validasi di sisi server sebagai pengaman
                    ->rule(
                        'after_or_equal:created_from',
                        // Hanya jalankan aturan ini jika 'created_from' sudah diisi
                        fn(Get $get): bool => filled($get('created_from')),
                    ),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['created_from'],
                        fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['created_until'],
                        fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                    );
            });

        return $filters;
    }

    public function getTitle(): string
    {
        return $this->module?->name ?? 'Modul';
    }

    protected function getFileIcon(?string $state): string
    {
        if (blank($state)) {
            return 'heroicon-o-x-circle'; // Ikon jika tidak ada file
        }

        $extension = strtolower(pathinfo($state, PATHINFO_EXTENSION));

        return match ($extension) {
            'doc', 'docx', 'pdf' => 'heroicon-o-document-text',
            'xls', 'xlsx', 'csv' => 'heroicon-o-table-cells',
            'zip', 'rar', '7z', 'tar', 'tgz', 'tar.gz' => 'heroicon-o-archive-box',
            'txt', 'md', 'json' => 'heroicon-o-document',
            default => 'heroicon-o-paper-clip', // Ikon default
        };
    }

    protected function getExportableFields(string $format = 'excel')
    {
        $excelExcludes = ['image', 'file', 'wysiwyg', 'text'];
        $documentExcludes = ['image', 'file', 'wysiwyg'];

        $excludes = $format === 'excel' ? $excelExcludes : $documentExcludes;

        return $this->module->fields->filter(fn($field) => !in_array($field->data_type, $excludes));
    }

    protected function getExportHeaders(string $format = 'excel'): array
    {
        return $this->getExportableFields($format)->map(fn($field) => Str::headline($field->column_name))->toArray();
    }
}
