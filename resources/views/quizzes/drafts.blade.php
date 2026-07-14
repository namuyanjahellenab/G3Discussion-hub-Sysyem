@extends('layouts.app')

@section('content')
<style>
    .content-workspace { padding: 28px; max-width: 1000px; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-header p.eyebrow { text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0; }
    .page-header h1 { font-size: 24px; font-weight: 800; margin: 0; }

    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }

    .draft-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--surface-border); flex-wrap: wrap; }
    .draft-row:last-child { border-bottom: none; }
    .draft-row .title { font-weight: 700; color: var(--text-heading); font-size: 14px; }
    .draft-row .meta { color: var(--text-muted); font-size: 12.5px; margin-top: 4px; }
    .draft-row .actions { display: flex; gap: 8px; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.5rem 0.9rem; border-radius: var(--radius-md); font-size: 12.5px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }
    .btn-outline-danger { color: var(--accent-danger); border-color: var(--accent-danger); background: none; }
    .btn-outline-danger:hover { background: var(--accent-danger); color: #fff; }

    .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13.5px; }
</style>

<div class="content-workspace">
    <div class="page-header">
        <div>
            <p class="eyebrow">Quizzes</p>
            <h1>My Draft Quizzes</h1>
        </div>
        <a href="{{ route('quiz.schedule') }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Quiz</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom: 20px;">{{ session('status') }}</div>
    @endif

    <div class="panel">
        @forelse($drafts as $draft)
            <div class="draft-row">
                <div>
                    <div class="title">{{ $draft->Title }}</div>
                    <div class="meta">
                        {{ $draft->group?->GroupName ?? 'No group selected yet' }}
                        &bull; {{ $draft->questions_count }} {{ Str::plural('question', $draft->questions_count) }}
                        &bull; Last saved {{ $draft->UpdatedAt->diffForHumans() }}
                    </div>
                </div>
                <div class="actions">
                    <a href="{{ route('quiz.schedule', ['draft' => $draft->QuizID]) }}" class="btn btn-primary">
                        <i class="fa-solid fa-calendar-check"></i> Continue &amp; Schedule
                    </a>
                    <form method="POST" action="{{ route('quiz.draft.delete', $draft->QuizID) }}" onsubmit="return confirm('Delete this draft?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                No saved drafts. Start a new quiz and click <strong>Save Draft</strong> to keep your progress here.
            </div>
        @endforelse
    </div>
</div>
@endsection
