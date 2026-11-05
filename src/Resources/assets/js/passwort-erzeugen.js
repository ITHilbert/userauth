// Button „Passwort erzeugen“ der Benutzerverwaltung: holt ein Passwort von der Adresse des Hosts
// (data-passwort-erzeugen) und trägt es in password und password2 ein. Klassisches Skript, defer geladen.
(function () {
    var erzeugen = document.querySelector('[data-passwort-erzeugen]');
    var kopieren = document.querySelector('[data-passwort-kopieren]');
    var hinweis = document.querySelector('[data-passwort-hinweis]');

    function meldung(text) {
        if (hinweis) {
            hinweis.textContent = text === null || text === undefined ? '' : String(text);
        }
    }

    function fehler() {
        meldung(erzeugen.getAttribute('data-fehler'));
    }

    if (erzeugen) {
        erzeugen.addEventListener('click', function () {
            meldung('');
            return fetch(erzeugen.getAttribute('data-passwort-erzeugen'), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }).then(function (antwort) {
                if (!antwort || !antwort.ok) {
                    throw new Error('status');
                }
                return antwort.json();
            }).then(function (daten) {
                var passwort = daten && typeof daten === 'object' ? daten.passwort : null;
                if (typeof passwort !== 'string' || passwort === '') {
                    throw new Error('passwort');
                }
                var feld = document.querySelector('input[name="password"]');
                var feld2 = document.querySelector('input[name="password2"]');
                if (feld) {
                    feld.value = passwort;
                    feld.type = 'text';
                }
                if (feld2) {
                    feld2.value = passwort;
                }
            }).catch(fehler);
        });
    }

    if (kopieren) {
        kopieren.addEventListener('click', function () {
            var feld = document.querySelector('input[name="password"]');
            if (!feld || !navigator.clipboard) {
                return undefined;
            }
            return navigator.clipboard.writeText(feld.value).then(function () {
                var kopiert = kopieren.getAttribute('data-kopiert');
                meldung(kopiert);
                setTimeout(function () {
                    if (hinweis && hinweis.textContent === String(kopiert)) {
                        meldung('');
                    }
                }, 2000);
            }).catch(function () {
                meldung('');
            });
        });
    }
})();
