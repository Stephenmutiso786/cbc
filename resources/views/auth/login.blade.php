<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('school.name') }}</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gradient-to-br from-green-900 to-green-700 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        {{-- Logo / School Name --}}
        <div class="text-center mb-8">
            <div class="inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-white p-2 shadow-lg mb-4">
                @if(config('school.logo_data'))
                    <img src="{{ config('school.logo_data') }}" alt="{{ config('school.name') }} logo" class="h-full w-full object-contain">
                @else
                    <svg class="h-9 w-9 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422A12.083 12.083 0 0121 13c0 2.386-.37 4.687-1.058 6.85A12.006 12.006 0 0112 21a12.006 12.006 0 01-7.942-1.15A12.083 12.083 0 013 13c0-.836.068-1.655.2-2.455L12 14z"/>
                    </svg>
                @endif
            </div>
            <h1 class="text-white text-2xl font-bold">{{ config('school.name') }}</h1>
            <p class="text-green-200 text-sm mt-1">{{ config('school.motto') }}</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-gray-800 text-xl font-semibold mb-1">Welcome back</h2>
            <p class="text-gray-500 text-sm mb-6">Sign in to your account</p>

            @if (isset($errors) && $errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
            @endif

            @if (session('status'))
            <div class="mb-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-600 mb-1">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-600 mb-1">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-11 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-green-700" aria-label="Show password" aria-pressed="false">
                            <svg id="password-eye" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-green-600"> Remember me
                    </label>
                    @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-green-700 hover:text-green-800">Forgot password?</a>
                    @endif
                </div>
                <button type="submit"
                        class="w-full bg-green-700 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-green-800 active:bg-green-900 transition-colors mt-2">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-green-300 text-xs mt-6">
            CBC School Management System &copy; {{ date('Y') }}
        </p>
    </div>
    <script>
        document.getElementById('toggle-password').addEventListener('click', function () {
            const password = document.getElementById('password');
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            this.setAttribute('aria-pressed', visible ? 'true' : 'false');
        });
</script>
@include('layouts.partials.loading')
</body>
</html>
