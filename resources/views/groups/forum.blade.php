@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="mb-4">
        <h1 class="h3 fw-bold">{{ $group->GroupName }} Forum</h1>
        <p class="text-muted">This is the forum page for your selected group. Topics and posts will appear here.</p>
    </div>

    <div class="card rounded-4 shadow-sm border-0">
        <div class="card-body">
            <p class="text-muted mb-0">Forum functionality is under construction. Use this page as the group forum entry point.</p>
        </div>
    </div>
</div>
@endsection
