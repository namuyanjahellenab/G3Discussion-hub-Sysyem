@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width: 1100px;">
    <div class="mb-4">
        <h1 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">View Discussion Groups</h1>
    </div>
    <div class="mb-5">
        <form method="GET" action="" class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
            <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                <i class="bi bi-search"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 ps-2 py-2.5" placeholder="Search for courses, lecturers or topics..." style="font-size: 0.95rem;">
            <button class="btn btn-primary px-4 fw-medium d-flex align-items-center" type="submit" style="background-color: #0052CC; border-color: #0052CC;">
                Search <i class="bi bi-arrow-right short ms-2"></i>
            </button>
        </form>
    </div>

    <div class="row g-4">
        @foreach($groups as $group)
            <div class="col-12 col-md-6">
                <div class="card shadow-sm border-0 h-100 bg-white positional-card" 
                     style="border-radius: 14px; 
                            border-left: 5px solid {{ $group->userJoined ? '#DC3545' : '#0052CC' }} !important; 
                            box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                    
                    <div class="card-body p-4 d-flex flex-column">
                        
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge px-2.5 py-1.5 rounded text-uppercase fw-bold" 
                                  style="font-size: 0.75rem; 
                                         background-color: {{ $group->userJoined ? '#FDF2F2' : '#E6F0FF' }}; 
                                         color: {{ $group->userJoined ? '#DC3545' : '#0052CC' }};">
                                {{ $group->CourseCode ?? 'CSC301' }}
                            </span>
                            
                            <div class="d-flex align-items-center text-secondary px-2.5 py-1 rounded" style="background-color: #F4F5F7; font-size: 0.8rem; font-weight: 500;">
                                <i class="bi bi-people-fill me-1.5 text-muted" style="font-size: 0.85rem;"></i>
                                <span>{{ $group->member_count ?? 0 }} members</span>
                            </div>
                        </div>

                        <h4 class="fw-bold text-dark mb-4" style="font-size: 1.35rem; letter-spacing: -0.3px;">
                            {{ $group->GroupName }}
                        </h4>

                        @if(!empty($group->Description))
                            <p class="text-muted small mb-4 d-none">{{ $group->Description }}</p>
                        @endif

                        <div class="mt-auto">
                            @if($group->userJoined)
                                <form method="POST" action="{{ route('groups.leave', $group->GroupID) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger w-100 py-2.5 rounded-3 fw-bold d-flex align-items-center justify-content-center"  style="background-color: #FFF; border-color: #DC3545; font-size: 0.95rem;">">
                                        Leave Group <i class="bi bi-chevron-right ms-1 small"></i>
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('groups.join', $group->GroupID) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold d-flex align-items-center justify-content-center" style="background-color: #0052CC; border-color: #0052CC; font-size: 0.95rem;">
                                        Join Group <i class="bi bi-chevron-right ms-1 small"></i>
                                    </button>
                                </form>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-center mt-5">
        <p class="text-muted italic small" style="font-size: 0.85rem;">
            You can join additional groups at any time from your dashboard. Some groups may require administrative approval.
        </p>
    </div>
</div>
@endsection