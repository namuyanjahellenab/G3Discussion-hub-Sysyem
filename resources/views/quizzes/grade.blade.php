@extends('layouts.app')

@section('content')
<div class="chat-page">
    <div class="chat-header">
        <h2><i class="fa-solid fa-clipboard-check"></i> {{ $result->quiz->Title }} — Grading</h2>
        <p class="text-muted">
            Student: {{ $student->UserName ?? 'Unknown' }} ·
            Score: {{ $result->Score }} ·
            Submitted {{ \Carbon\Carbon::parse($result->SubmissionTime)->diffForHumans() }}
        </p>
    </div>

    <form method="POST" action="{{ route('quiz.grade.save', $result->ResultID) }}">
        @csrf

        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
            @foreach($questions as $i => $question)
                @php $answer = $answers->get($question->QuestionID); @endphp
                <div class="card" style="padding: 1.25rem; border-top: 3px solid {{ $question->QuestionType === 'MCQ' ? ($answer && $answer->IsCorrect ? 'var(--accent-success)' : 'var(--accent-danger)') : 'var(--luna-mid)' }};">
                    <div style="font-weight: 600; margin-bottom: 0.6rem;">
                        Q{{ $i + 1 }}. {{ $question->QuestionText }}
                        <span class="text-muted" style="font-weight: 400; font-size: 0.85rem;">({{ $question->Marks }} marks)</span>
                    </div>

                    <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-muted);">Answer:</span>
                        <span style="font-weight: 500;">{{ $answer->ResponseText ?? 'Not answered' }}</span>
                    </div>

                    @if($question->QuestionType === 'MCQ')
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            @if($answer && $answer->IsCorrect)
                                <i class="fa-solid fa-circle-check" style="color: var(--accent-success);"></i> Correct — awarded {{ $question->Marks }} marks automatically
                            @else
                                <i class="fa-solid fa-circle-xmark" style="color: var(--accent-danger);"></i> Incorrect — correct answer was "{{ $question->CorrectAnswer }}"
                            @endif
                        </div>
                    @else
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label class="form-label mb-0" style="font-size: 0.9rem;">Marks awarded:</label>
                            <input type="number" step="0.01" min="0" max="{{ $question->Marks }}"
                                   name="marks[{{ $answer->AnswerID ?? '' }}]"
                                   class="form-control form-control-sm" style="width: 100px;"
                                   value="{{ $answer->MarksAwarded ?? '' }}"
                                   {{ $answer ? '' : 'disabled' }}>
                            <span class="text-muted" style="font-size: 0.85rem;">/ {{ $question->Marks }}</span>
                            @if($answer && is_null($answer->MarksAwarded))
                                <span class="badge bg-warning-subtle text-warning">Ungraded</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Marks
            </button>
            <a href="{{ route('quiz.results', $result->QuizID) }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Results
            </a>
        </div>
    </form>
</div>
@endsection
