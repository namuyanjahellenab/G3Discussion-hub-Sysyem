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
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
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
                    {{-- Deliberately a sibling of .app-sidebar-wrapper, not a child: that
                         wrapper gets `transform` below lg (the off-canvas slide), and a
                         `transform` on an ancestor creates a new containing block for any
                         position:fixed descendant — this button would slide off-screen with
                         the drawer instead of staying pinned to the real viewport. --}}
                    <button id="sidebar-toggle-btn" onclick="toggleAppSidebar()" title="Toggle sidebar"
                        style="position: fixed; top: 16px; left: 16px; z-index: 1100; width: 36px; height: 36px;
                        background: #ffffff; border: 1px solid #e4e7ec; border-radius: 8px; cursor: pointer;
                        display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(16,24,40,0.08);">
                        <i class="fa-solid fa-bars" style="color: #667085;"></i>
                    </button>

                    <div class="app-sidebar-wrapper">
                        @include('layouts.sidebar')
                    </div>
                    {{-- Below lg, the sidebar is an off-canvas drawer (see admin-theme.css)
                         toggled by the button above. This backdrop closes it on an outside
                         tap, same as any standard mobile nav drawer. --}}
                    <div class="mobile-sidebar-backdrop" onclick="document.body.classList.remove('mobile-sidebar-open')"></div>
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
        <script src="/js/quiz-alert.js"></script>
        <script>
            function toggleAppSidebar() {
                // Desktop (>=992px) collapses the inline sidebar via
                // sidebar-collapsed; mobile slides the off-canvas drawer via
                // mobile-sidebar-open. These can't both be toggled together —
                // sidebar-collapsed's `.sidebar-panel { width: 0 !important }`
                // isn't scoped to a desktop-only media query, so toggling it
                // while opening the mobile drawer collapsed the panel to
                // zero width even though the wrapper had slid on-screen.
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    document.body.classList.toggle('mobile-sidebar-open');
                } else {
                    document.body.classList.toggle('sidebar-collapsed');
                }
            }
        </script>
    </body>
</html>
    