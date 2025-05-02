<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Daftar Aset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin-bottom: 0;
        }
        .number-cell {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daftar Aset</h1>
        <p>Tanggal: {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>Jenis</th>
                <th>Tanggal Perolehan</th>
                <th>Nilai Perolehan</th>
                <th>Nilai Buku</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $asset)
            <tr>
                <td>{{ $asset->code }}</td>
                <td>{{ $asset->name }}</td>
                <td>{{ $asset->category->name }}</td>
                <td>{{ App\Models\Asset::assetTypes()[$asset->type] }}</td>
                <td>{{ $asset->acquisition_date ? date('d/m/Y', strtotime($asset->acquisition_date)) : '-' }}</td>
                <td class="number-cell">{{ number_format($asset->cost_basis, 2) }}</td>
                <td class="number-cell">{{ number_format($asset->net_book_value, 2) }}</td>
                <td>{{ App\Models\Asset::statusOptions()[$asset->status] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 