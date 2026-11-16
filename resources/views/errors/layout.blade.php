<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title', 'Something went wrong · Isimbi Mart')</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased">
        <main class="store-error-shell">
            <div class="store-error-frame">
                <div class="store-error-topbar">
                    <a href="/" class="store-error-brand" aria-label="Isimbi Mart home">
                        <img src="/images/isimbi-mart-logo.png" alt="Isimbi Mart" width="479" height="298">
                    </a>
                    <a href="/shop" class="store-error-topbar-link">Shop all bags</a>
                </div>

                <div class="store-error-card">
                    <div>
                        <p class="store-error-code">@yield('code')</p>
                        <h1 class="store-error-title">@yield('heading')</h1>
                        <p class="store-error-copy">@yield('message')</p>
                        <div class="store-error-actions">@yield('actions')</div>
                        <div class="store-error-hints">@yield('hints')</div>
                    </div>

                    <div class="store-error-art" aria-hidden="true">
                        <svg viewBox="0 0 320 300" role="presentation" focusable="false">
                            <circle cx="160" cy="150" r="116" class="art-tint" />
                            <path class="art-fill" d="M78 116h164l-13 124a16 16 0 0 1-16 15H107a16 16 0 0 1-16-15Z" />
                            <path class="art-line" d="M78 116h164l-13 124a16 16 0 0 1-16 15H107a16 16 0 0 1-16-15Z" />
                            <path class="art-accent" d="M121 116V99a39 39 0 0 1 78 0v17" />
                            <path class="art-line" d="M99 148h122" opacity="0.32" />
                            <circle cx="160" cy="183" r="12" class="art-tint" />
                            <path class="art-line" d="M160 171v-7M160 202v-7" />
                            <path class="art-accent" d="M245 74v22M234 85h22" />
                            <path class="art-accent" d="M66 196v16M58 204h16" />
                        </svg>
                    </div>
                </div>

                <p class="store-error-foot">Isimbi Mart · Thoughtfully selected bags for everyday life</p>
            </div>
        </main>
    </body>
</html>
