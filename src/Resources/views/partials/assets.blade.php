{{--
    Lädt die Frontend-Assets des HOST-Projekts – unabhängig vom dort genutzten
    Build-Tool.

    Grund: Dieses Package darf kein Build-Tool voraussetzen. Ein hartes
    @vite(...) bricht jedes Projekt, das mit Laravel Mix baut (kein
    public/build/manifest.json -> "Vite manifest not found", HTTP 500 auf
    /login). Umgekehrt gilt dasselbe für ein hartes mix().

    Reihenfolge der Erkennung:
      1. Vite-Manifest vorhanden (oder Hot-Reload aktiv) -> @vite
      2. Mix-Manifest vorhanden                          -> mix()
      3. nichts davon -> statische Pfade, sofern die Dateien existieren
--}}
@php
    $viteReady = file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'));
    $mixReady = file_exists(public_path('mix-manifest.json'));
@endphp

@if($viteReady)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@elseif($mixReady)
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <script src="{{ mix('js/app.js') }}" defer></script>
@else
    @if(file_exists(public_path('css/app.css')))
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
    @if(file_exists(public_path('js/app.js')))
        <script src="{{ asset('js/app.js') }}" defer></script>
    @endif
@endif
