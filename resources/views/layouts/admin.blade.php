<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} - {{ $title ?? 'Administration' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased">
<div class="min-h-screen">
    <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-green-800 text-white">
        <div class="flex h-16 items-center bg-green-900 px-5">
            <span class="truncate font-bold">{{ config('school.name') }}</span>
        </div>
        <nav class="space-y-1 px-3 py-4 text-sm">
            @foreach([
                ['admin.dashboard', 'Dashboard'], ['admin.students.index', 'Learners'],
                ['admin.staff.index', 'Staff'], ['finance.payments.index', 'Fees'],
                ['admin.assessment.index', 'Assessments'], ['admin.exams.index', 'Exams'],
                ['admin.notes.index', 'Learning Notes'], ['admin.inventory.index', 'Inventory'],
                ['admin.timetable.index', 'Timetable'], ['admin.reports.index', 'Reports'],
                ['admin.settings.index', 'Settings'],
            ] as [$route, $label])
                <a href="{{ route($route) }}" class="block rounded-lg px-4 py-2.5 text-green-100 hover:bg-green-700">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="absolute inset-x-0 bottom-0 border-t border-green-700 px-4 py-3">
            <p class="truncate text-xs text-green-200">{{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mt-1 text-xs text-green-300 hover:text-white">Sign out</button>
            </form>
        </div>
    </aside>
    <div class="pl-64">
        <header class="flex h-16 items-center justify-between bg-white px-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
            <span class="text-sm text-gray-500">{{ config('school.academic_year') }}</span>
        </header>
        <main class="p-6">
            @yield('content')
            @isset($slot){{ $slot }}@endisset
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
