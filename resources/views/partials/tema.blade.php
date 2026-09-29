@php
    // Tema awal: dari akun (biar ikut di perangkat lain), fallback 'light'
    $temaAwal = auth()->check() ? (auth()->user()->theme_mode ?? 'light') : 'light';
@endphp

{{-- ⚡ ANTI-KEDIP: dijalankan SEBELUM halaman tampil, jadi tema tidak "berkedip" --}}
<script>
    (function () {
        try {
            var dariAkun  = @json($temaAwal);
            var dariLokal = localStorage.getItem('pm-tema');

            // Prioritas: pilihan di browser ini (paling baru) → kalau belum ada, ikut akun
            var tema = dariLokal || dariAkun || 'light';

            document.documentElement.setAttribute('data-theme', tema);

            // Samakan localStorage dengan akun saat pertama kali di perangkat baru
            if (!dariLokal && dariAkun) {
                localStorage.setItem('pm-tema', dariAkun);
            }
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

{{-- ⚡ SWITCH: tombolnya ada di topbar (lihat layouts/app.blade.php) --}}
<script>
    function pmTemaSekarang() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function pmPerbaruiIkon() {
        var ikon = document.getElementById('ikonTema');
        if (!ikon) return;
        var gelap = pmTemaSekarang() === 'dark';
        ikon.className = gelap ? 'fas fa-sun' : 'fas fa-moon';
        var tombol = document.getElementById('tombolTema');
        if (tombol) {
            tombol.title = gelap ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
        }
    }

    function pmGantiTema() {
        var baru = pmTemaSekarang() === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', baru);
        try { localStorage.setItem('pm-tema', baru); } catch (e) {}
        pmPerbaruiIkon();

        // Simpan ke akun supaya ikut di perangkat lain (tanpa reload halaman)
        try {
            fetch({!! json_encode(route('preferensi.tema'), JSON_UNESCAPED_SLASHES) !!}, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
                body: JSON.stringify({ theme: baru })
            }).catch(function () {});
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        pmPerbaruiIkon();

        // Chart.js: warna teks & garis dibuat lebih terang saat mode gelap
        if (window.Chart && pmTemaSekarang() === 'dark') {
            try {
                Chart.defaults.global.defaultFontColor = '#98a2bd';
                if (Chart.defaults.scale && Chart.defaults.scale.gridLines) {
                    Chart.defaults.scale.gridLines.color = 'rgba(255,255,255,0.09)';
                    Chart.defaults.scale.gridLines.zeroLineColor = 'rgba(255,255,255,0.16)';
                }
            } catch (e) {}
        }
    });
</script>
