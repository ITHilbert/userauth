# Abdeckung: userauth-datatable-ohne-jquery

Risiko normal: Format wie bei kritisch, Sätze je Aussage (nicht vom Hook zerlegt).

AK1 | "enthält das Inline-Skript `new DataTable('.data-table', {…})`; Optionen, Spalten, Aktionsspalte, processing, serverSide, responsive, language.url, Ajax-URL bleiben" | tests/Feature/AdminViewsKitTest.php::test_ak1_liste_nutzt_new_datatable_ohne_jquery_mit_denselben_spalten_und_derselben_ajax_route, tests/Feature/AdminViewsSkripteTest.php::test_ak4_sprachdatei_url_der_initialisierung_endet_auf_den_dateinamen
AK1 | "und keinen Aufruf von `$` oder `jQuery`" | tests/Feature/AdminViewsKitTest.php::test_ak1_inline_skript_der_liste_ruft_weder_dollar_noch_jquery_auf
AK2 | "dann wird `.data-table` genau einmal ohne Fehler initialisiert (ohne window.$/jQuery, mit window.DataTable)" | tests/Feature/AdminViewsSkripteTest.php::test_ak2_ohne_jquery_wird_data_table_genau_einmal_ohne_fehler_initialisiert
AK3 | "Die Option ist `ajax: { url, headers: { 'X-Requested-With': 'XMLHttpRequest' } }`" | tests/Feature/AdminViewsKitTest.php::test_ak3_ajax_option_traegt_url_und_header_x_requested_with, tests/Feature/AdminViewsSkripteTest.php::test_ak3_ajax_option_im_lauf_traegt_url_und_header
AK4 | "permission.js ruft weder `$` noch `jQuery` auf" | tests/js/permission.test.mjs::AK4: permission.js ruft weder `$` noch `jQuery` auf, tests/Feature/AdminViewsSkripteTest.php::test_ak4_permission_js_laeuft_ohne_bundle_und_ohne_jquery_ohne_fehler
AK4 | "Änderung von `group_display` setzt `group_name`" | tests/js/permission.test.mjs::AK4: Änderung von group_display setzt group_name
AK4 | "showSinglePermissions, newPermission, deletePermission, editIntern bleiben globale Funktionen mit derselben Wirkung auf das DOM" | tests/js/permission.test.mjs::AK4: showSinglePermissions blendet #group_permissions_show je nach Checkbox mit d-none aus und ein, tests/js/permission.test.mjs::AK4: newPermission klont die Vorlage: sichtbar, Nummer, Namen, Löschen-Button, tests/js/permission.test.mjs::AK4: deletePermission entfernt neue Zeilen und blendet vorhandene aus (delete_*-Namen), tests/js/permission.test.mjs::AK4: editIntern bildet den internen Namen einer vorhandenen Zeile aus group_name und Anzeige, tests/Feature/AdminViewsSkripteTest.php::test_randfall3_permission_js_laeuft_nach_dem_bundle
AK5 | "nennt keine jQuery-Pflicht mehr; Voraussetzung ist window.DataTable; Listen rufen new DataTable('.data-table', …) auf" | tests/Feature/AdminViewsSkripteTest.php::test_ak5_readme_nennt_window_datatable_statt_jquery_pflicht
AK5 | "Der CHANGELOG hat einen Eintrag 3.4.0" | tests/Feature/AdminViewsSkripteTest.php::test_ak5_changelog_hat_eintrag_3_4_0
Randfall 1 | "Hat der Host jQuery trotzdem geladen, funktionieren die Listen unverändert" | tests/Feature/AdminViewsSkripteTest.php::test_randfall1_mit_geladenem_jquery_laeuft_die_liste_unveraendert_ohne_jquery_aufruf
Randfall 2 | "hoechsteNeueNummer zählt weiter ab der höchsten vorhandenen Nummer" | tests/js/permission.test.mjs::Randfall: neu ergänzte Zeilen nach old()-Zeilen bekommen freie Nummern und füllen ihre eigene Zeile

Einschränkung AK4: Verhalten über minimales DOM (node --test, vm, kein jsdom), nicht im echten Browser. AK2: Fixture fälscht window.DataTable; echte DataTables-Initialisierung ungeprüft (Echtlauf im NACHWEIS).
