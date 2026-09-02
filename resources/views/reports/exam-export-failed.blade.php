<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Report cards unavailable</title></head>
<body style="font-family: sans-serif; padding: 3rem; color: #172033">
    <h1>Report cards could not be generated</h1>
    <p>{{ $export->error ?: 'The report export failed without a recorded error.' }}</p>
    <p><a href="{{ url()->previous() }}">Return to exams</a></p>
</body>
</html>
