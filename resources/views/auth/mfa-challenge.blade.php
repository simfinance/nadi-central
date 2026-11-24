<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NADI Central | Verifikasi MFA</title>
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
            max-width: 440px;
            padding: 32px;
            box-sizing: border-box;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(20, 35, 80, .12);
        }

        input {
            width: 100%;
            padding: 14px;
            margin-top: 10px;
            box-sizing: border-box;
            border: 1px solid #cbd3e3;
            border-radius: 8px;
            font-size: 20px;
            text-align: center;
            letter-spacing: .25em;
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

        .muted {
            color: #65708a;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Verifikasi dua langkah</h1>
        <p class="muted">Masukkan kode Google Authenticator untuk akun <strong>{{ $user->email }}</strong>. Kode pemulihan sekali pakai juga dapat digunakan.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('mfa.challenge.verify') }}">
            @csrf
            <label for="code">Kode autentikasi atau kode pemulihan</label>
            <input
                id="code"
                name="code"
                type="text"
                inputmode="text"
                autocomplete="one-time-code"
                minlength="6"
                maxlength="10"
                required
                autofocus
            >
            <button type="submit">Verifikasi dan Masuk</button>
        </form>
    </main>
</body>
</html>
