// Echte Bildschirminhalte für Shots.so: iPhone 17 (402x874 pt, @3x) mit Statusleiste,
// MacBook Air 13 (1280x800 pt, @2x) mit schlichter Browserleiste. Quelle: lokale Lou-Website.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const OUT = path.join(__dirname, 'screens'); fs.mkdirSync(OUT, { recursive: true });
const BASE = 'http://127.0.0.1:8765';
const FONT = 'file:///home/claude/lou-digital-site/fonts/instrument-sans-latin-wght-normal.woff2';
const pages = [
  ['start', '/start.html'], ['website-check', '/website-check.html'], ['kontakt', '/kontakt.html'],
  ['pakete', '/pakete.html'], ['automatik', '/automatik.html'], ['websites', '/websites.html'],
];
// Desktop-Ausschnitt je Seite (Scrollposition in px), z. B. Paketkarten statt Einleitungstext
const DSCROLL = { pakete: 380 };
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
const URLS = { start: 'lou-digital.de', 'website-check': 'lou-digital.de/website-check', kontakt: 'lou-digital.de/kontakt', pakete: 'lou-digital.de/pakete', automatik: 'lou-digital.de/automatik', websites: 'lou-digital.de/websites' };

async function shoot(browser, url, w, h, dsf, file, scrollY = 0) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: dsf, reducedMotion: 'reduce', locale: 'de-DE' });
  const pg = await ctx.newPage();
  await pg.route('**/*', r => (r.request().url().startsWith(BASE) ? r.continue() : r.abort()));
  await pg.goto(BASE + url, { waitUntil: 'networkidle' });
  await pg.addStyleTag({ content: '.fb, .fb-tab, .fb-panel { display: none !important; }' });
  if (scrollY) { await pg.evaluate(v => window.scrollTo(0, v), scrollY); }
  await pg.waitForTimeout(2500);
  await pg.screenshot({ path: file });
  await ctx.close();
}

(async () => {
  const browser = await chromium.launch();
  for (const [id, url] of pages) {
    if (ONLY && !ONLY.includes(id)) continue;
    // Handy: Inhalt 402x812 + Statusleiste 62
    const mInner = path.join(OUT, `_m-${id}.png`);
    await shoot(browser, url, 402, 812, 3, mInner);
    // Laptop: Inhalt 1280x748 + Browserleiste 52
    const dInner = path.join(OUT, `_d-${id}.png`);
    await shoot(browser, url, 1280, 748, 2, dInner, DSCROLL[id] || 0);

    const ctx = await browser.newContext({ viewport: { width: 402, height: 874 }, deviceScaleFactor: 3 });
    const pg = await ctx.newPage();
    const mHtml = path.join(OUT, `_m-${id}.html`);
    fs.writeFileSync(mHtml, `<!doctype html><html><head><style>
      @font-face{font-family:IS;src:url('${FONT}') format('woff2');font-weight:100 900}
      *{margin:0;padding:0;box-sizing:border-box} body{width:402px;height:874px;background:#f5f6f3;font-family:IS,sans-serif;overflow:hidden}
      .sb{height:62px;display:flex;align-items:center;justify-content:space-between;padding:6px 30px 0 44px;color:#11191b}
      .t{font-weight:600;font-size:17px;letter-spacing:-.01em}
      .ic{display:flex;gap:7px;align-items:center}
      img{display:block;width:402px;height:812px}
    </style></head><body>
      <div class="sb"><span class="t">9:41</span><span class="ic">
        <svg width="18" height="12" viewBox="0 0 18 12"><rect x="0" y="8" width="3" height="4" rx="1" fill="#11191b"/><rect x="5" y="5.5" width="3" height="6.5" rx="1" fill="#11191b"/><rect x="10" y="3" width="3" height="9" rx="1" fill="#11191b"/><rect x="15" y="0" width="3" height="12" rx="1" fill="#11191b"/></svg>
        <svg width="16" height="12" viewBox="0 0 16 12"><path d="M8 11.5 5.6 9.1a3.4 3.4 0 0 1 4.8 0z M2.9 6.4a7.2 7.2 0 0 1 10.2 0l-1.3 1.3a5.4 5.4 0 0 0-7.6 0z M.3 3.8a10.9 10.9 0 0 1 15.4 0l-1.3 1.3a9.1 9.1 0 0 0-12.8 0z" fill="#11191b"/></svg>
        <svg width="27" height="13" viewBox="0 0 27 13"><rect x=".5" y=".5" width="22" height="12" rx="3.5" fill="none" stroke="#11191b" opacity=".4"/><rect x="2" y="2" width="19" height="9" rx="2" fill="#11191b"/><rect x="24" y="4.5" width="2" height="4" rx="1" fill="#11191b" opacity=".4"/></svg>
      </span></div>
      <img src="file://${mInner}">
    </body></html>`);
    await pg.goto('file://' + mHtml, { waitUntil: 'load' });
    await pg.evaluate(() => document.fonts.ready);
    await pg.screenshot({ path: path.join(OUT, `m-${id}.png`) });
    await ctx.close();

    const ctx2 = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 });
    const pg2 = await ctx2.newPage();
    const dHtml = path.join(OUT, `_d-${id}.html`);
    fs.writeFileSync(dHtml, `<!doctype html><html><head><style>
      @font-face{font-family:IS;src:url('${FONT}') format('woff2');font-weight:100 900}
      *{margin:0;padding:0;box-sizing:border-box} body{width:1280px;height:800px;background:#fff;font-family:IS,-apple-system,sans-serif;overflow:hidden}
      .tb{height:52px;background:#ececee;border-bottom:1px solid #d9d9dc;display:flex;align-items:center;padding:0 16px;position:relative}
      .tl{display:flex;gap:8px} .tl i{width:12px;height:12px;border-radius:50%;display:block}
      .url{position:absolute;left:50%;transform:translateX(-50%);width:560px;height:32px;background:#dfdfe2;border-radius:9px;display:flex;align-items:center;justify-content:center;gap:7px;font-size:14px;color:#2b2f31}
      img{display:block;width:1280px;height:748px}
    </style></head><body>
      <div class="tb"><span class="tl"><i style="background:#ff5f57"></i><i style="background:#febc2e"></i><i style="background:#28c840"></i></span>
      <span class="url"><svg width="10" height="12" viewBox="0 0 10 12"><rect x=".5" y="5" width="9" height="6.5" rx="1.5" fill="#6b7073"/><path d="M2.5 5V3.5a2.5 2.5 0 0 1 5 0V5" fill="none" stroke="#6b7073" stroke-width="1.2"/></svg>${URLS[id]}</span></div>
      <img src="file://${dInner}">
    </body></html>`);
    await pg2.goto('file://' + dHtml, { waitUntil: 'load' });
    await pg2.evaluate(() => document.fonts.ready);
    await pg2.screenshot({ path: path.join(OUT, `d-${id}.png`) });
    await ctx2.close();
    console.log('ok', id);
  }
  await browser.close();
})();
