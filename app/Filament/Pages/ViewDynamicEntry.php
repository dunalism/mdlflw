<?php

namespace App\Filament\Pages;

use App\Filament\Traits\HandlesDynamicStatusDisplay;
use App\Models\Module;
use Cloudinary\Cloudinary;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewDynamicEntry extends Page implements HasInfolists
{
    use HandlesDynamicStatusDisplay;
    use InteractsWithInfolists; // <-- 3. Gunakan Trait

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.view-dynamic-entry';

    protected static bool $shouldRegisterNavigation = false;

    public ?Module $module = null;

    public ?Model $record = null;

    public ?string $permissionBase = null;

    public function mount(): void
    {
        $moduleName = request()->query('module');
        $recordId = request()->query('record');

        if (! $moduleName || ! $recordId) {
            abort(404);
        }

        $this->module = Module::with('fields')->where('table_name', $moduleName)->firstOrFail();
        $modelName = Str::studly(Str::singular($this->module->table_name));
        $modelClass = 'App\\Models\\Dynamic\\'.$modelName;
        $this->permissionBase = Str::singular($this->module->table_name);

        $this->record = $modelClass::findOrFail($recordId);
    }

    /**
     * =================================================================
     * 1. TAMBAHKAN AKSI DI HEADER
     * =================================================================
     */
    protected function getHeaderActions(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return [
            // Tombol "Kembali" untuk navigasi
            Action::make('back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => ListDynamicEntries::getUrl(['module' => $this->module->table_name])),

            // Tombol "Edit" untuk membuka modal formulir
            EditAction::make()
                ->record($this->record)
                ->form($this->getFormSchema())
                ->visible(fn (): bool => $user?->can('update_'.$this->permissionBase) ?? false),

            // Tombol "Hapus" dengan konfirmasi
            DeleteAction::make()
                ->record($this->record)
                ->after(fn () => redirect(ListDynamicEntries::getUrl(['module' => $this->module->table_name])))
                ->visible(fn (): bool => $user?->can('delete_'.$this->permissionBase) ?? false),
        ];
    }

    /**
     * =================================================================
     * 2. BUAT JUDUL HALAMAN MENJADI LEBIH DESKRIPTIF
     * =================================================================
     */

    // public function getTitle(): string
    // {
    //     $recordIdentifier = $this->record->name ?? ($this->record->title ?? $this->record->id);
    //     return Str::singular($this->module->name) . ': ' . $recordIdentifier;
    // }

    public function getTitle(): string
    {
        return 'Detail '.Str::singular($this->module?->name ?? 'Modul');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->record($this->record)->schema($this->getInfolistSchema());
    }

    protected function getInfolistSchema(): array
    {
        if (! $this->record) {
            return [];
        }

        return $this->module->fields
            ->map(function ($field) {
                if ($this->isStatusColumn($field->column_name)) {
                    return TextEntry::make($field->column_name)
                        ->label(Str::headline($field->column_name))
                        ->badge()
                        ->color(fn (?string $state): string => $this->getStatusColor($state) ?? 'primary');
                }

                // Logika lama untuk kolom non-status
                switch ($field->data_type) {
                    case 'boolean':
                        return IconEntry::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->boolean();
                    case 'wysiwyg':
                        return TextEntry::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->html()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'prose dark:prose-invert']);

                    case 'image':
                        $claudinaryUrl = $this->record->{$field->column_name};

                        return ImageEntry::make($field->column_name)
                            ->disk('cloudinary')
                            ->label(Str::headline($field->column_name))
                            ->height('300px') // Sesuaikan tinggi yang kamu inginkan
                            ->extraAttributes([
                                'style' => 'object-fit: contain; width: 100%; max-width: 500px;',
                                'onclick' => "window.open('$claudinaryUrl', '_blank')",
                            ]);

                    case 'file': // <-- LOGIKA BARU UNTUK TIPE FILE
                        return TextEntry::make($field->column_name)
                            ->label(Str::headline($field->column_name))
                            ->formatStateUsing(function (?string $state): ?string {
                                if (blank($state)) {
                                    return 'Tidak ada file';
                                }

                                // Render tombol download
                                return Blade::render(
                                    '<x-filament::button tag="a" href="'.
                                        e($state).
                                        '" target="_blank" icon="heroicon-o-arrow-down-tray">Download File</x-filament::button>',
                                );
                            })
                            ->html();
                        break;
                    default:
                        return TextEntry::make($field->column_name)->label(Str::headline($field->column_name));
                }
            })
            ->all();
    }

    protected function getFormSchema(): array
    {
        if (! $this->module) {
            return [];
        }
        $components = [];

        foreach ($this->module->fields as $field) {
            $component = null;
            switch ($field->data_type) {
                case 'string':
                    $component = Forms\Components\TextInput::make($field->column_name);
                    break;
                case 'integer':
                    $component = Forms\Components\TextInput::make($field->column_name)->numeric();
                    break;
                case 'text':
                    $component = Forms\Components\Textarea::make($field->column_name)->columnSpanFull();
                    break;
                case 'wysiwyg':
                    $component = Forms\Components\RichEditor::make($field->column_name)
                        ->columnSpanFull()
                        ->disableToolbarButtons(['attachFiles']);
                    break;
                case 'options':
                    $options = [];
                    if ($field->source_type === 'module' && $field->relatedModule) {
                        $relatedModule = $field->relatedModule;
                        $displayColumn =
                            $relatedModule->fields()->where('data_type', 'string')->first()?->column_name ?? 'id';

                        $options = DB::table($relatedModule->table_name)
                            ->pluck($displayColumn, $displayColumn)
                            ->map(
                                fn ($label) => $label ??
                                    'Please add data to: ('.
                                        $displayColumn.
                                        '), in the module: ('.
                                        $relatedModule->name.
                                        ')',
                            )
                            ->toArray();
                    } else {
                        $manualOptions = array_map('trim', explode(',', $field->options ?? ''));
                        $options = array_combine($manualOptions, $manualOptions);
                    }
                    $component = Select::make($field->column_name)->options($options);
                    break;
                case 'image':
                    $component = FileUpload::make($field->column_name)
                        ->image()
                        ->imageEditor()
                        ->visibility('public')
                        ->columnSpanFull()
                        ->storeFiles(false)
                        ->maxSize(3072)
                        ->mutateDehydratedStateUsing(function ($state, ?Model $record) use ($field): ?string {
                            if ($state instanceof TemporaryUploadedFile) {
                                $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));
                                $uploadResult = $cloudinary->uploadApi()->upload($state->getRealPath(), [
                                    'folder' => 'modules/'.$this->module->table_name,
                                    'public_id' => Str::random(20),
                                    'upload_preset' => 'modules',
                                ]);

                                return $uploadResult['secure_url'];
                            }
                            if (empty($state) && $record) {
                                return $record->{$field->column_name};
                            }

                            return $state;
                        })
                        ->helperText('maximum image size is 3MB.');
                    break;
                case 'file':
                    $component = FileUpload::make($field->column_name)
                        ->visibility('public')
                        ->columnSpanFull()
                        ->storeFiles(false)
                        ->maxSize(5120)
                        ->mutateDehydratedStateUsing(function ($state, ?Model $record) use ($field): ?string {
                            if ($state instanceof TemporaryUploadedFile) {
                                $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));
                                $uploadResult = $cloudinary->uploadApi()->upload($state->getRealPath(), [
                                    'folder' => 'modules/'.$this->module->table_name,
                                    'public_id' => Str::random(20),
                                    'upload_preset' => 'modules',
                                    'resource_type' => 'raw',
                                ]);

                                return $uploadResult['secure_url'];
                            }
                            if (empty($state) && $record) {
                                return $record->{$field->column_name};
                            }

                            return $state;
                        })
                        ->helperText('maximum file size is 5MB.');
                    break;
                case 'date':
                    $component = Forms\Components\DatePicker::make($field->column_name);
                    break;
                case 'boolean':
                    $component = Forms\Components\Toggle::make($field->column_name);
                    break;
            }

            if ($component) {
                $component->label(Str::headline($field->column_name));
                if ($field->is_required) {
                    // Terapkan 'required' secara berbeda untuk file/gambar
                    if (in_array($field->data_type, ['image', 'file'])) {
                        // Hanya wajib saat membuat ('create'), tidak saat mengedit ('edit')
                        $component->required(fn (string $operation): bool => $operation === 'create');
                    } else {
                        // Perilaku normal untuk semua tipe kolom lainnya
                        $component->required();
                    }
                }
                if ($field->is_unique) {
                    $component->unique($this->module->table_name, $field->column_name, ignoreRecord: true);
                }
                $components[] = $component;
            }
        }

        return $components;
    }
}
