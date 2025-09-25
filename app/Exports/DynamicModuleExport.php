<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DynamicModuleExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $records;

    protected Collection $exportableFields;

    protected array $headings;

    public function __construct(Collection $records, Collection $exportableFields)
    {
        $this->records = $records;
        $this->exportableFields = $exportableFields;
        $this->headings = $this->exportableFields
            ->pluck('column_name')
            ->map(fn ($name) => \Illuminate\Support\Str::headline($name))
            ->toArray();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->records;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @param  mixed  $record
     */
    public function map($record): array
    {
        $mappedData = [];
        foreach ($this->exportableFields as $field) {
            $columnName = $field->column_name;
            $value = $record->{$columnName};

            // Format boolean sebagai "Ya" atau "Tidak"
            if ($field->data_type === 'boolean') {
                $mappedData[] = $value ? 'Ya' : 'Tidak';
            } else {
                $mappedData[] = $value;
            }
        }

        return $mappedData;
    }
}
