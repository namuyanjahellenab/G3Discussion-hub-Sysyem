<button id="sidebar-toggle-btn" onclick="toggleAppSidebar()" title="Toggle sidebar"
    style="position: fixed; top: 16px; left: 16px; z-index: 1000; width: 36px; height: 36px;
    background: #ffffff; border: 1px solid #e4e7ec; border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(16,24,40,0.08);">
    <i class="fa-solid fa-bars" style="color: #667085;"></i>
</button>

<style>
    body.sidebar-collapsed .sidebar-panel {
        width: 0 !important;
        min-width: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        border: none !important;
    }
    body.sidebar-collapsed .dashboard-grid-container { grid-template-columns: 0 1fr 340px !important; }
</style>

<div class="sidebar-panel">
    <div class="sidebar-brand"><i class="fa-solid fa-comments"></i><span>DISCUSSION HUB</span></div>
    <ul class="sidebar-menu">
        <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        </li>
        <li class="{{ request()->routeIs('forum.index') ? 'active' : '' }}">
            <a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a>
        </li>
        <li class="{{ request()->routeIs('messages.index') ? 'active' : '' }}">
            <a href="{{ route('messages.index') }}"><i class="fa-regular fa-envelope"></i> Messages</a>
        </li>
        <li class="{{ request()->routeIs('marks.index') ? 'active' : '' }}">
    @if(auth()->user()->Role === 'Lecturer')
    <a href="{{ route('dashboard') }}"><i class="fa-regular fa-star"></i> Marks</a>
@else
    <a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a>
@endif
        </li>

        @if(auth()->user()->Role === 'Lecturer')
            <li class="dropdown {{ request()->routeIs('quiz.schedule') || request()->routeIs('dashboard') ? 'active' : '' }}">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-regular fa-file-lines"></i> Quizzes
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="{{ route('quiz.schedule') }}">
                            <i class="fa-solid fa-plus"></i> Schedule Quiz
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('dashboard') }}">
                            <i class="fa-solid fa-chart-simple"></i> Quiz Results
                        </a>
                    </li>
                </ul>
            </li>
        @else
            <li class="{{ request()->routeIs('quizzes.index') ? 'active' : '' }}">
                <a href="{{ route('quizzes.index') }}"><i class="fa-regular fa-file-lines"></i> Quizzes</a>
            </li>
        @endif

        <li class="{{ request()->routeIs('recommend.index') ? 'active' : '' }}">
            <a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a>
        </li>
        <li class="{{ request()->routeIs('settings.index') ? 'active' : '' }}">
            <a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a>
        </li>
    </ul>
</div>

<script>
    function toggleAppSidebar() {
        document.body.classList.toggle('sidebar-collapsed');
    }
</script>