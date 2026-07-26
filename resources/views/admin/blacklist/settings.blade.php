@extends('layouts.app')

@section('content')
<div class="blacklist-settings-page-shell">
    <div class="blacklist-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="blacklist-title-icon">
                <i class="fa-solid fa-gear"></i>
            </div>
            <div class="blacklist-title-block">
                <h2 class="fw-bold mb-2">Blacklist Settings</h2>
                <p class="text-muted mb-0">Configure when a student is considered inactive, and whether inactive students are automatically warned and blacklisted.</p>
            </div>
        </div>

        <a href="{{ route('admin.blacklist') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Blacklist
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.blacklist.settings.update') }}">
        @csrf

        <div class="card blacklist-settings-card mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-1">Inactivity Threshold</h5>
                <p class="text-muted small mb-3">A student is considered inactive after not sending any message (post, reply, or group chat message) for this long.</p>

                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Period</label>
                        <input type="number" min="1" name="InactivityValue" class="form-control" style="width: 140px;" value="{{ old('InactivityValue', $settings->InactivityValue) }}" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Unit</label>
                        <select name="InactivityUnit" class="form-select" style="width: 160px;">
                            @foreach(['seconds', 'minutes', 'hours', 'days'] as $unit)
                                <option value="{{ $unit }}" {{ old('InactivityUnit', $settings->InactivityUnit) === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card blacklist-settings-card mb-4">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="autoBlacklistEnabled" name="AutoBlacklistEnabled" value="1" {{ old('AutoBlacklistEnabled', $settings->AutoBlacklistEnabled) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="autoBlacklistEnabled">Automatically warn and blacklist inactive students</label>
                </div>
                <p class="text-muted small mb-3">
                    When enabled, an inactive student is sent two warnings at the intervals below. If they remain inactive after the second warning, they are automatically blacklisted.
                </p>

                <div id="autoBlacklistFields">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">First warning</label>
                        <p class="text-muted small mb-2">Issue the first warning to a student who has been inactive for this long.</p>
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <input type="number" min="1" name="FirstWarningValue" class="form-control" style="width: 140px;" value="{{ old('FirstWarningValue', $settings->FirstWarningValue) }}">
                            </div>
                            <div class="col-auto">
                                <select name="FirstWarningUnit" class="form-select" style="width: 160px;">
                                    @foreach(['seconds', 'minutes', 'hours', 'days'] as $unit)
                                        <option value="{{ $unit }}" {{ old('FirstWarningUnit', $settings->FirstWarningUnit) === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-2">Message sent: "You have been inactive for {value} {unit}"</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Second warning</label>
                        <p class="text-muted small mb-2">Issue the second warning to a student who has been inactive for this long after the first warning. Only sent if the student did not respond to the first warning.</p>
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <input type="number" min="1" name="SecondWarningValue" class="form-control" style="width: 140px;" value="{{ old('SecondWarningValue', $settings->SecondWarningValue) }}">
                            </div>
                            <div class="col-auto">
                                <select name="SecondWarningUnit" class="form-select" style="width: 160px;">
                                    @foreach(['seconds', 'minutes', 'hours', 'days'] as $unit)
                                        <option value="{{ $unit }}" {{ old('SecondWarningUnit', $settings->SecondWarningUnit) === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-2">Message sent: "You have been inactive for {sum of the first and second warning periods}"</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Automatic blacklist</label>
                        <p class="text-muted small mb-2">Automatically blacklist a student who is still inactive this long after the second warning.</p>
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <input type="number" min="1" name="BlacklistAfterSecondWarningValue" class="form-control" style="width: 140px;" value="{{ old('BlacklistAfterSecondWarningValue', $settings->BlacklistAfterSecondWarningValue) }}">
                            </div>
                            <div class="col-auto">
                                <select name="BlacklistAfterSecondWarningUnit" class="form-select" style="width: 160px;">
                                    @foreach(['seconds', 'minutes', 'hours', 'days'] as $unit)
                                        <option value="{{ $unit }}" {{ old('BlacklistAfterSecondWarningUnit', $settings->BlacklistAfterSecondWarningUnit) === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-2">Message sent: "You have been blacklisted for not responding to the second warning of being inactive"</p>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Blacklist duration</label>
                        <p class="text-muted small mb-2">An inactive student blacklisted automatically should be removed from the blacklist after this long.</p>
                        <div class="row g-3 align-items-end">
                            <div class="col-auto">
                                <input type="number" min="1" name="BlacklistDurationValue" class="form-control" style="width: 140px;" value="{{ old('BlacklistDurationValue', $settings->BlacklistDurationValue) }}">
                            </div>
                            <div class="col-auto">
                                <select name="BlacklistDurationUnit" class="form-select" style="width: 160px;">
                                    @foreach(['seconds', 'minutes', 'hours', 'days'] as $unit)
                                        <option value="{{ $unit }}" {{ old('BlacklistDurationUnit', $settings->BlacklistDurationUnit) === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="{{ route('admin.blacklist') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div>

<script>
    (function () {
        var checkbox = document.getElementById('autoBlacklistEnabled');
        var fields = document.getElementById('autoBlacklistFields');

        function sync() {
            fields.style.display = checkbox.checked ? '' : 'none';
        }

        checkbox.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection

@push('styles')
<style>
    .blacklist-settings-page-shell {
        max-width: 1500px;
        margin: 0 auto;
        padding: 1.5rem 1.5rem 2.5rem;
    }

    .blacklist-settings-card {
        border: 1px solid var(--surface-border);
        box-shadow: var(--shadow-soft);
    }

    .blacklist-page-header {
        margin-bottom: 1.75rem;
    }

    .blacklist-title-block h2 {
        margin-bottom: 0.5rem;
    }

    .blacklist-title-block p {
        max-width: 56rem;
        line-height: 1.6;
    }

    .blacklist-title-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--luna-lightest);
        color: var(--luna-mid);
        box-shadow: var(--shadow-soft);
        flex: 0 0 auto;
    }

    .blacklist-title-icon i {
        font-size: 1.1rem;
    }
</style>
@endpush
