<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In · Baseline</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'jet-black': '#000000', 'electric-lime': '#8ce805', 'electric-lime-hover': '#7ed302', 'surface-low': '#f8f8f8', 'surface-line': '#e5e5e5', 'secondary': '#737373', 'error': '#ba1a1a' }, fontFamily: { sans: ['Inter', 'sans-serif'] } } } };
    </script>
</head>
<body class="min-h-screen bg-surface-low font-sans text-jet-black antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="w-full max-w-md border border-surface-line bg-white p-7 shadow-sm sm:p-10" aria-labelledby="login-title">
            <div class="mb-8 border-b border-surface-line pb-7">
                <img class="mb-7 h-12 w-auto object-contain" src="https://lh3.googleusercontent.com/aida/AEtjO1W74BxnqIpM2adg2g50ZRkxPrxhxzt4rFyPV2RZ_pcesH76RvrwIh5IG9xQBSxWKDwB7UpnVJLv98A3_H3ydgs8qw20GSqGwLHV5FzZhgGRpfXuGtEfgzVjGyivn3fcqBD6XgdbdrjVoRQGw5VAp9UYW5wNxYkxR9Ma0LsKdAZADYCWuBlS5LMvzz5GTKZmem-6WA0dhQ821GcPaIIwNn9zZNyuk-e8-Qg1BUSZhqd6RqhguR9IStTszZUO4kXfWmVqcIQRKZbooOg" alt="Baseline official logo">
                <p class="mb-1 text-xs font-bold uppercase tracking-[0.18em] text-secondary">Inventory &amp; POS</p>
                <h1 id="login-title" class="text-2xl font-extrabold tracking-tight">Sign in to your account</h1>
                <p class="mt-2 text-sm text-secondary">Use the username and password provided by your Admin.</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 border border-error/20 bg-red-50 px-4 py-3 text-sm text-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-semibold" for="username">Username</label>
                    <input class="w-full border border-surface-line bg-white px-3 py-3 text-sm outline-none transition focus:border-jet-black focus:ring-2 focus:ring-electric-lime/40" id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold" for="password">Password</label>
                    <input class="w-full border border-surface-line bg-white px-3 py-3 text-sm outline-none transition focus:border-jet-black focus:ring-2 focus:ring-electric-lime/40" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="w-full border border-jet-black bg-electric-lime px-4 py-3 text-sm font-bold uppercase tracking-wider transition hover:bg-electric-lime-hover" type="submit">Sign In</button>
            </form>

            <p class="mt-7 border-t border-surface-line pt-5 text-center text-xs text-secondary">Accounts are created by an Admin. Public registration is unavailable.</p>
        </section>
    </main>
</body>
</html>
