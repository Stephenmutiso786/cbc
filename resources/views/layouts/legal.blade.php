<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Legal information' }} - {{ config('school.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 text-gray-800">
    <header class="bg-green-800 px-4 py-6 text-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
            <a href="{{ route('login') }}" class="font-bold">{{ config('school.name') }}</a>
            <nav class="flex gap-4 text-sm"><a href="{{ route('legal.terms') }}" class="hover:underline">Terms</a><a href="{{ route('legal.privacy') }}" class="hover:underline">Privacy</a></nav>
        </div>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-8">@yield('content')</main>
</body>
</html>
