<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sign in · {{ setting('site_name') }} admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-100 px-4 py-12 dark:bg-slate-950">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center text-center">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-linear-to-br from-rose-500 to-brand-600 text-white shadow-lg"><x-icon name="lock" class="size-6" /></span>
            <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">Admin sign in</h1>
            <p class="mt-1 text-sm text-slate-500">{{ setting('site_name') }} – website owner area</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="card space-y-4 p-6">
            @csrf
            <div>
                <label for="email" class="label">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="username">
                @error('email')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="remember" value="1" class="accent-brand-600"> Keep me signed in
            </label>
            <button type="submit" class="btn btn-primary w-full">Sign in</button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">No account yet? Create one on the server with <code class="kbd">php artisan pdf:admin</code></p>
    </div>
</body>
</html>
