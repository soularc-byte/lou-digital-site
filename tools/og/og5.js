// Seitenbezogene Vorschaubilder (og:image, 1200x630) für lou-digital.de, Version 5
// v5: Limetten-Markierung liegt hinter der ganzen Überschrift (eigene Ebene), Website-Check mit größerem Handy und größerer Browser-Figur,
// Automatik mit 3D-Glocke (three.js, Blick von links oben, /home/claude/work/3d/bell.html) statt Brief.
// Geräte: echte Mockups aus Shots.so (iPhone 17 Black, MacBook Air 13), je Seite andere Ansicht:
// Blickpunkt = Bildmitte: alles rechts davon zeigt seine linke Seite (Geräte mit Perspektive, 3D-Figuren gespiegelt),
// Figuren nur unterhalb der Augenhöhe (man sieht ihre Oberseite). start = Laptop + Handy frontal,
// website-check = Handy leicht nach links geneigt + Browser-Figur, kontakt = Handy gerade als Nahaufnahme + Brief,
// pakete = Laptop frontal + Paket, automatik = Handy leicht nach rechts geneigt + Brief, websites = Laptop schräg.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const DIR = __dirname;
const SITE = '/home/claude/lou-digital-site';
const DEV = '/home/claude/work/shots';
const f = p => 'file://' + p;
const d = n => f(path.join(DEV, 'dev-' + n + '.png'));
const fig = n => f(path.join(SITE, 'img', 'fig-' + n + '.webp'));
const BELL = f('/home/claude/work/3d/out/fig-bell.png');

const pages = [
  { id: 'start', kicker: 'Für Unternehmen mit Anspruch', h: 'Websites und<br>Automatisierung<br><mark>aus Karlsruhe</mark>.', sub: 'Website in 7 Tagen, persönlich betreut. Vor Ort oder per Video.', cta: 'Jetzt ansehen', url: 'lou-digital.de',
    layers: [
      { src: d('start'), css: 'width:560px; left:592px; top:128px;', shadow: true },
      { src: d('start-handy'), css: 'height:330px; left:972px; top:262px;', fade: true },
    ] },
  { id: 'website-check', kicker: 'Kostenloser Website-Check', h: 'Wie gut ist Ihre <mark>Website</mark> wirklich?', sub: '100 Prüfpunkte in neun Bereichen. Ergebnis in etwa 15 Sekunden, ohne Anmeldung.', cta: 'Website prüfen', url: 'lou-digital.de/website-check', clip: true,
    layers: [ { src: d('website-check-pov'), css: 'height:640px; left:123px; top:-4px;' }, { src: fig('web'), css: 'width:300px; left:603px; top:318px;', fig: true, out: true } ] },
  { id: 'kontakt', kicker: 'Anfrage an Lou Digital', h: 'Antwort in der Regel<br>innerhalb von <mark>24&nbsp;Stunden</mark>.', sub: 'Sie bekommen sofort eine Bestätigung und von mir eine persönliche Antwort.', cta: 'Anfrage senden', url: 'lou-digital.de/kontakt', clip: true,
    layers: [ { src: d('kontakt-gerade'), css: 'height:780px; left:-5px; top:40px;' }, { src: fig('mail'), css: 'width:178px; left:640px; top:392px;', fig: true, out: true } ] },
  { id: 'pakete', kicker: 'Pakete und Preise', h: 'Vorher wissen,<br><mark>was&nbsp;es&nbsp;kostet</mark>.', sub: 'Feste Grundpreise, klar abgegrenzte Betreuung, Rabatte verrechnen sich automatisch.', cta: 'Pakete ansehen', url: 'lou-digital.de/pakete',
    layers: [ { src: d('pakete'), css: 'width:596px; left:590px; top:132px;', shadow: true }, { src: fig('box'), css: 'width:170px; left:1006px; top:392px;', fig: true } ] },
  { id: 'automatik', kicker: 'Anfrage-Automatik', h: 'Keine Anfrage geht <mark>mehr verloren</mark>.', sub: 'Formular, Tabelle und Handy arbeiten zusammen. Jede Anfrage kommt sofort bei Ihnen an.', cta: 'So funktioniert es', url: 'lou-digital.de/automatik',
    layers: [ { src: d('automatik-pov'), css: 'height:556px; left:730px; top:36px;' }, { src: BELL, css: 'width:225px; left:904px; top:372px;', front: true } ] },
  { id: 'websites', kicker: 'Webdesign aus Karlsruhe', h: 'Ihre Website<br>in <mark>7&nbsp;Tagen</mark>.', sub: 'Fester Preis, feste Lieferzeit. Ein Onepager oder bis zu fünf Seiten mit Ihren Inhalten.', cta: 'Mehr erfahren', url: 'lou-digital.de/websites',
    layers: [ { src: d('websites-schraeg'), css: 'width:604px; left:588px; top:124px;', shadow: true } ] },
];

const css = `
@font-face { font-family: 'IS'; src: url('${f(SITE + '/fonts/instrument-sans-latin-wght-normal.woff2')}') format('woff2'); font-weight: 100 900; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; background: #f5f6f3; font-family: 'IS', sans-serif; color: #11191b; -webkit-font-smoothing: antialiased; }
.wrap { position: relative; width: 1200px; height: 630px; }
.left { position: absolute; left: 64px; top: 52px; width: 560px; height: 526px; display: flex; flex-direction: column; z-index: 3; }
.logo { display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 24px; letter-spacing: -0.02em; }
.logo .sq { width: 40px; height: 40px; border-radius: 8px; background: #0b6e69; position: relative; box-shadow: 0 4px 10px rgba(11,110,105,.25); }
.logo .sq b { position: absolute; left: 11px; top: 7px; width: 6px; height: 24px; background: #fff; border-radius: 2px; }
.logo .sq i { position: absolute; left: 11px; top: 25px; width: 15px; height: 6px; background: #fff; border-radius: 2px; }
.logo .sq u { position: absolute; left: 27px; top: 24px; width: 8px; height: 8px; background: #d2f05a; border-radius: 50%; }
.kicker { margin-top: 54px; font-size: 21px; font-weight: 600; color: #0b6e69; letter-spacing: -0.005em; }
h1 { margin-top: 14px; font-size: 60px; line-height: 1.04; font-weight: 640; letter-spacing: -0.035em; }
h1 mark { background: #d2f05a; color: #11191b; padding: 0 .1em; border-radius: 8px; -webkit-box-decoration-break: clone; box-decoration-break: clone; }
.sub { margin-top: 20px; font-size: 22px; line-height: 1.38; color: #3d474a; max-width: 520px; }
.foot { margin-top: auto; display: flex; align-items: center; gap: 22px; }
.btn { background: #0b6e69; color: #fff; font-weight: 600; font-size: 21px; padding: 15px 28px; border-radius: 999px; box-shadow: 0 10px 22px rgba(11,110,105,.32), 0 2px 4px rgba(17,25,27,.12); }
.url { font-size: 19px; color: #3d474a; }
.panel { position: absolute; right: 40px; top: 40px; width: 500px; height: 550px; border-radius: 20px; background: #dcefeb; overflow: hidden;
  background-image: radial-gradient(#0b6e69 1.6px, transparent 1.7px); background-size: 22px 22px; background-position: 11px 11px; }
.panel::after { content: ''; position: absolute; inset: 0; border-radius: 20px; background: radial-gradient(120% 90% at 50% 45%, rgba(220,239,235,.72), rgba(220,239,235,.5)); z-index: 0; }
.panel .dev { z-index: 1; }
.dev { position: absolute; z-index: 2; display: block; }
.dev.shadow { filter: drop-shadow(0 26px 26px rgba(17,25,27,.20)) drop-shadow(0 6px 8px rgba(17,25,27,.10)); }
.dev.fig { transform: scaleX(-1); z-index: 3; }
.dev.front { z-index: 3; }
.hwrap { position: relative; margin-top: 14px; }
.hwrap h1 { margin-top: 0; }
.hwrap.small h1 { font-size: 54px; }
h1.hl { position: absolute; left: 0; top: 0; width: 100%; color: transparent; }
h1.hl mark { color: transparent; }
h1.tx { position: relative; z-index: 1; }
h1.tx mark { background: transparent; }
.panel .dev.fig { z-index: 2; }
.dev.fade { -webkit-mask-image: linear-gradient(to bottom, #000 88%, transparent 100%); mask-image: linear-gradient(to bottom, #000 88%, transparent 100%); }
`;

const layerHtml = l => `<img class="dev${l.shadow ? ' shadow' : ''}${l.fade ? ' fade' : ''}${l.fig ? ' fig' : ''}${l.front ? ' front' : ''}" style="${l.css}" src="${l.src}">`;

const html = p => `<!doctype html><html lang="de"><head><meta charset="utf-8"><style>${css}</style></head><body><div class="wrap">
  <div class="left">
    <div class="logo"><span class="sq"><b></b><i></i><u></u></span>Lou Digital</div>
    <div class="kicker">${p.kicker}</div>
    <div class="hwrap${p.id === 'kontakt' ? ' small' : ''}"><h1 class="hl" aria-hidden="true">${p.h}</h1><h1 class="tx">${p.h}</h1></div>
    <p class="sub">${p.sub}</p>
    <div class="foot"><span class="btn">${p.cta}</span><span class="url">${p.url}</span></div>
  </div>
  <div class="panel">${p.clip ? p.layers.filter(l => !l.out).map(layerHtml).join('') : ''}</div>
  ${(p.clip ? p.layers.filter(l => l.out) : p.layers).map(layerHtml).join('\n  ')}
  </div></body></html>`;

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  const only = process.argv[2];
  for (const p of pages) {
    if (only && p.id !== only) continue;
    const file = path.join(DIR, `og5-${p.id}.html`);
    fs.writeFileSync(file, html(p));
    await page.goto('file://' + file, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(DIR, `og5-${p.id}@2x.png`) });
    console.log('ok', p.id);
  }
  await browser.close();
})();
