<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} — Student Portal</title>
    @include('layouts.partials.pwa')
    @include('layouts.partials.theme')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased">
<div class="min-h-screen">
    <div data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-black/50 md:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col bg-emerald-900 transition-transform duration-300 md:translate-x-0">
        <div class="flex h-16 items-center justify-between bg-emerald-950 px-5"><span class="truncate font-bold text-white">Student Portal</span><button type="button" data-sidebar-close class="rounded p-2 text-emerald-100 md:hidden" aria-label="Close menu">&times;</button></div>
        <nav class="flex-1 space-y-1 px-3 py-4">
            @foreach([['student.dashboard','Dashboard'],['student.results','My Results'],['student.notes','Learning Notes'],['student.notifications','Notifications'],['student.support','Support Tickets']] as [$route,$label])
                <a href="{{ route($route) }}" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-100 hover:bg-emerald-800">{{ $label }}</a>
            @endforeach
            <a href="{{ route('legal.terms') }}" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-100 hover:bg-emerald-800">Terms and Conditions</a>
            <a href="{{ route('legal.privacy') }}" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-100 hover:bg-emerald-800">Privacy Policy</a>
        </nav>
        <div class="border-t border-emerald-800 px-4 py-3"><p class="truncate text-xs text-emerald-200">{{ auth()->user()->name }}</p><form method="POST" action="{{ route('logout') }}">@csrf<button class="mt-1 text-xs text-emerald-300 hover:text-white">Sign out</button></form></div>
    </aside>
    <div class="min-h-screen md:ml-60">
        <header class="flex h-14 items-center gap-3 bg-white px-4 shadow-sm md:px-6"><button type="button" data-mobile-menu aria-expanded="false" class="relative z-50 rounded-lg p-2 text-gray-700 md:hidden" aria-label="Open menu">&#9776;</button><h1 class="truncate text-lg font-semibold text-gray-800">{{ $header ?? 'Student Portal' }}</h1><div class="ml-auto flex items-center gap-2">@include('layouts.partials.theme-toggle')<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600">Log out</button></form></div></header>
        <main class="min-w-0 overflow-x-hidden p-4 md:p-6">@yield('content')</main>
        <footer class="px-4 pb-6 text-center text-xs text-gray-500"><a href="{{ route('legal.terms') }}" class="underline">Terms</a> · <a href="{{ route('legal.privacy') }}" class="underline">Privacy</a></footer>
    </div>
</div>
@livewireScripts
@include('layouts.partials.loading')
@include('layouts.partials.cookie-consent')
</body>
</html>
