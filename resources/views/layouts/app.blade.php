<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Dashboard Management Project Media">
    <meta name="author" content="">

    <title>{{ $title ?? 'Dashboard' }} - Project Media</title>

    <!-- Custom fonts for this template-->
    <link href="{{ asset('sb-admin-2/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="{{ asset('sb-admin-2/css/sb-admin-2.min.css') }}" rel="stylesheet">
    
    <!-- Cropper.js CSS untuk Fitur Crop Foto -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

    <!-- Custom CSS untuk Sidebar Sticky & Dropdown Profil -->
    <style>
        #accordionSidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        body.sidebar-toggled #accordionSidebar .sidebar-heading,
        body.sidebar-toggled #accordionSidebar span {
            display: none !important;
        }
        body.sidebar-toggled #accordionSidebar .sidebar-brand-text {
            display: none !important;
        }
        .profile-dropdown-card {
            width: 320px !important;
            padding: 20px !important;
        }
        .img-container {
            max-height: 300px;
            width: 100%;
        }
        .img-container img {
            max-width: 100%;
        }

        /* ===================================================== */
        /* WELCOME ANIMATION OVERLAY — Minimalis Premium         */
        /* ===================================================== */
        .welcome-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            background: linear-gradient(135deg, #4e73df 0%, #5b5dd8 35%, #6f5bcf 65%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            animation: welcomeOverlayOut 0.6s ease-in 2.4s forwards;
        }

        @keyframes welcomeOverlayOut {
            from { opacity: 1; visibility: visible; }
            to { opacity: 0; visibility: hidden; }
        }

        .welcome-content {
            text-align: center;
            color: #ffffff;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        /* "SELAMAT DATANG" — kecil, letter-spacing lebar */
        .welcome-salam {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 8px;
            text-transform: uppercase;
            opacity: 0;
            margin-bottom: 18px;
            color: rgba(255, 255, 255, 0.85);
            animation: welcomeSlideUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.1s forwards;
        }

        /* NAMA SANTRI — besar, tebal, capslock */
        .welcome-name {
            font-size: 44px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.15;
            opacity: 0;
            text-shadow: 0 6px 30px rgba(0, 0, 0, 0.2);
            animation: welcomeSlideUp 1s cubic-bezier(0.22, 1, 0.36, 1) 0.4s forwards;
        }

        /* Slide up + fade */
        @keyframes welcomeSlideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Garis dekoratif di bawah nama */
        .welcome-line {
            width: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.7);
            margin: 20px auto 0;
            animation: welcomeLineExpand 0.8s ease-out 1.1s forwards;
            border-radius: 2px;
        }

        @keyframes welcomeLineExpand {
            to { width: 60px; }
        }

        /* Subtext kecil */
        .welcome-sub {
            font-size: 12.5px;
            font-weight: 400;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.75);
            margin-top: 16px;
            opacity: 0;
            animation: welcomeSlideUp 0.7s ease-out 1.2s forwards;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .welcome-name { font-size: 30px; letter-spacing: 1px; }
            .welcome-salam { font-size: 12px; letter-spacing: 5px; margin-bottom: 14px; }
            .welcome-sub { font-size: 11.5px; }
        }
    </style>
</head>

<body id="page-top">

    {{-- ===================================================== --}}
    {{-- WELCOME ANIMATION OVERLAY (Minimalis + Sound) --}}
    {{-- ===================================================== --}}
    @if(session('show_welcome'))
        <div id="welcomeOverlay" class="welcome-overlay">
            <div class="welcome-content">
                <div class="welcome-salam">Selamat Datang</div>
                <div class="welcome-name">{{ session('welcome_user_name') }}</div>
                <div class="welcome-line"></div>
                <div class="welcome-sub">Semangat berkarya hari ini ✨</div>
            </div>
        </div>

        <script>
            // ==========================================
            // PREMIUM WELCOME CHIME (Web Audio API)
            // Menghasilkan suara "ding~" naik (C-E-G chord)
            // tanpa perlu file MP3/audio
            // ==========================================
            function playWelcomeSound() {
                try {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;

                    const ctx = new AudioContextClass();
                    const now = ctx.currentTime;

                    // Tone 1: C5 (523.25 Hz)
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.value = 523.25;
                    gain1.gain.setValueAtTime(0, now);
                    gain1.gain.linearRampToValueAtTime(0.15, now + 0.05);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
                    osc1.connect(gain1).connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 1);

                    // Tone 2: E5 (659.25 Hz) — delay 150ms
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.value = 659.25;
                    gain2.gain.setValueAtTime(0, now + 0.15);
                    gain2.gain.linearRampToValueAtTime(0.12, now + 0.20);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.0);
                    osc2.connect(gain2).connect(ctx.destination);
                    osc2.start(now + 0.15);
                    osc2.stop(now + 1.2);

                    // Tone 3: G5 (783.99 Hz) — delay 300ms, softest
                    const osc3 = ctx.createOscillator();
                    const gain3 = ctx.createGain();
                    osc3.type = 'sine';
                    osc3.frequency.value = 783.99;
                    gain3.gain.setValueAtTime(0, now + 0.30);
                    gain3.gain.linearRampToValueAtTime(0.10, now + 0.35);
                    gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.3);
                    osc3.connect(gain3).connect(ctx.destination);
                    osc3.start(now + 0.30);
                    osc3.stop(now + 1.5);
                } catch (e) {
                    // Silent fail — kalau browser blokir audio, tidak masalah
                    console.log('Welcome sound not played:', e);
                }
            }

            // Play sound saat overlay muncul (delay 200ms biar sinkron dengan animasi)
            setTimeout(playWelcomeSound, 200);

            // Auto-remove overlay setelah selesai (3.1 detik)
            setTimeout(function () {
                var overlay = document.getElementById('welcomeOverlay');
                if (overlay) overlay.remove();
            }, 3100);
        </script>
    @endif

    @php
        $user = auth()->user();
        $userDiv = strtolower(trim($user->divisi ?? $user->division ?? ''));
        $userRole = strtolower(trim($user->role ?? ''));
        
        $isAllAccess = str_contains($userDiv, 'pembina') || str_contains($userDiv, 'ketua') || $userRole === 'administrator' || str_contains($userRole, 'ustadz');
        
        $isLapangan = str_contains($userDiv, 'lapangan');
        $isEditing = str_contains($userDiv, 'editing');
        $isBroadcast = str_contains($userDiv, 'broadcast');
        $isAiResearch = str_contains($userDiv, 'ai') || str_contains($userDiv, 'research');

        // Statistik Project Pengguna
        $userCompletedProjects = 0;
        $userActiveProjects = 0;
        if ($user) {
            $userCompletedProjects = \App\Models\Project::whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            })->where('status', 'Completed')->count();

            $userActiveProjects = \App\Models\Project::whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            })->where('status', '!=', 'Completed')->count();
        }

        // Avatar URL yang benar menggunakan asset('storage/...')
        $avatarUrl = ($user && $user->avatar)
            ? asset('storage/avatars/' . $user->avatar)
            : asset('sb-admin-2/img/undraw_profile.svg');
    @endphp

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ url('/dashboard') }}">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-photo-video"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Project Media</div>
            </a>

            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a class="nav-link" href="{{ url('/dashboard') }}">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
            </li>

            <hr class="sidebar-divider">

            @if($isAllAccess)
                <div class="sidebar-heading">Menu Utama</div>
                
                <!-- Nav Item - Project Management -->
                <li class="nav-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('projects.index') }}">
                        <i class="fas fa-fw fa-project-diagram"></i>
                        <span>Project Management</span></a>
                </li>
                
                <!-- Nav Item - Stock Management -->
                <li class="nav-item {{ request()->routeIs('contents.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('contents.index') }}">
                        <i class="fas fa-fw fa-boxes"></i>
                        <span>Stock Management</span></a>
                </li>
                
                <!-- Nav Item - Team Management -->
                <li class="nav-item {{ request()->routeIs('team.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ url('/team') }}">
                        <i class="fas fa-fw fa-users"></i>
                        <span>Team Management</span></a>
                </li>

                <hr class="sidebar-divider">
                <div class="sidebar-heading">Monitoring Divisi</div>
                
                <!-- Nav Item - Tim & Laporan -->
                <li class="nav-item {{ request()->routeIs('division.*') ? 'active' : '' }}">
                    <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseMonitoring"
                        aria-expanded="true" aria-controls="collapseMonitoring">
                        <i class="fas fa-fw fa-desktop"></i>
                        <span>Tim & Laporan</span>
                    </a>
                    <div id="collapseMonitoring" class="collapse {{ request()->routeIs('division.*') ? 'show' : '' }}" aria-labelledby="headingMonitoring" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            <h6 class="collapse-header">Daftar Divisi:</h6>
                            <a class="collapse-item" href="{{ route('division.show', 'Tim Lapangan') }}">Tim Lapangan</a>
                            <a class="collapse-item" href="{{ route('division.show', 'Tim Editing') }}">Tim Editing</a>
                            <a class="collapse-item" href="{{ route('division.show', 'Tim Broadcast') }}">Tim Broadcast</a>
                            <a class="collapse-item" href="{{ route('division.show', 'Tim AI Research') }}">Tim AI Research</a>
                        </div>
                    </div>
                </li>
                
                <!-- Nav Item - Cetak Laporan -->
                <li class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('reports.index') }}">
                        <i class="fas fa-fw fa-print"></i>
                        <span>Cetak Laporan</span></a>
                </li>
            @else
                <div class="sidebar-heading">Monitoring Divisi</div>
                @if($isLapangan)
                    <li class="nav-item {{ request()->routeIs('division.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('division.show', 'Tim Lapangan') }}"><i class="fas fa-fw fa-desktop"></i><span>Tim Lapangan</span></a>
                    </li>
                @elseif($isEditing)
                    <li class="nav-item {{ request()->routeIs('division.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('division.show', 'Tim Editing') }}"><i class="fas fa-fw fa-desktop"></i><span>Tim Editing</span></a>
                    </li>
                @elseif($isBroadcast)
                    <li class="nav-item {{ request()->routeIs('contents.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('contents.index') }}">
                            <i class="fas fa-fw fa-boxes"></i>
                            <span>Stock Management</span>
                        </a>
                    </li>
                    <li class="nav-item {{ request()->routeIs('division.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('division.show', 'Tim Broadcast') }}"><i class="fas fa-fw fa-desktop"></i><span>Tim Broadcast</span></a>
                    </li>
                @elseif($isAiResearch)
                    <li class="nav-item {{ request()->routeIs('division.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('division.show', 'Tim AI Research') }}"><i class="fas fa-fw fa-desktop"></i><span>Tim AI Research</span></a>
                    </li>
                @endif
            @endif

            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-800 font-weight-bold" style="font-size: 15px;">{{ $user->name ?? 'User' }}</span>
                                <img class="img-profile rounded-circle shadow-sm" src="{{ $avatarUrl }}" style="width: 40px; height: 40px; object-fit: cover;">
                            </a>

                            <!-- Dropdown Detail Profil -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in profile-dropdown-card"
                                aria-labelledby="userDropdown">
                                
                                <div class="text-center mb-3">
                                    <img class="rounded-circle mb-2 shadow" src="{{ $avatarUrl }}" style="width: 85px; height: 85px; object-fit: cover; border: 3px solid #4e73df;">
                                    <h5 class="font-weight-bold text-gray-900 mb-1" style="font-size: 17px;">{{ $user->name ?? '-' }}</h5>
                                    <p class="mb-2">
                                        <span class="badge badge-primary px-2 py-1" style="font-size: 12px;">{{ $user->role ?? '-' }}</span>
                                        <span class="badge badge-secondary px-2 py-1" style="font-size: 12px;">{{ $user->divisi ?? $user->division ?? '-' }}</span>
                                    </p>
                                    <button class="btn btn-outline-primary btn-sm mt-1" data-toggle="modal" data-target="#changeAvatarModal" style="font-size: 12px;">
                                        <i class="fas fa-camera mr-1"></i> Ganti Foto Profil
                                    </button>
                                </div>

                                <div class="dropdown-divider"></div>

                                <div class="px-2 py-2 text-gray-800" style="font-size: 13.5px;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-dark">Project Selesai:</span>
                                        <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 13px;">{{ $userCompletedProjects }} Project</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="font-weight-bold text-dark">Project Aktif:</span>
                                        <span class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 13px;">{{ $userActiveProjects }} Project</span>
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item text-danger text-center font-weight-bold py-2 mt-1" href="#" data-toggle="modal" data-target="#logoutModal" style="font-size: 14px;">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @yield('content')
                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Project Media Management 2026</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>

    <!-- Modal Ganti & Crop Foto Profil -->
    <div class="modal fade" id="changeAvatarModal" tabindex="-1" role="dialog" aria-labelledby="changeAvatarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form action="{{ route('profile.updateAvatar') }}" method="POST" id="cropForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary" id="changeAvatarModalLabel">Ganti & Sesuaikan Foto Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <div class="form-group">
                        <input type="file" id="imageInput" class="form-control-file mb-3" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <!-- Area Crop Gambar -->
                    <div class="img-container" style="display: none;">
                        <img id="imageToCrop" src="" alt="Picture">
                    </div>
                    <!-- Input tersembunyi untuk menampung data base64 hasil crop -->
                    <input type="hidden" name="cropped_avatar" id="croppedAvatarInput">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="cropAndUploadBtn" style="display: none;">Simpan Foto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Pilih "Logout" di bawah jika ingin mengakhiri sesi ini.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        Logout
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript & Cropper.js Script -->
    <script src="{{ asset('sb-admin-2/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('sb-admin-2/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('sb-admin-2/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('sb-admin-2/js/sb-admin-2.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <script>
        let cropper;
        const imageInput = document.getElementById('imageInput');
        const imageToCrop = document.getElementById('imageToCrop');
        const cropForm = document.getElementById('cropForm');
        const croppedAvatarInput = document.getElementById('croppedAvatarInput');
        const cropAndUploadBtn = document.getElementById('cropAndUploadBtn');
        const imgContainer = document.querySelector('.img-container');

        imageInput.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                const reader = new FileReader();
                reader.onload = function (e) {
                    imageToCrop.src = e.target.result;
                    imgContainer.style.display = 'block';
                    cropAndUploadBtn.style.display = 'inline-block';
                    
                    if (cropper) {
                        cropper.destroy();
                    }
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1, // Persegi (1:1) agar pas untuk foto profil bulat
                        viewMode: 1,
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        cropForm.addEventListener('submit', function (e) {
            if (cropper) {
                e.preventDefault();
                const canvas = cropper.getCroppedCanvas({
                    width: 300,
                    height: 300,
                });
                croppedAvatarInput.value = canvas.toDataURL('image/jpeg');
                cropForm.submit();
            }
        });
    </script>

</body>

</html>
