@extends('layouts.app')

@section('content')
@php
    $displayName = $user->UserName ?? $user->name ?? 'User';
    $initials = Str::initials($displayName);
    $currentTheme = $user->ThemeColor ?? 'luna';
@endphp

<style>
    .content-workspace { padding: 28px; }
    .page-header { margin-bottom: 24px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .settings-layout { display: flex; gap: 20px; align-items: flex-start; }
    .settings-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 20px; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }
    .panel-header p { font-size: 12.5px; color: var(--text-muted); margin: 4px 0 0 0; }
    .panel-body { padding: 20px; }

    .field-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    @media (max-width: 640px) { .field-grid { grid-template-columns: 1fr; } }

    .theme-swatches { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 4px; }
    .theme-swatch-option { display: flex; flex-direction: column; align-items: center; gap: 8px; cursor: pointer; }
    .theme-swatch-option input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .theme-swatch {
        width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--surface-border);
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px;
        transition: box-shadow 0.15s ease, transform 0.15s ease;
    }
    .theme-swatch.luna { background: #26658C; }
    .theme-swatch.black { background: #2B2B2B; }
    .theme-swatch.brown { background: #8C5A2B; }
    .theme-swatch.green { background: #2F7D5E; }
    .theme-swatch-option input:checked + .theme-swatch {
        box-shadow: 0 0 0 3px var(--surface-card), 0 0 0 5px var(--luna-mid);
        transform: scale(1.05);
    }
    .theme-swatch-option input:checked + .theme-swatch::after {
        content: "\f00c"; font-family: "Font Awesome 6 Free"; font-weight: 900;
    }
    .theme-swatch-option input:focus-visible + .theme-swatch { outline: 2px solid var(--luna-light); outline-offset: 2px; }
    .theme-swatch-label { font-size: 12px; color: var(--text-muted); font-weight: 600; }

    .panel-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    .danger-panel { border-color: var(--accent-danger-bg); }
    .danger-panel .panel-header h2 { color: var(--accent-danger); }

    @media (max-width: 1024px) {
        .settings-layout { flex-direction: column; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="page-header">
        <p class="eyebrow">Account</p>
        <h1>Settings</h1>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom: 20px;">{{ session('status') }}</div>
    @endif

    <div class="settings-layout">
        <div class="settings-main">
            <form method="POST" action="{{ route('settings.update') }}">
                @csrf

                <div class="panel" style="margin-bottom: 20px;">
                    <div class="panel-header"><h2>Profile</h2></div>
                    <div class="panel-body">
                        <div class="field-grid">
                            <div><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $user->name }}"></div>
                            <div><label class="form-label">Username</label><input name="username" class="form-control" value="{{ $user->UserName }}"></div>
                            <div><label class="form-label">Email</label><input name="email" class="form-control" value="{{ $user->email }}"></div>
                            @if($user->Role === 'Lecturer')
                                <div>
                                    <label class="form-label">Default quiz duration (minutes)</label>
                                    <input type="number" min="1" name="default_quiz_duration" class="form-control" value="{{ $user->DefaultQuizDurationMinutes }}" placeholder="e.g. 30">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom: 20px;">
                    <div class="panel-header"><h2>Password</h2></div>
                    <div class="panel-body">
                        <div class="field-grid">
                            <div><label class="form-label">Current password</label><input name="current_password" type="password" class="form-control"></div>
                            <div><label class="form-label">New password</label><input name="new_password" type="password" class="form-control"></div>
                            <div><label class="form-label">Confirm password</label><input name="new_password_confirmation" type="password" class="form-control"></div>
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom: 20px;">
                    <div class="panel-header">
                        <h2>Appearance</h2>
                        <p>Pick an accent color for the whole site. Applies everywhere, on every device.</p>
                    </div>
                    <div class="panel-body">
                        <div class="theme-swatches">
                            @foreach($themeOptions as $value => $label)
                                <label class="theme-swatch-option">
                                    <input type="radio" name="theme_color" value="{{ $value }}" {{ $currentTheme === $value ? 'checked' : '' }}>
                                    <span class="theme-swatch {{ $value }}"></span>
                                    <span class="theme-swatch-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="panel-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <div class="panel danger-panel">
                <div class="panel-header"><h2>Sessions</h2></div>
                <div class="panel-body">
                    <div class="panel-actions">
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Logout</button>
                        </form>
                        <form method="POST" action="{{ route('settings.logout-all-devices') }}" class="d-inline" onsubmit="return confirm('This will log you out on every device where you\'re signed in. Continue?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">Log out of all devices</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'U') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">{{ $user->Role ?? 'Student' }} Account</div>
                    </div>
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-shield-halved"></i> Security</div>
                <div class="body">Keep your profile and password updated to protect your account. Use "Log out of all devices" if you ever sign in on a shared computer.</div>
            </div>
        </div>
    </div>
</div>
@endsection
