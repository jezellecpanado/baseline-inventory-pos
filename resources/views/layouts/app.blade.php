<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Baseline</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {darkMode:'class',theme:{extend:{colors:{'primary':'#000000','primary-container':'#8ce805','on-primary-container':'#121212','surface':'#ffffff','background':'#ffffff','surface-variant':'#e5e5e5','surface-container-low':'#f8f8f8','surface-container':'#f4f4f4','surface-container-high':'#e8e8e8','surface-container-highest':'#e5e5e5','surface-container-lowest':'#ffffff','on-surface':'#000000','on-surface-variant':'#4c4546','secondary':'#737373','outline':'#7e7576','outline-variant':'#e5e5e5','error':'#ba1a1a','inverse-surface':'#121212','inverse-on-surface':'#ffffff','jet-black':'#000000','onyx-surface':'#121212','electric-lime':'#8ce805','electric-lime-hover':'#7ed302','electric-lime-muted':'#f2fddb','neutral-100':'#f8f8f8','neutral-200':'#eeeeee','neutral-300':'#e0e0e0','neutral-500':'#737373','neutral-700':'#333333'},fontFamily:{'body-md':['Inter'],'body-sm':['Inter'],'body-lg':['Inter'],'headline-sm':['Inter'],'headline-md':['Inter'],'headline-lg':['Inter'],'display-md':['Inter'],'display-lg':['Inter'],'label-sm':['Inter'],'label-md':['Inter'],'label-lg':['Inter'],'mono-data':['Inter'],'numeric-display':['Inter'],'numeric-table':['Inter']},fontSize:{'body-md':['14px',{lineHeight:'20px',letterSpacing:'0em',fontWeight:'400'}],'body-sm':['12px',{lineHeight:'16px',letterSpacing:'.01em',fontWeight:'400'}],'body-lg':['16px',{lineHeight:'24px',letterSpacing:'-.005em',fontWeight:'400'}],'label-sm':['11px',{lineHeight:'14px',letterSpacing:'.08em',fontWeight:'800'}],'label-md':['12px',{lineHeight:'16px',letterSpacing:'.05em',fontWeight:'700'}],'label-lg':['14px',{lineHeight:'18px',letterSpacing:'.04em',fontWeight:'700'}],'headline-sm':['18px',{lineHeight:'24px',letterSpacing:'-.01em',fontWeight:'600'}],'headline-md':['22px',{lineHeight:'28px',letterSpacing:'-.015em',fontWeight:'600'}],'headline-lg':['28px',{lineHeight:'34px',letterSpacing:'-.02em',fontWeight:'700'}],'display-md':['36px',{lineHeight:'40px',letterSpacing:'-.025em',fontWeight:'700'}],'display-lg':['48px',{lineHeight:'52px',letterSpacing:'-.03em',fontWeight:'800'}],'numeric-display':['36px',{lineHeight:'40px',letterSpacing:'-.03em',fontWeight:'700'}],'numeric-table':['13px',{lineHeight:'18px'}],'mono-data':['13px',{lineHeight:'18px',letterSpacing:'.02em',fontWeight:'600'}]},spacing:{'space-xs':'.25rem','space-sm':'.5rem','space-md':'1rem','space-lg':'1.5rem','space-xl':'2rem','space-2xl':'3rem','gutter':'1rem','gutter-tablet':'1.25rem','gutter-desktop':'1.5rem','margin':'1rem','margin-tablet':'1.5rem','margin-desktop':'2rem'},borderRadius:{DEFAULT:'.25rem',lg:'.5rem',xl:'.75rem',full:'9999px'}}}};
    </script>
    <style>
        html,body{margin:0;min-height:100%;}body{overscroll-behavior:none}::-webkit-scrollbar{display:none}
        .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 20}
    </style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="bg-surface-container-lowest font-body-md text-on-surface antialiased">
<header class="fixed inset-x-0 top-0 z-50 border-b border-surface-variant bg-surface-container-lowest">
    <div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-2 px-gutter sm:gap-space-lg sm:px-gutter-tablet lg:px-gutter-desktop">
        <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-space-lg lg:gap-space-2xl">
            <a class="flex shrink-0 items-center" href="{{ route('dashboard') }}" aria-label="Baseline dashboard">
                <img alt="Baseline Logo" class="h-10 w-auto object-contain" src="{{ asset('images/baseline-logo.png') }}">
            </a>
            <nav class="flex h-16 min-w-0 flex-1 items-center gap-space-xs overflow-x-auto md:flex-none" aria-label="Main navigation">
                <a class="whitespace-nowrap border-b-2 px-space-md py-space-sm font-label-md text-label-md uppercase tracking-wider transition-colors {{ request()->routeIs('dashboard') ? 'border-primary-container bg-inverse-surface text-inverse-on-surface' : 'border-transparent text-secondary hover:bg-surface-container hover:text-on-surface' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
                @if (($user->role ?? null) !== 'pos_staff')
                    <a class="whitespace-nowrap border-b-2 px-space-md py-space-sm font-label-md text-label-md uppercase tracking-wider transition-colors {{ request()->routeIs('warehouse*') ? 'border-primary-container bg-inverse-surface text-inverse-on-surface' : 'border-transparent text-secondary hover:bg-surface-container hover:text-on-surface' }}" href="{{ route('warehouse') }}" @if(request()->routeIs('warehouse*')) aria-current="page" @endif>Warehouse</a>
                @endif
                @if (($user->role ?? null) !== 'warehouse_staff')
                    <a class="whitespace-nowrap border-b-2 px-space-md py-space-sm font-label-md text-label-md uppercase tracking-wider transition-colors {{ request()->routeIs('pos') ? 'border-primary-container bg-inverse-surface text-inverse-on-surface' : 'border-transparent text-secondary hover:bg-surface-container hover:text-on-surface' }}" href="{{ route('pos') }}" @if(request()->routeIs('pos')) aria-current="page" @endif>POS</a>
                @endif
                @if (($user->role ?? null) === 'admin')
                    <a class="whitespace-nowrap border-b-2 px-space-md py-space-sm font-label-md text-label-md uppercase tracking-wider transition-colors {{ request()->routeIs('admin.*') ? 'border-primary-container bg-inverse-surface text-inverse-on-surface' : 'border-transparent text-secondary hover:bg-surface-container hover:text-on-surface' }}" href="{{ route('admin.index') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Admin</a>
                @endif
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-space-xs sm:gap-space-md">
            <div class="hidden items-center border border-outline-variant bg-surface-container-lowest sm:flex"><span class="border-r border-outline-variant bg-surface-container-low px-space-sm py-space-xs font-mono-data text-label-sm uppercase text-secondary">Role</span><span class="px-space-sm py-space-xs font-label-sm text-label-sm uppercase tracking-wider text-on-surface">{{ ['admin'=>'Admin','warehouse_staff'=>'Warehouse Staff','pos_staff'=>'POS Staff'][$user->role] ?? $user->role }}</span></div>
            <div class="flex items-center gap-2 border border-outline-variant bg-surface-container-lowest px-2 py-1.5 sm:hidden">
                <span class="font-label-sm text-label-sm uppercase text-on-surface">{{ ['admin'=>'Admin','warehouse_staff'=>'Warehouse','pos_staff'=>'POS Staff'][$user->role] ?? $user->role }}</span>
            </div>
            <div class="hidden h-8 w-8 items-center justify-center rounded-full bg-primary-container font-semibold text-on-primary-container sm:flex">{{ mb_substr($user->name, 0, 1) }}</div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-2 py-2 text-secondary hover:bg-surface-container-low hover:text-on-surface" type="submit" title="Log out" aria-label="Log out"><span class="material-symbols-outlined">logout</span></button></form>
        </div>
    </div>
</header>
<main class="min-h-screen bg-surface pt-16">
    @if (session('success'))<div class="mx-auto max-w-[1600px] px-gutter pt-4 sm:px-gutter-tablet lg:px-gutter-desktop"><div role="status" class="rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm font-medium text-primary">{{ session('success') }}</div></div>@endif
    @if ($errors->any())<div class="mx-auto max-w-[1600px] px-gutter pt-4 sm:px-gutter-tablet lg:px-gutter-desktop"><div role="alert" class="rounded-lg border border-error/20 bg-white px-4 py-3 text-sm text-error"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    @yield('content')
</main>
</body>
</html>
