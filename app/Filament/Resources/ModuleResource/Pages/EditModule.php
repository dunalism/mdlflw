<?php

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
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
                ->modalSubmitActionLabel('Yes, delete module'),
        ];
    }
}
