<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Setup Link Invalid' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; color: #111827; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 460px; background: #fff; border: 1px solid #d1d5db; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .head { padding: 22px 24px; border-bottom: 1px solid #e5e7eb; background: #991b1b; color: #fff; }
        .head h1 { margin: 0; font-size: 20px; line-height: 28px; }
        .body { padding: 24px; }
        p { margin: 0; font-size: 14px; line-height: 22px; color: #374151; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <h1>{{ $title ?? 'Setup Link Invalid' }}</h1>
        </div>
        <div class="body">
            <p>{{ $message ?? 'This setup link is invalid or has expired.' }}</p>
        </div>
    </div>
</div>
</body>
</html>
