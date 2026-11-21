{{-- Lösch-Dialog der Listen: Blade-Markup im Kit-Kern-Stil plus Inline-Skript (Stack userauth-scripts), ohne Vue-Insel. --}}
<div class="kit-dialog__overlay" data-kit-delete-overlay hidden></div>
<div class="kit-dialog__content" data-kit-delete-dialog role="dialog" aria-modal="true" aria-labelledby="userauth-loeschen-titel" hidden>
    <h2 class="kit-dialog__title" id="userauth-loeschen-titel">{{ $titel }}</h2>
    <p>{{ $body }}</p>
    <div class="userauth-dialog-aktionen">
        <button type="button" class="kit-button" data-variant="cancel" data-kit-delete-abbrechen>@lang('Abbrechen')</button>
        <button type="button" class="kit-button" data-variant="delete" data-kit-delete-bestaetigen>@lang('Löschen')</button>
    </div>
</div>

<form id="formDelete" method="POST">
    @csrf
    @method('DELETE')
</form>

@push('userauth-scripts')
<script>
(function () {
    var route = @json($route);
    var aktuelleId = null;

    function elemente() {
        return {
            overlay: document.querySelector('[data-kit-delete-overlay]'),
            dialog: document.querySelector('[data-kit-delete-dialog]'),
            formular: document.getElementById('formDelete'),
        };
    }

    function zeigen(an) {
        var e = elemente();
        if (e.overlay) { e.overlay.hidden = !an; }
        if (e.dialog) { e.dialog.hidden = !an; }
        if (!an) { aktuelleId = null; }
    }

    function routeMitId(id) {
        var teile = route.split('?');
        var pfad = teile.shift().replace(/[^\/]*$/, encodeURIComponent(id));
        return teile.length ? pfad + '?' + teile.join('?') : pfad;
    }

    document.addEventListener('click', function (ev) {
        var ziel = ev.target;
        if (!ziel || !ziel.closest) { return; }
        var ausloeser = ziel.closest('[data-kit-delete]');
        if (ausloeser) {
            aktuelleId = ausloeser.getAttribute('data-kit-delete');
            zeigen(true);
            return;
        }
        if (aktuelleId === null) { return; }
        if (ziel.closest('[data-kit-delete-bestaetigen]')) {
            var formular = elemente().formular;
            var id = aktuelleId;
            zeigen(false);
            if (formular) {
                formular.action = routeMitId(id);
                formular.submit();
            }
        } else if (ziel.closest('[data-kit-delete-abbrechen]') || ziel.closest('[data-kit-delete-overlay]')) {
            zeigen(false);
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && aktuelleId !== null) {
            zeigen(false);
        }
    });
})();
</script>
@endpush
