{{--
  Die Verwaltungsseiten legen ihre Skripte in den Stack `userauth-scripts`.
  Hier werden sie in `scripts` (Stack) und `js` (Section) des Host-Layouts gebracht; das Layout gibt, was es kennt, aus.
  Ebenso der <link> auf die vorkompilierte Verwaltungs-CSS: in `styles` (Stack) und `css` (Section).
  Gibt das Layout beides aus, bleibt je Markierung nur der erste Block stehen, jedes Skript und der Link stehen genau einmal in der Seite.
--}}
@php
    $userauthHatSkripte = trim($__env->yieldPushContent('userauth-scripts')) !== '';
    $userauthCssLink = '<link rel="stylesheet" href="'.e(route('userauth.assets.verwaltung', ['v' => app(\ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion::class)->verwaltungCssVersion()])).'">';
@endphp
@push('styles')<!--userauth-css-->{!! $userauthCssLink !!}<!--/userauth-css-->@endpush
@section('css')@parent<!--userauth-css-->{!! $userauthCssLink !!}<!--/userauth-css-->@endsection
@if ($userauthHatSkripte)
    @push('scripts')<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endpush
    @section('js')@parent<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endsection
@endif
@php
    $userauthGesehen = [];
    $userauthHtml = $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render();
    $userauthBearbeitet = preg_replace_callback(
        '/<!--userauth-(skripte|css)-->.*?<!--\/userauth-\1-->/s',
        function (array $treffer) use (&$userauthGesehen): string {
            if (isset($userauthGesehen[$treffer[1]])) {
                return '';
            }
            $userauthGesehen[$treffer[1]] = true;

            return $treffer[0];
        },
        $userauthHtml
    );
    // Schlägt die Nachbearbeitung fehl (null), bleibt die Seite nie leer: unbearbeitetes HTML.
    echo $userauthBearbeitet ?? $userauthHtml;
@endphp
