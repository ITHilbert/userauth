// Hilfsprogramm für AdminViewsSkripteTest: führt die Skripte einer Admin-View aus, wie der Browser es bei einem
// Host mit Modul-Bundle tut. Zuerst laufen die Skripte ohne defer/async/module in Dokument-Reihenfolge ohne `DataTable`
// (das Bundle ist noch nicht ausgeführt). Dann läuft das "Bundle": `window.DataTable` (DataTables 2) steht als Konstruktor
// bereit, defer-/module-Skripte laufen,
// danach feuern DOMContentLoaded und load, Timer laufen ab. Skripte, die per createElement nachgeladen werden,
// laufen beim Anhängen (permission.js kommt aus dem Manifest statt per URL).
// Ohne jQuery (userauth-datatable-ohne-jquery): `$` und `jQuery` sind nicht definiert; jeder Zugriff wird in
// `jqueryZugriffe` gezählt und wirft einen ReferenceError. Mit dem zweiten Argument `mit-jquery` ist jQuery geladen
// (Randfall 1): Aufrufe landen in `jqueryAufrufe`.
// Modus `nachtraeglich` (#2267, AK6): Der Host fügt die Skripte erst nach DOMContentLoaded ein. `window.DataTable` steht
// bereit, `document.readyState` ist 'complete', alle Skripte laufen in Dokument-Reihenfolge, DOMContentLoaded wird
// nicht erneut gefeuert (nur Timer laufen ab).
// `aufrufeVorDomReady` zählt die DataTable-Aufrufe unmittelbar vor dem Feuern von DOMContentLoaded.
// Aufruf: node inline-skript-lauf.mjs <manifest.json> [mit-jquery] [nachtraeglich]
// Manifest: [{ "typ": "inline"|"src", "code": "<Quelltext>", "src": "<url>", "spaeter": bool }]
// Ausgabe: JSON {fehlerVorBundle, fehler, aufrufe, optionen, funktionen, jqueryZugriffe, jqueryAufrufe}.
import fs from 'node:fs';
import vm from 'node:vm';

const manifest = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const listener = [];
const timer = [];
const aufrufe = [];
const optionen = [];
const nachtraeglich = process.argv.slice(3).includes('nachtraeglich');
const mitJquery = process.argv.slice(3).includes('mit-jquery');
const jqueryZugriffe = [];
const jqueryAufrufe = [];
const ergebnis = { fehlerVorBundle: null, fehler: null, aufrufe, optionen, funktionen: [], jqueryZugriffe, jqueryAufrufe, jqueryGeladen: false, aufrufeVorDomReady: null };
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
    querySelector: () => beliebig(),
    querySelectorAll: () => [],
    getElementById: () => beliebig(),
};
const fenster = { addEventListener: (art, fn) => listener.push([art, fn]), document };
const kontext = vm.createContext({
    document,
    window: fenster,
    console,
    setTimeout: (fn) => timer.push(fn),
});
fenster.setTimeout = kontext.setTimeout;
if (!mitJquery) {
    for (const name of ['$', 'jQuery']) {
        Object.defineProperty(kontext, name, {
            configurable: true,
            get() {
                jqueryZugriffe.push(name);
                throw new ReferenceError(`${name} is not defined`);
            },
        });
    }
}

// Phase 1: Skripte, die der Browser sofort ausführt (nachträglich: erst nach dem Bundle).
if (!nachtraeglich) {
    for (const s of manifest) {
        if (!s.spaeter) ausfuehren(s.code);
    }
}

// Phase 2: das Modul-Bundle läuft, DataTables 2 steht als `window.DataTable` bereit.
bundleLaeuft = true;
function DataTable(selektor, opt) {
    aufrufe.push(selektor);
    optionen.push(opt);
}
kontext.DataTable = DataTable;
fenster.DataTable = DataTable;
if (mitJquery) {
    const jq = (arg) => {
        jqueryAufrufe.push(typeof arg === 'function' ? 'function' : arg);
        return beliebig();
    };
    kontext.$ = jq;
    kontext.jQuery = jq;
}
if (nachtraeglich) {
    document.readyState = 'complete';
    for (const s of manifest) ausfuehren(s.code);
    ergebnis.aufrufeVorDomReady = aufrufe.length;
    try {
        while (timer.length) timer.shift()();
    } catch (e) {
        beiFehler(e);
    }
} else {
document.readyState = 'interactive';
for (const s of manifest) {
    if (s.spaeter) ausfuehren(s.code);
}

try {
    ergebnis.aufrufeVorDomReady = aufrufe.length;
    for (const [art, fn] of [...listener]) if (art === 'DOMContentLoaded') fn({ type: art });
    document.readyState = 'complete';
    for (const [art, fn] of [...listener]) if (art === 'load') fn({ type: art });
    while (timer.length) timer.shift()();
} catch (e) {
    beiFehler(e);
}
}

ergebnis.jqueryGeladen = mitJquery && typeof kontext.$ === 'function' && typeof kontext.jQuery === 'function';
ergebnis.funktionen = ['showSinglePermissions', 'newPermission', 'deletePermission', 'editIntern'].filter((f) => typeof kontext[f] === 'function');
console.log(JSON.stringify(ergebnis));
