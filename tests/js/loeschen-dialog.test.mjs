// Tests für das Inline-Skript des Lösch-Dialogs (userauth partials/loeschen.blade.php, Stack `userauth-scripts`).
// Markup und Skript stammen aus der Partial `partials/loeschen.blade.php` (Blade-Quelltext, ohne PHP: nur `{{ $titel }}`,
// `{{ $body }}`, `{{ $route }}`, `{!! … !!}`, `@json($route)`, `@csrf`, `@method('…')`, `@lang('…')` und Kommentare werden ersetzt,
// jedes andere Blade-Konstrukt lässt den Test mit einer klaren Meldung scheitern) und laufen
// in einem vm-Kontext mit minimalem DOM-Ersatz (keine npm-Abhängigkeit). Der Kontext kennt keine Globals des Hosts:
// `$`/`jQuery`, `Vue`, `deleteID` und `OpenDeleteForm` sind nicht definiert, jeder Fehler des Skripts wird gesammelt.
// Annahmen zur Spec: „versteckt“ heißt Attribut `hidden`, `style.display = none` oder Klasse `d-none` (am Element oder
// einem Vorfahren); die zwei `kit-button` im Dialog sind in dieser Reihenfolge Abbrechen und Löschen; Escape kommt als
// `keydown` am document an.
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const hier = path.dirname(fileURLToPath(import.meta.url));
const partial = path.resolve(hier, '../../src/Resources/views/partials/loeschen.blade.php');

let fehlerListe = [];
const melde = (e) => fehlerListe.push(`${e?.name}: ${e?.message}`);

// ---------- Ereignisse ----------
class Ereignis {
    constructor(type, init = {}) {
        Object.assign(this, { type, bubbles: init.bubbles === true, detail: init.detail ?? null, defaultPrevented: false, cancelBubble: false }, init);
    }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.cancelBubble = true; }
    stopImmediatePropagation() { this.cancelBubble = true; }
}

class Ziel {
    constructor() { this.listeners = {}; }
    get parentTarget() { return null; }
    addEventListener(t, f) { (this.listeners[t] ??= []).push(f); }
    removeEventListener(t, f) { this.listeners[t] = (this.listeners[t] ?? []).filter((x) => x !== f); }
    dispatchEvent(ev) {
        ev.target ??= this;
        for (let z = this; z; z = ev.bubbles ? z.parentTarget : null) {
            ev.currentTarget = z;
            for (const f of [...(z.listeners[ev.type] ?? [])]) {
                try { typeof f === 'function' ? f.call(z, ev) : f.handleEvent(ev); } catch (e) { melde(e); }
            }
            if (ev.cancelBubble) break;
        }
        return !ev.defaultPrevented;
    }
}

// ---------- minimaler DOM ----------
class Text {
    constructor(data) { this.data = data; this.nodeType = 3; this.parentNode = null; }
    get textContent() { return this.data; }
}

class El extends Ziel {
    constructor(tag, attrs = {}) {
        super();
        this.tagName = tag.toUpperCase();
        this.nodeType = 1;
        this.attrs = {};
        this.childNodes = [];
        this.parentNode = null;
        this.style = { display: '', setProperty(k, v) { if (k === 'display') this.display = v; }, removeProperty(k) { if (k === 'display') this.display = ''; } };
        for (const [k, v] of Object.entries(attrs)) this.attrs[k] = String(v);
        const self = this;
        this.classList = {
            liste: () => (self.attrs.class ?? '').split(/\s+/).filter(Boolean),
            add: (...n) => { const l = self.classList.liste(); n.forEach((x) => l.includes(x) || l.push(x)); self.attrs.class = l.join(' '); },
            remove: (...n) => { self.attrs.class = self.classList.liste().filter((x) => !n.includes(x)).join(' '); },
            contains: (n) => self.classList.liste().includes(n),
            toggle: (n, erzwingen) => {
                const an = erzwingen ?? !self.classList.contains(n);
                an ? self.classList.add(n) : self.classList.remove(n);
                return an;
            },
        };
        this.dataset = new Proxy({}, {
            get: (_, k) => self.attrs['data-' + String(k).replace(/[A-Z]/g, (m) => '-' + m.toLowerCase())],
            set: (_, k, v) => { self.attrs['data-' + String(k).replace(/[A-Z]/g, (m) => '-' + m.toLowerCase())] = String(v); return true; },
        });
    }
    get parentTarget() { return this.parentNode; }
    get id() { return this.attrs.id ?? ''; }
    set id(v) { this.attrs.id = String(v); }
    get className() { return this.attrs.class ?? ''; }
    set className(v) { this.attrs.class = String(v); }
    get hidden() { return 'hidden' in this.attrs; }
    set hidden(v) { v ? (this.attrs.hidden = '') : delete this.attrs.hidden; }
    get action() { return this.attrs.action ?? ''; }
    set action(v) { this.attrs.action = String(v); }
    get type() { return this.attrs.type ?? (this.tagName === 'BUTTON' ? 'submit' : ''); }
    get parentElement() { return this.parentNode?.nodeType === 1 ? this.parentNode : null; }
    get children() { return this.childNodes.filter((k) => k.nodeType === 1); }
    get firstElementChild() { return this.children[0] ?? null; }
    get textContent() { return this.childNodes.map((k) => k.textContent).join(''); }
    set textContent(v) { this.childNodes = [new Text(String(v))]; this.childNodes[0].parentNode = this; }
    get innerText() { return this.textContent; }
    getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
    setAttribute(n, v) { this.attrs[n] = String(v); }
    hasAttribute(n) { return n in this.attrs; }
    removeAttribute(n) { delete this.attrs[n]; }
    toggleAttribute(n, an) { const neu = an ?? !this.hasAttribute(n); neu ? (this.attrs[n] = '') : delete this.attrs[n]; return neu; }
    appendChild(k) { if (k.parentNode) k.parentNode.removeChild(k); k.parentNode = this; this.childNodes.push(k); return k; }
    append(...xs) { xs.forEach((x) => this.appendChild(typeof x === 'string' ? new Text(x) : x)); }
    removeChild(k) { this.childNodes = this.childNodes.filter((x) => x !== k); k.parentNode = null; return k; }
    remove() { this.parentNode?.removeChild(this); }
    contains(el) { for (let x = el; x; x = x.parentNode) if (x === this) return true; return false; }
    matches(s) { return s.split(',').some((t) => passt(this, t.trim())); }
    closest(s) { for (let x = this; x && x.nodeType === 1; x = x.parentNode) if (x.matches(s)) return x; return null; }
    nachfahren() { return this.children.flatMap((k) => [k, ...k.nachfahren()]); }
    querySelectorAll(s) { return suche([this], s); }
    querySelector(s) { return suche([this], s)[0] ?? null; }
    focus() {}
    blur() {}
    submit() { this.beiSubmit?.(); }
    requestSubmit() { if (this.dispatchEvent(new Ereignis('submit', { bubbles: true }))) this.beiSubmit?.(); }
}

function passt(el, einfach) {
    let rest = einfach;
    while (rest) {
        let m;
        if ((m = rest.match(/^\[([\w-]+)(?:([\^$*]?)="([^"]*)")?\]/))) {
            if (!el.hasAttribute(m[1])) return false;
            const ist = el.getAttribute(m[1]);
            if (m[3] !== undefined) {
                if (m[2] === '^' ? !ist.startsWith(m[3]) : m[2] === '$' ? !ist.endsWith(m[3]) : m[2] === '*' ? !ist.includes(m[3]) : ist !== m[3]) return false;
            }
        } else if ((m = rest.match(/^#([\w-]+)/))) {
            if (el.id !== m[1]) return false;
        } else if ((m = rest.match(/^\.([\w-]+)/))) {
            if (!el.classList.contains(m[1])) return false;
        } else if ((m = rest.match(/^\*/))) {
            // beliebiges Element
        } else if ((m = rest.match(/^([a-zA-Z][\w-]*)/))) {
            if (el.tagName !== m[1].toUpperCase()) return false;
        } else {
            throw new Error('Selektor nicht unterstützt: ' + einfach);
        }
        rest = rest.slice(m[0].length);
    }
    return true;
}

function suche(wurzeln, selektor) {
    const treffer = [];
    for (const teil of selektor.split(',')) {
        const teile = teil.trim().match(/>|(?:[^\s\[>]|\[[^\]]*\])+/g);
        let kandidaten = wurzeln;
        let kind = false;
        for (const t of teile) {
            if (t === '>') { kind = true; continue; }
            kandidaten = kandidaten.flatMap((w) => (kind ? w.children : w.nachfahren())).filter((e) => passt(e, t));
            kind = false;
        }
        kandidaten.forEach((e) => treffer.includes(e) || treffer.push(e));
    }
    return treffer;
}

// ---------- HTML lesen ----------
const LEER = new Set(['input', 'br', 'hr', 'img', 'meta', 'link', 'source']);
const entitaeten = (t) => t.replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

function lesen(html, wurzel) {
    const re = /<!--[\s\S]*?-->|<\/([a-zA-Z][\w-]*)\s*>|<([a-zA-Z][\w-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*(\/?)>|([^<]+)/g;
    let aktuell = wurzel;
    for (const m of html.matchAll(re)) {
        if (m[1]) {
            for (let x = aktuell; x && x !== wurzel; x = x.parentNode) {
                if (x.tagName === m[1].toUpperCase()) { aktuell = x.parentNode; break; }
            }
        } else if (m[2]) {
            const attrs = {};
            for (const a of m[3].matchAll(/([^\s=>\/]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g)) {
                attrs[a[1]] = entitaeten(a[2] ?? a[3] ?? a[4] ?? '');
            }
            const el = new El(m[2], attrs);
            aktuell.appendChild(el);
            if (!LEER.has(m[2].toLowerCase()) && !m[4]) aktuell = el;
        } else if (m[5] !== undefined && m[5].trim() !== '') {
            aktuell.appendChild(new Text(entitaeten(m[5])));
        }
    }
}

// ---------- Aufbau ----------
const ROUTE = 'http://host.test/user/delete/0';

const html = (t) => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

function rendern(quelle, werte, unbekannt) {
    const wert = (ausdruck) => {
        const m = ausdruck.trim().match(/^\$(titel|body|route)$/);
        if (!m) { unbekannt.push('Blade-Ausdruck ' + ausdruck.trim()); return ''; }
        return werte[m[1]];
    };
    const text = quelle
        .replace(/\{\{--[\s\S]*?--\}\}/g, '')
        .replace(/@csrf/g, '<input type="hidden" name="_token" value="test-token">')
        .replace(/@method\('(\w+)'\)/g, '<input type="hidden" name="_method" value="$1">')
        .replace(/@lang\('[^']*'\)/g, 'Text')
        .replace(/@json\(([^)]*)\)/g, (_, a) => JSON.stringify(wert(a)))
        .replace(/\{!!([\s\S]*?)!!\}/g, (_, a) => wert(a))
        .replace(/\{\{([\s\S]*?)\}\}/g, (_, a) => html(wert(a)));
    const uebrig = [...text.matchAll(/@[a-zA-Z]+\b|\{\{|\{!!/g)].map((m) => m[0]).filter((x) => x !== '@push' && x !== '@endpush');
    if (uebrig.length) unbekannt.push('Blade-Konstrukt ' + uebrig[0]);
    return text;
}

function bauen({ route = ROUTE, titel = 'Benutzer löschen', body = 'Wollen Sie wirklich diesen Benutzer löschen?' } = {}) {
    fehlerListe = [];
    const unbekannt = [];
    const gerendert = rendern(fs.readFileSync(partial, 'utf8'), { route, titel, body }, unbekannt);
    const stack = [...gerendert.matchAll(/@push\('userauth-scripts'\)([\s\S]*?)@endpush/g)].map((m) => m[1]).join('\n');
    const markup = gerendert.replace(/@push\('userauth-scripts'\)[\s\S]*?@endpush/g, '');

    const fenster = new Ziel();
    const html = new El('html');
    const seite = new El('body');
    html.appendChild(seite);
    const doc = Object.assign(new Ziel(), {
        nodeType: 9,
        readyState: 'loading',
        documentElement: html,
        body: seite,
        children: [html],
        nachfahren: () => [html, ...html.nachfahren()],
        querySelector: (s) => suche([doc], s)[0] ?? null,
        querySelectorAll: (s) => suche([doc], s),
        getElementById: (id) => html.nachfahren().find((e) => e.id === id) ?? null,
        createElement: (t) => new El(t),
        contains: (el) => html.contains(el),
    });
    Object.defineProperty(doc, 'parentTarget', { get: () => fenster });
    html.parentNode = doc;
    lesen(markup, seite);

    const sendungen = [];
    const formular = doc.querySelector('#formDelete');
    if (formular) formular.beiSubmit = () => sendungen.push(formular.action);

    const timer = [];
    const kontext = vm.createContext({
        console, URL, URLSearchParams, Event: Ereignis, CustomEvent: Ereignis, Element: El, HTMLElement: El, Node: El,
        document: doc,
        location: { href: 'http://host.test/user', origin: 'http://host.test' },
        setTimeout: (fn) => timer.push(fn),
        requestAnimationFrame: (fn) => timer.push(fn),
    });
    kontext.window = kontext;
    kontext.addEventListener = (t, f) => fenster.addEventListener(t, f);
    kontext.removeEventListener = (t, f) => fenster.removeEventListener(t, f);
    kontext.dispatchEvent = (ev) => fenster.dispatchEvent(ev);
    const zugriffe = [];
    for (const name of ['$', 'jQuery']) {
        Object.defineProperty(kontext, name, { configurable: true, get() { zugriffe.push(name); throw new ReferenceError(`${name} is not defined`); } });
    }

    const skripte = [...stack.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map((m) => m[1]).filter((c) => c.trim() !== '');
    for (const code of skripte) {
        try { vm.runInContext(code, kontext); } catch (e) { melde(e); }
    }
    const laufen = () => { while (timer.length) { try { timer.shift()(); } catch (e) { melde(e); } } };
    doc.dispatchEvent(new Ereignis('DOMContentLoaded', { bubbles: true }));
    doc.readyState = 'complete';
    fenster.dispatchEvent(new Ereignis('load'));
    laufen();

    const verstecktAm = (el) => { for (let x = el; x && x.nodeType === 1; x = x.parentNode) if (x.hidden || x.style.display === 'none' || x.classList.contains('d-none')) return true; return false; };
    const klick = (ziel) => {
        const ok = ziel.dispatchEvent(new Ereignis('click', { bubbles: true }));
        const knopf = ziel.closest('button');
        const form = knopf?.closest('form');
        if (ok && knopf && knopf.type === 'submit' && form) form.requestSubmit();
        laufen();
    };
    const overlay = doc.querySelector('.kit-dialog__overlay');
    const inhalt = doc.querySelector('.kit-dialog__content');
    const [abbrechen, loeschen] = inhalt ? inhalt.querySelectorAll('button.kit-button') : [];

    return {
        doc, seite, formular, overlay, inhalt, abbrechen, loeschen, sendungen, skripte, zugriffe, klick, unbekannt,
        sichtbar: () => !!overlay && !!inhalt && !verstecktAm(overlay) && !verstecktAm(inhalt),
        zu: () => !!overlay && !!inhalt && verstecktAm(overlay) && verstecktAm(inhalt),
        taste: (key) => { doc.dispatchEvent(new Ereignis('keydown', { bubbles: true, key, code: key, keyCode: key === 'Escape' ? 27 : 0 })); laufen(); },
        knopfMit: (id, kind = false) => {
            const k = new El('button', { type: 'button', 'data-kit-delete': id });
            const ziel = kind ? k.appendChild(new El('span')) : k;
            seite.appendChild(k);
            return ziel;
        },
    };
}

// Aufbau greift: Markup mit Dialog und Skript ist da, das Skript läuft ohne Fehler und ohne Host-Globals.
function pruefeAufbau(d) {
    assert.ok(d.overlay && d.inhalt, 'Aufbau: Dialog-Markup (.kit-dialog__overlay, .kit-dialog__content) ist da');
    assert.ok(d.abbrechen && d.loeschen, 'Aufbau: Knöpfe Abbrechen und Löschen im Dialog');
    assert.ok(d.formular, 'Aufbau: Formular #formDelete ist da');
    assert.ok(d.skripte.length >= 1, 'Aufbau: Inline-Skript im Stack userauth-scripts');
    assert.deepEqual(fehlerListe, [], 'Skript läuft ohne Fehler (kein Global des Hosts, keine Vue-Insel)');
    assert.deepEqual(d.zugriffe, [], 'kein Zugriff auf $/jQuery');
    assert.deepEqual(d.unbekannt, [], 'Partial nutzt nur die vom Test ersetzbaren Blade-Konstrukte');
    assert.ok(d.zu(), 'Dialog ist anfangs versteckt');
}

// AK8
test('AK8: Klick auf [data-kit-delete] zeigt den Dialog und Löschen setzt die Route mit der ID und sendet einmal', () => {
    const d = bauen();
    pruefeAufbau(d);
    const knopf = d.knopfMit('7');

    d.klick(knopf);
    assert.ok(d.sichtbar(), 'Dialog ist nach dem Klick sichtbar');
    assert.deepEqual(d.sendungen, [], 'noch nichts gesendet');

    d.klick(d.loeschen);
    assert.deepEqual(d.sendungen, ['http://host.test/user/delete/7'], 'einmal gesendet, ID als letztes Segment');
    assert.equal(d.formular.action, 'http://host.test/user/delete/7');
    assert.ok(d.zu(), 'Dialog ist nach dem Senden zu');
    assert.deepEqual(fehlerListe, []);
});

// AK8
function pruefeSchliessen(schliessen) {
    const d = bauen();
    pruefeAufbau(d);

    d.klick(d.knopfMit('7'));
    assert.ok(d.sichtbar(), 'Gegenprobe: der Dialog war offen');
    schliessen(d);

    assert.ok(d.zu(), 'Dialog ist zu');
    assert.deepEqual(d.sendungen, [], 'nichts gesendet');
    assert.deepEqual(fehlerListe, []);
}

// AK8
test('AK8: Abbrechen blendet den Dialog aus und sendet nicht', () => pruefeSchliessen((d) => d.klick(d.abbrechen)));

// AK8
test('AK8: Klick auf das Overlay blendet den Dialog aus und sendet nicht', () => pruefeSchliessen((d) => d.klick(d.overlay)));

// AK8
test('AK8: Escape blendet den Dialog aus und sendet nicht', () => pruefeSchliessen((d) => d.taste('Escape')));

// AK8
test('AK8: ein später eingefügter [data-kit-delete]-Button löst ebenfalls aus (Klick auf ein Kind des Buttons)', () => {
    const d = bauen();
    pruefeAufbau(d);

    d.klick(d.knopfMit('11', true));

    assert.ok(d.sichtbar());
    d.klick(d.loeschen);
    assert.deepEqual(d.sendungen, ['http://host.test/user/delete/11']);
});

// Randfall 3
test('Randfall 3: Query der Route bleibt und die ID wird URL-kodiert', () => {
    const d = bauen({ route: '/user/0?x=1' });
    pruefeAufbau(d);

    d.klick(d.knopfMit('7'));
    d.klick(d.loeschen);
    assert.deepEqual(d.sendungen, ['/user/7?x=1'], 'Query bleibt');

    d.klick(d.knopfMit('a/b c&d?#'));
    d.klick(d.loeschen);
    assert.deepEqual(d.sendungen.at(-1), '/user/a%2Fb%20c%26d%3F%23?x=1', 'Sonderzeichen kodiert');
    assert.equal(d.sendungen.length, 2);
});

// Randfall 4
test('Randfall 4: zwei Klicks hintereinander auf „Löschen“ senden einmal', () => {
    const d = bauen();
    pruefeAufbau(d);

    d.klick(d.knopfMit('7'));
    d.klick(d.loeschen);
    assert.equal(d.sendungen.length, 1, 'Gegenprobe: der erste Klick hat gesendet');
    d.klick(d.loeschen);

    assert.deepEqual(d.sendungen, ['http://host.test/user/delete/7']);
});
