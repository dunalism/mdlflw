<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $moduleName }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <h2>Export data: {{ $moduleName }}</h2>
    <p>Export date: {{ now()->format('d F Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if ($records->isEmpty())
                <tr>
                    <td colspan="{{ count($headers) }}">Tidak ada data.</td>
                </tr>
            @else
                @foreach ($records as $record)
                    <tr>
                        @foreach ($fields as $field)
                            <td>
                                {{-- Menangani nilai boolean agar tampil sebagai Ya/Tidak --}}
                                @if ($field->data_type === 'boolean')
                                    {{ $record->{$field->column_name} ? 'Yes' : 'No' }}
                                @else
                                    {{ $record->{$field->column_name} }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</body>

</html>

