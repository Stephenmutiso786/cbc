<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} — Parent Portal</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50">
<div class="max-w-4xl mx-auto py-6 px-4">
    @include('layouts.partials.impersonation-banner')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-green-900">{{ config('school.name') }}</h1>
        @include('layouts.partials.online-users')
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-sm text-red-600">Logout</button></form>
    </div>
    <nav class="mb-6 flex flex-wrap gap-2">
        @foreach([['parent.dashboard','Dashboard'],['parent.progress.index','Progress'],['parent.fees.index','Fees'],['parent.notes.index','Notes'],['parent.notifications.index','Notifications'],['parent.support.index','Support']] as [$r,$l])
        @php($badgeModule = app(\App\Services\ModuleNotificationService::class)->moduleForRoute($r))
        <a href="{{ route($r) }}" class="flex items-center justify-between px-4 py-2 rounded-lg text-sm font-medium bg-white border hover:bg-green-50 text-gray-700"><span>{{ $l }}</span>@if($badgeModule)<livewire:notifications.module-notification-badge :module="$badgeModule" />@endif</a>
        @endforeach
        <a href="{{ route('legal.terms') }}" class="px-4 py-2 rounded-lg text-sm font-medium bg-white border hover:bg-green-50 text-gray-700">Terms and Conditions</a>
        <a href="{{ route('legal.privacy') }}" class="px-4 py-2 rounded-lg text-sm font-medium bg-white border hover:bg-green-50 text-gray-700">Privacy Policy</a>
        <a href="{{ route('learning.miyagi') }}" target="_blank" rel="noopener noreferrer" class="px-4 py-2 rounded-lg text-sm font-medium bg-green-700 text-white hover:bg-green-800">Miyagi AI Learning</a>
    </nav>
    @yield('content')
    @isset($slot)
        {{ $slot }}
    @endisset
    <footer class="mt-8 text-center text-xs text-gray-500"><a href="{{ route('legal.terms') }}" class="underline">Terms and Conditions</a> · <a href="{{ route('legal.privacy') }}" class="underline">Privacy Policy</a></footer>
</div>
@livewireScripts
@include('layouts.partials.loading')
</body>
</html>
