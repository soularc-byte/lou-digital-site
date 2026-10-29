/* Lou Digital – 100-Punkte-Website-Check (27.10.2026). Spricht /api/check100.php an und zeigt das Ergebnis.
   Darunter: Ergebnisblatt anfordern (sendet an /api/anfrage.php, nur wenn das Formular abgeschickt wird).
   Ohne Fremddienste, ohne Speicherung im Browser.
   Version 4 (28.10.2026): gezählt wird immer von 100 (bestanden, fehlt, trifft nicht zu, nicht prüfbar),
   der Ablauf zeigt die neun Bereiche nacheinander mit ihren echten Prüfpunkten (zusammen etwa 15 Sekunden). */
(function () {
  'use strict';
  // Info-Knöpfe im Prüfkatalog und im Beispiel auf der Seite (ohne Skript bleiben die Texte sichtbar, siehe <noscript>)
  document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('.c100-info[data-static]') : null;
    if (!b) return;
    var p = document.getElementById(b.getAttribute('aria-controls'));
    if (!p) return;
    var auf = b.getAttribute('aria-expanded') !== 'true';
    b.setAttribute('aria-expanded', String(auf)); p.hidden = !auf;
  });
  var f = document.getElementById('qcheck100');
  if (!f) return;
  var EN = (document.documentElement.lang || '').indexOf('en') === 0;
  var T = EN ? {
    run: 'Checking…', go: 'Check now', forHost: 'Result for ', of: ' of ', passed: ' checkpoints passed', of100: ' of 100 checkpoints passed',
    tier: { 1: 'Critical', 2: 'Important', 3: 'Recommended' },
    st: { ok: 'passed', fehlt: 'missing', na: 'not applicable', np: 'could not be checked' },
    zahlen: { ok: function (n) { return n + ' passed'; }, fehlt: function (n) { return n + ' missing'; }, na: function (n) { return n + ' not applicable'; }, np: function (n) { return n + ' could not be checked'; } },
    von: function (a, b) { return a + ' of ' + b; }, wert: 'Overall score', wertAria: function (n) { return 'Overall score ' + n + ' percent'; },
    weights: 'The overall score weights the checked points by importance, critical five times, important twice, recommended once. Points that do not apply or could not be checked are not counted in it.',
    gruppeAria: function (g) { return g.titel + ', ' + g.ok + ' of ' + g.gesamt + ' passed' + (g.fehlt ? ', ' + g.fehlt + ' missing' : '') + (g.na ? ', ' + g.na + ' not applicable' : '') + (g.np ? ', ' + g.np + ' could not be checked' : ''); },
    lauf: { head: function (h) { return 'Checking ' + h; }, count: function (n) { return 'Checkpoint ' + n + ' of 100'; }, done: '100 of 100 checkpoints checked', wait: 'Putting the result together …',
      pts: function (n) { return n + ' points'; }, checked: function (n) { return n + ' points checked'; }, res: function (g) { return g.ok + ' of ' + g.gesamt + ' passed'; },
      start: 'The check is running and takes about 15 seconds.', aria: 'Progress of the check' },
    first: 'Fix these first', allGood: 'Very good. None of the critical or important points are missing.',
    more: function (n) { return '… and ' + n + ' more missing ' + (n === 1 ? 'point' : 'points') + ' in the full list below.'; },
    help: 'How I can help', helpP: 'Each missing point belongs to one of my services. Ask me for a fixed price.',
    pts: function (n) { return n === 1 ? '1 point' : n + ' points'; },
    all: 'Show all 100 checkpoints', ok: 'passed', no: 'missing', na: 'not applicable',
    info: 'How the check works', how: 'How the check works', why: 'Why it matters', todo: 'What to do', fix: '',
    infoFor: 'Explanation for ',
    talk: 'Discuss the result', again: 'Check another website',
    note: 'Automatic check of what any visitor can see. It does not replace legal advice. The result is only sent to me if you submit the form below.',
    steps: ['Security', 'Legal and privacy', 'Address and reachability', 'Mobile and display', 'Google and visibility', 'Speed', 'Contact and trust', 'Integrations and interfaces', 'Domain email'],
    err: { url: 'Please enter an address like your-company.com.', dns: 'This address doesn’t seem to exist.', unreachable: 'The website did not respond. Is the address correct?', rate: 'Too many checks in a short time. Please try again in an hour.', blocked: 'This address cannot be checked.', server: 'The check is not available right now. Please send an inquiry instead.' },
    contact: '/en/contact', privacy: '/en/privacy',
    lead: {
      h: 'Request the result sheet for free',
      p: 'I also check your website personally on phone and computer and send you a result sheet with the order of steps and the effort for each point. Free and without obligation.',
      name: 'Your name', firma: 'Company', opt: '(optional)', email: 'Your email', tel: 'Phone', msg: 'Your message',
      msgVal: function (h) { return 'Please send me the detailed result sheet for ' + h + '.'; },
      ph: 'name@your-company.com',
      consent: 'I have read the <a href="/en/privacy">privacy policy</a> and agree that Lou Digital stores my details and this check result to process my inquiry.',
      sumNote: 'Sent with it are your address, the score and the missing points.',
      send: 'Request result sheet', sending: 'Sending…',
      eName: 'Please enter your name.', eMail: 'Please check the email address.', eTel: 'Please check the phone number.', eMsg: 'Please write briefly what it is about (at least 10 characters).',
      eOk: 'Please accept the privacy policy. Without your consent I cannot store or send your inquiry.',
      check: 'Please check the highlighted fields.',
      fail: 'The message could not be sent. Please try again in a few minutes or write to <a href="mailto:info@lou-digital.de">info@lou-digital.de</a>.',
      okH: 'Thank you, your request has arrived.', okP: 'You will receive a confirmation by email in a moment. I will usually send the result sheet within one working day.'
    },
    sum: {
      head: function (r, z) { return 'Website check for ' + r.host + '. ' + z.ok + ' of 100 checkpoints passed, ' + z.fehlt + ' missing, ' + z.na + ' not applicable' + (z.np ? ', ' + z.np + ' could not be checked' : '') + '. Overall score ' + r.punkte + ' percent.'; },
      q: function (x) { return '“' + x + '”'; }, k: 'Missing critical points include ', w: 'Missing important points include ', e: function (n) { return n === 1 ? 'One recommended point is also missing.' : n + ' recommended points are also missing.'; }, none: 'No critical or important points are missing.'
    }
  } : {
    run: 'Wird geprüft …', go: 'Jetzt prüfen', forHost: 'Ergebnis für ', of: ' von ', passed: ' Prüfpunkten bestanden', of100: ' von 100 Prüfpunkten bestanden',
    tier: { 1: 'Kritisch', 2: 'Wichtig', 3: 'Empfohlen' },
    st: { ok: 'bestanden', fehlt: 'fehlt', na: 'trifft nicht zu', np: 'nicht prüfbar' },
    zahlen: { ok: function (n) { return n + ' bestanden'; }, fehlt: function (n) { return n === 1 ? '1 fehlt' : n + ' fehlen'; }, na: function (n) { return n === 1 ? '1 trifft nicht zu' : n + ' treffen nicht zu'; }, np: function (n) { return n + ' nicht prüfbar'; } },
    von: function (a, b) { return a + ' von ' + b; }, wert: 'Gesamtwert', wertAria: function (n) { return 'Gesamtwert ' + n + ' Prozent'; },
    weights: 'Der Gesamtwert gewichtet die geprüften Punkte nach Bedeutung, kritische fünffach, wichtige doppelt, empfohlene einfach. Was nicht zutrifft oder nicht prüfbar war, zählt dabei nicht mit.',
    gruppeAria: function (g) { return g.titel + ', ' + g.ok + ' von ' + g.gesamt + ' bestanden' + (g.fehlt ? ', ' + (g.fehlt === 1 ? '1 fehlt' : g.fehlt + ' fehlen') : '') + (g.na ? ', ' + (g.na === 1 ? '1 trifft nicht zu' : g.na + ' treffen nicht zu') : '') + (g.np ? ', ' + g.np + ' nicht prüfbar' : ''); },
    lauf: { head: function (h) { return 'Prüfe ' + h; }, count: function (n) { return 'Prüfpunkt ' + n + ' von 100'; }, done: '100 von 100 Prüfpunkten geprüft', wait: 'Ergebnis wird zusammengestellt …',
      pts: function (n) { return n + ' Punkte'; }, checked: function (n) { return n + ' Punkte geprüft'; }, res: function (g) { return g.ok + ' von ' + g.gesamt + ' bestanden'; },
      start: 'Der Check läuft und dauert etwa 15 Sekunden.', aria: 'Fortschritt des Checks' },
    first: 'Das sollten Sie zuerst angehen', allGood: 'Sehr gut. Bei den kritischen und wichtigen Punkten fehlt nichts.',
    more: function (n) { return '… und ' + n + ' weitere' + (n === 1 ? 'r fehlender Punkt' : ' fehlende Punkte') + ' in der vollständigen Liste unten.'; },
    help: 'So kann ich helfen', helpP: 'Jeder fehlende Punkt gehört zu einer meiner Leistungen. Fragen Sie mich nach einem Festpreis.',
    pts: function (n) { return n === 1 ? '1 Punkt' : n + ' Punkte'; },
    all: 'Alle 100 Prüfpunkte ansehen', ok: 'bestanden', no: 'fehlt', na: 'trifft nicht zu',
    info: 'So prüft der Check', how: 'So prüft der Check', why: 'Warum es wichtig ist', todo: 'Was zu tun ist', fix: '',
    infoFor: 'Erklärung zu ',
    talk: 'Ergebnis besprechen', again: 'Andere Website prüfen',
    note: 'Automatische Prüfung dessen, was jeder Besucher sehen kann. Sie ersetzt keine Rechtsberatung. Das Ergebnis wird nur an mich übertragen, wenn Sie das Formular unten absenden.',
    steps: ['Sicherheit', 'Recht und Datenschutz', 'Adresse und Erreichbarkeit', 'Handy und Darstellung', 'Google und Sichtbarkeit', 'Geschwindigkeit', 'Kontakt und Vertrauen', 'Anbindungen und Schnittstellen', 'E-Mail der Domain'],
    err: { url: 'Bitte geben Sie eine Adresse wie ihre-firma.de ein.', dns: 'Diese Adresse scheint es nicht zu geben.', unreachable: 'Die Website hat nicht geantwortet. Stimmt die Adresse?', rate: 'Zu viele Checks in kurzer Zeit. Bitte versuchen Sie es in einer Stunde noch einmal.', blocked: 'Diese Adresse lässt sich nicht prüfen.', server: 'Der Check ist gerade nicht verfügbar. Senden Sie mir gern eine Anfrage.' },
    contact: '/kontakt', privacy: '/datenschutz',
    lead: {
      h: 'Ergebnisblatt kostenlos anfordern',
      p: 'Ich sehe mir Ihre Website zusätzlich persönlich auf Handy und Computer an und schicke Ihnen ein Ergebnisblatt mit Reihenfolge und Aufwand je Punkt. Kostenlos und unverbindlich.',
      name: 'Ihr Name', firma: 'Firma', opt: '(optional)', email: 'Ihre E-Mail', tel: 'Telefon', msg: 'Ihre Nachricht',
      msgVal: function (h) { return 'Bitte schicken Sie mir das ausführliche Ergebnisblatt für ' + h + '.'; },
      ph: 'name@ihre-firma.de',
      consent: 'Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen und bin einverstanden, dass Lou Digital meine Angaben und dieses Prüfergebnis zur Bearbeitung meiner Anfrage speichert.',
      sumNote: 'Mitgeschickt werden Ihre Adresse, der Wert und die fehlenden Punkte.',
      send: 'Ergebnisblatt anfordern', sending: 'Wird gesendet …',
      eName: 'Bitte geben Sie Ihren Namen ein.', eMail: 'Bitte prüfen Sie die E-Mail-Adresse.', eTel: 'Bitte prüfen Sie die Telefonnummer.', eMsg: 'Bitte schreiben Sie kurz, worum es geht (mindestens 10 Zeichen).',
      eOk: 'Bitte akzeptieren Sie die Datenschutzerklärung. Ohne Ihre Zustimmung kann ich die Anfrage nicht speichern und nicht absenden.',
      check: 'Bitte prüfen Sie die markierten Felder.',
      fail: 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es in ein paar Minuten noch einmal oder schreiben Sie an <a href="mailto:info@lou-digital.de">info@lou-digital.de</a>.',
      okH: 'Danke, Ihre Anfrage ist angekommen.', okP: 'Sie erhalten gleich eine Bestätigung per E-Mail. Das Ergebnisblatt schicke ich Ihnen in der Regel innerhalb eines Werktags.'
    },
    sum: {
      head: function (r, z) { return 'Website-Check für ' + r.host + '. ' + z.ok + ' von 100 Prüfpunkten bestanden, ' + (z.fehlt === 1 ? '1 fehlt' : z.fehlt + ' fehlen') + ', ' + (z.na === 1 ? '1 trifft nicht zu' : z.na + ' treffen nicht zu') + (z.np ? ', ' + z.np + ' nicht prüfbar' : '') + '. Gesamtwert ' + r.punkte + ' Prozent.'; },
      q: function (x) { return '„' + x + '“'; }, k: 'Es fehlen kritische Punkte wie ', w: 'Es fehlen wichtige Punkte wie ', e: function (n) { return n === 1 ? 'Dazu fehlt ein empfohlener Punkt.' : 'Dazu fehlen ' + n + ' empfohlene Punkte.'; }, none: 'Bei kritischen und wichtigen Punkten fehlt nichts.'
    }
  };
  var reUrl = /^(https?:\/\/)?[^\s\/.]+(\.[^\s\/.]+)+(\/\S*)?$/i;
  var reMail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/, reTel = /^[+\d][\d\s\/()-]{5,24}$/;
  var inp = document.getElementById('qc100-url'), btn = document.getElementById('qc100-go'), out = document.getElementById('qc100-out'), err = document.getElementById('qc100-err'), status = document.getElementById('qc100-status');
  function say(t) { if (status) status.textContent = t; }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function showErr(code) { err.textContent = T.err[code] || T.err.server; err.hidden = false; }
  function go(node) { try { node.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); } catch (e) { node.scrollIntoView(); } }

  // Vorab-Adresse aus ?url= übernehmen (z. B. aus einem Link)
  try { var pre = new URLSearchParams(location.search).get('url'); if (pre && reUrl.test(pre)) inp.value = pre; } catch (e) { /* alt */ }

  // ---------- Ablauf: die neun Bereiche nacheinander, einfache schnell, aufwendige länger (zusammen etwa 15 Sekunden)
  var N_BER = [15, 11, 10, 9, 20, 10, 8, 11, 6];
  var DAUER = [2200, 2000, 1800, 1700, 2100, 2000, 1100, 1300, 1700];
  var LAUF = null;
  function katalogTitel() {
    var o = [];
    document.querySelectorAll('.c100-katalog details').forEach(function (d) { o.push([].map.call(d.querySelectorAll('ol > li > b'), function (b) { return b.textContent.trim(); })); });
    return o;
  }
  function hostAus(v) { return String(v).trim().replace(/^https?:\/\//i, '').split(/[\/?#]/)[0].toLowerCase(); }
  // Ergebnis vereinheitlichen: Status je Punkt, Zahlen je Bereich und Stufe immer aus den 100 Punkten
  function statusVon(it) { return it.status === 'ok' || it.status === 'fehlt' || it.status === 'na' || it.status === 'np' ? it.status : (it.ok === true ? 'ok' : it.ok === false ? 'fehlt' : 'na'); }
  function leer(id, titel) { return { id: id, titel: titel, ok: 0, n: 0, gesamt: 0, fehlt: 0, na: 0, np: 0 }; }
  function norm(r) {
    var z = { ok: 0, fehlt: 0, na: 0, np: 0 }, gr = {}, folge = [];
    (r.gruppen || []).forEach(function (g) { gr[g.id] = leer(g.id, g.titel); folge.push(g.id); });
    var stu = { 1: leer(1, ''), 2: leer(2, ''), 3: leer(3, '') };
    r.items.forEach(function (it) {
      it.status = statusVon(it);
      z[it.status]++;
      if (!gr[it.gruppe]) { gr[it.gruppe] = leer(it.gruppe, it.gruppe); folge.push(it.gruppe); }
      [gr[it.gruppe], stu[it.stufe]].forEach(function (x) { if (!x) return; x.gesamt++; x[it.status]++; if (it.status === 'ok' || it.status === 'fehlt') x.n++; });
    });
    r.gruppen = folge.map(function (id) { return gr[id]; });
    r.stufen = stu; r.z = z;
    return r;
  }
  function guete(g) { var q = g.n ? g.ok / g.n : 1; return q >= 0.85 ? 'gut' : q >= 0.6 ? 'mittel' : 'schwach'; }
  function lauf(host) {
    stopLauf();
    out.innerHTML = '';
    var titel = katalogTitel();
    var box = el('div', 'c50-lauf c100-lauf');
    var kopf = el('div', 'c100-lk');
    kopf.appendChild(el('p', 'c100-lh', T.lauf.head(host)));
    var zz = el('p', 'c100-lz', T.lauf.count(0)); kopf.appendChild(zz);
    var bar = el('div', 'c100-lb'), fill = el('i'); bar.appendChild(fill);
    bar.setAttribute('role', 'progressbar'); bar.setAttribute('aria-label', T.lauf.aria);
    bar.setAttribute('aria-valuemin', '0'); bar.setAttribute('aria-valuemax', '100'); bar.setAttribute('aria-valuenow', '0');
    kopf.appendChild(bar); box.appendChild(kopf);
    var ol = el('ol', 'c100-lg');
    var zeilen = T.steps.map(function (name, b) {
      var li = el('li'); li.setAttribute('data-s', 'offen');
      var ic = el('span', 'c100-li'); ic.setAttribute('aria-hidden', 'true'); li.appendChild(ic);
      li.appendChild(el('span', 'c100-ln', name));
      var st = el('span', 'c100-ls', T.lauf.pts(N_BER[b])); li.appendChild(st);
      var jetzt = el('small', 'c100-lt'); li.appendChild(jetzt);
      // Version 28: eigener Strich je Bereich, füllt sich mit jedem Prüfpunkt (abbremsend)
      var lp = el('span', 'c100-lp'), lpi = el('i'); lp.setAttribute('aria-hidden', 'true'); lp.appendChild(lpi); li.appendChild(lp);
      ol.appendChild(li);
      return { li: li, st: st, jetzt: jetzt, lp: lpi, fertig: false };
    });
    box.appendChild(ol); out.appendChild(box);
    // Plan: je Bereich eine Dauer mit etwas Zufall, darin die Punkte in ungleichen Abständen
    var plan = [];
    N_BER.forEach(function (n, b) {
      // Version 28: in jedem Bereich erst schnell, dann langsamer (abbremsend), mit etwas Zufall
      var d = DAUER[b] * (0.9 + Math.random() * 0.2), w = [], s = 0, k;
      for (k = 0; k < n; k++) { var q = n > 1 ? k / (n - 1) : 1, x = (0.45 + 1.3 * Math.pow(q, 1.35)) * (0.8 + Math.random() * 0.4); w.push(x); s += x; }
      for (k = 0; k < n; k++) plan.push({ b: b, k: k, ms: d * w[k] / s });
    });
    var L = { r: null, cb: null, n: 0, i: 0, b: -1, timer: null, fertig: false, stop: false };
    function gruppe(b) { if (!L.r) return null; var g = L.r.gruppen.filter(function (x) { return x.titel === T.steps[b]; })[0]; return g || L.r.gruppen[b] || null; }
    function zeige(b) {
      var z = zeilen[b], g = gruppe(b);
      z.st.textContent = g ? T.lauf.res(g) : T.lauf.checked(N_BER[b]);
      if (g) z.li.className = guete(g);
    }
    function bereichFertig(b) {
      var z = zeilen[b]; if (!z || z.fertig) return;
      z.fertig = true; z.li.setAttribute('data-s', 'fertig'); z.jetzt.textContent = ''; z.lp.style.width = '100%';
      zeige(b);
    }
    function weiter() {
      if (L.fertig && L.r && L.cb) { var cb = L.cb; L.cb = null; zz.textContent = T.lauf.done; setTimeout(function () { if (!L.stop) cb(L.r); }, reduce ? 0 : 450); }
    }
    function schritt() {
      if (L.stop) return;
      var p = plan[L.i];
      if (!p) { bereichFertig(L.b); L.fertig = true; zz.textContent = L.r ? T.lauf.done : T.lauf.wait; if (!L.r) say(T.lauf.wait); weiter(); return; }
      if (p.b !== L.b) { if (L.b >= 0) bereichFertig(L.b); L.b = p.b; zeilen[p.b].li.setAttribute('data-s', 'an'); }
      L.n++; L.i++;
      zz.textContent = T.lauf.count(L.n);
      fill.style.width = L.n + '%'; bar.setAttribute('aria-valuenow', String(L.n));
      zeilen[p.b].jetzt.textContent = (titel[p.b] && titel[p.b][p.k]) || '';
      zeilen[p.b].lp.style.width = ((p.k + 1) / N_BER[p.b] * 100).toFixed(1) + '%';
      L.timer = setTimeout(schritt, p.ms);
    }
    // Ergebnis ist da: fertige Bereiche zeigen sofort ihre Zahlen, das Ergebnis erscheint nach dem letzten Bereich
    L.ergebnis = function (r, cb) { L.r = r; L.cb = cb; zeilen.forEach(function (z, b) { if (z.fertig) zeige(b); }); weiter(); };
    LAUF = L;
    say(T.lauf.start);
    L.timer = setTimeout(schritt, 300);
    return L;
  }
  function stopLauf() { if (LAUF) { LAUF.stop = true; clearTimeout(LAUF.timer); LAUF = null; } }

  // Info-Knopf mit Erklärung (So prüft der Check / Warum / Was zu tun ist)
  var nInfo = 0;
  function infoKnopf(it, wrap, ohneLeistung) {
    var id = 'c100i-' + (++nInfo);
    var b = el('button', 'c100-info'); b.type = 'button';
    b.setAttribute('aria-expanded', 'false'); b.setAttribute('aria-controls', id);
    b.setAttribute('aria-label', T.infoFor + it.titel); b.title = T.info;
    b.appendChild(el('span', '', 'i')).setAttribute('aria-hidden', 'true');
    var p = el('div', 'c100-infotext'); p.id = id; p.hidden = true;
    var dl = el('dl');
    [[T.how, it.info && it.info.wie], [T.why, it.info && it.info.warum], [T.todo, it.info && it.info.tun]].forEach(function (x) {
      if (!x[1]) return; dl.appendChild(el('dt', '', x[0])); dl.appendChild(el('dd', '', x[1]));
    });
    p.appendChild(dl);
    if (it.ok === false && it.leistung && !ohneLeistung) p.appendChild(el('p', 'c100-leistung', T.fix + it.leistung));
    b.addEventListener('click', function () {
      var auf = b.getAttribute('aria-expanded') !== 'true';
      b.setAttribute('aria-expanded', String(auf)); p.hidden = !auf;
      if (auf && !reduce && p.animate) p.animate([{ opacity: 0, transform: 'translateY(-4px)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(.2,.7,.2,1)' });
    });
    wrap.appendChild(p);
    return b;
  }

  function zusammenfassung(r) {
    if (!r.z) norm(r);
    var fehlt = function (s) { return r.items.filter(function (it) { return it.status === 'fehlt' && it.stufe === s; }).map(function (it) { return it.titel; }); };
    var k = fehlt(1), w = fehlt(2), e = fehlt(3);
    var t = T.sum.head(r, r.z);
    if (!k.length && !w.length) t += '\n' + T.sum.none;
    if (k.length) t += '\n' + T.sum.k + k.map(T.sum.q).join(', ') + '.';
    if (w.length) t += '\n' + T.sum.w + w.map(T.sum.q).join(', ') + '.';
    if (e.length) t += '\n' + T.sum.e(e.length);
    if (t.length > 900) t = t.slice(0, 897).replace(/[;,.\s]+\S*$/, '') + ' …';
    return t;
  }

  function leadForm(r, eingabe) {
    var L = T.lead;
    var box = el('div', 'c100-lead');
    var fm = el('form', 'form c100-form'); fm.noValidate = true; fm.setAttribute('action', '/api/anfrage.php'); fm.method = 'post';
    fm.appendChild(el('h4', 'c50-h4', L.h));
    fm.appendChild(el('p', 'c100-leadp', L.p));
    function feld(id, name, label, typ, opt, attrs, errText) {
      var d = el('div', 'field');
      var lb = el('label', '', label); lb.htmlFor = id;
      if (opt) { lb.appendChild(document.createTextNode(' ')); lb.appendChild(el('em', '', L.opt)); }
      var i = typ === 'textarea' ? el('textarea') : el('input');
      if (typ !== 'textarea') i.type = typ;
      i.id = id; i.name = name;
      Object.keys(attrs || {}).forEach(function (k) { i.setAttribute(k, attrs[k]); });
      d.appendChild(lb); d.appendChild(i);
      var er = null;
      if (errText) { er = el('span', 'err', errText); er.id = id + '-e'; er.hidden = true; d.appendChild(er); i.setAttribute('aria-describedby', er.id); }
      return { d: d, i: i, e: er };
    }
    var r1 = el('div', 'row2'), r2 = el('div', 'row2');
    var fName = feld('c100-name', 'name', L.name, 'text', false, { autocomplete: 'name', maxlength: '100', required: '' }, L.eName);
    var fFirma = feld('c100-firma', 'firma', L.firma, 'text', true, { autocomplete: 'organization', maxlength: '120' });
    var fMail = feld('c100-mail', 'email', L.email, 'email', false, { autocomplete: 'email', maxlength: '150', required: '', placeholder: L.ph }, L.eMail);
    var fTel = feld('c100-tel', 'telefon', L.tel, 'tel', true, { autocomplete: 'tel', maxlength: '30' }, L.eTel);
    var fMsg = feld('c100-msg', 'nachricht', L.msg, 'textarea', false, { maxlength: '1500', required: '' }, L.eMsg);
    fMsg.i.value = L.msgVal(r.host);
    r1.appendChild(fName.d); r1.appendChild(fFirma.d); r2.appendChild(fMail.d); r2.appendChild(fTel.d);
    fm.appendChild(r1); fm.appendChild(r2); fm.appendChild(fMsg.d);
    // feste Angaben für die Anfrage
    var hid = function (n, v) { var h = el('input'); h.type = 'hidden'; h.name = n; h.value = v; fm.appendChild(h); return h; };
    hid('pakete[]', 'check');
    hid('website_url', String(eingabe).slice(0, 200));
    hid('zusammenstellung', zusammenfassung(r));
    var hp = el('div', 'hp'); hp.setAttribute('aria-hidden', 'true');
    var hpl = el('label', '', EN ? 'Please leave empty' : 'Bitte leer lassen'); hpl.htmlFor = 'c100-hp';
    var hpi = el('input'); hpi.type = 'text'; hpi.id = 'c100-hp'; hpi.name = 'website'; hpi.tabIndex = -1; hpi.autocomplete = 'off';
    hp.appendChild(hpl); hp.appendChild(hpi); fm.appendChild(hp);
    var cl = el('label', 'consent'); cl.htmlFor = 'c100-ok';
    var cb = el('input'); cb.type = 'checkbox'; cb.id = 'c100-ok'; cb.name = 'einwilligung'; cb.value = '1'; cb.required = true;
    var cs = el('span'); cs.innerHTML = L.consent + ' <b class="req" aria-hidden="true">*</b>';
    cl.appendChild(cb); cl.appendChild(cs); fm.appendChild(cl);
    var eOk = el('span', 'err err-box', L.eOk); eOk.id = 'c100-ok-e'; eOk.hidden = true; eOk.setAttribute('role', 'alert'); fm.appendChild(eOk);
    fm.appendChild(el('p', 'req-note c100-sumnote', L.sumNote));
    var sb = el('button', 'btn btn--primary', L.send); sb.type = 'submit'; fm.appendChild(sb);
    var res = el('p', 'msg'); res.hidden = true; res.setAttribute('role', 'status'); fm.appendChild(res);
    box.appendChild(fm);

    function mark(list) { list.forEach(function (x) { x[0].i.setAttribute('aria-invalid', String(!x[1])); if (x[0].e) x[0].e.hidden = x[1]; }); }
    function consentOk() { var ok = cb.checked; eOk.hidden = ok; cl.classList.toggle('invalid', !ok); cb.setAttribute('aria-invalid', String(!ok)); return ok; }
    cb.addEventListener('change', function () { if (cb.checked) consentOk(); });
    fm.addEventListener('submit', function (e) {
      e.preventDefault();
      var tv = fTel.i.value.trim();
      var checks = [[fName, fName.i.value.trim().length > 1], [fMail, reMail.test(fMail.i.value.trim())], [fTel, !tv || reTel.test(tv)], [fMsg, fMsg.i.value.trim().length >= 10]];
      mark(checks);
      var cOk = consentOk();
      if (!(checks.every(function (x) { return x[1]; }) && cOk)) {
        res.className = 'msg bad'; res.textContent = L.check; res.hidden = false;
        (fm.querySelector('[aria-invalid="true"]') || cb).focus(); return;
      }
      var data = new FormData(fm);
      data.set('sprache', EN ? 'en' : 'de'); data.set('seite', location.pathname);
      sb.disabled = true; sb.textContent = L.sending; res.hidden = true;
      fetch(fm.getAttribute('action'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
        .then(function (x) { return x.json().catch(function () { return { ok: false }; }).then(function (j) { if (!x.ok || !j || !j.ok) { var er = new Error('fehler'); er.data = j || {}; throw er; } return j; }); })
        .then(function () {
          var ok = el('div', 'sent msg c100-sent'); ok.setAttribute('role', 'status'); ok.tabIndex = -1;
          ok.appendChild(el('h4', 'c50-h4', L.okH)); ok.appendChild(el('p', '', L.okP));
          fm.replaceWith(ok); go(ok);
          try { ok.focus({ preventScroll: true }); } catch (e2) { /* alt */ }
        })
        .catch(function (er) {
          var fl = er && er.data && er.data.fields;
          var map = { name: fName, email: fMail, telefon: fTel, nachricht: fMsg };
          if (fl && fl.length) {
            mark(fl.map(function (k) { return map[k] ? [map[k], false] : null; }).filter(Boolean));
            if (fl.indexOf('einwilligung') > -1) { cb.checked = false; consentOk(); }
            res.className = 'msg bad'; res.textContent = L.check;
          } else { res.className = 'msg bad'; res.innerHTML = L.fail; }
          res.hidden = false; sb.disabled = false; sb.textContent = L.send;
        });
    });
    return box;
  }

  var ICON = { ok: '✓', fehlt: '✕', na: '–', np: '?' }, KL = { ok: 'ok', fehlt: 'no', na: 'na', np: 'np' };
  function zeile(it, inPrio) {
    var stt = statusVon(it);
    var li = el('li', inPrio ? 's' + it.stufe : KL[stt] + ' s' + it.stufe);
    if (inPrio) {
      var h = el('div', 'c100-ph');
      var kopf = el('div', 'c50-ph'); kopf.appendChild(el('span', 'c50-badge', T.tier[it.stufe])); kopf.appendChild(el('strong', '', it.titel));
      h.appendChild(kopf); li.appendChild(h);
      li.appendChild(el('p', 'c50-detail', it.detail));
      if (it.leistung) li.appendChild(el('p', 'c100-loes', T.fix + it.leistung));
      h.appendChild(infoKnopf(it, li, true));
      return li;
    }
    var b = el('b', '', ICON[stt]); b.setAttribute('aria-hidden', 'true');
    var sp = el('span', 'c100-txt');
    var t = el('strong', '', it.titel); sp.appendChild(t);
    sp.appendChild(el('small', '', it.detail));
    var rechts = el('span', 'c100-re');
    rechts.appendChild(el('span', 'c50-badge', T.tier[it.stufe]));
    rechts.appendChild(el('em', '', T.st[stt]));
    li.appendChild(b); li.appendChild(sp); li.appendChild(rechts);
    rechts.appendChild(infoKnopf(it, li));
    return li;
  }
  function zahlenZeile(z, cls) {
    var p = el('p', cls);
    ['ok', 'fehlt', 'na', 'np'].forEach(function (k) {
      if (z[k] || k === 'ok' || k === 'fehlt') { var s = el('span', 'z-' + k); var i = el('b', '', ICON[k]); i.setAttribute('aria-hidden', 'true'); s.appendChild(i); s.appendChild(document.createTextNode(' ' + T.zahlen[k](z[k]))); p.appendChild(s); }
    });
    return p;
  }
  function balken(g) {
    var bar = el('i', 'c50-gb c100-gb');
    ['ok', 'fehlt', 'na', 'np'].forEach(function (k) { if (!g[k]) return; var x = el('i', 'g-' + k); x.style.width = (g.gesamt ? g[k] / g.gesamt * 100 : 0) + '%'; bar.appendChild(x); });
    return bar;
  }

  function render(r, eingabe) {
    stopLauf();
    norm(r);
    var z = r.z;
    out.innerHTML = '';
    nInfo = 0;
    var w = el('div', 'c50 c100'); w.setAttribute('role', 'region'); w.setAttribute('aria-label', T.forHost + r.host);
    // Kopf: Gesamtwert im Ring, darunter immer „von 100“
    var kopf = el('div', 'c50-kopf');
    var ring = el('div', 'c50-ring c100-ring'); ring.style.setProperty('--p', reduce ? r.punkte : 0);
    var bz = el('b'), zahl = el('span', 'c100-zn', reduce ? String(r.punkte) : '0'); bz.appendChild(zahl); bz.appendChild(el('span', 'c100-pz', '%'));
    ring.appendChild(bz); ring.appendChild(el('small', '', T.wert));
    ring.setAttribute('role', 'img'); ring.setAttribute('aria-label', T.wertAria(r.punkte));
    var kt = el('div', 'c50-kt');
    kt.appendChild(el('p', 'muted', T.forHost + r.host));
    kt.appendChild(el('h3', '', z.ok + T.of100));
    kt.appendChild(zahlenZeile(z, 'c100-zahlen'));
    var st = el('p', 'c50-stufen c100-stufen');
    [1, 2, 3].forEach(function (k) { var s = r.stufen[k]; st.appendChild(el('span', 't' + k, T.tier[k] + ' ' + T.von(s.ok, s.gesamt))); });
    kt.appendChild(st);
    kt.appendChild(el('p', 'c100-gew', T.weights));
    kopf.appendChild(ring); kopf.appendChild(kt); w.appendChild(kopf);
    // Bereiche: bestanden von allen Punkten des Bereichs, Balken mit fehlt, trifft nicht zu, nicht prüfbar
    var gl = el('ul', 'c50-gruppen c100-gruppen');
    r.gruppen.forEach(function (g) {
      var li = el('li', guete(g));
      li.setAttribute('aria-label', T.gruppeAria(g));
      var n1 = el('span', 'c50-gn', g.titel), n2 = el('span', 'c50-gz', T.von(g.ok, g.gesamt)), b = balken(g);
      [n1, n2, b].forEach(function (x) { x.setAttribute('aria-hidden', 'true'); li.appendChild(x); });
      gl.appendChild(li);
    });
    w.appendChild(gl);
    // Prioritäten
    var fehlt = r.items.filter(function (it) { return it.status === 'fehlt'; }).sort(function (a, b) { return a.stufe - b.stufe || a.nr - b.nr; });
    var pr = el('div', 'c50-prio c100-prio');
    pr.appendChild(el('h4', '', T.first));
    var wichtigeFehlen = fehlt.filter(function (it) { return it.stufe < 3; }).length;
    if (!wichtigeFehlen) pr.appendChild(el('p', 'c50-lob', T.allGood));
    var ol = el('ol');
    var zeigen = Math.min(fehlt.length, Math.max(6, Math.min(10, wichtigeFehlen)));
    fehlt.slice(0, zeigen).forEach(function (it) { ol.appendChild(zeile(it, true)); });
    if (fehlt.length) pr.appendChild(ol);
    if (fehlt.length > zeigen) pr.appendChild(el('p', 'c50-mehr', T.more(fehlt.length - zeigen)));
    w.appendChild(pr);
    // So kann ich helfen
    if (r.leistungen && r.leistungen.length) {
      var hb = el('div', 'c100-hilfe');
      hb.appendChild(el('h4', '', T.help)); hb.appendChild(el('p', 'c100-hilfep', T.helpP));
      var hl = el('ul');
      var titel = {}; r.items.forEach(function (it) { titel[it.id] = it.titel; });
      r.leistungen.forEach(function (l) {
        var li = el('li'); li.appendChild(el('strong', '', l.name || l.text));
        li.appendChild(el('span', 'c100-hz', T.pts(l.punkte.length)));
        if (l.name) li.appendChild(el('p', 'c100-ht', l.text));
        li.appendChild(el('small', '', l.punkte.slice(0, 4).map(function (id) { return titel[id]; }).join(' · ') + (l.punkte.length > 4 ? ' · …' : '')));
        hl.appendChild(li);
      });
      hb.appendChild(hl); w.appendChild(hb);
    }
    // Alle 100 Punkte
    var det = el('details', 'c50-alle c100-alle'); det.appendChild(el('summary', '', T.all));
    r.gruppen.forEach(function (g) {
      det.appendChild(el('h5', '', g.titel + ' · ' + T.von(g.ok, g.gesamt) + ' ' + T.st.ok));
      var ul = el('ul', 'checks c50-liste c100-liste');
      r.items.filter(function (it) { return it.gruppe === g.id; }).forEach(function (it) { ul.appendChild(zeile(it, false)); });
      det.appendChild(ul);
    });
    w.appendChild(det);
    // Ergebnisblatt anfordern
    w.appendChild(leadForm(r, eingabe));
    // Aktionen
    var act = el('div', 'row-actions');
    var a2 = el('a', 'btn btn--line', T.talk); a2.href = T.contact + '?pakete=termin&url=' + encodeURIComponent(eingabe);
    var a3 = el('button', 'link', T.again); a3.type = 'button';
    a3.addEventListener('click', function () { out.innerHTML = ''; inp.value = ''; say(''); try { f.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); } catch (e) { /* alt */ } inp.focus({ preventScroll: true }); });
    act.appendChild(a2); act.appendChild(a3); w.appendChild(act);
    w.appendChild(el('p', 'fine', T.note));
    out.appendChild(w);
    say(T.forHost + r.host + '. ' + z.ok + T.of100 + ', ' + T.zahlen.fehlt(z.fehlt) + '. ' + T.wertAria(r.punkte) + '.');
    if (!reduce) {
      var n = 0, ziel = r.punkte;
      var iv = setInterval(function () { n = Math.min(ziel, n + Math.max(1, Math.round(ziel / 30))); ring.style.setProperty('--p', n); zahl.textContent = String(n); if (n >= ziel) clearInterval(iv); }, 28);
    }
    go(w);
  }
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    if (f.classList.contains('busy')) return;
    var v = inp.value.trim();
    if (!reUrl.test(v)) { showErr('url'); inp.setAttribute('aria-invalid', 'true'); inp.focus(); return; }
    inp.setAttribute('aria-invalid', 'false'); err.hidden = true;
    btn.disabled = true; btn.textContent = T.run; f.classList.add('busy');
    var L = lauf(hostAus(v));
    var frei = function () { btn.disabled = false; btn.textContent = T.go; f.classList.remove('busy'); };
    var fehler = function (code) { stopLauf(); out.innerHTML = ''; showErr(code); say(T.err[code] || T.err.server); frei(); };
    var data = new FormData(); data.set('url', v); data.set('sprache', EN ? 'en' : 'de'); data.set('modus', 'sofort');
    var abbruch = setTimeout(function () { if (LAUF === L) fehler('server'); }, 60000);
    fetch('/api/check100.php', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json().catch(function () { return { ok: false, error: 'server' }; }); })
      .then(function (r) {
        clearTimeout(abbruch);
        if (LAUF !== L) return;
        if (!r || !r.ok || !r.items || !r.items.length) { fehler(r && r.error); return; }
        L.ergebnis(norm(r), function (x) { render(x, v); frei(); });
      })
      .catch(function () { clearTimeout(abbruch); if (LAUF === L) fehler('server'); });
  });
})();
