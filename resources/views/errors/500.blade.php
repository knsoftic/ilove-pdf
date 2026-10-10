<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Server error</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#1e293b;text-align:center;padding:1.5rem}
        h1{font-size:1.75rem;margin:.5rem 0}p{color:#64748b;max-width:32rem}a{display:inline-block;margin-top:1.5rem;background:#4f46e5;color:#fff;padding:.7rem 1.2rem;border-radius:.75rem;text-decoration:none;font-weight:600}
        small{color:#6366f1;font-weight:700;letter-spacing:.15em;text-transform:uppercase}
    </style>
</head>
<body>
    <main>
        <small>Error 500</small>
        <h1>Server processing error</h1>
        <p>Something went wrong on our side. Your files are safe and will be deleted automatically. Please try again in a moment.</p>
        <a href="{{ url('/') }}">Go to home page</a>
    </main>
</body>
</html>
