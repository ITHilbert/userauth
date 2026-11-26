/*
 * userauth: Anmeldung und Einrichtung mit Passkey (WebAuthn). Reines JavaScript ohne Build-Schritt und ohne
 * Fremdbibliothek, ausgeliefert über die Route `userauth.assets.passkey`.
 *
 * Anmeldung:  <button data-passkey-login data-optionen-url="…" data-anmelden-url="…" data-meldung="<id>"
 *                     data-text-nicht-unterstuetzt="…" data-text-abgebrochen="…" data-text-fehler="…">
 * Einrichten: <form data-passkey-registrieren data-optionen-url="…" data-registrieren-url="…" data-meldung="<id>" …>
 *             mit <input name="name">
 *             Bestätigung vor dem Hinzufügen: data-bestaetigen-url, data-bestaetigung="<id des Dialogs>", bei Benutzern mit
 *             Passkey zusätzlich data-bestaetigen-optionen-url und data-bestaetigen-assertion-url, data-text-bestaetigung.
 *             Antwortet das Hinzufügen mit 403 und bestaetigung_erforderlich, öffnet der Dialog; nach der Bestätigung
 *             wird das Hinzufügen genau einmal wiederholt.
 */
(function () {
    'use strict';

    function b64uZuBytes(text) {
        var b64 = text.replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) {
            b64 += '=';
        }
        var roh = atob(b64);
        var bytes = new Uint8Array(roh.length);
        for (var i = 0; i < roh.length; i++) {
            bytes[i] = roh.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function bytesZuB64u(puffer) {
        var bytes = new Uint8Array(puffer);
        var roh = '';
        for (var i = 0; i < bytes.length; i++) {
            roh += String.fromCharCode(bytes[i]);
        }
        return btoa(roh).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function senden(url, daten) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: daten === undefined ? '{}' : JSON.stringify(daten)
        }).then(function (antwort) {
            return antwort.json().catch(function () {
                return {};
            }).then(function (json) {
                if (!antwort.ok) {
                    var fehler = new Error(json.message || '');
                    fehler.serverMeldung = json.message || '';
                    fehler.status = antwort.status;
                    fehler.bestaetigungErforderlich = antwort.status === 403 && json.bestaetigung_erforderlich === true;
                    throw fehler;
                }
                return json;
            });
        });
    }

    function anfrageOptionen(json) {
        if (window.PublicKeyCredential && typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function') {
            return PublicKeyCredential.parseRequestOptionsFromJSON(json);
        }
        var optionen = Object.assign({}, json, { challenge: b64uZuBytes(json.challenge) });
        optionen.allowCredentials = (json.allowCredentials || []).map(function (c) {
            return Object.assign({}, c, { id: b64uZuBytes(c.id) });
        });
        return optionen;
    }

    function erstellOptionen(json) {
        if (window.PublicKeyCredential && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function') {
            return PublicKeyCredential.parseCreationOptionsFromJSON(json);
        }
        var optionen = Object.assign({}, json, {
            challenge: b64uZuBytes(json.challenge),
            user: Object.assign({}, json.user, { id: b64uZuBytes(json.user.id) })
        });
        optionen.excludeCredentials = (json.excludeCredentials || []).map(function (c) {
            return Object.assign({}, c, { id: b64uZuBytes(c.id) });
        });
        return optionen;
    }

    function alsJson(credential) {
        if (typeof credential.toJSON === 'function') {
            return credential.toJSON();
        }
        var r = credential.response;
        var antwort = { clientDataJSON: bytesZuB64u(r.clientDataJSON) };
        if (r.attestationObject) {
            antwort.attestationObject = bytesZuB64u(r.attestationObject);
            antwort.transports = typeof r.getTransports === 'function' ? r.getTransports() : [];
        } else {
            antwort.authenticatorData = bytesZuB64u(r.authenticatorData);
            antwort.signature = bytesZuB64u(r.signature);
            antwort.userHandle = r.userHandle ? bytesZuB64u(r.userHandle) : null;
        }
        return {
            id: credential.id,
            rawId: bytesZuB64u(credential.rawId),
            type: credential.type,
            authenticatorAttachment: credential.authenticatorAttachment || null,
            clientExtensionResults: credential.getClientExtensionResults ? credential.getClientExtensionResults() : {},
            response: antwort
        };
    }

    function meldung(element, text) {
        var ziel = element.dataset.meldung ? document.getElementById(element.dataset.meldung) : null;
        if (!ziel) {
            return;
        }
        ziel.textContent = text || '';
        ziel.hidden = !text;
    }

    // Abbruch des Browser-Dialogs (oder Zeitüberschreitung): keine Anfrage an den Server
    function istAbbruch(fehler) {
        return fehler && (fehler.name === 'NotAllowedError' || fehler.name === 'AbortError');
    }

    function unterstuetzt() {
        return !!(window.PublicKeyCredential && navigator.credentials);
    }

    function anmelden(knopf) {
        meldung(knopf, '');
        knopf.disabled = true;
        senden(knopf.dataset.optionenUrl)
            .then(function (json) {
                return navigator.credentials.get({ publicKey: anfrageOptionen(json) }).then(function (credential) {
                    return senden(knopf.dataset.anmeldenUrl, alsJson(credential));
                }, function (fehler) {
                    if (istAbbruch(fehler)) {
                        fehler.abgebrochen = true;
                    }
                    throw fehler;
                });
            })
            .then(function (json) {
                window.location.assign(json.redirect || '/');
            })
            .catch(function (fehler) {
                meldung(knopf, fehler.abgebrochen ? knopf.dataset.textAbgebrochen : (fehler.serverMeldung || knopf.dataset.textFehler));
                knopf.disabled = false;
            });
    }

    function registrieren(formular, wiederholung) {
        var knopf = formular.querySelector('button');
        var feld = formular.querySelector('input[name="name"]');
        var dialog = formular.dataset.bestaetigung ? document.getElementById(formular.dataset.bestaetigung) : null;
        meldung(formular, '');
        if (knopf) {
            knopf.disabled = true;
        }
        senden(formular.dataset.optionenUrl)
            .then(function (json) {
                return navigator.credentials.create({ publicKey: erstellOptionen(json) }).then(function (credential) {
                    return senden(formular.dataset.registrierenUrl, { credential: alsJson(credential), name: feld ? feld.value : '' });
                }, function (fehler) {
                    if (istAbbruch(fehler)) {
                        fehler.abgebrochen = true;
                    }
                    throw fehler;
                });
            })
            .then(function (json) {
                window.location.assign(json.redirect || window.location.href);
            })
            .catch(function (fehler) {
                if (fehler.bestaetigungErforderlich && !wiederholung && dialog) {
                    bestaetigungOeffnen(formular, dialog, fehler.serverMeldung);
                } else {
                    meldung(formular, fehler.abgebrochen ? formular.dataset.textAbgebrochen : (fehler.serverMeldung || formular.dataset.textFehler));
                }
                if (knopf) {
                    knopf.disabled = false;
                }
            });
    }

    function dialogMeldung(dialog, text) {
        var ziel = dialog.querySelector('[data-testid="passkey-bestaetigung-meldung"]');
        if (ziel) {
            ziel.textContent = text || '';
            ziel.hidden = !text;
        }
    }

    function bestaetigungOeffnen(formular, dialog, meldungText) {
        var feld = dialog.querySelector('input[name="aktuelles_passwort"]');
        dialogMeldung(dialog, meldungText || '');
        if (feld) {
            feld.value = '';
        }
        dialog.hidden = false;
        if (feld) {
            feld.focus();
        }
    }

    // Bestätigung gesendet: bei Erfolg Dialog schließen und das Hinzufügen genau einmal wiederholen
    function bestaetigen(formular, dialog, knoepfe, anfrage) {
        dialogMeldung(dialog, '');
        knoepfe.forEach(function (k) {
            k.disabled = true;
        });
        anfrage()
            .then(function () {
                dialog.hidden = true;
                registrieren(formular, true);
            })
            .catch(function (fehler) {
                dialogMeldung(dialog, fehler.abgebrochen ? formular.dataset.textAbgebrochen : (fehler.serverMeldung || formular.dataset.textBestaetigung || formular.dataset.textFehler));
            })
            .then(function () {
                knoepfe.forEach(function (k) {
                    k.disabled = false;
                });
            });
    }

    function bestaetigungEinrichten(formular) {
        var dialog = formular.dataset.bestaetigung ? document.getElementById(formular.dataset.bestaetigung) : null;
        if (!dialog || !formular.dataset.bestaetigenUrl) {
            return;
        }
        var feld = dialog.querySelector('input[name="aktuelles_passwort"]');
        var passwortKnopf = dialog.querySelector('[data-testid="passkey-bestaetigung-passwort"]');
        var passkeyKnopf = dialog.querySelector('[data-testid="passkey-bestaetigung-passkey"]');
        var knoepfe = [passwortKnopf, passkeyKnopf].filter(Boolean);

        if (passwortKnopf) {
            passwortKnopf.addEventListener('click', function (ereignis) {
                ereignis.preventDefault();
                bestaetigen(formular, dialog, knoepfe, function () {
                    return senden(formular.dataset.bestaetigenUrl, { aktuelles_passwort: feld ? feld.value : '' });
                });
            });
        }
        if (passkeyKnopf && formular.dataset.bestaetigenOptionenUrl && formular.dataset.bestaetigenAssertionUrl) {
            passkeyKnopf.addEventListener('click', function (ereignis) {
                ereignis.preventDefault();
                bestaetigen(formular, dialog, knoepfe, function () {
                    return senden(formular.dataset.bestaetigenOptionenUrl).then(function (json) {
                        return navigator.credentials.get({ publicKey: anfrageOptionen(json) }).then(function (credential) {
                            return senden(formular.dataset.bestaetigenAssertionUrl, alsJson(credential));
                        }, function (fehler) {
                            if (istAbbruch(fehler)) {
                                fehler.abgebrochen = true;
                            }
                            throw fehler;
                        });
                    });
                });
            });
        }
    }

    function einrichten() {
        document.querySelectorAll('[data-passkey-login]').forEach(function (knopf) {
            if (!unterstuetzt()) {
                knopf.disabled = true;
                meldung(knopf, knopf.dataset.textNichtUnterstuetzt);
                return;
            }
            knopf.addEventListener('click', function (ereignis) {
                ereignis.preventDefault();
                anmelden(knopf);
            });
        });

        document.querySelectorAll('[data-passkey-registrieren]').forEach(function (formular) {
            if (!unterstuetzt()) {
                var knopf = formular.querySelector('button');
                if (knopf) {
                    knopf.disabled = true;
                }
                meldung(formular, formular.dataset.textNichtUnterstuetzt);
                return;
            }
            bestaetigungEinrichten(formular);
            formular.addEventListener('submit', function (ereignis) {
                ereignis.preventDefault();
                registrieren(formular, false);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', einrichten);
    } else {
        einrichten();
    }
})();
