@extends('layouts.legal', ['title' => 'Required acceptance'])

@section('content')
<div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow-sm md:p-10">
    <p class="text-sm font-semibold uppercase tracking-wide text-green-700">Action required</p>
    <h1 class="mt-2 text-3xl font-bold">Review and accept the school policies</h1>
    <p class="mt-3 text-gray-600">Before continuing, read the current <a class="font-semibold text-green-700 underline" href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms and Conditions</a> and <a class="font-semibold text-green-700 underline" href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('legal.accept') }}" class="mt-6 space-y-4">
        @csrf
        <label class="flex items-start gap-3 rounded-lg border p-4"><input type="checkbox" name="accept_terms" value="1" required class="mt-1"><span>I have read and accept the Terms and Conditions.</span></label>
        <label class="flex items-start gap-3 rounded-lg border p-4"><input type="checkbox" name="accept_privacy" value="1" required class="mt-1"><span>I have read and acknowledge the Privacy Policy.</span></label>
        <p class="text-xs text-gray-500">Policy version {{ config('legal.version') }}. Your acceptance time, IP address, browser, and policy version are recorded for accountability.</p>
        <button type="submit" class="w-full rounded-lg bg-green-700 px-4 py-3 font-semibold text-white hover:bg-green-800">Accept and continue</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm text-red-700 underline">Sign out instead</button></form>
</div>
@endsection
