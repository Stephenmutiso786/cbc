<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} — Teacher Portal</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100">
<div class="min-h-screen">
    <div data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-black/50 md:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col bg-blue-900 transition-transform duration-300 md:translate-x-0">
        <div class="h-16 flex items-center px-5 bg-blue-950">
            <span class="text-white font-bold text-sm">Teacher Portal</span>
            <button type="button" data-sidebar-close class="ml-auto rounded p-2 text-blue-100 hover:bg-blue-800 md:hidden" aria-label="Close menu">&times;</button>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1">
            @foreach([
                ['teacher.dashboard','Dashboard'],['teacher.assessment.index','Assessment Entry'],
                ['teacher.notes.index','Learning Notes'],['teacher.notifications.index','Message Parents'],['teacher.attendance.index','Attendance'],
                ['teacher.timetable.index','Timetable'],
            ] as [$route,$label])
            <a href="{{ route($route) }}" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-blue-100 hover:bg-blue-800 transition-colors">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="px-4 py-3 border-t border-blue-800">
            <p class="text-blue-300 text-xs">{{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-xs text-blue-400 hover:text-white mt-1">Sign out</button></form>
        </div>
    </aside>
    <div class="min-h-screen md:ml-60">
        <header class="bg-white h-14 flex items-center gap-3 px-4 shadow-sm md:px-6">
            <button type="button" data-mobile-menu aria-expanded="false" class="rounded-lg p-2 text-gray-700 hover:bg-gray-100 md:hidden" aria-label="Open menu">&#9776;</button>
            <h1 class="text-lg font-semibold text-gray-800">{{ $header ?? 'Teacher Portal' }}</h1>
        </header>
        <main class="p-4 md:p-6">
            @yield('content')
            @isset($slot)
                {{ $slot }}
            @endisset
        </main>
    </div>
</div>
@livewireScripts
@include('layouts.partials.loading')
</body>
</html>
