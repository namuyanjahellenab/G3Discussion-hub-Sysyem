@extends('layouts.app')

@section('content')
<div class="chat-page">
    <div class="chat-header">
        <h2><i class="fa-solid fa-clipboard-check"></i> {{ $quiz->Title }} — Your Review</h2>
        <p class="text-muted">Score: {{ $result->Score }} · Submitted {{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}</p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
        @foreach($questions as $i => $question)
            @php $myAnswer = $myAnswers->get($question->QuestionID); @endphp
            <div class="card" style="padding: 1.25rem; border-top: 3px solid {{ $myAnswer && $myAnswer->IsCorrect ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                <div style="font-weight: 600; margin-bottom: 0.6rem;">
                    Q{{ $i + 1 }}. {{ $question->QuestionText }}
                </div>

                <div style="font-size: 0.9rem; margin-bottom: 0.3rem;">
                    <span style="color: var(--text-muted);">Your answer:</span>
                    <span style="font-weight: 500; color: {{ $myAnswer && $myAnswer->IsCorrect ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                        {{ $myAnswer->ResponseText ?? 'Not answered' }}
                    </span>
                    @if($myAnswer && $myAnswer->IsCorrect)
                        <i class="fa-solid fa-circle-check" style="color: var(--accent-success);"></i>
                    @else
                        <i class="fa-solid fa-circle-xmark" style="color: var(--accent-danger);"></i>
                    @endif
                </div>

                @if(!$myAnswer || !$myAnswer->IsCorrect)
                    <div style="font-size: 0.9rem;">
                        <span style="color: var(--text-muted);">Correct answer:</span>
                        <span style="font-weight: 500; color: var(--accent-success);">{{ $question->CorrectAnswer }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <a href="{{ route('quizzes.index') }}" class="btn btn-outline-secondary" style="margin-top: 1.5rem;">
        <i class="fa-solid fa-arrow-left"></i> Back to Quizzes
    </a>
</div>
@endsection