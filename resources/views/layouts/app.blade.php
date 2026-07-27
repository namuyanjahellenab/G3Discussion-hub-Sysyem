<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Discussion Hub') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">

        {{-- Inlined ahead of every stylesheet link so the LUNA custom
             properties (and the Bootstrap --bs-primary/--bs-primary-rgb
             retint) exist the instant the browser starts computing styles -
             admin-theme.css declares the same variables, but as an external
             file it's requested last and can visibly arrive after Bootstrap
             (bundled via Vite below) already painted stock blue on
             bg-primary/text-primary/bg-opacity-* elements (cards' icon
             chips, badges, progress bars). Inlining removes that race
             instead of hoping the external file wins it. Keep this in sync
             with the :root / body[data-theme=...] blocks at the top of
             admin-theme.css. --}}
        <style>
            /* Every sidebar link is a full page navigation (this is a
               traditional server-rendered app, not a SPA) - by default that
               means the browser blanks the page to white the instant you
               click, then paints the new one once it arrives, which reads
               as "the page disappears" even when the new page loads fast.
               This is the native browser API for smoothing exactly that
               (Chrome/Edge 111+): it cross-fades between the old and new
               page instead of a hard blank-then-paint. No JS, no SPA
               conversion, and it silently no-ops in browsers that don't
               support it yet (Firefox/Safari) - same as today there, no
               regression possible. */
            @view-transition {
                navigation: auto;
            }
            :root {
                --luna-lightest: #A7EBF2;
                --luna-light:    #54ACBF;
                --luna-mid:      #26658C;
                --luna-dark:     #023859;
                --luna-darkest:  #011C40;
                --luna-subtle-bg:#EAF1F4;
                --bs-primary: var(--luna-mid);
                --bs-primary-rgb: 38, 101, 140;
            }
            body[data-theme="black"] {
                --luna-lightest: #D8D8D8;
                --luna-mid:      #2B2B2B;
                --luna-dark:     #171717;
                --luna-subtle-bg:#E9E9E9;
                --bs-primary-rgb: 43, 43, 43;
            }
            body[data-theme="brown"] {
                --luna-lightest: #EAD9C4;
                --luna-mid:      #8C5A2B;
                --luna-dark:     #5C3A1A;
                --luna-subtle-bg:#F3E9DC;
                --bs-primary-rgb: 140, 90, 43;
            }
            body[data-theme="green"] {
                --luna-lightest: #CFE8DC;
                --luna-mid:      #2F7D5E;
                --luna-dark:     #1B4D33;
                --luna-subtle-bg:#E1F0E8;
                --bs-primary-rgb: 47, 125, 94;
            }
        </style>

        {{-- Fonts, Bootstrap, Bootstrap Icons, and Font Awesome are all
             bundled locally via Vite (resources/css/app.css /
             resources/js/app.js) instead of loaded from
             fonts.bunny.net/cdn.jsdelivr.net - those external requests
             measured 1.7-7 seconds EACH on this network (vs 2-40ms for
             every local asset), and they're render-blocking on every full
             page navigation. Same versions as before, so nothing visually
             changes; --}}
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
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
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

        {{-- Bootstrap JS is bundled into resources/js/app.js (loaded via
             @vite above) instead of this cdn.jsdelivr.net script tag -
             same reasoning as the CSS/font bundling at the top of <head>. --}}
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
    