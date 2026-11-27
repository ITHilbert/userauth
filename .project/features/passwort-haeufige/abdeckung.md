# Abdeckung: passwort-haeufige

Risiko normal: Format wie bei kritisch, Sätze je Aussage (nicht vom Hook zerlegt).

AK1 | "POST /password/update mit \"password1234\" bei haeufige_ablehnen true und min_laenge 12: Validierungsfehler im Feld password, Hash bleibt unverändert" | tests/Feature/PasswortHaeufigeTest.php::test_ak1_aendern_lehnt_haeufiges_passwort_ab_und_laesst_hash
AK2 | "\"Password1234\" und \" password1234 \" (Randleerzeichen) werden abgelehnt (Schreibweise und Randleerzeichen zählen nicht)" | tests/Feature/PasswortHaeufigeTest.php::test_ak2_schreibweise_und_randleerzeichen_zaehlen_nicht
AK3 | "\"blauerhafenkran\" wird bei haeufige_ablehnen true und min_laenge 12 angenommen" | tests/Feature/PasswortHaeufigeTest.php::test_ak3_seltenes_passwort_wird_angenommen
AK4 | "haeufige_ablehnen false oder Schlüssel fehlt: \"password1234\" wird angenommen wie bisher" | tests/Feature/PasswortHaeufigeTest.php::test_ak4_standard_false_und_fehlender_schluessel_lassen_haeufiges_durch
AK5 | "Zurücksetzen mit Token: \"password1234\" Validierungsfehler im Feld password" | tests/Feature/PasswortHaeufigeTest.php::test_ak5_zuruecksetzen_mit_token_lehnt_haeufiges_passwort_ab
AK5 | "Selbstregistrierung: \"password1234\" Validierungsfehler im Feld password" | tests/Feature/PasswortHaeufigeTest.php::test_ak5_selbstregistrierung_lehnt_haeufiges_passwort_ab
AK5 | "Anlegen bzw. Ändern durch den Admin: \"password1234\" Validierungsfehler im Feld password" | tests/Feature/PasswortHaeufigeTest.php::test_ak5_admin_anlegen_und_aendern_lehnen_haeufiges_passwort_ab
AK6 | "Meldung in de und en (Schlüssel userauth::password.haeufig), enthält nie das eingegebene Passwort" | tests/Feature/PasswortHaeufigeTest.php::test_ak6_meldung_in_de_und_en_ohne_das_passwort
AK7 | "keine Netzabfrage (Http::fake sieht keine Anfrage) und 1000 Prüfungen in unter 1 Sekunde" | tests/Feature/PasswortHaeufigeTest.php::test_ak7_keine_netzabfrage_und_tausend_pruefungen_unter_einer_sekunde
AK8 | "src/Config/config.php enthält passwort.haeufige_ablehnen mit Standard false (Env USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN)" | tests/Feature/PasswortHaeufigeTest.php::test_ak8_config_datei_enthaelt_schluessel_mit_standard_false_und_env
AK8 | "README nennt Einstellung, Quelle (SecLists) und Lizenz (MIT); CHANGELOG hat einen Eintrag 4.5.0" | tests/Feature/PasswortHaeufigeTest.php::test_ak8_readme_nennt_einstellung_quelle_lizenz_und_changelog_hat_4_5_0
Randfall 1 | "Ungültiger Wert (z. B. \"abc\") wirkt wie false; Strings \"true\", \"1\", \"on\" wirken wie true" | tests/Feature/PasswortHaeufigeTest.php::test_randfall_string_werte_der_config
Randfall 2 | "Passwort kein String: die Regel string greift, die Listenprüfung überspringt es" | nicht prüfbar: Eigenverhalten der Regel string; Listenprüfung nur über Implementierungsdetail beobachtbar (Risiko normal)
Randfall 3 | "Liste: Kleinbuchstaben, eindeutig, je Zeile ein Eintrag" | nicht prüfbar: Datenformat der mitgelieferten Datei, kein Verhalten (Test würde Quelldatei lesen)
Randfall 4 | "Fehlt die Datei oder ist unlesbar, lehnt die Regel nichts ab (kein Absturz)" | nicht prüfbar: braucht Dateieingriff im Paket (Datei verschieben)
