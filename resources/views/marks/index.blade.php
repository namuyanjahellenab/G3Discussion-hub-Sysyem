@extends('layouts.app')

@section('content')
@php
    $nameParts = explode(' ', auth()->user()->name ?? auth()->user()->UserName ?? '');
    $initials = collect($nameParts)->filter()->map(fn($part) => mb_substr($part,0,1))->take(2)->implode('');
@endphp

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .dashboard-grid-container { display: grid !important; grid-template-columns: 260px 1fr 340px !important; min-height: 100vh !important; width: 100% !important; background-color: #fcfcfd !important; font-family: 'Inter', sans-serif !important; }
    .sidebar-panel { background: #ffffff !important; border-right: 1px solid #e4e7ec !important; padding-top: 24px !important; }
    .sidebar-brand { padding: 0 24px 24px 24px !important; display: flex !important; align-items: center !important; gap: 12px !important; border-bottom: 1px solid #f2f4f7 !important; color: #0d52cc !important; font-weight: 700 !important; font-size: 1.2rem !important; letter-spacing: -0.5px !important; }
    .sidebar-menu { list-style: none !important; padding: 20px 0 !important; margin: 0 !important; }
    .sidebar-menu li a { padding: 12px 24px !important; font-size: 0.95rem !important; display: flex !important; align-items: center !important; gap: 12px !important; color: #667085 !important; text-decoration: none !important; font-weight: 500 !important; }
    .sidebar-menu li.active a { color: #0d52cc !important; background: #eef4ff !important; border-radius: 0 24px 24px 0 !important; margin-right: 12px !important; font-weight: 600 !important; }
    .content-workspace { padding: 3rem 2.5rem !important; background: #fcfcfd !important; }
    .dashboard-group-card { background: #ffffff !important; border: 1px solid #e4e7ec !important; border-radius: 16px !important; box-shadow: 0px 2px 12px rgba(16, 24, 40, 0.02) !important; padding: 24px !important; display: flex !important; flex-direction: column !important; }
    .right-info-panel { border-left: 1px solid #e4e7ec !important; background: #ffffff !important; padding: 3rem 2rem !important; display: flex !important; flex-direction: column !important; gap: 2.5rem !important; box-sizing: border-box !important; }
    .student-profile-box { background: #f8fafc !important; border: 1px solid #e4e7ec !important; border-radius: 14px !important; padding: 1.25rem !important; display: flex !important; align-items: center !important; gap: 12px !important; }
    .profile-avatar { width: 44px !important; height: 44px !important; background: #0d52cc !important; color: white !important; font-weight: 700 !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; }
    .announcement-banner { background: #0d52cc !important; color: white !important; border-radius: 12px !important; padding: 1.25rem !important; margin-top: auto !important; }
</style>

<div class="dashboard-grid-container" id="clean-dashboard-root">
    <div class="sidebar-panel">
        <div class="sidebar-brand"><i class="fa-solid fa-comments"></i><span>DISCUSSION HUB</span></div>
        <ul class="sidebar-menu">
            <li><a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
            <li><a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a></li>
            <li><a href="{{ route('messages.index') }}"><i class="fa-regular fa-envelope"></i> Messages</a></li>
            <li class="active"><a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a></li>
            <li><a href="{{ route('quizzes.index') }}"><i class="fa-regular fa-file-lines"></i> Quizzes</a></li>
            <li><a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a></li>
            <li><a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="content-workspace">
        <div style="margin-bottom: 2rem;">
            <p style="text-transform: uppercase; color: #667085; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0;">Performance</p>
            <h1 style="letter-spacing: -0.5px; color: #101828; font-size: 2rem; font-weight: 700; margin: 0;">MARKS</h1>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            @foreach([['Coursework', $marks['coursework'], 'fa-book-open'], ['CATs', $marks['cats'], 'fa-file-lines'], ['Exams', $marks['exams'], 'fa-pen-to-square'], ['GPA', $marks['gpa'], 'fa-graduation-cap']] as [$label, $value, $icon])
                <div class="dashboard-group-card">
                    <div style="width: 44px; height: 44px; background: #eef4ff; color: #0d52cc; border-radius: 10px; display:flex; align-items:center; justify-content:center; margin-bottom: 16px;"><i class="fa-solid {{ $icon }}"></i></div>
                    <h5 style="color: #101828; font-size: 1.05rem; font-weight: 700; margin: 0 0 8px 0;">{{ $label }}</h5>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #101828;">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="right-info-panel">
        <div>
            <div style="color: #667085; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; margin-bottom: 8px;">Student Info</div>
            <div class="student-profile-box">
                <div class="profile-avatar">{{ $initials ?: 'SU' }}</div>
                <div>
                    <div style="color: #101828; font-weight: 700; font-size: 0.95rem;">{{ auth()->user()->UserName ?? auth()->user()->name }}</div>
                    <div style="color: #667085; font-size: 0.8rem;">Student Account</div>
                </div>
            </div>
        </div>
        <div class="announcement-banner">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><i class="fa-solid fa-bullhorn"></i><span style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9;">Summary</span></div>
            <div style="font-size: 0.88rem; font-weight: 500; line-height: 1.4;">Your academic performance is updated from your latest activities.</div>
        </div>
    </div>
</div>
@endsection
