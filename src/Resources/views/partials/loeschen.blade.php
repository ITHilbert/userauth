{{-- Lösch-Dialog der Listen: <x-kit::dialog-delete> (Theme kern, inkl. Formular #formDelete) plus Modul-Skript des Kits über die Asset-Route (#2449). --}}
{{-- Genau einmal je Seite einbinden: das Kit-Modul verdrahtet einen Dialog pro Seite. --}}
<x-kit::dialog-delete :titel="$titel" :body="$body" :route="$route" />

@push('userauth-scripts')
<script type="module">
import { initLoeschDialog } from @json(route('userauth.assets.kit.loesch-dialog'));
initLoeschDialog();
</script>
@endpush
