<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Hub | Login</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1f2937;
        }
        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
            padding: 36px 28px;
        }
        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
        }
        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #dbe4f0;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .password-wrap {
            position: relative;
        }
        .password-wrap button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            color: #64748b;
        }
        .btn {
            width: 100%;
            border: none;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary {
            background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
            color: #fff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.18);
        }
        .btn-outline {
            background: #fff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .footer-link {
            text-align: center;
            margin-top: 18px;
        }
        .footer-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .inline-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .inline-actions a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .error-text {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 6px;
        }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px; }
            .brand h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="auth-card">
        <div class="brand">
            <h1>Discussion Hub</h1>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" class="form-control" type="password" name="password" required autocomplete="current-password">
                    <button type="button" aria-label="Toggle password visibility" onclick="togglePassword('password', this)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="inline-actions">
                <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; color:#475569;">
                    <input type="checkbox" name="remember" style="accent-color:#2563eb;">
                    Remember me
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Forgot your password?</a>
                @endif
            </div>

            <div style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Log In</button>
            </div>
        </form>

        <div class="footer-link">
            <a href="{{ route('register') }}" class="btn btn-outline" style="display:inline-block; width:auto; text-decoration:none; padding:11px 18px;">Create New Account</a>
        </div>
    </div>
</div>

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        button.innerHTML = isHidden
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
</script>
</body>
</html>
