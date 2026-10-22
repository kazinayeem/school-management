<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <script>try{var l=localStorage.getItem("locale");if(l==="ar"||l==="bn"){document.documentElement.lang=l;document.documentElement.dir=l==="ar"?"rtl":"ltr"}var a=localStorage.getItem("appearance")||"system";if(a==="dark"||(a==="system"&&matchMedia("(prefers-color-scheme: dark)").matches))document.documentElement.classList.add("dark")}catch(e){}</script>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'ReadySchool') }}</title>
        <meta name="theme-color" content="#b31299">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
