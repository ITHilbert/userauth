// Gemeinsame Regel für interne Namen (wie Traits/Format::formatDisplayToIntern auf dem Server):
// Kleinbuchstaben, Umlaute und ß ersetzen, ; : " ' ` ~ < > | entfernen, Leerraum außen entfernen,
// Leerraum innen zu genau einem _.
function formatDisplayToIntern(value) {
    if (value === undefined || value === null) return '';

    value = String(value).toLowerCase();
    value = value.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');
    value = value.replace(/[;:"'`~<>|]/g, '');
    value = value.replace(/^\s+|\s+$/g, '');

    return value.replace(/\s+/g, '_');
}
