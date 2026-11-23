@php
    // IP yang dilihat server. Jika proxy/load balancer sudah didaftarkan di TrustProxies,
    // request()->ip() otomatis mengembalikan IP klien asli dari X-Forwarded-For.
    $networkIp = request()->ip();

    // IP asal yang dilaporkan proxy (hanya ditampilkan bila berbeda dan valid).
    $forwardedRaw = trim(explode(',', (string) request()->header('X-Forwarded-For'))[0] ?? '');
    $forwardedIp  = filter_var($forwardedRaw, FILTER_VALIDATE_IP) ? $forwardedRaw : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NADI Central | Login</title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='0.35'%3E%3Cstop offset='0' stop-color='%232f4cf0'/%3E%3Cstop offset='0.55' stop-color='%230a8fe0'/%3E%3Cstop offset='1' stop-color='%2308b5c4'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='18' fill='url(%23g)'/%3E%3Cg transform='translate(15 15) scale(1.4167)' fill='none' stroke='%23fff' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M2 12h5l2-4 3 9 3-13 2 8h5'/%3E%3C/g%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #eaf0fb;
            --surface: #ffffff;
            --ink: #0b1430;
            --muted: #5b6685;
            --line: #dce3f1;
            --accent: #2f4cf0;
            --cyan: #08b5c4;
            --ok: #12a150;
            --danger: #d92d20;
            --brand: linear-gradient(100deg, #2f4cf0 0%, #0a8fe0 55%, #08b5c4 100%);
            --font: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            font-family: var(--font);
            color: var(--ink);
            background:
                radial-gradient(680px 440px at 10% 6%, rgba(47, 76, 240, .20), transparent 62%),
                radial-gradient(720px 480px at 92% 98%, rgba(8, 181, 196, .22), transparent 62%),
                var(--bg);
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
        }

        /* Lapisan 1: grid presisi */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(30, 41, 110, .075) 1px, transparent 1px),
                linear-gradient(90deg, rgba(30, 41, 110, .075) 1px, transparent 1px);
            background-size: 44px 44px;
            -webkit-mask-image: radial-gradient(ellipse 70% 65% at 50% 50%, #000 20%, transparent 80%);
            mask-image: radial-gradient(ellipse 70% 65% at 50% 50%, #000 20%, transparent 80%);
            pointer-events: none;
        }

        /* Lapisan 2: cincin konsentris ala radar, statis */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                repeating-radial-gradient(circle at 50% 50%,
                    transparent 0, transparent 95px,
                    rgba(47, 76, 240, .14) 95px, rgba(47, 76, 240, .14) 96px);
            -webkit-mask-image: radial-gradient(circle at 50% 50%, #000 0, transparent 62%);
            mask-image: radial-gradient(circle at 50% 50%, #000 0, transparent 62%);
            pointer-events: none;
        }

        .wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        .brand { text-align: center; margin-bottom: 28px; }

        .brand-mark {
            position: relative;
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            border-radius: 18px;
            background: var(--brand);
            display: grid;
            place-items: center;
            box-shadow:
                0 16px 34px -12px rgba(47, 76, 240, .85),
                0 0 0 6px rgba(47, 76, 240, .08),
                0 0 0 7px rgba(47, 76, 240, .16);
        }

        .brand-mark svg { width: 31px; height: 31px; }

        .brand-name {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: .26em;
            padding-left: .26em;
            line-height: 1;
            background: linear-gradient(100deg, #0b1430 0%, #2f4cf0 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .brand-full {
            margin-top: 10px;
            font: 500 11px var(--mono);
            color: var(--muted);
        }

        /* Kartu: border gradien, cahaya tipis, garis cahaya di tepi atas */
        .card {
            position: relative;
            padding: 38px 34px 28px;
            border: 1px solid transparent;
            border-radius: 22px;
            background:
                linear-gradient(var(--surface), var(--surface)) padding-box,
                linear-gradient(140deg, rgba(47, 76, 240, .75), rgba(8, 181, 196, .65) 45%, rgba(220, 227, 241, .9) 72%) border-box;
            box-shadow:
                0 30px 70px -34px rgba(30, 41, 110, .5),
                0 0 70px -18px rgba(8, 181, 196, .35);
        }

        .card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 28px;
            right: 28px;
            height: 3px;
            border-radius: 0 0 4px 4px;
            background: var(--brand);
            box-shadow: 0 6px 18px rgba(47, 76, 240, .45);
        }

        /* Tanda sudut ala panel instrumen */
        .corner {
            position: absolute;
            width: 16px;
            height: 16px;
            border: 0 solid var(--accent);
            pointer-events: none;
        }

        .corner.tl { top: -8px; left: -8px; border-top-width: 2px; border-left-width: 2px; border-top-left-radius: 5px; }
        .corner.tr { top: -8px; right: -8px; border-top-width: 2px; border-right-width: 2px; border-top-right-radius: 5px; }
        .corner.bl { bottom: -8px; left: -8px; border-bottom-width: 2px; border-left-width: 2px; border-bottom-left-radius: 5px; }
        .corner.br { bottom: -8px; right: -8px; border-bottom-width: 2px; border-right-width: 2px; border-bottom-right-radius: 5px; }

        .card-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .card-subtitle {
            margin: 0 0 26px;
            font-size: 13.5px;
            color: var(--muted);
        }

        .alert-error {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
            color: #b42318;
            background: #fef3f2;
            border: 1px solid #fecdca;
            border-radius: 12px;
        }

        .alert-error svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }

        .form-group { margin-bottom: 18px; }

        .form-label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; }

        .field { position: relative; }

        .field > .icon {
            position: absolute;
            top: 50%;
            left: 15px;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            color: #8a94b2;
            pointer-events: none;
            transition: color .2s;
        }

        .field:focus-within > .icon { color: var(--accent); }

        .form-control {
            width: 100%;
            height: 50px;
            padding: 0 14px 0 44px;
            font: 500 14px var(--font);
            color: var(--ink);
            background: #f6f8fe;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .form-control::placeholder { color: #9aa3bd; }

        .form-control:focus {
            background: #fff;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(47, 76, 240, .14), 0 0 22px -4px rgba(8, 181, 196, .45);
        }

        .form-control.is-invalid { border-color: var(--danger); }

        .password-wrapper .form-control { padding-right: 52px; }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 7px;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            transition: color .2s, background .2s;
        }

        .password-toggle svg { width: 20px; height: 20px; }
        .password-toggle .eye-off { display: none; }
        .password-toggle.is-visible .eye { display: none; }
        .password-toggle.is-visible .eye-off { display: block; }
        .password-toggle:hover { color: var(--accent); background: rgba(47, 76, 240, .09); }

        .error-message { margin-top: 7px; font-size: 12px; color: var(--danger); }

        .hint { display: none; margin-top: 7px; font-size: 12px; color: #b54708; }
        .hint.is-on { display: block; }

        .login-button {
            width: 100%;
            height: 52px;
            margin-top: 10px;
            border: 0;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font: 700 15px var(--font);
            cursor: pointer;
            box-shadow:
                0 14px 28px -14px rgba(47, 76, 240, .9),
                inset 0 1px 0 rgba(255, 255, 255, .35);
            transition: filter .2s;
        }

        .login-button:hover { filter: brightness(1.07); }
        .login-button:disabled { opacity: .75; cursor: not-allowed; filter: none; }

        .login-button .spinner {
            display: none;
            width: 15px;
            height: 15px;
            margin-right: 9px;
            vertical-align: -2px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        .login-button.is-loading .spinner { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Panel pembacaan sesi */
        .readout {
            margin-top: 24px;
            padding: 14px 16px;
            border: 1px dashed #c3cee8;
            border-radius: 12px;
            background: linear-gradient(180deg, #f4f7fe, #f0f6fc);
        }

        .readout-head {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 11px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
        }

        .led {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--ok);
            box-shadow: 0 0 0 3px rgba(18, 161, 80, .16), 0 0 10px rgba(18, 161, 80, .55);
        }

        .readout dl { margin: 0; display: grid; gap: 8px; }

        .readout .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 14px;
            font: 500 12px var(--mono);
        }

        .readout dt { color: var(--muted); white-space: nowrap; }

        .readout dd {
            margin: 0;
            text-align: right;
            color: var(--ink);
            word-break: break-all;
        }

        .readout dd.is-muted { color: #8a94b2; }

        .login-footer {
            margin-top: 26px;
            font: 500 11px/1.7 var(--mono);
            text-align: center;
            color: #8a94b2;
        }

        :focus-visible { outline: 3px solid rgba(47, 76, 240, .45); outline-offset: 2px; }
        .form-control:focus-visible { outline: none; }

        @media (max-width: 480px) {
            body { padding: 24px 16px; }
            .card { padding: 32px 22px 24px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-button .spinner { animation-duration: 1.6s; }
        }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12h5l2-4 3 9 3-13 2 8h5"/>
                </svg>
            </div>
            <div class="brand-name">NADI</div>
            <div class="brand-full">Network, Application &amp; Database Intelligence</div>
        </div>

        <div class="card">
            <span class="corner tl"></span>
            <span class="corner tr"></span>
            <span class="corner bl"></span>
            <span class="corner br"></span>

            <h1 class="card-title">Masuk ke NADI Central</h1>
            <p class="card-subtitle">Silakan masuk untuk melanjutkan.</p>

            @if ($errors->any())
                <div class="alert-error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" id="loginForm">
                @csrf

                <input type="hidden" name="device_ip" id="deviceIpField" value="">

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>

                    <div class="field">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}"
                            value="{{ old('username') }}"
                            placeholder="Masukkan username"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    @error('username')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>

                    <div class="field password-wrapper">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                            aria-pressed="false"
                            title="Tampilkan password"
                        >
                            <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-6.4 0-10-7-10-7a17.6 17.6 0 0 1 4.1-5M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17.7 17.7 0 0 1-2.2 3.2M9.9 9.9a3 3 0 0 0 4.2 4.2M2 2l20 20"/></svg>
                        </button>
                    </div>

                    <div class="hint" id="capsHint">Caps Lock aktif. Periksa huruf besar dan kecil pada password.</div>
                </div>

                <button type="submit" class="login-button" id="loginButton">
                    <span class="spinner" aria-hidden="true"></span>
                    <span id="loginLabel">Login</span>
                </button>
            </form>

            <div class="readout" aria-live="polite">
                <div class="readout-head">
                    <span class="led" aria-hidden="true"></span>
                    Sesi terdeteksi
                </div>

                <dl>
                    <div class="row">
                        <dt>IP Proxy</dt>
                        <dd>{{ $networkIp }}</dd>
                    </div>

                    @if ($forwardedIp && $forwardedIp !== $networkIp)
                        <div class="row">
                            <dt>IP Perangkat</dt>
                            <dd>{{ $forwardedIp }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="login-footer">
            NADI Monitoring Platform <br>
            Pesantren Islam Al-Irsyad Tengaran
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('loginForm');
            var passwordInput = document.getElementById('password');
            var toggle = document.getElementById('togglePassword');
            var capsHint = document.getElementById('capsHint');
            var button = document.getElementById('loginButton');
            var label = document.getElementById('loginLabel');
            var deviceIpText = document.getElementById('deviceIp');
            var deviceIpField = document.getElementById('deviceIpField');

            toggle.addEventListener('click', function () {
                var hidden = passwordInput.type === 'password';

                passwordInput.type = hidden ? 'text' : 'password';
                toggle.classList.toggle('is-visible', hidden);
                toggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');

                var text = hidden ? 'Sembunyikan password' : 'Tampilkan password';
                toggle.setAttribute('aria-label', text);
                toggle.setAttribute('title', text);
            });

            function checkCaps(e) {
                if (typeof e.getModifierState === 'function') {
                    capsHint.classList.toggle('is-on', e.getModifierState('CapsLock'));
                }
            }

            passwordInput.addEventListener('keyup', checkCaps);
            passwordInput.addEventListener('keydown', checkCaps);
            passwordInput.addEventListener('blur', function () {
                capsHint.classList.remove('is-on');
            });

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.classList.add('is-loading');
                label.textContent = 'Memproses...';
            });

            // Reset tombol jika halaman dipulihkan dari cache browser (tombol Back)
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    label.textContent = 'Login';
                }
            });
        })();
    </script>
</body>
</html>