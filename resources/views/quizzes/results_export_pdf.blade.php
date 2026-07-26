<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quiz->Title ?? 'Quiz Results' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { border-bottom: 2px solid #26658C; padding-bottom: 8px; margin-bottom: 16px; }
        .header h2 { margin: 0 0 4px 0; color: #26658C; }
        .meta { color: #4b5563; font-size: 10px; margin-bottom: 2px; }

        .stats { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .stats td { width: 25%; text-align: center; padding: 10px; background: #EAF1F4; border: 1px solid #ffffff; }
        .stats .stat-value { display: block; font-size: 18px; font-weight: bold; color: #023859; }
        .stats .stat-label { display: block; font-size: 9px; color: #6b7280; margin-top: 2px; }

        table.results { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.results thead th { background: #26658C; color: #fff; text-align: left; padding: 8px 10px; }
        table.results tbody td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
        table.results tbody tr:nth-child(even) { background: #f9fafb; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }
        .badge-manual { color: #1a7f4e; background: #e6f6ee; }
        .badge-auto { color: #b3261e; background: #fbe9e8; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $quiz->Title ?? 'Quiz Results' }}</h2>
        <div class="meta">Total Marks: {{ number_format($totalMarks, 2) }} | Quiz ID: {{ $quiz->QuizID }}</div>
        <div class="meta">Generated: {{ now()->setTimezone('Africa/Kampala')->format('Y-m-d H:i') }}</div>
    </div>

    <table class="stats">
        <tr>
            <td><span class="stat-value">{{ $attempted }}</span><span class="stat-label">STUDENTS ATTEMPTED</span></td>
            <td><span class="stat-value">{{ $average }}</span><span class="stat-label">AVERAGE SCORE</span></td>
            <td><span class="stat-value">{{ $highest }}</span><span class="stat-label">HIGHEST SCORE</span></td>
            <td><span class="stat-value">{{ $autoCount }}</span><span class="stat-label">AUTO-SUBMITTED</span></td>
        </tr>
    </table>

    @if($results->isEmpty())
        <p>No submissions yet.</p>
    @else
        <table class="results">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Score</th>
                    <th>Submission Type</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $i => $r)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $r->StudentName ?? '—' }}</td>
                        <td>{{ number_format($r->Score, 2) }} / {{ number_format($totalMarks, 2) }}</td>
                        <td>
                            @if($r->IsAutoSubmit)
                                <span class="badge badge-auto">Auto</span>
                            @else
                                <span class="badge badge-manual">Manual</span>
                            @endif
                        </td>
                        <td>{{ $r->SubmissionTime ? \Carbon\Carbon::parse($r->SubmissionTime)->setTimezone('Africa/Kampala')->format('d M Y, H:i') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
