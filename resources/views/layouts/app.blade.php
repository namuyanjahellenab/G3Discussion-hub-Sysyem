<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Discussion Hub') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        {{-- Font Awesome is bundled via app.css/Vite below, not the cdnjs CDN
             link this used to be — that CDN was silently unreachable for at
             least one real user, blanking every icon app-wide. --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- filemtime query strings bust the browser cache whenever these
             plain public/css/ files change (no Vite build step covers them) -
             without this, a browser that already cached an older copy kept
             running it indefinitely after an edit, same issue quiz-alert.js
             below already had to work around. --}}
        <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}?v={{ filemtime(public_path('css/admin-theme.css')) }}">
        @stack('styles')
    </head>
    <body class="bg-light" data-theme="{{ auth()->user()->ThemeColor ?? 'luna' }}">
        <div class="min-h-screen bg-light">
            
            {{-- PERMANENT FIX: Hide navbar completely if showNavbar is set to false --}}
            @if(!isset($showNavbar) || $showNavbar !== false)
                @include('layouts.navbar')
            @endif

            <div class="d-flex">
                @if(!isset($showSidebar) || $showSidebar !== false)
                    {{-- Deliberately OUTSIDE .app-sidebar-wrapper, not inside it: that
                         wrapper collapses to width:0/overflow:hidden when toggled off,
                         so a button living inside it would vanish along with the
                         sidebar - leaving no way to bring it back. position:fixed keeps
                         it visible/clickable regardless of collapse state. --}}
                    <button id="sidebar-toggle-btn" onclick="toggleAppSidebar()" title="Toggle sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div class="app-sidebar-wrapper">
                        @include('layouts.sidebar')
                    </div>
                @endif

                <div class="flex-fill">
                    @isset($header)
                        <header class="bg-white shadow-sm">
                            <div class="container-fluid py-3 px-4">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="py-4">
                        {{-- Global flash banner: most views don't render session('status')/
                             ('success') themselves, so actions like "PDF is generating" or
                             "Warning issued" were silently invisible. One place to show them. --}}
                        <div class="container-fluid px-4">
                            @if(session('status'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('status') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if($errors->any() && !isset($hideGlobalErrors))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ $errors->first() }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                        </div>
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous" defer></script>
        {{-- filemtime query string busts the browser cache whenever this
             file changes - it's a plain public/js/ file (no Vite build
             step), so without this a browser that already cached an older
             copy would keep running it indefinitely after an edit. --}}
        <script src="/js/quiz-alert.js?v={{ filemtime(public_path('js/quiz-alert.js')) }}"></script>
        <script>
            // One collapse behavior at every screen width - the desktop client
            // has no separate "mobile" layout to branch on, so this doesn't
            // either. Toggling just hides/shows .app-sidebar-wrapper (see
            // admin-theme.css) to give the main content the freed-up space.
            function toggleAppSidebar() {
                document.body.classList.toggle('sidebar-collapsed');
            }
        </script>
    </body>
</html>
    