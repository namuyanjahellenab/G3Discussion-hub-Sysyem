<!-- cat > resources/views/topics/create.blade.php << 'EOF' -->
@extends('layouts.app')
@section('content')
<div class="container py-5" style="max-width: 600px;">
    <h1 class="h4 fw-bold mb-4">New Topic in {{ $group->GroupName }}</h1>
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('topics.store') }}">
        @csrf
        <input type="hidden" name="GroupID" value="{{ $group->GroupID }}">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="Title" class="form-control" value="{{ old('Title') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Your question or discussion topic</label>
            <textarea name="Content" class="form-control" rows="5" required>{{ old('Content') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">Post Topic</button>
    </form>
</div>
@endsection
