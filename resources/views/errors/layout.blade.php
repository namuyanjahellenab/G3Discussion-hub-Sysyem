<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Error' }} | Discussion Hub</title>
    @vite(['resources/css/icons.css'])
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: var(--surface-bg); font-family: var(--font-body); color: var(--text-body);
        }
        .error-card {
            background: var(--surface-card); border: 1px solid var(--surface-border);
            border-top: 4px solid var(--luna-mid); border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium); padding: 40px; max-width: 440px; width: 90%;
            text-align: center;
        }
        .error-icon {
            width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 20px;
            background: var(--luna-lightest); color: var(--luna-dark);
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }
        .error-icon.danger { background: var(--accent-danger-bg); color: var(--accent-danger); }
        .error-code { font-family: var(--font-display); font-weight: 700; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .error-title { font-family: var(--font-display); font-weight: 700; font-size: 22px; color: var(--text-heading); margin: 0 0 10px; }
        .error-message { font-size: 14px; color: var(--text-muted); line-height: 1.5; margin-bottom: 26px; }
        .error-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
        .btn-primary { background: var(--luna-mid); color: #fff; }
        .btn-primary:hover { background: var(--luna-dark); color: #fff; }
        .btn-outline { background: #fff; border-color: var(--surface-border); color: var(--text-body); }
        .btn-outline:hover { border-color: var(--luna-mid); color: var(--luna-mid); }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon {{ $iconClass ?? '' }}"><i class="fa-solid {{ $icon ?? 'fa-triangle-exclamation' }}"></i></div>
        <div class="error-code">Error {{ $code ?? '' }}</div>
        <h1 class="error-title">{{ $title ?? 'Something went wrong' }}</h1>
        <p class="error-message">{{ $message ?? 'Please try again, or head back to somewhere useful.' }}</p>
        <div class="error-actions">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="fa-solid fa-house"></i> Go to Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Log in</a>
            @endauth
            <a href="javascript:history.back()" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Go back</a>
        </div>
    </div>
</body>
</html>
