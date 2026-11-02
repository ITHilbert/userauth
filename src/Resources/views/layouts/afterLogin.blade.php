{{--
  Die Verwaltungsseiten legen ihre Skripte in den Stack `userauth-scripts`.
  Hier werden sie in `scripts` (Stack) und `js` (Section) des Host-Layouts gebracht; das Layout gibt, was es kennt, aus.
  Gibt es beides aus, bleibt nur der erste markierte Block stehen, jedes Skript steht genau einmal in der Seite.
--}}
@php
    $userauthHatSkripte = trim($__env->yieldPushContent('userauth-scripts')) !== '';
@endphp
@if ($userauthHatSkripte)
    @push('scripts')<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endpush
    @section('js')@parent<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endsection
@endif
@php
    $userauthGesehen = false;
    $userauthHtml = $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render();
    $userauthBearbeitet = preg_replace_callback(
        '/<!--userauth-skripte-->.*?<!--\/userauth-skripte-->/s',
        function (array $treffer) use (&$userauthGesehen): string {
            if ($userauthGesehen) {
                return '';
            }
            $userauthGesehen = true;

            return $treffer[0];
        },
        $userauthHtml
    );
    // Schlägt die Nachbearbeitung fehl (null), bleibt die Seite nie leer: unbearbeitetes HTML.
    echo $userauthBearbeitet ?? $userauthHtml;
@endphp
