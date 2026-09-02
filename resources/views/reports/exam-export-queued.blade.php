<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta http-equiv="refresh" content="5;url={{ route($routeName, [$export->id]) }}"><title>Preparing report cards</title></head>
<body style="font-family: sans-serif; padding: 3rem; text-align: center; color: #172033">
    <h1>Preparing report cards</h1>
    <p>The complete exam report is being generated. This page will open the download automatically when it is ready.</p>
    <p><a href="{{ route($routeName, [$export->id]) }}">Check again</a></p>
</body>
</html>
