<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Detail Aset - {{ $asset->code }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            width: 30%;
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .number-cell {
            text-align: right;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detail Aset</h1>
        <p>{{ $asset->code }}: {{ $asset->name }}</p>
        <p>Tanggal Cetak: {{ date('d/m/Y') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Informasi Umum</div>
        <table>
            <tr>
                <th>Kode Aset</th>
                <td>{{ $asset->code }}</td>
            </tr>
            <tr>
                <th>Nama Aset</th>
                <td>{{ $asset->name }}</td>
            </tr>
            <tr>
                <th>Kategori</th>
                <td>{{ $asset->category->name }}</td>
            </tr>
            <tr>
                <th>Perusahaan</th>
                <td>{{ $asset->company->name }}</td>
            </tr>
            <tr>
                <th>Cabang</th>
                <td>{{ $asset->branch->name }}</td>
            </tr>
            <tr>
                <th>Jenis</th>
                <td>{{ App\Models\Asset::assetTypes()[$asset->type] }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ App\Models\Asset::statusOptions()[$asset->status] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informasi Perolehan</div>
        <table>
            <tr>
                <th>Cara Perolehan</th>
                <td>{{ App\Models\Asset::acquisitionTypes()[$asset->acquisition_type] }}</td>
            </tr>
            <tr>
                <th>Tanggal Perolehan</th>
                <td>{{ $asset->acquisition_date ? date('d/m/Y', strtotime($asset->acquisition_date)) : '-' }}</td>
            </tr>
            <tr>
                <th>Nilai Perolehan</th>
                <td class="number-cell">{{ number_format($asset->cost_basis, 2) }}</td>
            </tr>
            <tr>
                <th>Kadaluwarsa Garansi</th>
                <td>{{ $asset->warranty_expiry ? date('d/m/Y', strtotime($asset->warranty_expiry)) : '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informasi Penyusutan</div>
        <table>
            <tr>
                <th>Dapat Disusutkan</th>
                <td>{{ $asset->is_depreciable ? 'Ya' : 'Tidak' }}</td>
            </tr>
            <tr>
                <th>Dapat Diamortisasi</th>
                <td>{{ $asset->is_amortizable ? 'Ya' : 'Tidak' }}</td>
            </tr>
            <tr>
                <th>Metode Penyusutan</th>
                <td>{{ App\Models\Asset::depreciationMethods()[$asset->depreciation_method] }}</td>
            </tr>
            <tr>
                <th>Nilai Residu</th>
                <td class="number-cell">{{ number_format($asset->salvage_value, 2) }}</td>
            </tr>
            <tr>
                <th>Umur Ekonomis (Bulan)</th>
                <td>{{ $asset->useful_life_months }}</td>
            </tr>
            <tr>
                <th>Tanggal Mulai Penyusutan</th>
                <td>{{ $asset->depreciation_start_date ? date('d/m/Y', strtotime($asset->depreciation_start_date)) : '-' }}</td>
            </tr>
            <tr>
                <th>Akumulasi Penyusutan</th>
                <td class="number-cell">{{ number_format($asset->accumulated_depreciation, 2) }}</td>
            </tr>
            <tr>
                <th>Nilai Buku</th>
                <td class="number-cell">{{ number_format($asset->net_book_value, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($asset->notes)
    <div class="section">
        <div class="section-title">Catatan</div>
        <p>{{ $asset->notes }}</p>
    </div>
    @endif
</body>
</html> 