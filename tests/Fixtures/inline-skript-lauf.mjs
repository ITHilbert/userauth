// Hilfsprogramm für AdminViewsSkripteTest: führt die Skripte einer Admin-View aus, wie der Browser es bei einem
// Host mit Modul-Bundle tut. Zuerst laufen die Skripte ohne defer/async/module in Dokument-Reihenfolge ohne `$`/`jQuery`
// (das Bundle ist noch nicht ausgeführt). Dann läuft das "Bundle": `$` steht bereit, defer-/module-Skripte laufen,
// danach feuern DOMContentLoaded und load, Timer laufen ab. Skripte, die per createElement nachgeladen werden,
// laufen beim Anhängen (permission.js kommt aus dem Manifest statt per URL).
// Aufruf: node inline-skript-lauf.mjs <manifest.json>
// Manifest: [{ "typ": "inline"|"src", "code": "<Quelltext>", "src": "<url>", "spaeter": bool }]
// Ausgabe: JSON {fehlerVorBundle, fehler, aufrufe, optionen, funktionen}.
import fs from 'node:fs';
import vm from 'node:vm';

const manifest = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const listener = [];
const timer = [];
const aufrufe = [];
const optionen = [];
const ergebnis = { fehlerVorBundle: null, fehler: null, aufrufe, optionen, funktionen: [] };
let bundleLaeuft = false;

const beiFehler = (e) => {
    const text = `${e.name}: ${e.message}`;
    if (bundleLaeuft) ergebnis.fehler ??= text;
    else ergebnis.fehlerVorBundle ??= text;
};

const beliebig = () => new Proxy(function () {}, {
    get: (_, k) => (k === Symbol.toPrimitive ? () => '' : k === 'length' ? 0 : beliebig()),
    set: () => true,
    apply: () => beliebig(),
});

const codeFuer = (src) => manifest.find((s) => s.typ === 'src' && src && String(src).endsWith(s.src.split('/').pop()));

const ausfuehren = (code) => {
    try {
        vm.runInContext(code, kontext);
    } catch (e) {
        beiFehler(e);
    }
};

const anhaengen = (el) => {
    const treffer = codeFuer(el.src);
    if (treffer) {
        ausfuehren(treffer.code);
        if (typeof el.onload === 'function') el.onload({ type: 'load' });
    }
    return el;
};
const behaelter = () => ({ appendChild: anhaengen, append: anhaengen, insertBefore: anhaengen });

const document = {
    readyState: 'loading',
    currentScript: null,
    head: behaelter(),
    body: behaelter(),
    documentElement: behaelter(),
    addEventListener: (art, fn) => listener.push([art, fn]),
    createElement: () => ({ setAttribute(k, v) { this[k] = v; }, addEventListener(art, fn) { if (art === 'load') this.onload = fn; } }),
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
};
const fenster = { addEventListener: (art, fn) => listener.push([art, fn]), document };
const kontext = vm.createContext({
    document,
    window: fenster,
    console,
    setTimeout: (fn) => timer.push(fn),
});
fenster.setTimeout = kontext.setTimeout;

// Phase 1: Skripte, die der Browser sofort ausführt.
for (const s of manifest) {
    if (!s.spaeter) ausfuehren(s.code);
}

// Phase 2: das Modul-Bundle läuft, jQuery und DataTables stehen bereit.
bundleLaeuft = true;
const jq = (arg) => {
    if (typeof arg === 'function') {
        arg(jq);
        return jq;
    }
    aufrufe.push(arg);
    return new Proxy(beliebig(), {
        get: (ziel, k) => (k === 'DataTable' ? (opt) => { optionen.push(opt); return {}; } : ziel[k]),
    });
};
kontext.$ = jq;
kontext.jQuery = jq;
document.readyState = 'interactive';
for (const s of manifest) {
    if (s.spaeter) ausfuehren(s.code);
}

try {
    for (const [art, fn] of [...listener]) if (art === 'DOMContentLoaded') fn({ type: art });
    document.readyState = 'complete';
    for (const [art, fn] of [...listener]) if (art === 'load') fn({ type: art });
    while (timer.length) timer.shift()();
} catch (e) {
    beiFehler(e);
}

ergebnis.funktionen = ['showSinglePermissions', 'newPermission'].filter((f) => typeof kontext[f] === 'function');
console.log(JSON.stringify(ergebnis));
