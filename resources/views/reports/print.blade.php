<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kinerja & Evaluasi Divisi Media</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            background: #ffffff;
            color: #000000;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .report-header h2 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }
        .report-header h3 {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }
        .report-header p {
            font-size: 12px;
            margin: 0;
            text-align: center;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: left;
            vertical-align: middle;
            font-size: 11.5px;
        }
        th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-success { color: #1cc88a; font-weight: bold; }
        .text-danger { color: #e74a3b; font-weight: bold; }
        .font-weight-bold { font-weight: bold; }
        
        .no-print {
            text-align: center;
            margin-top: 30px;
        }
        .btn {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: bold;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        .btn-success { background-color: #1cc88a; }
        .btn-primary { background-color: #4e73df; }
        .btn-secondary { background-color: #858796; }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="container" id="reportContent">
        <!-- Header Rata Tengah & Proporsional -->
        <div class="report-header">
            <h2>PP HUBBUL KHOIR SUKOHARJO</h2>
            <h3>LAPORAN KINERJA & EVALUASI DIVISI MEDIA</h3>
            <p>Periode: <strong>{{ date('d F Y', strtotime($startDate)) }}</strong> s/d <strong>{{ date('d F Y', strtotime($endDate)) }}</strong></p>
        </div>

        <!-- Bagian I: Ringkasan Parameter Utama -->
        <div class="section-title">I. Ringkasan Parameter Utama Periode Ini</div>
        <table>
            <tr>
                <th width="35%">Personil Aktif Menyelesaikan Task</th>
                <th width="15%">Jumlah</th>
                <th width="35%">Konten Terposting Pekan Ini</th>
                <th width="15%">Jumlah</th>
            </tr>
            <tr>
                <td class="text-center">{{ $metrics['active_members'] }} dari {{ $metrics['total_members'] }} Orang</td>
                <td class="text-center font-weight-bold">Aktif</td>
                <td class="text-center text-success">{{ $metrics['posted_contents'] }} Konten <br><small style="font-size: 10px; color: #555;">(Pekan lalu: {{ $metrics['prev_posted_contents'] }})</small></td>
                <td class="text-center font-weight-bold text-success">Ready</td>
            </tr>
        </table>

        <!-- Bagian II: Performa & Target Divisi -->
        <div class="section-title">II. Performa & Target Divisi (Bandingan Pekan Ini & Lalu)</div>
        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nama Divisi</th>
                    <th width="13%">Project Baru</th>
                    <th width="15%">Task Selesai<br><small style="font-weight: normal;">(Pekan Ini)</small></th>
                    <th width="15%">Task Selesai<br><small style="font-weight: normal;">(Pekan Lalu)</small></th>
                    <th width="14%">Project Completed</th>
                    <th width="13%">Overdue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($divisions as $index => $div)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-weight-bold">{{ $div['name'] }}</td>
                    <td class="text-center">{{ $div['assigned'] }} Project</td>
                    <td class="text-center text-success">{{ $div['completed'] }} Task</td>
                    <td class="text-center">{{ $div['prev_completed'] }} Task</td>
                    <td class="text-center">{{ $div['completed_projects'] }} Project</td>
                    <td class="text-center text-danger">{{ $div['overdue'] }} Overdue</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Bagian III: Analisis Kendala Operasional -->
        <div class="section-title">III. Analisis Kendala Operasional Divisi</div>
        <table>
            <thead>
                <tr>
                    <th width="25%">Divisi</th>
                    <th width="38%">Kendala Pekan Ini</th>
                    <th width="37%">Kendala Pekan Sebelumnya</th>
                </tr>
            </thead>
            <tbody>
                @foreach($divisions as $div)
                <tr>
                    <td class="font-weight-bold">{{ $div['name'] }}</td>
                    <td>{{ $div['issues'] }}</td>
                    <td>{{ $div['prev_issues'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Tombol Aksi -->
    <div class="no-print">
        <button onclick="downloadWord()" class="btn btn-success">
            Unduh Dokumen (Word)
        </button>
        <button onclick="saveAsPDF()" class="btn btn-primary">
            Simpan PDF
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-secondary">
            Kembali
        </a>
    </div>

    <!-- Script Unduh & Simpan -->
    <script>
        function saveAsPDF() {
            window.print();
        }

        function downloadWord() {
            var content = document.getElementById("reportContent").innerHTML;
            // Menambahkan <br> di depan setiap section-title khusus untuk format Word agar ada jarak 1 enter nyata
            content = content.replace(/<div class="section-title">/g, '<br><div style="font-weight: bold; margin-top: 15px; margin-bottom: 5px; text-transform: uppercase;">');

            var header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><title>Laporan Kinerja</title><style>body{font-family:'Times New Roman'; font-size:11pt;} h2,h3,p{text-align:center;} table{border-collapse:collapse; width:100%; margin-bottom:15px;} th, td{border:1px solid black; padding:5px 8px; text-align:left;} th{background-color:#f2f2f2; text-align:center;}</style></head><body>";
            var footer = "</body></html>";
            var sourceHTML = header + content + footer;

            var source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
            var fileDownload = document.createElement("a");
            document.body.appendChild(fileDownload);
            fileDownload.href = source;
            fileDownload.download = 'Laporan_Kinerja_Divisi_Media.doc';
            fileDownload.click();
            document.body.removeChild(fileDownload);
        }
    </script>

</body>
</html>