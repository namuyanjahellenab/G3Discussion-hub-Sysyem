<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Hub | Register</title>
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
            max-width: 980px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(37, 99, 235, 0.08);
            padding: 36px 28px;
        }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand h1 { margin: 0; font-size: 2rem; font-weight: 700; color: #2563eb; }
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #dbe4f0;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .password-wrap { position: relative; }
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
        .guidelines {
            background: #f8fafc;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            padding: 16px;
            margin: 10px 0 18px;
        }
        .guidelines h3 { margin: 0 0 10px; font-size: 1rem; }
        .guidelines ul { margin: 0; padding-left: 18px; }
        .guidelines li { margin-bottom: 8px; color: #475569; }
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0 18px;
            font-size: 0.95rem;
            color: #334155;
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
        .footer-link {
            text-align: center;
            margin-top: 14px;
            color: #64748b;
        }
        .footer-link a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .error-text { color: #dc2626; font-size: 0.85rem; margin-top: 6px; }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
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

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" onsubmit="syncFullName()">
            @csrf
            <input type="hidden" name="name" id="full_name" value="{{ old('name') }}">

            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input id="first_name" class="form-control" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" class="form-control" type="text" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                @error('email')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" class="form-select" name="role">
                    <option value="Student" {{ old('role') == 'Student' ? 'selected' : '' }}>Student</option>
                    <option value="Lecturer" {{ old('role') == 'Lecturer' ? 'selected' : '' }}>Lecturer</option>
                    <option value="Administrator" {{ old('role') == 'Administrator' ? 'selected' : '' }}>Administrator</option>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" class="form-control" type="password" name="password" required autocomplete="new-password">
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
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="password-wrap">
                        <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required autocomplete="new-password">
                        <button type="button" aria-label="Toggle password visibility" onclick="togglePassword('password_confirmation', this)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="guidelines">
                <h3>Discussion Hub Guidelines</h3>
                <ul>
                    <li>Be respectful and professional in all discussions.</li>
                    <li>Share accurate information and cite your sources when needed.</li>
                    <li>Avoid posting personal data or confidential class material.</li>
                    <li>Use relevant categories and keep conversations constructive.</li>
                </ul>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" name="terms" id="terms" required style="accent-color:#2563eb;">
                <label for="terms">I agree to Discussion Hub guidelines</label>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="{{ route('login') }}">Log in here</a>
        </div>
    </div>
</div>

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        button.innerHTML = isHidden
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 8 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }

    function syncFullName() {
        const first = document.getElementById('first_name').value.trim();
        const last = document.getElementById('last_name').value.trim();
        document.getElementById('full_name').value = [first, last].filter(Boolean).join(' ');
    }
</script>
</body>
</html>
