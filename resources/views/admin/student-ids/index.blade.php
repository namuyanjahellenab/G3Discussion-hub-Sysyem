@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Student IDs</h2>
            <p class="text-muted mb-0">Add student ID numbers from the class roster - only a listed, unused number can be used to register as a Student.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentIdModal">
            <i class="fa-solid fa-plus"></i> Add Student ID
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student ID Number</th>
                        <th>Status</th>
                        <th>Registered By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($studentIds as $studentId)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $studentId->StudentIDNumber }}</td>
                            <td>
                                @if($studentId->IsUsed)
                                    <span class="badge bg-secondary">Used</span>
                                @else
                                    <span class="badge bg-success">Available</span>
                                @endif
                            </td>
                            <td>
                                {{ $studentId->linkedUser->UserName ?? '—' }}
                            </td>
                            <td>
                                @if(!$studentId->IsUsed)
                                    <form method="POST" action="{{ route('admin.student-ids.destroy', $studentId->StudentCodeID) }}"
                                          onsubmit="return confirm('Remove this student ID?');" style="display:inline;">
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
                            <td colspan="4" class="text-center text-muted py-4">No student IDs yet. Add one to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student ID Modal -->
<div class="modal fade" id="addStudentIdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.student-ids.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Student ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student ID Number</label>
                        <input type="text" name="StudentIDNumber" class="form-control" maxlength="50"
                               placeholder="e.g. STU-2024-118" required>
                        @error('StudentIDNumber')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student ID</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
