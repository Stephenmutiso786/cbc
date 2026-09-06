<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify school document - {{ config('school.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-green-900 px-4 py-12 text-gray-900">
    <main class="mx-auto max-w-lg rounded-2xl bg-white p-8 shadow-xl">
        <h1 class="text-2xl font-bold text-green-800">Verify school document</h1>
        <p class="mt-3 text-gray-600">This QR code belongs to {{ config('school.name') }}. Compare the learner, examination, class, and date details on the document with the official record supplied by the school.</p>
        <p class="mt-5 rounded-lg bg-green-50 p-4 text-sm text-green-900">For official confirmation, present the document to the school administration. This is the permanent verification entry point used on school-generated documents.</p>
        <a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-green-700 px-4 py-2 font-medium text-white">School staff sign in</a>
    </main>
</body>
</html>
