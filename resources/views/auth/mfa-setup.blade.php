<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NADI Central | Aktivasi MFA</title>
    <style>
        body {
            margin: 0;
            padding: 32px 16px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            box-sizing: border-box;
            background: #eef2fb;
            color: #17213b;
            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 560px;
            padding: 32px;
            box-sizing: border-box;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(20, 35, 80, .12);
        }

        h1 {
            margin-top: 0;
        }

        p {
            line-height: 1.6;
        }

        #qrcode {
            width: 220px;
            min-height: 220px;
            margin: 24px auto;
            display: grid;
            place-items: center;
        }

        .secret {
            padding: 14px;
            overflow-wrap: anywhere;
            border-radius: 8px;
            background: #f1f4fa;
            font-family: monospace;
            font-weight: 700;
            letter-spacing: .08em;
        }

        input {
            width: 100%;
            padding: 13px;
            margin-top: 8px;
            box-sizing: border-box;
            border: 1px solid #cbd3e3;
            border-radius: 8px;
            font-size: 18px;
            letter-spacing: .3em;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 18px;
            border: 0;
            border-radius: 9px;
            background: #3154df;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            margin: 12px 0;
            color: #b42318;
        }

        .note {
            padding: 12px;
            border-radius: 8px;
            background: #fff7e6;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Aktifkan Google Authenticator</h1>
        <p>Pengamanan MFA wajib diaktifkan untuk akun NADI Central <strong>{{ $user->email }}</strong>.</p>
        <p>1. Buka Google Authenticator, pilih tambah akun, lalu pindai QR berikut.</p>

        <div id="qrcode" aria-label="QR code untuk aktivasi MFA"></div>

        <p>Jika tidak dapat memindai QR, masukkan secret ini secara manual:</p>
        <div class="secret">{{ $secret }}</div>

        <p class="note">Gunakan tipe kunci berbasis waktu (Time-based). Secret ini terikat pada akun NADI di atas dan jangan dibagikan kepada siapa pun.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('mfa.setup.confirm') }}">
            @csrf
            <label for="code">Masukkan kode 6 digit dari aplikasi</label>
            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
            >
            <button type="submit">Konfirmasi dan Aktifkan MFA</button>
        </form>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function () {
            var target = document.getElementById('qrcode');
            var provisioningUri = @json($provisioningUri);

            if (window.QRCode) {
                new QRCode(target, {
                    text: provisioningUri,
                    width: 220,
                    height: 220,
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                target.textContent = 'QR tidak tersedia. Gunakan secret manual di atas.';
            }
        })();
    </script>
</body>
</html>
