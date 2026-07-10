@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Lecturer Staff IDs</h2>
            <p class="text-muted mb-0">Add staff ID numbers lecturers use to register.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffIdModal">
            <i class="fa-solid fa-plus"></i> Add Staff ID
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Staff ID Number</th>
                        <th>Status</th>
                        <th>Registered By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staffIds as $staffId)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $staffId->StaffIDNumber }}</td>
                            <td>
                                @if($staffId->IsUsed)
                                    <span class="badge bg-secondary">Used</span>
                                @else
                                    <span class="badge bg-success">Available</span>
                                @endif
                            </td>
                            <td>
                                {{ $staffId->linkedUser->UserName ?? '—' }}
                            </td>
                            <td>
                                @if(!$staffId->IsUsed)
                                    <form method="POST" action="{{ route('admin.lecturer-staff.destroy', $staffId->StaffCodeID) }}"
                                          onsubmit="return confirm('Remove this staff ID?');" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Locked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No staff IDs yet. Add one to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Staff ID Modal -->
<div class="modal fade" id="addStaffIdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.lecturer-staff.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Staff ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff ID Number</label>
                        <input type="text" name="StaffIDNumber" class="form-control" maxlength="50"
                               placeholder="e.g. STF-2024-118" required>
                        @error('StaffIDNumber')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff ID</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection