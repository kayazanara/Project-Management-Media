<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Login Project Management Media HK">

    <title>Login — Project Management Media HK</title>

    <!-- Fonts -->
    <link href="{{ asset('sb-admin-2/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap core (SB Admin 2) -->
    <link href="{{ asset('sb-admin-2/css/sb-admin-2.min.css') }}" rel="stylesheet">

    {{-- ⚡ FITUR: mode gelap/terang --}}
    <link href="{{ asset('css/tema-gelap.css') }}" rel="stylesheet">
    @include('partials.tema')

    <style>
        * {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4e73df 0%, #5b5dd8 35%, #6f5bcf 65%, #8b5cf6 100%);
            position: relative;
            overflow: hidden;
        }

        /* Decorative blobs di background */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            top: -250px;
            right: -250px;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%);
            bottom: -200px;
            left: -200px;
            pointer-events: none;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow:
                0 20px 60px rgba(78, 115, 223, 0.25),
                0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 40px 36px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            background: linear-gradient(135deg, #4e73df 0%, #6f5bcf 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(78, 115, 223, 0.35);
        }

        .login-logo i {
            font-size: 30px;
            color: #ffffff;
        }

        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a2e;
            text-align: center;
            margin: 0 0 6px;
            letter-spacing: -0.4px;
        }

        .login-subtitle {
            font-size: 13.5px;
            color: #8492a6;
            text-align: center;
            margin: 0 0 28px;
            font-weight: 500;
        }

        .form-group-custom {
            margin-bottom: 18px;
            position: relative;
        }

        .input-custom {
            width: 100%;
            height: 52px;
            border-radius: 14px;
            border: 1.5px solid #e5e9f2;
            padding: 0 48px 0 46px;
            font-size: 14.5px;
            color: #1a1a2e;
            background: #fafbfd;
            transition: all 0.2s ease;
            outline: none;
            box-sizing: border-box;
        }

        .input-custom::placeholder {
            color: #a4b0c1;
            font-weight: 500;
        }

        .input-custom:focus {
            border-color: #4e73df;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(78, 115, 223, 0.1);
        }

        .input-icon-left {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a4b0c1;
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-icon-right {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a4b0c1;
            font-size: 15px;
            cursor: pointer;
            transition: color 0.2s;
            background: none;
            border: none;
            padding: 0;
        }

        .input-icon-right:hover {
            color: #4e73df;
        }

        .btn-login {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #4e73df 0%, #6f5bcf 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px rgba(78, 115, 223, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(78, 115, 223, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
            color: #cbd3df;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e9f2;
        }

        .divider span {
            padding: 0 12px;
        }

        .btn-wa {
            width: 100%;
            height: 48px;
            border-radius: 14px;
            background: #f0fdf4;
            color: #16a34a;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1.5px solid #bbf7d0;
            transition: all 0.2s ease;
        }

        .btn-wa:hover {
            background: #dcfce7;
            border-color: #86efac;
            color: #15803d;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .btn-wa i {
            font-size: 18px;
        }

        .alert-custom {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-custom i {
            font-size: 15px;
            flex-shrink: 0;
        }

        .footer-text {
            text-align: center;
            color: rgba(255, 255, 255, 0.75);
            font-size: 12.5px;
            font-weight: 500;
            margin-top: 24px;
            letter-spacing: 0.2px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }
            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="login-card">

            {{-- Logo --}}
            <div class="login-logo">
                <i class="fas fa-photo-video"></i>
            </div>

            {{-- Title --}}
            <h1 class="login-title">Project Management Media HK</h1>
            <p class="login-subtitle">Silakan masuk untuk melanjutkan</p>

            {{-- Error --}}
            @if($errors->any())
                <div class="alert-custom">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- Form Login --}}
            <form action="{{ route('login.process') }}" method="POST">
                @csrf

                {{-- Email --}}
                <div class="form-group-custom">
                    <input type="email"
                           name="email"
                           id="emailInput"
                           class="input-custom"
                           placeholder="Masukkan alamat email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email">
                    <i class="fas fa-envelope input-icon-left"></i>
                </div>

                {{-- Password --}}
                <div class="form-group-custom">
                    <input type="password"
                           name="password"
                           id="passwordInput"
                           class="input-custom"
                           placeholder="Masukkan password"
                           required
                           autocomplete="current-password">
                    <i class="fas fa-lock input-icon-left"></i>
                    <button type="button"
                            class="input-icon-right"
                            id="togglePassword"
                            aria-label="Tampilkan / sembunyikan password">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>

            {{-- Divider --}}
            <div class="divider"><span>BUTUH BANTUAN?</span></div>

            {{-- Hubungi Admin via WhatsApp --}}
            <a href="https://wa.me/6285161148611?text=Assalamu'alaikum%2C%20saya%20butuh%20bantuan%20akun%20di%20sistem%20Project%20Management%20Media%20HK."
               target="_blank"
               rel="noopener"
               class="btn-wa">
                <i class="fab fa-whatsapp"></i>
                Hubungi Admin Media
            </a>

        </div>

        {{-- Footer --}}
        <p class="footer-text">
            &copy; {{ date('Y') }} PP Hubbul Khoir Sukoharjo — Divisi Media
        </p>
    </div>

    {{-- Toggle Password Show/Hide --}}
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>

</body>

</html>