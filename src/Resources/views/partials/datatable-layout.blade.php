{{-- Steuerleiste der DataTables: Suche links, Anzahl-Auswahl rechts. Version beim Aufruf lesen (DataTables 2: layout, 1.x: dom). --}}
function userauthDtLayout() {
    var version = typeof DataTable !== 'undefined' && DataTable.version;
    if (version && parseInt(version, 10) < 2) {
        return { dom: "flrtip" };
    }
    return { layout: { topStart: 'search', topEnd: 'pageLength' } };
}
