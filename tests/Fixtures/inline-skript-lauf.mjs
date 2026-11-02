// Hilfsprogramm für AdminViewsSkripteTest: führt ein Inline-Skript einer Admin-View aus, wie es der Browser bei
// einem Host mit Modul-Bundle tut. Das Skript läuft zuerst ohne `$`/`jQuery` (Dokument lädt noch), danach stellt
// das "Bundle" `$` bereit, und erst dann feuern DOMContentLoaded/load (und anstehende Timer).
// Aufruf: node inline-skript-lauf.mjs <skriptdatei>; Ausgabe: JSON {fehler, aufrufe, optionen}.
import fs from 'node:fs';
import vm from 'node:vm';

const quelle = fs.readFileSync(process.argv[2], 'utf8');
const listener = { document: [], window: [] };
const timer = [];
const aufrufe = [];
const optionen = [];

const document = {
    readyState: 'loading',
    addEventListener: (art, fn) => listener.document.push([art, fn]),
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
};
const window = {
    addEventListener: (art, fn) => listener.window.push([art, fn]),
};
const kontext = vm.createContext({
    document,
    window,
    console,
    setTimeout: (fn) => timer.push(fn),
});
window.document = document;
kontext.window = Object.assign(window, { setTimeout: kontext.setTimeout });

const ergebnis = { fehler: null, fehlerVorBundle: null, aufrufe, optionen };

try {
    vm.runInContext(quelle, kontext);
} catch (e) {
    ergebnis.fehlerVorBundle = `${e.name}: ${e.message}`;
}

// Das Modul-Bundle läuft: jQuery und DataTables stehen bereit.
const jq = (arg) => {
    if (typeof arg === 'function') {
        arg(jq);
        return jq;
    }
    aufrufe.push(arg);
    return {
        DataTable: (opt) => {
            optionen.push(opt);
            return {};
        },
    };
};
kontext.$ = jq;
kontext.jQuery = jq;
document.readyState = 'interactive';

try {
    for (const [art, fn] of [...listener.document, ...listener.window]) {
        if (art === 'DOMContentLoaded') fn({ type: art });
    }
    document.readyState = 'complete';
    for (const [art, fn] of [...listener.document, ...listener.window]) {
        if (art === 'load') fn({ type: art });
    }
    while (timer.length) timer.shift()();
} catch (e) {
    ergebnis.fehler = `${e.name}: ${e.message}`;
}

console.log(JSON.stringify(ergebnis));
