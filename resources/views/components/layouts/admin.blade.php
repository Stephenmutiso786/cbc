<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} — {{ $title ?? config('app.name') }}</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <div data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-black/50 md:hidden"></div>
        <aside data-sidebar class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full bg-green-800 shadow-lg transition-transform duration-300 md:translate-x-0">
            <div class="flex items-center h-16 px-6 bg-green-900">
                <span class="text-white font-bold text-lg truncate">{{ config('school.name') }}</span>
                <button type="button" data-sidebar-close class="ml-auto rounded p-2 text-green-100 hover:bg-green-700 md:hidden" aria-label="Close menu">&times;</button>
            </div>
            <nav class="mt-4 px-3 space-y-1 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Dashboard</a>
                <a href="{{ route('admin.students.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Learners</a>
                <a href="{{ route('admin.staff.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Staff</a>
                <a href="{{ route('finance.payments.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Fees</a>
                <a href="{{ route('admin.assessment.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Assessments</a>
                <a href="{{ route('admin.timetable.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Timetable</a>
                <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Reports</a>
                <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-green-100 hover:bg-green-700">Settings</a>
            </nav>
        </aside>
        <div class="md:pl-64">
            <header class="bg-white shadow h-16 flex items-center px-4 md:px-6 justify-between">
                <button type="button" data-mobile-menu aria-expanded="false" class="rounded-lg p-2 text-gray-700 hover:bg-gray-100 md:hidden" aria-label="Open menu">&#9776;</button>
                <h1 class="text-xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:underline">Logout</button>
                </form>
            </header>
            <main class="p-4 md:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
