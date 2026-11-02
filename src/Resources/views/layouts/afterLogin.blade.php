{{--
  Die Verwaltungsseiten legen ihre Skripte in den Stack `userauth-scripts`.
  Hier werden sie in `scripts` (Stack) und `js` (Section) des Host-Layouts gebracht; das Layout gibt, was es kennt, aus.
  Gibt es beides aus, bleibt nur der erste markierte Block stehen, jedes Skript steht genau einmal in der Seite.
--}}
@push('scripts')<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endpush
@section('js')<!--userauth-skripte-->@stack('userauth-scripts')<!--/userauth-skripte-->@endsection
@php
    $userauthGesehen = false;
    echo preg_replace_callback(
        '/<!--userauth-skripte-->.*?<!--\/userauth-skripte-->/s',
        function (array $treffer) use (&$userauthGesehen): string {
            if ($userauthGesehen) {
                return '';
            }
            $userauthGesehen = true;

            return $treffer[0];
        },
        $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render()
    );
@endphp
