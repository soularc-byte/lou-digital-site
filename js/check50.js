/* Lou Digital – 50-Punkte-Sofort-Check (27.10.2026). Spricht /api/check50.php an und zeigt das Ergebnis.
   Ohne Fremddienste, ohne Speicherung im Browser. */
(function () {
  'use strict';
  var f = document.getElementById('qcheck50');
  if (!f) return;
  var EN = (document.documentElement.lang || '').indexOf('en') === 0;
  var T = EN ? {
    run: 'Checking…', go: 'Check now', forHost: 'Result for ', of: ' of ', passed: ' checkpoints passed',
    tiers: ['Main points', 'Important', 'Further'], first: 'Fix these first', allGood: 'Very good: none of the main or important points are missing.',
    all: 'Show all 50 checkpoints', why: 'Why it matters', ok: 'passed', no: 'missing', na: 'not applicable',
    sheet: 'Request the detailed result sheet', talk: 'Discuss the result', again: 'Check another website',
    note: 'Automatic check of what any visitor can see. It does not replace legal advice. The result is not stored.',
    steps: ['Connection and certificate', 'Address and redirects', 'Legal texts and privacy', 'Mobile and display', 'Google and visibility', 'Speed', 'Contact', 'Domain email'],
    err: { url: 'Please enter an address like your-company.com.', dns: 'This address doesn’t seem to exist.', unreachable: 'The website did not respond. Is the address correct?', rate: 'Too many checks in a short time. Please try again in an hour.', blocked: 'This address cannot be checked.', server: 'The check is not available right now. Please send an inquiry instead.' },
    contact: '/en/contact', tierLabel: { 1: 'Main point', 2: 'Important', 3: 'Further point' }
  } : {
    run: 'Wird geprüft …', go: 'Jetzt prüfen', forHost: 'Ergebnis für ', of: ' von ', passed: ' Prüfpunkten bestanden',
    tiers: ['Hauptpunkte', 'Wichtige', 'Weitere'], first: 'Das sollten Sie zuerst angehen', allGood: 'Sehr gut: Bei den Haupt- und wichtigen Punkten fehlt nichts.',
    all: 'Alle 50 Prüfpunkte ansehen', why: 'Warum das wichtig ist', ok: 'bestanden', no: 'fehlt', na: 'nicht zutreffend',
    sheet: 'Ausführliches Ergebnisblatt anfordern', talk: 'Ergebnis besprechen', again: 'Andere Website prüfen',
    note: 'Automatische Prüfung dessen, was jeder Besucher sehen kann. Sie ersetzt keine Rechtsberatung. Das Ergebnis wird nicht gespeichert.',
    steps: ['Verbindung und Zertifikat', 'Adresse und Weiterleitungen', 'Rechtstexte und Datenschutz', 'Handy und Darstellung', 'Google und Sichtbarkeit', 'Geschwindigkeit', 'Kontakt', 'E-Mail der Domain'],
    err: { url: 'Bitte geben Sie eine Adresse wie ihre-firma.de ein.', dns: 'Diese Adresse scheint es nicht zu geben.', unreachable: 'Die Website hat nicht geantwortet. Stimmt die Adresse?', rate: 'Zu viele Checks in kurzer Zeit. Bitte versuchen Sie es in einer Stunde noch einmal.', blocked: 'Diese Adresse lässt sich nicht prüfen.', server: 'Der Check ist gerade nicht verfügbar. Senden Sie mir gern eine Anfrage.' },
    contact: '/kontakt', tierLabel: { 1: 'Hauptpunkt', 2: 'Wichtig', 3: 'Weiterer Punkt' }
  };
  var reUrl = /^(https?:\/\/)?[^\s\/.]+(\.[^\s\/.]+)+(\/\S*)?$/i;
  var inp = document.getElementById('qc50-url'), btn = document.getElementById('qc50-go'), out = document.getElementById('qc50-out'), err = document.getElementById('qc50-err'), status = document.getElementById('qc50-status');
  function say(t) { if (status) status.textContent = t; }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function showErr(code) { err.textContent = T.err[code] || T.err.server; err.hidden = false; }

  // Vorab-Adresse aus ?url= übernehmen (z. B. aus einem Link)
  try { var pre = new URLSearchParams(location.search).get('url'); if (pre && reUrl.test(pre)) inp.value = pre; } catch (e) { /* alt */ }

  var timer = null;
  function progress() {
    out.innerHTML = '';
    var box = el('div', 'c50-lauf'); box.setAttribute('role', 'status');
    var ul = el('ol', 'c50-schritte');
    T.steps.forEach(function (s) { ul.appendChild(el('li', '', s)); });
    box.appendChild(ul); out.appendChild(box);
    var i = 0, lis = ul.children;
    if (lis[0]) lis[0].className = 'an';
    timer = setInterval(function () { if (i < lis.length) lis[i].className = 'fertig'; i++; if (i < lis.length) lis[i].className = 'an'; }, reduce ? 200 : 650);
  }
  function stop() { if (timer) { clearInterval(timer); timer = null; } }

  function render(r, eingabe) {
    stop();
    out.innerHTML = '';
    var w = el('div', 'c50'); w.setAttribute('role', 'region'); w.setAttribute('aria-label', T.forHost + r.host);
    // Kopf mit Ring
    var kopf = el('div', 'c50-kopf');
    var ring = el('div', 'c50-ring'); ring.style.setProperty('--p', reduce ? r.punkte : 0);
    var zahl = el('b', '', reduce ? String(r.punkte) : '0'); ring.appendChild(zahl); ring.appendChild(el('span', '', '/100'));
    var kt = el('div', 'c50-kt');
    kt.appendChild(el('p', 'muted', T.forHost + r.host));
    kt.appendChild(el('h3', '', r.bestanden + T.of + r.geprueft + T.passed));
    var st = el('p', 'c50-stufen');
    [1, 2, 3].forEach(function (k, i) { var s = r.stufen[k] || { ok: 0, n: 0 }; var sp = el('span', '', T.tiers[i] + ' ' + s.ok + '/' + s.n); st.appendChild(sp); });
    kt.appendChild(st);
    kopf.appendChild(ring); kopf.appendChild(kt); w.appendChild(kopf);
    // Bereiche
    var gl = el('ul', 'c50-gruppen');
    r.gruppen.forEach(function (g) {
      var li = el('li'); var q = g.n ? g.ok / g.n : 1;
      li.className = q >= 0.85 ? 'gut' : q >= 0.6 ? 'mittel' : 'schwach';
      li.appendChild(el('span', 'c50-gn', g.titel));
      li.appendChild(el('span', 'c50-gz', g.n ? g.ok + '/' + g.n : '–'));
      var bar = el('i', 'c50-gb'); var fill = el('i'); fill.style.width = (g.n ? Math.round(q * 100) : 0) + '%'; bar.appendChild(fill); li.appendChild(bar);
      gl.appendChild(li);
    });
    w.appendChild(gl);
    // Prioritäten
    var fehlt = r.items.filter(function (it) { return it.ok === false; }).sort(function (a, b) { return a.stufe - b.stufe || a.nr - b.nr; });
    var pr = el('div', 'c50-prio');
    pr.appendChild(el('h4', '', T.first));
    var wichtigeFehlen = fehlt.filter(function (it) { return it.stufe < 3; }).length;
    if (!wichtigeFehlen) pr.appendChild(el('p', 'c50-lob', T.allGood));
    var ol = el('ol');
    fehlt.slice(0, 8).forEach(function (it) {
      var li = el('li', 's' + it.stufe);
      var h = el('div', 'c50-ph'); h.appendChild(el('span', 'c50-badge', T.tierLabel[it.stufe])); h.appendChild(el('strong', '', it.titel)); li.appendChild(h);
      li.appendChild(el('p', 'c50-detail', it.detail));
      if (it.warum) li.appendChild(el('p', 'c50-tipp', it.warum));
      ol.appendChild(li);
    });
    if (fehlt.length) pr.appendChild(ol);
    w.appendChild(pr);
    // Alle Punkte
    var det = el('details', 'c50-alle'); det.appendChild(el('summary', '', T.all));
    r.gruppen.forEach(function (g) {
      det.appendChild(el('h5', '', g.titel));
      var ul = el('ul', 'checks c50-liste');
      r.items.filter(function (it) { return it.gruppe === g.id; }).forEach(function (it) {
        var li = el('li', it.ok === true ? 'ok' : it.ok === false ? 'no' : 'na');
        var b = el('b', '', it.ok === true ? '✓' : it.ok === false ? '✕' : '–'); b.setAttribute('aria-hidden', 'true');
        var sp = el('span'); sp.appendChild(el('strong', '', it.titel)); sp.appendChild(el('small', '', it.detail));
        var em = el('em', '', it.ok === true ? T.ok : it.ok === false ? T.no : T.na);
        li.appendChild(b); li.appendChild(sp); li.appendChild(em); ul.appendChild(li);
      });
      det.appendChild(ul);
    });
    w.appendChild(det);
    // Aktionen
    var act = el('div', 'row-actions');
    var a1 = el('a', 'btn btn--primary', T.sheet); a1.href = T.contact + '?pakete=check&url=' + encodeURIComponent(eingabe);
    var a2 = el('a', 'btn btn--line', T.talk); a2.href = T.contact + '?pakete=termin&url=' + encodeURIComponent(eingabe);
    var a3 = el('button', 'link', T.again); a3.type = 'button';
    a3.addEventListener('click', function () { out.innerHTML = ''; inp.value = ''; say(''); try { f.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); } catch (e) { /* alt */ } inp.focus({ preventScroll: true }); });
    act.appendChild(a1); act.appendChild(a2); act.appendChild(a3); w.appendChild(act);
    w.appendChild(el('p', 'fine', T.note));
    out.appendChild(w);
    say(T.forHost + r.host + ': ' + r.punkte + '/100, ' + r.bestanden + T.of + r.geprueft + T.passed + '.');
    // Ring hochzählen
    if (!reduce) {
      var n = 0, ziel = r.punkte;
      var iv = setInterval(function () { n = Math.min(ziel, n + Math.max(1, Math.round(ziel / 30))); ring.style.setProperty('--p', n); zahl.textContent = String(n); if (n >= ziel) clearInterval(iv); }, 28);
    }
    try { w.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); } catch (e) { w.scrollIntoView(); }
  }

  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = inp.value.trim();
    if (!reUrl.test(v)) { showErr('url'); inp.setAttribute('aria-invalid', 'true'); inp.focus(); return; }
    inp.setAttribute('aria-invalid', 'false'); err.hidden = true;
    btn.disabled = true; btn.textContent = T.run; f.classList.add('busy');
    say(T.run);
    progress();
    var data = new FormData(); data.set('url', v); data.set('sprache', EN ? 'en' : 'de'); data.set('modus', 'sofort');
    fetch('/api/check50.php', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json().catch(function () { return { ok: false, error: 'server' }; }); })
      .then(function (r) {
        if (!r || !r.ok) { stop(); out.innerHTML = ''; showErr(r && r.error); return; }
        render(r, v);
      })
      .catch(function () { stop(); out.innerHTML = ''; showErr('server'); })
      .then(function () { btn.disabled = false; btn.textContent = T.go; f.classList.remove('busy'); });
  });
})();
