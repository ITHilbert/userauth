// Tests für src/Resources/assets/js/passwort-erzeugen.js (userauth-passwort-erzeugen, Dashboard #635): Browserlogik.
// Läuft per `node --test` in einem vm-Kontext mit minimalem DOM (keine npm-Abhängigkeit); Vorbild rechtename.test.mjs.
//
// Schnittstelle, die die Tests voraussetzen (das Skript ist ein klassisches Browser-Skript ohne Export, `defer` geladen,
// bindet seine Click-Handler beim Laden — das DOM steht dann schon):
//   Erzeugen-Button   `[data-passwort-erzeugen]`  Attributwert = Adresse; `data-fehler` = Text „nicht erzeugt“
//   Kopieren-Button   `[data-passwort-kopieren]`  `data-kopiert` = Text „Kopiert“
//   Hinweis-Element   `[data-passwort-hinweis]`   bekommt die Meldung per `textContent` (nie HTML)
//   Felder            `input[name="password"]` und `input[name="password2"]` (document.querySelector)
//   Abruf             fetch(<Adresse>, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' })
//   Zwischenablage    navigator.clipboard.writeText(<Feldinhalt von password>)
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const hier = path.dirname(fileURLToPath(import.meta.url));
const skript = path.resolve(hier, '../../src/Resources/assets/js/passwort-erzeugen.js');

const ADRESSE = '/passwort/erzeugen';
const FEHLER = 'FEHLER-TEXT';
const KOPIERT = 'KOPIERT-TEXT';

const unbehandelt = [];
process.on('unhandledRejection', (e) => { unbehandelt.push(e); });

// ---------- minimaler DOM ----------
function element(tag, attribute = {}) {
    const el = {
        tagName: tag.toUpperCase(),
        attrs: { ...attribute },
        listeners: {},
        value: attribute.value ?? '',
        type: attribute.type ?? '',
        textContent: '',
        hidden: false,
        classList: { add() {}, remove() {}, toggle() {}, contains: () => false },
        style: {},
        addEventListener(typ, f) { (this.listeners[typ] ??= []).push(f); },
        removeEventListener() {},
        getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
        setAttribute(n, v) { this.attrs[n] = String(v); },
        hasAttribute(n) { return n in this.attrs; },
        removeAttribute(n) { delete this.attrs[n]; },
        focus() {},
        select() {},
        setSelectionRange() {},
    };
    el.dataset = new Proxy({}, {
        get: (_, k) => el.attrs['data-' + String(k).replace(/[A-Z]/g, (c) => '-' + c.toLowerCase())],
    });
    Object.defineProperty(el, 'innerHTML', { get() { return ''; }, set() { throw new Error('innerHTML darf nie gesetzt werden'); } });
    Object.defineProperty(el, 'outerHTML', { set() { throw new Error('outerHTML darf nie gesetzt werden'); } });
    return el;
}

function passtZu(el, selektor) {
    return selektor.split(',').map((s) => s.trim()).some((s) => {
        const m = s.match(/^([a-z0-9]*)(#[\w-]+)?((?:\[[^\]]+\])*)$/i);
        if (!m) return false;
        if (m[1] && el.tagName.toLowerCase() !== m[1].toLowerCase()) return false;
        if (m[2] && el.attrs.id !== m[2].slice(1)) return false;
        for (const a of m[3].matchAll(/\[([\w-]+)(?:=["']?([^\]"']*)["']?)?\]/g)) {
            if (!(a[1] in el.attrs)) return false;
            if (a[2] !== undefined && el.attrs[a[1]] !== a[2]) return false;
        }
        return true;
    });
}

/**
 * Baut die Seite. opt: ohnePassword, ohnePassword2, ohneHinweis, ohneErzeugen, ohneKopieren, password, password2.
 */
function baueSeite(opt = {}) {
    const el = {
        password: opt.ohnePassword ? null : element('input', { name: 'password', type: 'password', value: opt.password ?? '' }),
        password2: opt.ohnePassword2 ? null : element('input', { name: 'password2', type: 'password', value: opt.password2 ?? '' }),
        erzeugen: opt.ohneErzeugen ? null : element('button', { type: 'button', 'data-passwort-erzeugen': opt.adresse ?? ADRESSE, 'data-fehler': FEHLER }),
        kopieren: opt.ohneKopieren ? null : element('button', { type: 'button', 'data-passwort-kopieren': '', 'data-kopiert': KOPIERT }),
        hinweis: opt.ohneHinweis ? null : element('span', { 'data-passwort-hinweis': '' }),
    };
    const alle = Object.values(el).filter(Boolean);
    const document = {
        readyState: 'complete',
        querySelector: (s) => alle.find((e) => passtZu(e, s)) ?? null,
        querySelectorAll: (s) => alle.filter((e) => passtZu(e, s)),
        getElementById: (id) => alle.find((e) => e.attrs.id === id) ?? null,
        addEventListener() {},
        createElement: (t) => element(t),
    };
    // Ein Klartext-Umschalter wie bei x-kit::input-password: vor dem Laden des Skripts registriert.
    if (el.password) {
        el.password.attrs.id = 'password';
        el.password.umschalter = () => { el.password.type = el.password.type === 'password' ? 'text' : 'password'; };
        el.password.addEventListener('umschalten', el.password.umschalter);
    }
    if (el.password2) el.password2.attrs.id = 'password2';
    return { el, document };
}

/**
 * antworten: Liste von Funktionen/Antworten, je Aufruf eine (die letzte wiederholt sich).
 * Eine Antwort ist { ok, status, json } oder eine Funktion, die wirft/liefert.
 */
function laden(opt = {}, antworten = []) {
    const seite = baueSeite(opt);
    const aufrufe = [];
    const geschrieben = [];
    const timer = [];
    const fetch = (url, optionen) => {
        aufrufe.push({ url, optionen });
        const a = antworten[Math.min(aufrufe.length - 1, antworten.length - 1)];
        if (typeof a === 'function') return Promise.resolve().then(a);
        return Promise.resolve(a);
    };
    const navigator = { clipboard: { writeText: async (t) => { geschrieben.push(t); } } };
    const ctx = vm.createContext({
        document: seite.document,
        fetch,
        navigator,
        console,
        setTimeout: (f) => { timer.push(f); return timer.length; },
        clearTimeout() {},
        Promise,
        URL,
    });
    ctx.window = ctx;
    ctx.document.defaultView = ctx;
    vm.runInContext(fs.readFileSync(skript, 'utf8'), ctx, { filename: 'passwort-erzeugen.js' });

    return { ...seite, aufrufe, geschrieben, timer, ctx };
}

const flush = async () => { for (let i = 0; i < 8; i++) await new Promise((r) => setImmediate(r)); };

async function klick(button) {
    const fehler = [];
    for (const f of button.listeners.click ?? []) {
        try {
            await f({ type: 'click', preventDefault() {}, stopPropagation() {}, currentTarget: button, target: button });
        } catch (e) {
            fehler.push(e);
        }
    }
    await flush();
    return fehler;
}

const antwort = (json, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => json });

// ---------------------------------------------------------------- AK4

// AK4
test('Klick holt die Adresse per GET mit Accept application/json und Cookies der gleichen Herkunft', async () => {
    const s = laden({}, [antwort({ passwort: 'Erzeugt-1!' })]);
    assert.ok(s.el.erzeugen.listeners.click?.length > 0, 'Aufbau: Click-Handler am Erzeugen-Button gebunden');

    await klick(s.el.erzeugen);

    assert.equal(s.aufrufe.length, 1);
    assert.equal(String(s.aufrufe[0].url), ADRESSE);
    const o = s.aufrufe[0].optionen ?? {};
    assert.ok(o.method === undefined || String(o.method).toUpperCase() === 'GET', 'GET');
    assert.equal(o.body, undefined, 'kein Body');
    const accept = Object.entries(o.headers ?? {}).find(([k]) => k.toLowerCase() === 'accept');
    assert.ok(accept, 'Header Accept gesetzt');
    assert.equal(accept[1], 'application/json');
    assert.ok([undefined, 'same-origin', 'include'].includes(o.credentials), 'Cookies werden mitgesendet');
});

// AK4
test('Klick setzt das Passwort in password und password2 und zeigt password im Klartext', async () => {
    const s = laden({ password: 'alt', password2: 'alt2' }, [antwort({ passwort: 'Erzeugt-1!' })]);
    assert.equal(s.el.password.type, 'password', 'Aufbau: Feld ist zu Beginn verdeckt');

    await klick(s.el.erzeugen);

    assert.equal(s.el.password.value, 'Erzeugt-1!');
    assert.equal(s.el.password2.value, 'Erzeugt-1!');
    assert.equal(s.el.password.type, 'text');
});

// AK4
test('Jeder Klick holt ein neues Passwort', async () => {
    const s = laden({}, [antwort({ passwort: 'Erstes-1!' }), antwort({ passwort: 'Zweites-2!' })]);

    await klick(s.el.erzeugen);
    assert.equal(s.el.password.value, 'Erstes-1!');
    await klick(s.el.erzeugen);

    assert.equal(s.aufrufe.length, 2, 'zwei Abrufe');
    assert.equal(s.el.password.value, 'Zweites-2!');
    assert.equal(s.el.password2.value, 'Zweites-2!');
});

// ---------------------------------------------------------------- AK5

// AK5
test('Kopieren schreibt den Feldinhalt von password in die Zwischenablage', async () => {
    const s = laden({}, [antwort({ passwort: 'Erzeugt-1!' })]);
    await klick(s.el.erzeugen);
    assert.equal(s.el.password.value, 'Erzeugt-1!', 'Aufbau: Passwort erzeugt');

    await klick(s.el.kopieren);

    assert.deepEqual(s.geschrieben, ['Erzeugt-1!']);
});

// AK5
test('Kopieren gilt auch für ein von Hand eingegebenes Passwort', async () => {
    const s = laden({ password: 'Von-Hand-1!' });

    await klick(s.el.kopieren);

    assert.deepEqual(s.geschrieben, ['Von-Hand-1!']);
    assert.equal(s.aufrufe.length, 0, 'Kopieren ruft den Generator nicht auf');
});

// AK5
test('Nach dem Kopieren erscheint „Kopiert“ am Feld', async () => {
    const s = laden({ password: 'Von-Hand-1!' });
    assert.equal(s.el.hinweis.textContent, '', 'Aufbau: vorher keine Meldung');

    await klick(s.el.kopieren);

    assert.equal(s.el.hinweis.textContent, KOPIERT);
});

// ---------------------------------------------------------------- AK6

const fehlerFaelle = {
    'Netzfehler': () => { throw new TypeError('Failed to fetch'); },
    'Status 500': antwort({ passwort: 'Nicht-Uebernehmen-1!' }, 500),
    'Status 404': antwort({ passwort: 'Nicht-Uebernehmen-1!' }, 404),
    'Status 302': { ok: false, status: 302, json: async () => ({ passwort: 'Nicht-Uebernehmen-1!' }) },
    'kein JSON': { ok: true, status: 200, json: async () => { throw new SyntaxError('Unexpected token <'); } },
    'passwort fehlt': antwort({ anderes: 'x' }),
    'passwort leer': antwort({ passwort: '' }),
    'passwort null': antwort({ passwort: null }),
    'passwort Zahl': antwort({ passwort: 12345678 }),
    'passwort Liste': antwort({ passwort: ['a'] }),
    'passwort Objekt': antwort({ passwort: { a: 1 } }),
    'Antwort ist ein Text': antwort('Nicht-Uebernehmen-1!'),
    'Antwort ist null': antwort(null),
};

// AK6
test('Fehler im Abruf: Hinweis am Feld und Felder unverändert', async (t) => {
    for (const [name, a] of Object.entries(fehlerFaelle)) {
        await t.test(name, async () => {
            const gut = laden({ password: 'alt', password2: 'alt2' }, [antwort({ passwort: 'Gegenprobe-1!' })]);
            await klick(gut.el.erzeugen);
            assert.equal(gut.el.password.value, 'Gegenprobe-1!', 'Gegenprobe: derselbe Aufbau mit gültiger Antwort füllt das Feld');
            assert.equal(gut.el.hinweis.textContent, '', 'Gegenprobe: dann kein Fehlerhinweis');

            const s = laden({ password: 'alt', password2: 'alt2' }, [a]);
            const fehler = await klick(s.el.erzeugen);

            assert.deepEqual(fehler, [], 'kein ungefangener Fehler im Handler');
            assert.equal(s.aufrufe.length, 1, 'Aufbau: Abruf wurde versucht');
            assert.equal(s.el.hinweis.textContent, FEHLER);
            assert.equal(s.el.password.value, 'alt');
            assert.equal(s.el.password2.value, 'alt2');
            assert.equal(s.el.password.type, 'password', 'Feld bleibt verdeckt');
        });
    }
});

// AK6
test('Fehler nach erfolgreichem Klick: das zuvor erzeugte Passwort bleibt stehen', async () => {
    const s = laden({}, [antwort({ passwort: 'Erstes-1!' }), antwort({}, 500)]);
    await klick(s.el.erzeugen);
    assert.equal(s.el.password.value, 'Erstes-1!', 'Aufbau: erster Klick gelungen');

    await klick(s.el.erzeugen);

    assert.equal(s.el.hinweis.textContent, FEHLER);
    assert.equal(s.el.password.value, 'Erstes-1!');
    assert.equal(s.el.password2.value, 'Erstes-1!');
});

// ---------------------------------------------------------------- Randfälle

// Randfall 1
test('Skript ohne Passwortfeld und ohne Buttons: Laden wirft nicht', () => {
    assert.doesNotThrow(() => laden({ ohnePassword: true, ohnePassword2: true, ohneErzeugen: true, ohneKopieren: true, ohneHinweis: true }));
});

// Randfall 1
test('Seite ohne Passwortfeld: Klick auf Erzeugen und Kopieren wirft nicht', async () => {
    const s = laden({ ohnePassword: true, ohnePassword2: true }, [antwort({ passwort: 'Erzeugt-1!' })]);
    assert.ok(s.el.erzeugen.listeners.click?.length > 0, 'Aufbau: Handler gebunden');

    assert.deepEqual(await klick(s.el.erzeugen), []);
    assert.deepEqual(await klick(s.el.kopieren), []);
    assert.deepEqual(unbehandelt, [], 'keine unbehandelte Ablehnung');
});

// Randfall 1
test('Ohne password2 wird nur password gefüllt', async () => {
    const s = laden({ ohnePassword2: true }, [antwort({ passwort: 'Erzeugt-1!' })]);

    assert.deepEqual(await klick(s.el.erzeugen), []);

    assert.equal(s.el.password.value, 'Erzeugt-1!');
    assert.equal(s.el.password.type, 'text');
    assert.deepEqual(unbehandelt, []);
});

// Randfall 1
test('Ohne Hinweis-Element wirft auch der Fehlerfall nicht', async () => {
    const s = laden({ ohneHinweis: true, password: 'alt' }, [antwort({}, 500)]);

    assert.deepEqual(await klick(s.el.erzeugen), []);
    assert.equal(s.el.password.value, 'alt');
    assert.deepEqual(unbehandelt, []);
});

// Randfall 2
test('Zusätzliche Felder der Antwort zählen nicht nur passwort', async () => {
    const s = laden({ password: 'alt', password2: 'alt2' }, [antwort({ password: 'Falsch-1!', password2: 'Falsch-2!', value: 'Falsch-3!', data: { passwort: 'Falsch-4!' }, passwort: 'Richtig-1!', extra: 1 })]);

    await klick(s.el.erzeugen);

    assert.equal(s.el.password.value, 'Richtig-1!');
    assert.equal(s.el.password2.value, 'Richtig-1!');
});

// Randfall 3
test('Der Wert wird als Text gesetzt und nie als HTML', async () => {
    const roh = '<b>x</b>"&amp;<img src=x onerror=alert(1)>';
    const s = laden({}, [antwort({ passwort: roh })]);

    assert.deepEqual(await klick(s.el.erzeugen), [], 'innerHTML/outerHTML werden nie gesetzt');

    assert.equal(s.el.password.value, roh);
    assert.equal(s.el.password2.value, roh);
});

// Randfall 3
test('Meldungen werden als Text gesetzt und nie als HTML', async () => {
    const s = laden({ password: 'alt' }, [antwort({}, 500)]);
    s.el.erzeugen.attrs['data-fehler'] = '<b>Fehler</b> &amp;';

    assert.deepEqual(await klick(s.el.erzeugen), [], 'innerHTML wird nie gesetzt');

    assert.equal(s.el.hinweis.textContent, '<b>Fehler</b> &amp;');
});

// Randfall 4
test('Der Klartext-Umschalter arbeitet nach dem Erzeugen weiter', async () => {
    const s = laden({}, [antwort({ passwort: 'Erstes-1!' }), antwort({ passwort: 'Zweites-2!' })]);
    const feld = s.el.password;

    await klick(s.el.erzeugen);
    assert.equal(feld.type, 'text', 'Aufbau: nach dem Erzeugen im Klartext');

    feld.listeners.umschalten.forEach((f) => f());
    assert.equal(feld.type, 'password', 'Umschalter verdeckt das Feld wieder');
    assert.equal(feld.listeners.umschalten.length, 1, 'Umschalter-Handler wurde nicht entfernt oder ersetzt');
    assert.equal(s.ctx.document.querySelector('input[name="password"]'), feld, 'Feld wurde nicht ersetzt');

    await klick(s.el.erzeugen);
    assert.equal(feld.type, 'text', 'nächster Klick zeigt wieder Klartext');
    feld.listeners.umschalten.forEach((f) => f());
    assert.equal(feld.type, 'password');
    assert.equal(feld.value, 'Zweites-2!');
});
