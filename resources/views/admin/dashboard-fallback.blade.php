<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard recovery</title>
    <style>
        body{margin:0;background:#f8fafc;color:#172033;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:720px;margin:9vh auto;padding:24px}.card{border:1px solid #f0c36b;border-radius:14px;background:#fffbeb;padding:28px;box-shadow:0 10px 28px #17203315}h1{margin:0;font-size:1.5rem}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}a{border-radius:8px;background:#166534;color:white;padding:10px 14px;text-decoration:none;font-weight:600;font-size:.9rem}.secondary{background:white;color:#166534;border:1px solid #86efac}.ref{color:#92400e;font-size:.75rem}
    </style>
</head>
<body><main class="wrap"><section class="card">
    <h1>Dashboard is recovering</h1>
    <p>Your signed-in account is active. A dashboard widget could not load, so this independent recovery page was used instead of a server error.</p>
    <p class="ref">Reference: {{ $exceptionId }}</p>
    <div class="actions">
        <a href="/admin/students">Learners</a><a class="secondary" href="/admin/classes">Classes</a><a class="secondary" href="/admin/settings">School settings</a>
    </div>
</section></main></body>
</html>
