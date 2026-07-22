@extends('layouts.app')

@section('content')
<style>
    .content { padding: 28px; max-width: 960px; }

    .review-card {
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        border-top: 3px solid var(--luna-mid);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        padding: 32px 36px;
    }

    .review-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        flex-wrap: wrap; gap: 16px; margin-bottom: 8px;
    }
    .review-title { font-size: 20px; font-weight: 700; color: var(--text-heading); font-family: var(--font-display); }
    .review-meta { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

    .back-link {
        font-size: 13px; color: #fff; text-decoration: none; font-weight: 700;
        display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;
        padding: 7px 14px; background: var(--luna-mid);
        border: none; border-radius: var(--radius-md);
        cursor: pointer; transition: background 0.15s;
    }
    .back-link:hover { background: var(--luna-dark); }

    .total-score-box {
        background: var(--luna-lightest); border-radius: var(--radius-md);
        padding: 14px 22px; text-align: center; min-width: 140px;
    }
    .total-score-box .value { font-size: 26px; font-weight: 800; color: var(--luna-dark); line-height: 1; }
    .total-score-box .label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-top: 4px; }

    .divider { border: none; border-top: 1px solid var(--surface-border); margin: 24px 0; }

    .question-card {
        border: 1px solid var(--surface-border); border-radius: var(--radius-md);
        padding: 18px 20px; margin-bottom: 16px;
    }
    .question-num { font-size: 11.5px; font-weight: 700; color: var(--luna-mid); text-transform: uppercase; margin-bottom: 6px; }
    .question-text { font-size: 15px; font-weight: 600; color: var(--text-heading); margin-bottom: 14px; }

    .answer-block { background: var(--surface-bg); border-radius: 8px; padding: 12px 16px; margin-bottom: 10px; }
    .answer-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
    .answer-text { font-size: 13.5px; color: var(--text-heading); white-space: pre-wrap; }
    .answer-none { color: var(--text-muted); font-style: italic; }

    .correct-answer-block { background: var(--accent-success-bg); }
    .correct-answer-block .answer-label { color: var(--accent-success); }

    .marks-row {
        display: flex; align-items: center; gap: 12px; margin-top: 14px;
        padding-top: 14px; border-top: 1px dashed var(--surface-border);
    }
    .marks-row label { font-size: 13px; font-weight: 600; color: var(--text-heading); }
    .marks-input {
        width: 80px; padding: 8px 10px; border: 1.5px solid var(--surface-border);
        border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center;
    }
    .marks-input:focus { outline: none; border-color: var(--luna-mid); }
    .save-indicator { font-size: 12px; font-weight: 600; margin-left: 8px; opacity: 0; transition: opacity 0.2s; }
    .save-indicator.show-saved { opacity: 1; color: var(--accent-success); }
    .save-indicator.show-error { opacity: 1; color: var(--accent-danger); }

    .max-marks-row {
        display: flex; align-items: center; gap: 10px; margin-top: 10px;
    }
    .max-marks-row label { font-size: 12.5px; color: var(--text-muted); font-weight: 600; }
    .max-marks-input {
        width: 70px; padding: 6px 8px; border: 1.5px solid var(--surface-border);
        border-radius: 8px; font-size: 13px; font-weight: 700; text-align: center;
    }
    .max-marks-input:focus { outline: none; border-color: var(--luna-mid); }
    .btn-save-max {
        background: var(--luna-mid); color: #fff; border: none; border-radius: 6px;
        padding: 6px 14px; font-size: 12.5px; font-weight: 700; cursor: pointer;
        transition: background 0.15s;
    }
    .btn-save-max:hover { background: var(--luna-dark); }
</style>

<div class="content">
    <a href="{{ route('quiz.results', $quiz->QuizID) }}" class="back-link">&larr; Back to results</a>

    <div class="review-card">
        <div class="review-header">
            <div>
                <div class="review-title">{{ $student->UserName ?? 'Unknown student' }}</div>
                <div class="review-meta">
                    {{ $quiz->Title }}
                    &middot; Submitted {{ optional($result->SubmissionTime)->diffForHumans() }}
                    &middot; {{ $result->IsAutoSubmit ? 'Auto-submitted' : 'Manually submitted' }}
                </div>
            </div>
            <div class="total-score-box">
                <div class="value" id="totalScoreValue">{{ rtrim(rtrim(number_format($result->Score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($totalMarks, 2), '0'), '.') }}</div>
                <div class="label">Total Score</div>
            </div>
        </div>

        <hr class="divider">

        @foreach($questions as $i => $question)
            @php($answer = $answers->get($question->QuestionID))
            <div class="question-card">
                <div class="question-num">Question {{ $i + 1 }} of {{ $questions->count() }} &middot; {{ rtrim(rtrim(number_format($question->Marks, 2), '0'), '.') }} marks</div>
                <div class="question-text">{{ $question->QuestionText }}</div>

                <div class="answer-block">
                    <div class="answer-label">Student's Answer</div>
                    <div class="answer-text">
                        @if($answer && $answer->ResponseText)
                            {{ $answer->ResponseText }}
                        @else
                            <span class="answer-none">No answer submitted</span>
                        @endif
                    </div>
                </div>

                @if($question->QuestionType === 'MCQ')
                    <div class="answer-block correct-answer-block">
                        <div class="answer-label">Correct Answer</div>
                        <div class="answer-text">{{ $question->CorrectAnswer }}</div>
                    </div>
                @endif

                <div class="marks-row">
                    <label for="marks-{{ $question->QuestionID }}">Marks awarded:</label>
                    <input type="text"
                           inputmode="decimal"
                           id="marks-{{ $question->QuestionID }}"
                           class="marks-input"
                           data-answer-id="{{ $answer->AnswerID ?? '' }}"
                           data-max="{{ $question->Marks }}"
                           value="{{ $answer->MarksAwarded ?? 0 }}"
                           data-server-max="{{ $question->Marks }}"
                           {{ $answer ? '' : 'disabled title=No submitted answer to grade' }}>
                    <span class="save-indicator" id="save-indicator-{{ $question->QuestionID }}"></span>
                </div>

                <div class="max-marks-row">
                    <label for="maxmarks-{{ $question->QuestionID }}">Maximum marks for this question:</label>
                    <input type="text"
                           inputmode="decimal"
                           id="maxmarks-{{ $question->QuestionID }}"
                           class="max-marks-input"
                           value="{{ rtrim(rtrim(number_format($question->Marks, 2), '0'), '.') }}">
                    <button type="button" class="btn-save-max" data-question-id="{{ $question->QuestionID }}">Save</button>
                    <span class="save-indicator" id="save-indicator-max-{{ $question->QuestionID }}"></span>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

document.querySelectorAll('.marks-input').forEach((input) => {
    input.addEventListener('change', async () => {
        const answerId = input.dataset.answerId;
        if (!answerId) return;

        const questionId = input.id.replace('marks-', '');
        const indicator = document.getElementById('save-indicator-' + questionId);
        const max = parseFloat(input.dataset.max);
        let value = parseFloat(input.value);
        if (isNaN(value) || value < 0) value = 0;
        if (value > max) value = max;
        input.value = value;

        try {
            // The ceiling shown here may just have been typed into "Maximum
            // marks" and not saved yet - persist it first so awarding
            // against it doesn't get rejected by the old, still-saved value.
            let serverMax = parseFloat(input.dataset.serverMax);
            if (value > serverMax) {
                const maxRes = await fetch(`/quiz/results/{{ $result->ResultID }}/questions/${questionId}/marks`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ marks: value }),
                });
                const maxData = await maxRes.json().catch(() => null);
                if (!maxRes.ok || !maxData?.success) {
                    throw new Error(maxData?.message || 'Could not update the marks ceiling');
                }
                input.dataset.serverMax = maxData.question_marks;
                input.dataset.max = maxData.question_marks;
                const maxMarksInput = document.getElementById('maxmarks-' + questionId);
                if (maxMarksInput) maxMarksInput.value = maxData.question_marks;
            }

            const res = await fetch(`/quiz/results/{{ $result->ResultID }}/answers/${answerId}/marks`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ marks: value }),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                throw new Error(data?.message || 'Save failed');
            }

            const totalEl = document.getElementById('totalScoreValue');
            const totalText = totalEl.textContent;
            const suffix = totalText.includes('/') ? totalText.slice(totalText.indexOf('/')) : '';
            totalEl.textContent = Number(data.new_total_score.toFixed(2)).toString() + ' ' + suffix;

            indicator.textContent = 'Saved';
            indicator.className = 'save-indicator show-saved';
        } catch (err) {
            indicator.textContent = 'Could not save';
            indicator.className = 'save-indicator show-error';
        } finally {
            setTimeout(() => { indicator.className = 'save-indicator'; }, 2500);
        }
    });
});

document.querySelectorAll('.max-marks-input').forEach((input) => {
    input.addEventListener('input', () => {
        const questionId = input.id.replace('maxmarks-', '');
        const awardedInput = document.getElementById('marks-' + questionId);
        if (!awardedInput) return;
        const value = parseFloat(input.value);
        if (!isNaN(value) && value >= 0) awardedInput.dataset.max = value;
    });
});

document.querySelectorAll('.btn-save-max').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const questionId = btn.dataset.questionId;
        const input = document.getElementById('maxmarks-' + questionId);
        const indicator = document.getElementById('save-indicator-max-' + questionId);

        let value = parseFloat(input.value);
        if (isNaN(value) || value < 0) value = 0;
        input.value = value;

        try {
            const res = await fetch(`/quiz/results/{{ $result->ResultID }}/questions/${questionId}/marks`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ marks: value }),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                throw new Error(data?.message || 'Save failed');
            }

            // Keep the "marks awarded" input's ceiling in sync with the change.
            const awardedInput = document.getElementById('marks-' + questionId);
            if (awardedInput) {
                awardedInput.dataset.max = data.question_marks;
                awardedInput.dataset.serverMax = data.question_marks;
                awardedInput.value = data.marks_awarded;
            }

            const totalEl = document.getElementById('totalScoreValue');
            totalEl.textContent = Number(data.new_total_score.toFixed(2)).toString()
                + ' / ' + Number(data.new_quiz_total_marks.toFixed(2)).toString();

            indicator.textContent = 'Saved';
            indicator.className = 'save-indicator show-saved';
        } catch (err) {
            indicator.textContent = 'Could not save';
            indicator.className = 'save-indicator show-error';
        } finally {
            setTimeout(() => { indicator.className = 'save-indicator'; }, 2500);
        }
    });
});
</script>
@endsection
