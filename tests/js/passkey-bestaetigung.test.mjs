// Tests für die Bestätigung vor „Passkey hinzufügen“ in passkey.js (userauth passkey-bestaetigung, #2225, AK8).
// Das echte Skript läuft in einem vm-Kontext mit minimalem DOM-Ersatz, Fake-`fetch` und Fake-`navigator.credentials`
// (keine npm-Abhängigkeit). Vorgaben an Seite und Skript (Hooks):
//  - Formular `[data-passkey-registrieren]` trägt `data-bestaetigen-url`, bei Benutzern mit Passkey zusätzlich
//    `data-bestaetigen-optionen-url` und `data-bestaetigen-assertion-url`, und `data-bestaetigung="<id>"` (Id des Dialogs).
//  - Der Dialog (`data-testid="passkey-bestaetigung"`, anfangs `hidden`) enthält `input[name="aktuelles_passwort"]`,
//    den Knopf `data-testid="passkey-bestaetigung-passwort"`, optional den Knopf `data-testid="passkey-bestaetigung-passkey"`
//    und das Meldungselement `data-testid="passkey-bestaetigung-meldung"`. Fehlermeldungen dürfen auch im Meldungselement
//    des Formulars (`data-meldung`) stehen.
//  - Antworten: 403 `{"message": …, "bestaetigung_erforderlich": true}` startet die Abfrage; 204 der Bestätigung wiederholt
//    das Hinzufügen genau einmal; 422/429 zeigen `message` und wiederholen nichts. Die Assertion geht wie bei der
//    Anmeldung als `PublicKeyCredential.toJSON()` (oberste Ebene) an `data-bestaetigen-assertion-url`.
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const hier = path.dirname(fileURLToPath(import.meta.url));
const quelle = fs.readFileSync(path.resolve(hier, '../../src/Resources/assets/js/passkey.js'), 'utf8');

const URLS = {
    optionen: '/passkey/register/options',
    registrieren: '/passkey/register',
    bestaetigen: '/passkey/bestaetigen',
    bestaetigenOptionen: '/passkey/bestaetigen/optionen',
    bestaetigenAssertion: '/passkey/bestaetigen/assertion',
};
const ERFORDERLICH = { status: 403, json: { message: 'Bitte bestätigen.', bestaetigung_erforderlich: true } };
const OPTIONEN = { status: 200, json: { challenge: 'AAEC', user: { id: 'AAEC', name: 'a', displayName: 'a' }, excludeCredentials: [], allowCredentials: [{ type: 'public-key', id: 'AAEC' }] } };

// ---------- minimaler DOM ----------
class El {
    constructor(tag, attrs = {}) {
        this.tagName = tag.toUpperCase();
        this.attrs = Object.fromEntries(Object.entries(attrs).map(([k, v]) => [k, String(v)]));
        this.kinder = [];
        this.eltern = null;
        this.listeners = {};
        this.value = '';
        this.disabled = false;
        this.text = '';
        const self = this;
        this.dataset = new Proxy({}, { get: (_, k) => self.attrs['data-' + String(k).replace(/[A-Z]/g, (m) => '-' + m.toLowerCase())] });
    }
    get id() { return this.attrs.id ?? ''; }
    get hidden() { return 'hidden' in this.attrs; }
    set hidden(v) { v ? (this.attrs.hidden = '') : delete this.attrs.hidden; }
    get textContent() { return this.text; }
    set textContent(v) { this.text = String(v); }
    getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
    setAttribute(n, v) { this.attrs[n] = String(v); }
    removeAttribute(n) { delete this.attrs[n]; }
    hasAttribute(n) { return n in this.attrs; }
    add(k) { k.eltern = this; this.kinder.push(k); return k; }
    nachfahren() { return this.kinder.flatMap((k) => [k, ...k.nachfahren()]); }
    addEventListener(t, f) { (this.listeners[t] ??= []).push(f); }
    removeEventListener(t, f) { this.listeners[t] = (this.listeners[t] ?? []).filter((x) => x !== f); }
    dispatchEvent(ev) {
        ev.target ??= this;
        for (const f of [...(this.listeners[ev.type] ?? [])]) f.call(this, ev);
        return !ev.defaultPrevented;
    }
    querySelectorAll(s) { return this.nachfahren().filter((e) => passtListe(e, s)); }
    querySelector(s) { return this.querySelectorAll(s)[0] ?? null; }
    focus() {}
}

function ereignis(type) { return { type, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, stopPropagation() {} }; }

function passtEinfach(el, s) {
    let rest = s;
    while (rest) {
        let m;
        if ((m = rest.match(/^\[([\w-]+)(?:=(?:"([^"]*)"|'([^']*)'|([^\]]*)))?\]/))) {
            const soll = m[2] ?? m[3] ?? m[4];
            if (!el.hasAttribute(m[1]) || (soll !== undefined && el.getAttribute(m[1]) !== soll)) return false;
        } else if ((m = rest.match(/^#([\w-]+)/))) {
            if (el.id !== m[1]) return false;
        } else if ((m = rest.match(/^\.([\w-]+)/))) {
            if (!(el.attrs.class ?? '').split(/\s+/).includes(m[1])) return false;
        } else if ((m = rest.match(/^([a-zA-Z][\w-]*)/))) {
            if (el.tagName !== m[1].toUpperCase()) return false;
        } else {
            throw new Error('Selektor nicht unterstützt: ' + s);
        }
        rest = rest.slice(m[0].length);
    }
    return true;
}

function passtListe(el, selektor) {
    return selektor.split(',').some((teil) => {
        const schritte = teil.trim().split(/\s+(?![^\[]*\])/);
        if (!passtEinfach(el, schritte.pop())) return false;
        let x = el.eltern;
        while (schritte.length) {
            const soll = schritte.pop();
            while (x && !passtEinfach(x, soll)) x = x.eltern;
            if (!x) return false;
            x = x.eltern;
        }
        return true;
    });
}

// ---------- Seite, Netz, Browser ----------
function aufbau({ mitPasskey = true, antworten = {} } = {}) {
    const wurzel = new El('html');
    wurzel.add(new El('meta', { name: 'csrf-token', content: 'TOKEN' }));
    const attrs = { 'data-passkey-registrieren': '', 'data-optionen-url': URLS.optionen, 'data-registrieren-url': URLS.registrieren, 'data-meldung': 'passkey-meldung', 'data-bestaetigen-url': URLS.bestaetigen, 'data-bestaetigung': 'passkey-bestaetigung', 'data-text-fehler': 'Fehler beim Einrichten', 'data-text-abgebrochen': 'Abgebrochen' };
    if (mitPasskey) {
        attrs['data-bestaetigen-optionen-url'] = URLS.bestaetigenOptionen;
        attrs['data-bestaetigen-assertion-url'] = URLS.bestaetigenAssertion;
    }
    const formular = wurzel.add(new El('form', attrs));
    const name = formular.add(new El('input', { name: 'name' }));
    name.value = 'Laptop';
    const formMeldung = formular.add(new El('p', { id: 'passkey-meldung', hidden: '' }));
    const knopf = formular.add(new El('button', { type: 'submit' }));
    const dialog = wurzel.add(new El('div', { id: 'passkey-bestaetigung', 'data-testid': 'passkey-bestaetigung', hidden: '' }));
    const passwort = dialog.add(new El('input', { name: 'aktuelles_passwort', type: 'password' }));
    const pwKnopf = dialog.add(new El('button', { type: 'button', 'data-testid': 'passkey-bestaetigung-passwort' }));
    const pkKnopf = mitPasskey ? dialog.add(new El('button', { type: 'button', 'data-testid': 'passkey-bestaetigung-passkey' })) : null;
    const dialogMeldung = dialog.add(new El('p', { 'data-testid': 'passkey-bestaetigung-meldung', hidden: '' }));

    const aufrufe = [];
    const warteschlangen = Object.fromEntries(Object.entries(antworten).map(([url, liste]) => [url, [...liste]]));
    const fetchFake = (url, optionen = {}) => {
        aufrufe.push({ url, methode: optionen.method, kopf: optionen.headers ?? {}, body: optionen.body ? JSON.parse(optionen.body) : null });
        const antwort = warteschlangen[url]?.shift() ?? { status: 500, json: { message: 'unerwartet: ' + url } };

        return Promise.resolve({
            ok: antwort.status >= 200 && antwort.status < 300,
            status: antwort.status,
            json: () => (antwort.json === undefined ? Promise.reject(new SyntaxError('leer')) : Promise.resolve(antwort.json)),
        });
    };

    const credentials = { erstellt: 0, geholt: 0, optionenGet: [] };
    const credential = (id) => ({ toJSON: () => ({ id, rawId: id, type: 'public-key', response: { clientDataJSON: 'Y2Q', attestationObject: 'YW8', authenticatorData: 'YWQ', signature: 'c2ln' } }) });
    const navigatorFake = {
        credentials: {
            create: () => { credentials.erstellt++; return Promise.resolve(credential('neu')); },
            get: (o) => { credentials.geholt++; credentials.optionenGet.push(o); return Promise.resolve(credential('bestehend')); },
        },
    };

    const ziele = [];
    const dokument = {
        readyState: 'complete',
        querySelector: (s) => wurzel.querySelector(s),
        querySelectorAll: (s) => wurzel.querySelectorAll(s),
        getElementById: (id) => wurzel.nachfahren().find((e) => e.id === id) ?? null,
        addEventListener() {},
    };
    const sandbox = { document: dokument, navigator: navigatorFake, fetch: fetchFake, atob, btoa, console, PublicKeyCredential: function () {} };
    sandbox.window = sandbox;
    sandbox.location = sandbox.window.location = { href: 'https://app.test/passkey', assign: (u) => ziele.push(u) };
    vm.createContext(sandbox);
    vm.runInContext(quelle, sandbox);

    return { formular, name, knopf, formMeldung, dialog, passwort, pwKnopf, pkKnopf, dialogMeldung, aufrufe, credentials, ziele };
}

const ruhe = async (n = 40) => { for (let i = 0; i < n; i++) await new Promise((r) => setImmediate(r)); };
const absenden = async (s) => { s.formular.dispatchEvent(ereignis('submit')); await ruhe(); };
const klick = async (el) => { el.dispatchEvent(ereignis('click')); await ruhe(); };
const anzahl = (s, url) => s.aufrufe.filter((a) => a.url === url).length;
const sichtbareMeldungen = (s) => [s.dialogMeldung, s.formMeldung].filter((e) => !e.hidden && e.textContent !== '').map((e) => e.textContent);

// AK8
test('AK8 ohne 403 laeuft das Hinzufuegen wie bisher ohne Abfrage', async () => {
    const s = aufbau({ antworten: { [URLS.optionen]: [OPTIONEN], [URLS.registrieren]: [{ status: 200, json: { redirect: '/passkey' } }] } });

    await absenden(s);

    assert.equal(s.credentials.erstellt, 1);
    assert.equal(anzahl(s, URLS.registrieren), 1);
    assert.equal(anzahl(s, URLS.bestaetigen), 0);
    assert.equal(s.dialog.hidden, true, 'Die Passwortabfrage ist ohne 403 sichtbar.');
    assert.deepEqual(s.ziele, ['/passkey']);
});

// AK8
test('AK8 403 zeigt die Passwortabfrage und die Bestaetigung per Passwort wiederholt genau einmal', async () => {
    const s = aufbau({ antworten: { [URLS.optionen]: [ERFORDERLICH, OPTIONEN], [URLS.bestaetigen]: [{ status: 204 }], [URLS.registrieren]: [{ status: 200, json: { redirect: '/passkey' } }] } });

    await absenden(s);
    assert.equal(s.dialog.hidden, false, 'Die Passwortabfrage erscheint nicht.');
    assert.equal(s.credentials.erstellt, 0, 'Vor der Bestätigung darf kein Passkey erstellt werden.');
    assert.equal(anzahl(s, URLS.registrieren), 0);
    assert.equal(anzahl(s, URLS.bestaetigen), 0, 'Ohne Eingabe darf nichts gesendet werden.');

    s.passwort.value = 'mein-Passwort';
    await klick(s.pwKnopf);

    const bestaetigung = s.aufrufe.find((a) => a.url === URLS.bestaetigen);
    assert.equal(bestaetigung.methode, 'POST');
    assert.deepEqual(bestaetigung.body, { aktuelles_passwort: 'mein-Passwort' });
    assert.equal(bestaetigung.kopf['X-CSRF-TOKEN'], 'TOKEN');
    assert.equal(anzahl(s, URLS.optionen), 2, 'Das Hinzufügen wird nicht genau einmal wiederholt.');
    assert.equal(s.credentials.erstellt, 1);
    const registrierung = s.aufrufe.find((a) => a.url === URLS.registrieren);
    assert.equal(registrierung.body.name, 'Laptop');
    assert.equal(registrierung.body.credential.id, 'neu');
    assert.equal(s.dialog.hidden, true, 'Die Passwortabfrage bleibt nach der Bestätigung offen.');
    assert.deepEqual(s.ziele, ['/passkey']);
});

// AK8
test('AK8 Mit Passkey bestaetigen sendet die Assertion und wiederholt genau einmal', async () => {
    const s = aufbau({ antworten: { [URLS.optionen]: [ERFORDERLICH, OPTIONEN], [URLS.bestaetigenOptionen]: [OPTIONEN], [URLS.bestaetigenAssertion]: [{ status: 204 }], [URLS.registrieren]: [{ status: 200, json: { redirect: '/passkey' } }] } });

    await absenden(s);
    assert.ok(s.pkKnopf, 'Der Aufbau fehlt: Knopf „Mit Passkey bestätigen“.');
    await klick(s.pkKnopf);

    assert.equal(s.credentials.geholt, 1, 'navigator.credentials.get wurde nicht genau einmal gerufen.');
    assert.equal(s.credentials.optionenGet[0].publicKey.challenge.byteLength, 3, 'Die Challenge wird nicht als Bytes übergeben.');
    const assertion = s.aufrufe.find((a) => a.url === URLS.bestaetigenAssertion);
    assert.equal(assertion.methode, 'POST');
    assert.equal(assertion.body.id, 'bestehend');
    assert.equal(assertion.body.response.signature, 'c2ln');
    assert.equal(anzahl(s, URLS.bestaetigen), 0, 'Der Passwortweg wurde nicht gebraucht.');
    assert.equal(anzahl(s, URLS.optionen), 2);
    assert.equal(s.credentials.erstellt, 1);
    assert.equal(anzahl(s, URLS.registrieren), 1);
});

async function passwortFehlerFall(status, text) {
    const s = aufbau({ antworten: { [URLS.optionen]: [ERFORDERLICH, OPTIONEN], [URLS.bestaetigen]: [{ status, json: { message: text } }, { status: 204 }], [URLS.registrieren]: [{ status: 200, json: { redirect: '/passkey' } }] } });

    await absenden(s);
    s.passwort.value = 'falsch';
    await klick(s.pwKnopf);

    assert.ok(sichtbareMeldungen(s).includes(text), `Die Meldung ${text} wird nicht angezeigt.`);
    assert.equal(anzahl(s, URLS.optionen), 1, 'Trotz Fehler wurde das Hinzufügen wiederholt.');
    assert.equal(s.credentials.erstellt, 0);
    assert.equal(anzahl(s, URLS.registrieren), 0);

    // Gegenprobe: der Aufbau greift, ein zweiter Versuch mit Erfolg führt zum Passkey
    s.passwort.value = 'richtig';
    await klick(s.pwKnopf);
    assert.equal(s.credentials.erstellt, 1);
    assert.equal(anzahl(s, URLS.registrieren), 1);
}

// AK8
test('AK8 Passwort Bestaetigung scheitert mit 422 und zeigt die Meldung ohne Wiederholung', () => passwortFehlerFall(422, 'Passwort falsch.'));

// AK8
test('AK8 Passwort Bestaetigung scheitert mit 429 und zeigt die Meldung ohne Wiederholung', () => passwortFehlerFall(429, 'Zu viele Versuche.'));

// AK8
test('AK8 Passkey Bestaetigung scheitert mit 422 und zeigt die Meldung ohne Wiederholung', async () => {
    const s = aufbau({ antworten: { [URLS.optionen]: [ERFORDERLICH, OPTIONEN], [URLS.bestaetigenOptionen]: [OPTIONEN], [URLS.bestaetigenAssertion]: [{ status: 422, json: { message: 'Passkey nicht erkannt.' } }] } });

    await absenden(s);
    await klick(s.pkKnopf);

    assert.equal(s.credentials.geholt, 1, 'Der Aufbau greift nicht: Assertion wurde nicht gesendet.');
    assert.ok(sichtbareMeldungen(s).includes('Passkey nicht erkannt.'));
    assert.equal(anzahl(s, URLS.optionen), 1);
    assert.equal(s.credentials.erstellt, 0);
    assert.equal(anzahl(s, URLS.registrieren), 0);
});

// AK8
test('AK8 verlangt die Wiederholung wieder eine Bestaetigung gibt es keine Schleife', async () => {
    const s = aufbau({ antworten: { [URLS.optionen]: [ERFORDERLICH, ERFORDERLICH, OPTIONEN], [URLS.bestaetigen]: [{ status: 204 }, { status: 204 }] } });

    await absenden(s);
    s.passwort.value = 'password';
    await klick(s.pwKnopf);

    assert.equal(anzahl(s, URLS.bestaetigen), 1, 'Es wurde mehr als einmal automatisch bestätigt.');
    assert.equal(anzahl(s, URLS.optionen), 2, 'Es wurde mehr als einmal wiederholt.');
    assert.equal(s.credentials.erstellt, 0);
    assert.ok(sichtbareMeldungen(s).length > 0 || s.dialog.hidden === false, 'Der Benutzer erfährt nicht, dass es nicht geklappt hat.');
});

// AK8
test('AK8 Benutzer ohne Passkey hat nur die Passwortabfrage', async () => {
    const s = aufbau({ mitPasskey: false, antworten: { [URLS.optionen]: [ERFORDERLICH, OPTIONEN], [URLS.bestaetigen]: [{ status: 204 }], [URLS.registrieren]: [{ status: 200, json: {} }] } });

    await absenden(s);
    assert.equal(s.pkKnopf, null);
    assert.equal(s.dialog.hidden, false);
    s.passwort.value = 'password';
    await klick(s.pwKnopf);

    assert.equal(s.credentials.erstellt, 1);
});

// Randfall 1 (Satz 2): Der Weg „Passwort vergessen“ aus der 403-Meldung muss im Dialog sichtbar bleiben.
test('Randfall1 die Server-Meldung der 403 bleibt im geoeffneten Dialog sichtbar', async () => {
    const hinweis = 'Hinweis-X: Passwort ueber Passwort vergessen setzen.';
    const s = aufbau({ antworten: { [URLS.optionen]: [{ status: 403, json: { message: hinweis, bestaetigung_erforderlich: true } }] } });

    await absenden(s);

    assert.equal(s.dialog.hidden, false, 'Der Aufbau greift nicht: Dialog ist nicht offen.');
    assert.equal(s.dialogMeldung.hidden, false, 'Die Meldung im Dialog ist ausgeblendet.');
    assert.ok(s.dialogMeldung.textContent.includes(hinweis), 'Die Server-Meldung der 403 steht nicht im Meldungselement des Dialogs.');
    assert.equal(sichtbareMeldungen(s).filter((t) => t.includes(hinweis)).length, 1, 'Die Meldung erscheint nicht genau einmal.');
});

// Randfall 1 (Satz 2): Gegenprobe, die Meldung wird bei Fehler ersetzt und nach Erfolg nicht mitgezogen.
test('Randfall1 Meldung wird bei neuem Fehler ersetzt und nach erfolgreicher Bestaetigung ist der Dialog zu', async () => {
    const hinweis = 'Hinweis-X: Passwort vergessen.';
    const s = aufbau({ antworten: { [URLS.optionen]: [{ status: 403, json: { message: hinweis, bestaetigung_erforderlich: true } }, OPTIONEN], [URLS.bestaetigen]: [{ status: 422, json: { message: 'Passwort falsch.' } }, { status: 204 }], [URLS.registrieren]: [{ status: 200, json: { redirect: '/passkey' } }] } });

    await absenden(s);
    assert.ok(s.dialogMeldung.textContent.includes(hinweis), 'Der Aufbau greift nicht: Hinweis fehlt nach der 403.');
    s.passwort.value = 'falsch';
    await klick(s.pwKnopf);
    assert.ok(s.dialogMeldung.textContent.includes('Passwort falsch.'));
    assert.ok(!s.dialogMeldung.textContent.includes(hinweis), 'Alter Hinweis steht doppelt/neben der neuen Meldung.');

    s.passwort.value = 'richtig';
    await klick(s.pwKnopf);
    assert.equal(s.dialog.hidden, true);
    assert.equal(s.credentials.erstellt, 1);
});
