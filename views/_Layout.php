{{/* The storefront's frame: header with the live cart badge, flash messages, language switch. */}}
<!doctype html>
<html lang="{{ $this->locale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Sonnenkorn</title>
    <link rel="stylesheet" href="/app.css">
    <link rel="icon" href="/favicon.svg">
</head>
<body class="min-h-screen bg-stone-50 text-stone-900">
{{ $this->flashes() }}
<header class="border-b border-stone-200 bg-white">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-4 px-4 py-4">
        <a href="/" class="flex items-center gap-2 text-2xl font-semibold tracking-tight text-amber-900">
            <img src="/favicon.svg" alt="" class="size-8"> Sonnenkorn
        </a>
        <span class="hidden text-sm text-stone-500 sm:inline">{{ $this->t('Bakery in Freiburg, order today, pick up fresh') }}</span>
        <nav class="ml-auto flex items-center gap-3 text-sm">
            <a href="?lang=en" class="{{ $this->locale() === 'en' ? 'font-semibold' : 'text-stone-500' }}">EN</a>
            <a href="?lang=de" class="{{ $this->locale() === 'de' ? 'font-semibold' : 'text-stone-500' }}">DE</a>
            <app-cart-badge></app-cart-badge>
        </nav>
    </div>
</header>
<main class="mx-auto max-w-5xl px-4 py-8">{{ $content }}</main>
<footer class="mx-auto max-w-5xl px-4 py-8 text-sm text-stone-500">
    {{ $this->t('Sonnenkorn is a made-up bakery: the example app of Tenma and Waypoint.') }}
    <a href="/backoffice" class="underline">{{ $this->t('Back office') }}</a>
</footer>
{{ $this->waypointJsTag() }}
{{ $this->pluginAssetTags() }}
</body>
</html>
