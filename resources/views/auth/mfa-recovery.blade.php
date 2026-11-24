<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NADI Central | Kode Pemulihan MFA</title>
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

        .codes {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 22px 0;
        }

        code {
            padding: 12px;
            border-radius: 8px;
            background: #f1f4fa;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .warning {
            padding: 12px;
            border-radius: 8px;
            background: #fff7e6;
            line-height: 1.6;
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
    </style>
</head>
<body>
    <main class="card">
        <h1>Kode pemulihan MFA</h1>

        @if (session()->has('recovery_codes'))
            <p>Simpan kode-kode berikut di tempat aman. Setiap kode hanya dapat digunakan satu kali dan tidak akan ditampilkan kembali.</p>
            <div class="codes">
                @foreach (session('recovery_codes') as $code)
                    <code>{{ $code }}</code>
                @endforeach
            </div>
        @else
            <p class="warning">Kode pemulihan hanya ditampilkan satu kali setelah aktivasi. Jika belum menyimpannya, hubungi administrator untuk proses pemulihan akun.</p>
        @endif

        <form method="POST" action="{{ route('mfa.recovery.finish') }}">
            @csrf
            <button type="submit">Lanjutkan ke Dashboard</button>
        </form>
    </main>
</body>
</html>
