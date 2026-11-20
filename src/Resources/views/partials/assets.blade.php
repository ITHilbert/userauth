{{--
    Lädt die Frontend-Assets des HOST-Projekts – unabhängig vom dort genutzten
    Build-Tool.

    Grund: Dieses Package darf kein Build-Tool voraussetzen. Ein hartes
    @vite(...) bricht jedes Projekt, das mit Laravel Mix baut (kein
    public/build/manifest.json -> "Vite manifest not found", HTTP 500 auf
    /login). Umgekehrt gilt dasselbe für ein hartes mix().

    Reihenfolge der Erkennung:
      1. Hot-Reload aktiv (alle Einträge aus userauth.vite_entries) oder Vite-Manifest
         mit mindestens einem dieser Einträge (nur die vorhandenen) -> @vite
      2. Mix-Manifest vorhanden                          -> mix()
      3. nichts davon -> statische Pfade, sofern die Dateien existieren
--}}
@php
    $viteManifest = public_path('build/manifest.json');
    $viteEintraege = array_values(array_filter((array) config('userauth.vite_entries', []), 'is_string'));
    if (! file_exists(public_path('hot'))) {
        // Nur Einträge laden, die das Manifest kennt; ungültiges JSON zählt wie "keine Einträge".
        $manifestInhalt = file_exists($viteManifest) ? json_decode((string) file_get_contents($viteManifest), true) : null;
        $viteEintraege = is_array($manifestInhalt)
            ? array_values(array_filter($viteEintraege, fn ($eintrag) => array_key_exists($eintrag, $manifestInhalt)))
            : [];
    }
    $viteReady = $viteEintraege !== [];
    $mixReady = file_exists(public_path('mix-manifest.json'));
@endphp

@if($viteReady)
    @vite($viteEintraege)
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
