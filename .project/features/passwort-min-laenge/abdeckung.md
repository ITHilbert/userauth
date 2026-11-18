# Abdeckung: passwort-min-laenge

Risiko normal: Format wie bei kritisch, Sätze je Aussage (nicht vom Hook zerlegt).

AK1 | "POST /password/update mit 11 Zeichen bei min_laenge 12: Validierungsfehler im Feld password, Hash bleibt unverändert; mit 12 Zeichen geht es durch" | tests/Feature/PasswortMinLaengeTest.php::test_ak1_aendern_verlangt_konfigurierte_mindestlaenge_und_laesst_hash_bei_zu_kurz
AK2 | "POST /password/updatewithtoken mit 11 Zeichen: Validierungsfehler im Feld password; mit 12 Zeichen geht es durch" | tests/Feature/PasswortMinLaengeTest.php::test_ak2_zuruecksetzen_mit_token_verlangt_konfigurierte_mindestlaenge
AK3 | "kein Config-Wert: 7 Zeichen Validierungsfehler; 8 Zeichen gehen durch" | tests/Feature/PasswortMinLaengeTest.php::test_ak3_ohne_config_wert_gilt_standard_acht
AK4 | "Admin legt Konto an oder ändert das Passwort mit 11 Zeichen: Validierungsfehler im Feld password" | tests/Feature/PasswortMinLaengeTest.php::test_ak4_admin_anlegen_und_aendern_verlangen_konfigurierte_mindestlaenge
AK4 | "Selbstregistrierung mit 11 Zeichen: Validierungsfehler im Feld password" | tests/Feature/PasswortMinLaengeTest.php::test_ak4_selbstregistrierung_verlangt_konfigurierte_mindestlaenge
AK5 | "src/Config/config.php enthält passwort.min_laenge mit Standard 8 (Env USERAUTH_PASSWORT_MIN_LAENGE)" | tests/Feature/PasswortMinLaengeTest.php::test_ak5_config_datei_enthaelt_passwort_min_laenge_mit_standard_acht_und_env
AK5 | "README nennt die Einstellung, CHANGELOG hat einen Eintrag 3.5.0" | tests/Feature/PasswortMinLaengeTest.php::test_ak5_readme_nennt_einstellung_und_changelog_hat_eintrag_3_5_0
Randfall 1 | "Ungültiger Wert (nicht ganzzahlig, < 1, leer): es gilt 8" | tests/Feature/PasswortMinLaengeTest.php::test_randfall_ungueltiger_wert_faellt_auf_acht_zurueck
Randfall 2 | "Die Meldung nennt die tatsächliche Mindestlänge (Laravel-Standardmeldung min)" | tests/Feature/PasswortMinLaengeTest.php::test_randfall_meldung_nennt_die_konfigurierte_mindestlaenge
