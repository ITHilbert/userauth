# Abdeckung: loesch-dialog-kit-component

Risiko normal: Format wie bei kritisch, Sätze je Aussage (nicht vom Hook zerlegt).

AK1 | "Dialog aus <x-kit::dialog-delete> in den drei Listen: Wurzel mit data-route, Titel, Text, Buttons (de/en), genau ein Formular #formDelete" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak1_liste_enthaelt_den_dialog_der_component_auf_deutsch, tests/Feature/LoeschDialogKitComponentTest.php::test_ak1_knoepfe_des_dialogs_sind_auf_englisch_cancel_und_delete
AK2 | "kein Inline-Skript mehr, genau ein Modul-Skript mit initLoeschDialog aus der Asset-Route" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak2_seite_hat_ein_modul_skript_und_keinen_inline_handler
AK3 | "Asset-Routen: 200, text/javascript, Inhalt der Kit-Datei, ohne Cookie, Cache-Control public max-age=300 ohne immutable" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak3_asset_route_liefert_die_kit_datei_ohne_session_und_mit_kurzem_cache
AK4 | "andere Kit-Dateien und Pfade mit .. geben 404" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak4_andere_dateien_und_pfade_mit_punkten_geben_404
AK5 | "Partial ohne eigenes form#formDelete und ohne Klick-Handler im Stack" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak5_partial_hat_kein_eigenes_formular_und_keinen_klick_handler
AK6 | "CSS und Quelle: .kit-dialog[hidden] unsichtbar, :not([hidden]) feste Ebene, Breite 32rem" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak6_css_hat_die_feste_dialog_ebene_der_kit_component
AK7 | "composer ^1.14, README mit Component und Asset-Routen, CHANGELOG 4.7.0" | tests/Feature/LoeschDialogKitComponentTest.php::test_ak7_composer_readme_und_changelog_nennen_component_und_version
AK8 | "Klick öffnet, Löschen setzt Route mit ID und sendet; Abbrechen/Overlay/Escape schließen ohne Senden" | tests/js/loeschen-dialog.test.mjs::Klick auf [data-kit-delete="5"] öffnet, Löschen setzt die Route mit ID 5 und sendet einmal, tests/js/loeschen-dialog.test.mjs::abbrechen schließt ohne zu senden, tests/js/loeschen-dialog.test.mjs::overlay schließt ohne zu senden, tests/js/loeschen-dialog.test.mjs::escape schließt ohne zu senden
AK9 | "bestehende Tests an die Component angepasst, Aussage gleich" | tests/Feature/KernDokuTest.php::test_ak11_composer_verlangt_laravel_kit_1_14, tests/Feature/KernDokuTest.php::test_ak11_readme_nennt_dialog_delete_nirgends_als_voraussetzung
