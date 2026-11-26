# Abdeckung: zugriff-403

Risiko normal: Format wie bei kritisch, Sätze je Aussage (nicht vom Hook zerlegt). Jeder Test läuft je Middleware (hasRole, isAdmin, hasPermission, hasPermissionAnd, hasPermissionOr) als DataProvider.

AK1 | "mit '403' antwortet die Anwendung für einen angemeldeten Benutzer ohne das Recht mit Status 403 (keine Umleitung) und der Inhalt der Route erscheint nicht" | tests/Feature/Zugriff403Test.php::test_ak1_ohne_recht_antwortet_403_ohne_umleitung_und_ohne_inhalt
AK2 | "ein JSON-Client (Accept: application/json) bekommt Status 403 mit JSON-Body" | tests/Feature/Zugriff403Test.php::test_ak2_json_client_bekommt_403_mit_json_body
AK3 | "ein Gast wird wie bisher zur Anmeldung umgeleitet" | tests/Feature/Zugriff403Test.php::test_ak3_gast_wird_weiter_zur_anmeldung_umgeleitet
AK4 | "ein Benutzer mit dem Recht erreicht den Inhalt (Status 200)" | tests/Feature/Zugriff403Test.php::test_ak4_benutzer_mit_recht_erreicht_den_inhalt
AK5 | "beim Standard (login) bzw. no-permission bleibt die Umleitung auf login bzw. no-permission unverändert" | tests/Feature/Zugriff403Test.php::test_ak5_standard_und_no_permission_leiten_weiter_wie_bisher, tests/Feature/Zugriff403Test.php::test_ak5_ohne_gesetzte_einstellung_gilt_der_standard_login
AK6 | "der Kommentar zu redirect_on_no_permission in config/config.php nennt den Wert '403'" | tests/Feature/Zugriff403Test.php::test_ak6_config_kommentar_nennt_den_wert_403
AK6 | "die README erklärt '403' im Abschnitt zu den Middlewares bzw. Rechten" | tests/Feature/Zugriff403Test.php::test_ak6_readme_erklaert_403_im_abschnitt_zu_den_middlewares_bzw_rechten
Randfall 1 | "Eine POST-Anfrage ohne Recht mit '403' ergibt ebenfalls 403" | tests/Feature/Zugriff403Test.php::test_randfall1_post_ohne_recht_ergibt_403
