/* Lou Digital, Version 12 (24.10.2026): Menü, Sprache, Hereinladen, Fragen, Paketauswahl, Baukasten,
   Anfrage mit Terminwunsch, Sofort-Check, Feedback, Reichweitenmessung ohne Cookies
   Version 26 (28.10.2026): Blockregale links und rechts, Knopf zum Anhalten
   Version 27 (28.10.2026): Kopfzeile passt sich an (große Schrift auf Android, schmale Handys, Tablets)
   Version 29 (29.10.2026): Menü fährt aus, Fußzeile zur Startseite, Kombi-Rabatt in der Paketauswahl
   Version 28 (29.10.2026): Blöcke bewegen sich immer (bei „weniger Bewegung“ ruhig), Tempo atmet, Logo unten führt nach oben, Karten neigen sich */
(function () {
  'use strict';
  window.__ldReady = true;
  var doc = document.documentElement;
  var EN = doc.lang === 'en';
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var T = EN ? {
    free: 'free', month: ' per month', arr: 'by arrangement', wish: 'Request: ', note: 'Pilot prices, no VAT charged (§ 19 UStG).', noteX: ' I discuss extras with you in a short call.',
    once: 'One-off', monthly: 'Monthly', time: 'Timeframe', plus: ' plus extras',
    check: 'Please check the highlighted fields.', sending: 'Sending', send: 'Send inquiry',
    fail: 'The message could not be sent. Please try again in a few minutes or write to ', ok: 'Thank you, your inquiry has arrived.',
    okTermin: 'Thank you, your appointment request has arrived.',
    okText: 'You will receive a confirmation by email in a moment. I will reply personally, usually within 24 hours on working days.',
    reqSel: 'Request this selection', reqCheck: 'Request the free check', freeNote: 'The check is free and without obligation. Add building blocks for a price.',
    askOk: 'Thank you, your question has arrived.', askSend: 'Send question',
    picked: function (n) { return n === 1 ? '1 package selected' : n + ' packages selected'; }, perMonth: '/month', oneOff: ' one-off', kombi: ' incl. 10% bundle discount', perYear: '/year (two months free)', firstFree: ', first month free',
    pilotOn: function (n, left) { return 'Code PILOT active: you save ' + n + ' vs. the regular price' + (left ? ' (' + left + ' of 5 pilot slots left)' : ''); }, pilotOff: 'Pilot phase finished, regular prices apply.',
    aktionOn: function (t, c, n, left) { return 'Promotion ' + t + ' (code ' + c + ') active: you save ' + n + (left ? ' (' + left + ' left)' : ''); },
    codeOk: function (c, p, n) { return 'Code ' + c + ' (' + p + '%): −' + n; }, codeOkNoSum: function (c, p) { return 'Code ' + c + ' (' + p + '%) applies to your first package.'; },
    codeBad: { unbekannt: 'This code is unknown.', unbekannt_empfehlung: 'This referral code will be checked before the quote.', abgelaufen: 'This code has expired.', aufgebraucht: 'This code has been used up.', inaktiv: 'This code is no longer active.', andere_aktion: 'Another promotion is running right now and is applied automatically.', noch_nicht: 'This code is not valid yet.', pruefen: 'Code will be checked before the quote.' },
    codeAdd: 'Enter a code', codeGo: 'Apply', codePh: 'LD-ABCD', codeDel: 'remove',
    fbOk: 'Thank you! Your feedback has arrived.', fbSend: 'Send', halt: 'Pause motion',
    qcRun: 'Checking', qcGo: 'Check now', qcPassed: 'passed', qcMissing: 'missing',
    qcErr: { url: 'Please enter an address like your-company.com.', dns: 'This address doesn’t seem to exist.', unreachable: 'The website did not respond. Is the address correct?', rate: 'Too many checks in a short time. Please try again later.', server: 'The check is not available right now. Please send an inquiry instead.' },
    qcTalk: 'Discuss the result', qcAgain: 'Check another website', qcFor: 'Result for ',
    eur: function (n) { return '€' + n.toLocaleString('en-GB'); }
  } : {
    free: 'kostenlos', month: ' im Monat', arr: 'nach Absprache', wish: 'Wunsch: ', note: 'Pilotpreise, ohne Umsatzsteuer (§ 19 UStG).', noteX: ' Extras kläre ich mit Ihnen im Gespräch.',
    once: 'Einmalig', monthly: 'Monatlich', time: 'Zeitrahmen', plus: ' plus Extras',
    check: 'Bitte prüfen Sie die markierten Felder.', sending: 'Wird gesendet', send: 'Anfrage senden',
    fail: 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es in einigen Minuten noch einmal oder schreiben Sie an ', ok: 'Danke, Ihre Anfrage ist angekommen.',
    okTermin: 'Danke, Ihr Terminwunsch ist angekommen.',
    okText: 'Sie bekommen gleich eine Bestätigung per E-Mail. Ich antworte Ihnen persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.',
    reqSel: 'Zusammenstellung anfragen', reqCheck: 'Kostenlosen Check anfordern', freeNote: 'Der Check ist kostenlos und unverbindlich. Für einen Preis fügen Sie Bausteine hinzu.',
    askOk: 'Danke, Ihre Frage ist angekommen.', askSend: 'Frage senden',
    picked: function (n) { return n === 1 ? '1 Paket gewählt' : n + ' Pakete gewählt'; }, perMonth: '/Monat', oneOff: ' einmalig', kombi: ' inkl. 10 % Kombi-Rabatt', perYear: '/Jahr (zwei Monate geschenkt)', firstFree: ', erster Monat geschenkt',
    pilotOn: function (n, left) { return 'Code PILOT aktiv: Sie sparen ' + n + ' gegenüber dem Normalpreis' + (left ? ' (noch ' + left + ' von 5 Pilotplätzen)' : ''); }, pilotOff: 'Pilotphase beendet, es gelten die Normalpreise.',
    aktionOn: function (t, c, n, left) { return 'Aktion ' + t + ' (Code ' + c + ') aktiv: Sie sparen ' + n + (left ? ' (noch ' + left + ' frei)' : ''); },
    codeOk: function (c, p, n) { return 'Code ' + c + ' (' + p + ' %): −' + n; }, codeOkNoSum: function (c, p) { return 'Code ' + c + ' (' + p + ' %) gilt für Ihr erstes Paket.'; },
    codeBad: { unbekannt: 'Dieser Code ist unbekannt.', unbekannt_empfehlung: 'Dieser Empfehlungscode wird vor dem Angebot geprüft.', abgelaufen: 'Dieser Code ist abgelaufen.', aufgebraucht: 'Dieser Code ist aufgebraucht.', inaktiv: 'Dieser Code ist nicht mehr aktiv.', andere_aktion: 'Gerade läuft schon eine andere Aktion, die wird automatisch angewendet.', noch_nicht: 'Dieser Code gilt noch nicht.', pruefen: 'Der Code wird vor dem Angebot geprüft.' },
    codeAdd: 'Code eingeben', codeGo: 'Einlösen', codePh: 'LD-ABCD', codeDel: 'entfernen',
    fbOk: 'Danke! Ihr Feedback ist angekommen.', fbSend: 'Senden', halt: 'Bewegung anhalten',
    qcRun: 'Wird geprüft', qcGo: 'Jetzt prüfen', qcPassed: 'bestanden', qcMissing: 'fehlt',
    qcErr: { url: 'Bitte geben Sie eine Adresse wie ihre-firma.de ein.', dns: 'Diese Adresse scheint es nicht zu geben.', unreachable: 'Die Website hat nicht geantwortet. Stimmt die Adresse?', rate: 'Zu viele Checks in kurzer Zeit. Bitte versuchen Sie es später noch einmal.', server: 'Der Check ist gerade nicht verfügbar. Senden Sie mir gern eine Anfrage.' },
    qcTalk: 'Ergebnis besprechen', qcAgain: 'Andere Website prüfen', qcFor: 'Ergebnis für ',
    eur: function (n) { return n.toLocaleString('de-DE') + ' €'; }
  };
  var MAIL = '<a href="mailto:info@lou-digital.de">info@lou-digital.de</a>.';
  function $(id) { return document.getElementById(id); }
  var kontaktUrl = EN ? '/en/contact' : '/kontakt';

  /* ---------- Reichweitenmessung ohne Cookies ---------- */
  var TR = !(navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true) && location.protocol !== 'file:';
  function track(t, k, extra) {
    if (!TR) return;
    try {
      var d = { t: t, p: location.pathname, l: doc.lang, w: window.innerWidth };
      if (k) d.k = String(k).slice(0, 60);
      if (extra) for (var x in extra) if (extra[x] !== undefined && extra[x] !== '') d[x] = extra[x];
      var body = JSON.stringify(d);
      if (navigator.sendBeacon) navigator.sendBeacon('/api/t.php', new Blob([body], { type: 'text/plain' }));
      else fetch('/api/t.php', { method: 'POST', body: body, keepalive: true });
    } catch (e) {}
  }
  (function () {
    var ref = '';
    try { if (document.referrer) { var r = new URL(document.referrer); if (r.hostname !== location.hostname) ref = r.hostname; } } catch (e) {}
    var src = new URLSearchParams(location.search).get('utm_source') || '';
    track('pv', null, { r: ref, q: src.slice(0, 40) });
    document.addEventListener('click', function (e) {
      var el = e.target.closest && e.target.closest('a,button,summary,label.chip,[data-track]');
      if (!el) return;
      var k = el.getAttribute('data-track') || (el.textContent || '').replace(/\s+/g, ' ').trim() || el.getAttribute('aria-label') || el.tagName.toLowerCase();
      var h = el.tagName === 'A' ? (el.getAttribute('href') || '') : '';
      var sh = Math.max(document.documentElement.scrollHeight, 1);
      track('c', k, { h: h.slice(0, 100), x: Math.round(e.clientX / Math.max(innerWidth, 1) * 100), y: Math.round((e.clientY + scrollY) / sh * 100) });
    }, true);
    var depth = 0, sent = false;
    addEventListener('scroll', function () { var sh = document.documentElement.scrollHeight - innerHeight; if (sh > 0) depth = Math.max(depth, Math.round(scrollY / sh * 100)); }, { passive: true });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && !sent) { sent = true; track('s', null, { d: depth }); } });
  })();

  /* ---------- Weiches Scrollen, Menü, Sprungmarken ---------- */
  var lenis = null;
  if (!reduce && window.Lenis) {
    lenis = new Lenis({ lerp: 0.09, wheelMultiplier: 0.9 });
    // Version 26: Schleife nur, solange sich etwas bewegt (sonst ruht der Hauptthread, die Blöcke gleiten auf der Grafikkarte)
    var lenisLauf = 0, lenisRuhe = 0;
    var lenisSchritt = function (t) {
      lenis.raf(t);
      if (lenis.isScrolling || (lenis.animate && lenis.animate.isRunning)) lenisRuhe = 0; else lenisRuhe++;
      if (lenisRuhe > 20) { lenisLauf = 0; lenis.time = 0; return; } // nach einer Drittelsekunde Ruhe schlafen, beim Aufwachen ohne Sprung weiter
      lenisLauf = requestAnimationFrame(lenisSchritt);
    };
    var lenisWach = function () { lenisRuhe = 0; if (!lenisLauf) lenisLauf = requestAnimationFrame(lenisSchritt); };
    ['wheel', 'touchstart', 'touchmove', 'keydown', 'pointerdown', 'scroll'].forEach(function (ev) { addEventListener(ev, lenisWach, { passive: true }); });
    var lenisScrollTo = lenis.scrollTo.bind(lenis);
    lenis.scrollTo = function () { lenisWach(); return lenisScrollTo.apply(null, arguments); };
    lenisWach();
  }
  var header = document.querySelector('.top');
  function go(el) { var off = -((header ? header.offsetHeight : 0) + 12); if (lenis) lenis.scrollTo(el, { offset: off, duration: 1.1 }); else window.scrollTo({ top: el.getBoundingClientRect().top + scrollY + off, behavior: reduce ? 'auto' : 'smooth' }); }
  var nav = $('nav'), menuBtn = $('menu-btn');
  /* Version 29: das Menü fährt aus und wieder ein (Höhe, Weichzeichnung, Einträge nacheinander) statt aufzupoppen */
  if (nav) nav.querySelectorAll('a').forEach(function (a, i) { a.style.setProperty('--i', String(i)); });
  function closeMenu() {
    if (!nav || !nav.classList.contains('open') || nav.classList.contains('closing')) return;
    menuBtn.setAttribute('aria-expanded', 'false');
    if (reduce || !nav.animate) { nav.classList.remove('open'); return; }
    nav.classList.add('closing');
    var h = nav.getBoundingClientRect().height;
    var an = nav.animate([{ height: h + 'px' }, { height: '0px' }], { duration: 240, easing: 'cubic-bezier(.4,0,.6,1)' });
    an.onfinish = an.oncancel = function () { nav.classList.remove('open', 'closing'); };
  }
  function openMenu() {
    if (!nav) return;
    nav.classList.remove('closing');
    nav.classList.add('open');
    menuBtn.setAttribute('aria-expanded', 'true');
    if (reduce || !nav.animate) return;
    var h = nav.getBoundingClientRect().height;
    nav.animate([{ height: '0px' }, { height: h + 'px' }], { duration: 400, easing: 'cubic-bezier(.05,.7,.1,1)' });
  }
  if (menuBtn) menuBtn.addEventListener('click', function () { if (nav.classList.contains('open') && !nav.classList.contains('closing')) closeMenu(); else openMenu(); });
  /* Klick neben das Menü schließt es */
  document.addEventListener('pointerdown', function (e) { if (nav && nav.classList.contains('open') && !nav.contains(e.target) && !menuBtn.contains(e.target)) closeMenu(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); closeFb(); } });
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var el = $(a.getAttribute('href').slice(1)); if (!el) return; e.preventDefault(); closeMenu(); go(el);
      /* Sprunglink: Fokus wandert in den Inhalt, damit die Tastatur dort weitergeht */
      if (a.classList.contains('skip')) { if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1'); try { el.focus({ preventScroll: true }); } catch (x) { el.focus(); } }
    });
  });
  var top = $('to-top');
  if (top) top.addEventListener('click', function () { if (lenis) lenis.scrollTo(0, { duration: 1.2 }); else window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
  /* Version 29: der große Schriftzug in der Fußzeile führt auf die Startseite (oben). Steht man schon dort,
     wird nur nach oben gescrollt, danach Fokus aufs Logo. */
  document.querySelectorAll('[data-nach-oben]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var home = EN ? '/en/' : '/';
      var here = location.pathname.replace(/\/(start|index)\.html$/, '/');
      if (here !== home) return; /* normaler Seitenwechsel auf die Startseite */
      e.preventDefault();
      if (lenis) lenis.scrollTo(0, { duration: 1.4 }); else window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
      var logo = document.querySelector('.top .logo');
      if (logo) setTimeout(function () { try { logo.focus({ preventScroll: true }); } catch (x) {} }, reduce ? 0 : 1000);
    });
  });
  /* Version 28: große Karten neigen sich zum Zeiger, das Bild darin verschiebt sich leicht (nur mit Maus, nicht bei weniger Bewegung) */
  if (!reduce && window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.teaser, [data-neigen]').forEach(function (k) {
      var plan = 0, px = 0, py = 0;
      function setzen() {
        plan = 0;
        var r = k.getBoundingClientRect(); if (!r.width) return;
        var x = Math.max(0, Math.min(1, (px - r.left) / r.width)) - 0.5, y = Math.max(0, Math.min(1, (py - r.top) / r.height)) - 0.5;
        k.style.setProperty('--nx', (x * 8).toFixed(2) + 'deg');
        k.style.setProperty('--ny', (-y * 7).toFixed(2) + 'deg');
        k.style.setProperty('--lx', ((x + 0.5) * 100).toFixed(1) + '%');
        k.style.setProperty('--ly', ((y + 0.5) * 100).toFixed(1) + '%');
        k.style.setProperty('--mx', (x * -10).toFixed(1) + 'px');
        k.style.setProperty('--my', (y * -8).toFixed(1) + 'px');
      }
      k.addEventListener('pointerenter', function (e) { if (e.pointerType !== 'mouse') return; k.classList.add('neigt'); });
      k.addEventListener('pointermove', function (e) { if (e.pointerType !== 'mouse') return; px = e.clientX; py = e.clientY; if (!plan) plan = requestAnimationFrame(setzen); });
      k.addEventListener('pointerleave', function () {
        if (plan) { cancelAnimationFrame(plan); plan = 0; }
        k.classList.remove('neigt');
        ['--nx', '--ny', '--mx', '--my'].forEach(function (v) { k.style.removeProperty(v); });
      });
    });
  }
  if (location.hash && $(location.hash.slice(1))) setTimeout(function () { go($(location.hash.slice(1))); }, 350);

  /* ---------- Sprachumschalter: Punkt gleitet, dann Seitenwechsel ---------- */
  document.querySelectorAll('.lang').forEach(function (sw) {
    sw.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (a.classList.contains('is-on')) { e.preventDefault(); return; }
        if (e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        sw.setAttribute('data-active', a.getAttribute('hreflang'));
        sw.querySelectorAll('a').forEach(function (x) { x.classList.toggle('is-on', x === a); });
        var href = a.getAttribute('href') + location.search;
        setTimeout(function () { location.href = href; }, reduce ? 0 : 280);
      });
    });
  });

  /* ---------- Hereinladen: jedes Element genau einmal ---------- */
  var items = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el) { io.observe(el); });
  } else { items.forEach(function (el) { el.classList.add('in'); }); }

  /* ---------- Fragen: weich auf- und zuklappen ---------- */
  document.querySelectorAll('.faq details').forEach(function (d) {
    var s = d.querySelector('summary'), body = d.querySelector('.a'), anim = null;
    if (d.open) d.classList.add('is-open');
    s.addEventListener('click', function (e) {
      e.preventDefault();
      if (anim) anim.cancel();
      var dur = reduce ? 160 : 420, ease = 'cubic-bezier(.2,.7,.2,1)';
      if (!d.open) {
        d.open = true; d.classList.add('is-open');
        anim = body.animate([{ height: '0px', opacity: 0 }, { height: body.scrollHeight + 'px', opacity: 1 }], { duration: dur, easing: ease });
        anim.onfinish = function () { anim = null; };
      } else {
        d.classList.remove('is-open');
        anim = body.animate([{ height: body.offsetHeight + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], { duration: dur * 0.8, easing: ease });
        anim.onfinish = function () { d.open = false; anim = null; };
      }
    });
  });

  /* ---------- Hilfen für Formulare ---------- */
  function post(url, data) {
    return fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { if (!r.ok || !j.ok) { var er = new Error(j.error || r.status); er.data = j; throw er; } return j; }); });
  }
  function sentBox(form, title, text) {
    var box = document.createElement('div'); box.className = 'sent msg'; box.setAttribute('role', 'status'); box.tabIndex = -1;
    var h = document.createElement('h3'); h.textContent = title;
    var p = document.createElement('p'); p.textContent = text;
    box.appendChild(h); box.appendChild(p); form.replaceWith(box); go(box);
    try { box.focus({ preventScroll: true }); } catch (e) {}
    return box;
  }
  function mark(list) { list.forEach(function (x) { if (!x[0]) return; x[0].setAttribute('aria-invalid', String(!x[1])); var er = $(x[2]); if (er) er.hidden = x[1]; }); }
  /* Zustimmung zur Datenschutzerklärung: ohne Haken wird nichts gesendet, Fehler gut sichtbar */
  function consent(box, errId) {
    var lab = box.closest('label'), er = $(errId), ok = box.checked;
    if (er) er.hidden = ok;
    if (lab) { lab.classList.toggle('invalid', !ok); if (!ok && !reduce && lab.animate) lab.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }], { duration: 360 }); }
    box.setAttribute('aria-invalid', String(!ok));
    return ok;
  }
  document.querySelectorAll('.consent input[type="checkbox"]').forEach(function (b) {
    b.addEventListener('change', function () { if (b.checked) { var lab = b.closest('label'); if (lab) lab.classList.remove('invalid'); b.setAttribute('aria-invalid', 'false'); var er = lab && lab.nextElementSibling; if (er && er.classList.contains('err')) er.hidden = true; } });
  });
  var reMail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/, reUrl = /^(https?:\/\/)?[\w-]+(\.[\w-]+)+(\/\S*)?$/i, reTel = /^[+\d][\d\s\/()-]{5,24}$/;

  /* ---------- Paket-Baukasten (/individuell) ---------- */
  /* Version 28: Zusatzdienste mit Preisen und Mengen, Vorauswahl über ?basis= und ?x=, die Auswahl bleibt in der Sitzung.
     Die Kontaktseite baut den Text mit summary() aus denselben Angaben nach (x als Schlüssel oder Schlüssel*Anzahl). */
  var TB = EN ? {
    ab: 'from ', once: 'One-off', monthly: 'Monthly', plus: ', plus services by arrangement',
    start: { asap: 'Start as soon as possible', later: 'Start in 1 to 2 months' },
    wish: function (t) { return 'Your own request “' + t + '”'; },
    note: 'Pilot prices for the base packages, no VAT charged (§ 19 UStG).',
    noteX: ' I clarify items by arrangement with you in a short call.',
    noteAb: ' Where it says “from”, the price depends on the scope, and I’ll tell you before I start.',
    kombi: 'Bundle discount website + automation (10%)',
    je: function (n, p) { return n + ' × ' + p; }
  } : {
    ab: 'ab ', once: 'Einmalig', monthly: 'Monatlich', plus: ', dazu Leistungen nach Absprache',
    start: { asap: 'Start so bald wie möglich', later: 'Start in 1 bis 2 Monaten' },
    wish: function (t) { return 'Eigener Wunsch „' + t + '“'; },
    note: 'Pilotpreise für die Grundpakete, alle Preise ohne Umsatzsteuer (§ 19 UStG).',
    noteX: ' Punkte nach Absprache kläre ich mit Ihnen im Gespräch.',
    noteAb: ' Bei „ab“ hängt der Preis vom Umfang ab, ich nenne ihn Ihnen vorher.',
    kombi: 'Kombi-Rabatt Website + Automatik (10 %)',
    je: function (n, p) { return n + ' × ' + p; }
  };
  var B = EN ? { web: ['Website in 7 days', 390], auto: ['Inquiry automation', 290], survey: ['Customer survey', 190], check: ['Website check', 0] }
             : { web: ['Website in 7 Tagen', 390], auto: ['Anfrage-Automatik', 290], survey: ['Kundenumfrage', 190], check: ['Website-Check', 0] };
  var C = EN ? { none: null, S: ['Maintenance S', 29], M: ['Maintenance M', 49], L: ['Maintenance L', 79] }
             : { none: null, S: ['Betreuung S', 29], M: ['Betreuung M', 49], L: ['Betreuung L', 79] };
  /* Zusatzdienste: Name, Preis (null = nach Absprache, 0 = kostenlos), höchste Anzahl (0 = ohne Menge), ab-Preis (1) */
  var X = EN ? {"pages": ["Additional page", 60, 20, 0], "texte": ["Copy for your pages", 40, 20, 0], "lang": ["Second language", 190, 0, 0], "booking": ["Online booking", 90, 0, 0], "move": ["Move your existing website", 149, 0, 0], "gprofil": ["Google Business Profile", 79, 0, 0], "korrektur": ["Extra round of revisions", 49, 0, 0], "grafik": ["Graphics in your brand style", 120, 0, 1], "kanal": ["Additional channel", 90, 5, 0], "ki": ["AI summary and reply draft", 190, 0, 0], "erinnerung": ["Appointment reminder", 90, 0, 0], "bericht": ["Monthly report by email", 90, 0, 0], "anbindung": ["Connection to your software", 149, 0, 1], "runde": ["Additional survey round", 49, 12, 0], "versand": ["Sending by email", 49, 0, 0], "ulang": ["Survey in a second language", 49, 0, 0], "besprechung": ["Discussing the result", 0, 0, 0], "umsetzung": ["Fixing the missing points", null, 0, 0]}
             : {"pages": ["Weitere Seite", 60, 20, 0], "texte": ["Texte für Ihre Seiten", 40, 20, 0], "lang": ["Zweite Sprache", 190, 0, 0], "booking": ["Online-Terminbuchung", 90, 0, 0], "move": ["Umzug Ihrer bisherigen Website", 149, 0, 0], "gprofil": ["Google-Unternehmensprofil", 79, 0, 0], "korrektur": ["Zusätzliche Korrekturrunde", 49, 0, 0], "grafik": ["Grafiken im Stil Ihrer Marke", 120, 0, 1], "kanal": ["Weiterer Eingang", 90, 5, 0], "ki": ["KI-Kurzfassung und Antwortvorschlag", 190, 0, 0], "erinnerung": ["Terminerinnerung", 90, 0, 0], "bericht": ["Monatsbericht per E-Mail", 90, 0, 0], "anbindung": ["Anbindung an Ihr Programm", 149, 0, 1], "runde": ["Weitere Umfragerunde", 49, 12, 0], "versand": ["Versand per E-Mail", 49, 0, 0], "ulang": ["Umfrage in zweiter Sprache", 49, 0, 0], "besprechung": ["Besprechung des Ergebnisses", 0, 0, 0], "umsetzung": ["Umsetzung der fehlenden Punkte", null, 0, 0]};
  var BASIS = { web: 'web', website: 'web', auto: 'auto', automatik: 'auto', automation: 'auto', umfrage: 'survey', survey: 'survey', check: 'check' };
  function xTeil(t) {
    var m = /^([a-z]+)(?:\*(\d{1,3}))?$/.exec(String(t || '').trim());
    if (!m || !X.hasOwnProperty(m[1])) return null;
    var x = X[m[1]], n = x[2] ? Math.max(1, Math.min(parseInt(m[2] || '1', 10) || 1, x[2])) : 1;
    return { k: m[1], x: x, n: n };
  }
  function summary(sel) {
    var once = 0, month = 0, lines = [], offen = 0, ab = false, seen = {};
    (sel.b || []).forEach(function (k) { var b = B.hasOwnProperty(k) && B[k]; if (!b || seen['b:' + k]) return; seen['b:' + k] = 1; once += b[1]; lines.push([b[0], b[1] ? T.eur(b[1]) : T.free]); });
    var c = C.hasOwnProperty(sel.c) && C[sel.c]; if (c) { month += c[1]; lines.push([c[0], T.eur(c[1]) + T.month]); }
    (sel.x || []).forEach(function (t) {
      var e = xTeil(t); if (!e || seen[e.k]) return; seen[e.k] = 1;
      var x = e.x, name = x[0] + (x[2] ? ' × ' + e.n : ''), preis;
      if (x[1] === null) { preis = T.arr; offen++; }
      else if (x[1] === 0) preis = T.free;
      else { once += x[1] * e.n; if (x[3]) ab = true; preis = (x[3] ? TB.ab : '') + T.eur(x[1] * e.n); }
      lines.push([name, preis]);
    });
    if (sel.wish) { lines.push([TB.wish(sel.wish), T.arr]); offen++; }
    /* Version 29: Kombi-Rabatt, wenn Website und Anfrage-Automatik zusammen gewählt sind */
    if (seen['b:web'] && seen['b:auto']) { var rab = Math.round((B.web[1] + B.auto[1]) * 0.1); once -= rab; lines.push([TB.kombi, '−' + T.eur(rab)]); }
    var text = lines.map(function (l) { return '- ' + l[0] + ', ' + l[1]; });
    text.push(TB.once + ' ' + (ab ? TB.ab : '') + T.eur(once) + (offen ? TB.plus : ''), TB.monthly + ' ' + T.eur(month), TB.start[sel.w] || TB.start.asap);
    return { once: once, month: month, lines: lines, extras: offen, ab: ab, text: text.join('\n') };
  }
  function bump(el, v) { if (el.textContent !== v) { el.textContent = v; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); } }
  var builder = $('builder');
  if (builder) {
    var list = $('sum-list'), empty = $('sum-empty'), sendB = $('sum-send'), onceEl = $('sum-once'), monthEl = $('sum-month'), freeOnly = false, KEY = 'ld-bau';
    var mengen = [].slice.call(builder.querySelectorAll('input[data-x]'));
    var hoechst = function (i) { return parseInt(i.getAttribute('max'), 10) || 20; };
    var zahl = function (i) { var n = parseInt(i.value, 10); return isNaN(n) ? 0 : Math.max(0, Math.min(n, hoechst(i))); };
    var current = function () {
      var x = [];  /* in der Reihenfolge der Seite */
      builder.querySelectorAll('input[name="x"], input[data-x]').forEach(function (i) {
        if (i.hasAttribute('data-x')) { var n = zahl(i); if (n > 0) x.push(i.getAttribute('data-x') + '*' + n); }
        else if (i.checked) x.push(i.value);
      });
      return {
        b: [].map.call(builder.querySelectorAll('input[name="b"]:checked'), function (i) { return i.value; }),
        c: (builder.querySelector('input[name="care"]:checked') || {}).value || 'none',
        x: x,
        w: (builder.querySelector('input[name="when"]:checked') || {}).value || 'asap',
        wish: $('x-wish').value.trim().slice(0, 200)
      };
    };
    var setzen = function (sel) {
      var b = Array.isArray(sel.b) ? sel.b : [], xs = {};
      (Array.isArray(sel.x) ? sel.x : []).forEach(function (t) { var e = xTeil(t); if (e) xs[e.k] = e.n; });
      builder.querySelectorAll('input[name="b"]').forEach(function (i) { i.checked = b.indexOf(i.value) > -1; });
      var c = builder.querySelector('input[name="care"][value="' + (C.hasOwnProperty(sel.c) ? sel.c : 'none') + '"]'); if (c) c.checked = true;
      builder.querySelectorAll('input[name="x"]').forEach(function (i) { i.checked = xs.hasOwnProperty(i.value); });
      mengen.forEach(function (i) { var k = i.getAttribute('data-x'); i.value = xs.hasOwnProperty(k) ? Math.min(xs[k], hoechst(i)) : 0; });
      var w = sel.w && builder.querySelector('input[name="when"][value="' + sel.w + '"]'); if (w) w.checked = true;
      if (typeof sel.wish === 'string') $('x-wish').value = sel.wish.slice(0, 200);
    };
    /* Mengen: Knöpfe sperren, Betrag an der Option zeigen */
    var mengeZeigen = function () {
      mengen.forEach(function (i) {
        var k = i.getAttribute('data-x'), n = zahl(i), box = i.closest('.opt'), x = X[k];
        if (!box || !x) return;
        box.classList.toggle('is-on', n > 0);
        var minus = box.querySelector('[data-mg="-"]'), plus = box.querySelector('[data-mg="+"]'), p = box.querySelector('.p'), s = p && p.querySelector('small');
        if (minus) minus.disabled = n <= 0;
        if (plus) plus.disabled = n >= hoechst(i);
        if (p && p.firstChild) p.firstChild.nodeValue = T.eur(x[1] * Math.max(n, 1));
        if (s) s.textContent = n > 1 ? TB.je(n, T.eur(x[1])) : p.getAttribute('data-je');
      });
    };
    var calc = function () {
      mengeZeigen();
      var sel = current(), s = summary(sel);
      list.innerHTML = '';
      s.lines.forEach(function (l) { var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('span'); a.textContent = l[0]; b.textContent = l[1]; if (!/\d/.test(l[1])) b.className = 'wort'; li.appendChild(a); li.appendChild(b); list.appendChild(li); });
      var has = s.lines.length > 0, paid = s.lines.some(function (l) { return l[1] !== T.free; });
      freeOnly = has && !paid;
      empty.hidden = has; sendB.disabled = !has; sendB.textContent = freeOnly ? T.reqCheck : T.reqSel;
      bump(onceEl, (s.ab ? TB.ab : '') + T.eur(s.once)); bump(monthEl, T.eur(s.month));
      $('sum-note').textContent = freeOnly ? T.freeNote : TB.note + (s.extras ? TB.noteX : '') + (s.ab ? TB.noteAb : '');
      try { sessionStorage.setItem(KEY, JSON.stringify(sel)); } catch (e) {}
    };
    builder.addEventListener('submit', function (e) { e.preventDefault(); });
    builder.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-mg]'); if (!b || !builder.contains(b)) return;
      var box = b.closest('.opt'), i = box && box.querySelector('input[data-x]'); if (!i) return;
      i.value = Math.max(0, Math.min(zahl(i) + (b.getAttribute('data-mg') === '+' ? 1 : -1), hoechst(i)));
      calc();
      if (b.disabled) i.focus();
    });
    builder.addEventListener('change', calc);
    builder.addEventListener('input', function (e) { if (e.target === $('x-wish') || (e.target.hasAttribute && e.target.hasAttribute('data-x'))) calc(); });
    mengen.forEach(function (i) { i.addEventListener('blur', function () { var n = zahl(i); if (String(n) !== i.value) { i.value = n; calc(); } }); });
    /* Vorauswahl: ?basis=web|auto|umfrage|check|S|M|L (auch mehrere), ?x=pages*2,lang; sonst die Auswahl aus dieser Sitzung */
    var q = new URLSearchParams(location.search), start = null, ausUrl = false;
    var basis = (q.get('basis') || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    if (basis.length || q.get('x') || q.get('b') || q.get('c')) {
      ausUrl = true;
      start = { b: (q.get('b') || '').split(',').filter(function (k) { return B.hasOwnProperty(k); }), c: q.get('c') || 'none', x: (q.get('x') || '').split(','), w: q.get('w') || null, wish: null };
      basis.forEach(function (t) {
        var l = t.toLowerCase(), k = BASIS.hasOwnProperty(l) ? BASIS[l] : null;
        if (k) { if (start.b.indexOf(k) < 0) start.b.push(k); }
        else if (/^[sml]$/.test(l)) start.c = l.toUpperCase();
        else if (l === 'betreuung' || l === 'maintenance') start.c = 'S';
      });
      if (!q.get('b') && start.b.indexOf('check') < 0) start.b.push('check');
    } else {
      try { start = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) { start = null; }
      if (!start || typeof start !== 'object' || !Array.isArray(start.b)) start = null;
    }
    if (start) setzen(start);
    calc();
    if (ausUrl && !reduce) {
      var neu = [];
      builder.querySelectorAll('input[name="b"]:checked, input[name="care"]:checked, input[name="x"]:checked').forEach(function (i) { if (i.value !== 'none' && i.value !== 'check') neu.push(i.closest('.opt')); });
      mengen.forEach(function (i) { if (zahl(i) > 0) neu.push(i.closest('.opt')); });
      neu.forEach(function (o) { if (o) o.classList.add('ist-neu'); });
      setTimeout(function () { neu.forEach(function (o) { if (o) o.classList.remove('ist-neu'); }); }, 2400);
    }
    sendB.addEventListener('click', function () {
      var s = current();
      if (freeOnly) { location.href = kontaktUrl + '?pakete=check'; return; }
      var qs = new URLSearchParams({ anliegen: 'individuell', b: s.b.join(','), c: s.c, x: s.x.join(','), w: s.w });
      try { if (s.wish) sessionStorage.setItem('ld-wish', s.wish); else sessionStorage.removeItem('ld-wish'); } catch (e) {}
      location.href = kontaktUrl + '?' + qs.toString();
    });
  }

  /* ---------- Version 30: Pilotstand und Codes live vom Server (/api/code.php) ---------- */
  var NORMAL = { website: 790, automatik: 590, umfrage: 290 }, PILOT = { website: 390, automatik: 290, umfrage: 190 };
  var PREIS = { website: 390, automatik: 290, umfrage: 190 }; /* aktuelle Preise, kommen vom Server (/api/code.php) */
  var pilotInfo = { pilot: true, frei: 5 }, aktionInfo = null;
  var codeInfo = null; /* { code, gueltig, art, prozent, text, grund } */
  var codeApi = function (code, pakete, cb) {
    if (!window.fetch) { cb(null); return; }
    fetch('/api/code.php?code=' + encodeURIComponent(code || '') + '&pakete=' + encodeURIComponent((pakete || []).join(',')), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; }).then(cb).catch(function () { cb(null); });
  };
  var storedCode = function () { var c = ''; try { c = sessionStorage.getItem('ld-code') || ''; } catch (e) {} var q = new URLSearchParams(location.search).get('code'); if (q) { c = q; } return (c || '').replace(/[^A-Za-z0-9-]/g, '').toUpperCase().slice(0, 20); };
  var keepCode = function (c) { try { if (c) sessionStorage.setItem('ld-code', c); else sessionStorage.removeItem('ld-code'); } catch (e) {} };
  /* Preise vom Server übernehmen: Pilot, Sonderaktion oder Normalpreise in Tabelle und Karten einsetzen */
  var applyPilot = function (d) {
    if (!d || !d.preise) return;
    pilotInfo = { pilot: !!d.pilot, frei: d.pilot_frei || 0 }; aktionInfo = d.aktion || null;
    document.body.classList.toggle('pilot-aus', !pilotInfo.pilot);
    document.body.classList.toggle('aktion-an', !!(aktionInfo && !pilotInfo.pilot));
    Object.keys(d.preise).forEach(function (k) { if (d.preise[k] && d.preise[k].length) { PREIS[k] = d.preise[k][0]; NORMAL[k] = d.preise[k][1]; } });
    document.querySelectorAll('[data-pick][data-once]').forEach(function (b) {
      var k = b.getAttribute('data-pick'); if (!NORMAL[k]) return;
      if (parseInt(b.getAttribute('data-once') || '0', 10) === PREIS[k]) return;
      b.setAttribute('data-once', String(PREIS[k]));
      var row = b.closest('[data-row]'); var cost = row && row.querySelector('.cost');
      if (cost) { cost.innerHTML = ''; if (PREIS[k] < NORMAL[k]) { var st = document.createElement('s'); st.textContent = T.eur(NORMAL[k]); cost.appendChild(st); } cost.appendChild(document.createTextNode(T.eur(PREIS[k]))); }
    });
    /* Paketkarten: Kombi-Preise aus den aktuellen Preisen */
    var kw = PREIS.website + PREIS.automatik, kn = NORMAL.website + NORMAL.automatik;
    var pv = { 'kombi': T.eur(Math.round(kw * 0.9)), 'kombi-ohne': T.eur(kw), 'kombi-normal': T.eur(Math.round(kn * 0.9)), 'kombi-normal-ohne': T.eur(kn) };
    document.querySelectorAll('[data-preis]').forEach(function (el) { var v = pv[el.getAttribute('data-preis')]; if (v) el.textContent = v; });
    var ab = $('aktion-banner');
    if (ab) { if (aktionInfo && !pilotInfo.pilot) { ab.hidden = false; ab.textContent = T.aktionOn(aktionInfo.titel, aktionInfo.code, '', aktionInfo.frei).replace(': Sie sparen ', '').replace(': you save ', '') + (aktionInfo.text ? ' – ' + aktionInfo.text : ''); } else ab.hidden = true; }
  };

  /* ---------- Pakete auswählen (Tabelle) mit Leiste unten ---------- */
  var bar = $('pickbar');
  if (bar) {
    var picks = {}, tier = null, tierPrice = { S: 29, M: 49, L: 79 }, jahr = false;
    /* Version 30: Zeile für Pilotpreis und Code in der Leiste */
    var pbCode = document.createElement('div'); pbCode.className = 'pb-code'; pbCode.id = 'pb-code'; pbCode.hidden = true;
    var pbTxt = bar.querySelector('.pb-txt'); if (pbTxt) pbTxt.appendChild(pbCode);
    var codeVal = storedCode();
    /* Version 32: Texte aus Server-Antworten (Aktionstitel, Hinweis) nur als Text in die Leiste, nie als HTML */
    var escHtml = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var renderCode = function () {
      var keys = Object.keys(picks).filter(function (k) { return NORMAL[k]; });
      var html = '';
      if (keys.length && (pilotInfo.pilot || aktionInfo)) { var sp = 0; keys.forEach(function (k) { sp += NORMAL[k] - PREIS[k]; }); if (sp > 0) html += '<span class="pb-tag">' + escHtml(pilotInfo.pilot ? T.pilotOn(T.eur(sp), pilotInfo.frei) : T.aktionOn(aktionInfo.titel, aktionInfo.code, T.eur(sp), aktionInfo.frei)) + '</span>'; }
      if (codeInfo && codeInfo.code) {
        if (codeInfo.gueltig && codeInfo.art !== 'pilot') html += '<span class="pb-tag pb-tag--ok">' + escHtml(codeInfo.ersparnis ? T.codeOk(codeInfo.code, codeInfo.prozent, T.eur(codeInfo.ersparnis)) : T.codeOkNoSum(codeInfo.code, codeInfo.prozent)) + '</span>';
        else if (codeInfo.art !== 'pilot') html += '<span class="pb-tag pb-tag--warn">' + escHtml(codeInfo.code + ': ' + (T.codeBad[codeInfo.grund] || T.codeBad.unbekannt)) + '</span>';
        html += ' <button type="button" class="pb-link" id="pb-code-del">' + T.codeDel + '</button>';
      } else {
        html += ' <button type="button" class="pb-link" id="pb-code-add">' + T.codeAdd + '</button>';
      }
      pbCode.innerHTML = html; pbCode.hidden = !(keys.length || codeInfo);
      var add = $('pb-code-add'), del = $('pb-code-del');
      if (add) add.addEventListener('click', function () {
        pbCode.innerHTML = '<form class="pb-form" id="pb-code-form"><input id="pb-code-in" type="text" maxlength="20" autocapitalize="characters" spellcheck="false" placeholder="' + T.codePh + '" aria-label="' + T.codeAdd + '"><button type="submit" class="pb-mini">' + T.codeGo + '</button></form>';
        var inp = $('pb-code-in'); inp.focus();
        $('pb-code-form').addEventListener('submit', function (e) { e.preventDefault(); var c = inp.value.replace(/[^A-Za-z0-9-]/g, '').toUpperCase().slice(0, 20); if (!c) { renderCode(); return; } codeVal = c; keepCode(c); checkCode(); });
      });
      if (del) del.addEventListener('click', function () { codeVal = ''; codeInfo = null; keepCode(''); render(); });
    };
    var checkCode = function () {
      var keys = Object.keys(picks).filter(function (k) { return NORMAL[k]; });
      codeApi(codeVal, keys, function (d) { applyPilot(d); codeInfo = d && d.code ? d.code : (codeVal ? { code: codeVal, gueltig: false, grund: 'pruefen', art: '' } : null); if (codeInfo && codeInfo.art === 'pilot' && d && d.aktion && codeInfo.code === d.aktion.code) codeInfo = null; render(); });
    };
    var render = function () {
      var keys = Object.keys(picks), n = keys.length + (tier ? 1 : 0), once = 0;
      keys.forEach(function (k) { once += picks[k]; });
      document.querySelectorAll('[data-pick]').forEach(function (b) {
        var on = b.getAttribute('data-pick') === 'betreuung' ? b.getAttribute('data-tier') === tier : picks.hasOwnProperty(b.getAttribute('data-pick'));
        b.setAttribute('aria-pressed', String(on));
      });
      document.querySelectorAll('.tr[data-row]').forEach(function (row) { row.classList.toggle('is-picked', !!row.querySelector('[data-pick][aria-pressed="true"]')); });
      $('pb-count').textContent = T.picked(n);
      /* Version 29: Kombi-Rabatt – Website und Anfrage-Automatik zusammen 10 % günstiger */
      var kombi = picks.hasOwnProperty('website') && picks.hasOwnProperty('automatik');
      var rabatt = kombi ? Math.round((picks.website + picks.automatik) * 0.1) : 0;
      var codeAb = (codeInfo && codeInfo.gueltig && codeInfo.art !== 'pilot' && codeInfo.ersparnis) ? codeInfo.ersparnis : 0;
      var parts = []; if (once || !tier) parts.push(T.eur(Math.max(0, once - rabatt - codeAb)) + T.oneOff + (kombi ? T.kombi : ''));
      if (tier) parts.push(jahr ? T.eur(tierPrice[tier] * 10) + T.perYear : T.eur(tierPrice[tier]) + T.perMonth + (kombi ? T.firstFree : ''));
      $('pb-sum').textContent = parts.join(' · ');
      var q = keys.slice(); if (tier) q.push('betreuung');
      $('pb-go').setAttribute('href', kontaktUrl + '?pakete=' + q.join(',') + (tier ? '&stufe=' + tier : '') + (kombi ? '&kombi=1' : '') + (tier && jahr ? '&zahlung=jahr' : '') + (codeVal ? '&code=' + encodeURIComponent(codeVal) : ''));
      if (typeof renderCode === 'function') renderCode();
      document.querySelectorAll('[data-bundle]').forEach(function (b) {
        var want = (b.getAttribute('data-bundle') || '').split(',').filter(Boolean), wt = b.getAttribute('data-bundle-tier') || '', wj = b.getAttribute('data-bundle-jahr') === '1';
        var on = want.every(function (k) { return picks.hasOwnProperty(k); }) && (!wt || tier === wt) && (!wj || jahr) && !!(want.length || wt);
        b.setAttribute('aria-pressed', String(on));
      });
      bar.classList.toggle('show', n > 0); bar.setAttribute('aria-hidden', String(n === 0));
      [].forEach.call(bar.querySelectorAll('a,button'), function (x) { x.tabIndex = n > 0 ? 0 : -1; });
      document.body.classList.toggle('has-pickbar', n > 0);
    };
    document.querySelectorAll('[data-pick]').forEach(function (b) {
      b.addEventListener('click', function () {
        var k = b.getAttribute('data-pick');
        if (k === 'betreuung') { var t = b.getAttribute('data-tier'); tier = tier === t ? null : t; }
        else if (picks.hasOwnProperty(k)) delete picks[k]; else picks[k] = parseInt(b.getAttribute('data-once') || '0', 10);
        render();
      });
    });
    /* Version 29: fertige Pakete per Klick – wählt alle Bausteine, Betreuungsstufe und ggf. Jahreszahlung */
    var priceOf = function (k) { var b = document.querySelector('[data-pick="' + k + '"]'); return b ? parseInt(b.getAttribute('data-once') || '0', 10) : 0; };
    document.querySelectorAll('[data-bundle]').forEach(function (b) {
      b.setAttribute('aria-pressed', 'false');
      b.addEventListener('click', function () {
        var want = (b.getAttribute('data-bundle') || '').split(',').filter(Boolean), wt = b.getAttribute('data-bundle-tier') || '', wj = b.getAttribute('data-bundle-jahr') === '1';
        var already = b.getAttribute('aria-pressed') === 'true';
        if (already) { want.forEach(function (k) { delete picks[k]; }); if (wt) { tier = null; jahr = false; } }
        else { want.forEach(function (k) { picks[k] = priceOf(k); }); if (wt) { tier = wt; jahr = wj; } }
        render();
        if (!already) { var pb = $('pickbar'); if (pb) { pb.classList.add('pulse'); setTimeout(function () { pb.classList.remove('pulse'); }, 900); } }
      });
    });
    document.querySelectorAll('[data-pick="betreuung"]').forEach(function (b) { b.addEventListener('click', function () { if (!tier) jahr = false; }); });
    $('pb-clear').addEventListener('click', function () { picks = {}; tier = null; jahr = false; render(); });
    render();
    /* Pilotstand laden; bei Codes auch die Ersparnis neu rechnen, wenn sich die Auswahl ändert */
    checkCode();
    var ct; bar.addEventListener('ld-picks', function () {});
    document.querySelectorAll('[data-pick],[data-bundle]').forEach(function (b) { b.addEventListener('click', function () { if (codeVal) { clearTimeout(ct); ct = setTimeout(checkCode, 250); } }); });
  }

  /* ---------- Version 31: Enter sendet kein Formular, sondern springt ins nächste leere Feld ---------- */
  ['contact', 'ask', 'fb-panel'].forEach(function (id) {
    var f = $(id); if (!f) return;
    var fields = function () { return [].filter.call(f.querySelectorAll('input, select, textarea'), function (el) { return !el.disabled && el.type !== 'hidden' && el.tabIndex !== -1 && el.offsetParent !== null; }); };
    f.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.isComposing) return;
      var t = e.target; if (!t || t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') return;
      e.preventDefault();
      var all = fields(), i = all.indexOf(t), next = null;
      for (var j = i + 1; j < all.length; j++) { var el = all[j]; if (el.type === 'checkbox' || el.type === 'radio') continue; if (el.tagName === 'SELECT' || el.value.trim() === '') { next = el; break; } }
      if (!next) for (var k = i + 1; k < all.length; k++) { if (all[k].type !== 'checkbox' && all[k].type !== 'radio') { next = all[k]; break; } }
      if (next) { next.focus(); if (next.select && next.tagName !== 'SELECT') try { next.select(); } catch (x) {} }
      else { var btn = f.querySelector('button[type="submit"]'); if (btn) btn.focus(); }
    });
    /* implizites Absenden (Enter in Textfeldern) zusätzlich am Formular abfangen; der Knopf sendet weiterhin */
    f.addEventListener('submit', function (e) { if (e.submitter === null) { e.preventDefault(); e.stopImmediatePropagation(); } });
  });

  /* ---------- Anfrageformular mit Auswahl und Terminwunsch ---------- */
  var form = $('contact');
  if (form) {
    var res = $('f-res'), btn = $('f-send'), pkg = $('f-pkg'), chips = form.querySelectorAll('input[name="pakete[]"]');
    var has = function (v) { var el = form.querySelector('input[name="pakete[]"][value="' + v + '"]'); return !!(el && el.checked); };
    var q = new URLSearchParams(location.search);
    var want = (q.get('pakete') || '').split(',');
    if (q.get('anliegen')) want.push(q.get('anliegen'));
    want.forEach(function (v) { v = (v || '').replace(/[^a-z]/g, ''); var el = v && form.querySelector('input[name="pakete[]"][value="' + v + '"]'); if (el) el.checked = true; });
    if (q.get('kombi') === '1') { var kb = document.createElement('input'); kb.type = 'hidden'; kb.name = 'kombi'; kb.value = '1'; form.appendChild(kb); }
    if (q.get('zahlung') === 'jahr') { var zj = document.createElement('input'); zj.type = 'hidden'; zj.name = 'zahlung'; zj.value = 'jahr'; form.appendChild(zj); var zl = $('tier-note'); if (zl) zl.hidden = false; }
    var cf = $('f-code');
    if (cf) {
      var c0 = storedCode(); if (c0) cf.value = c0;
      var hint = cf.parentNode.querySelector('.hint'), hint0 = hint ? hint.textContent : '';
      var pruefe = function () {
        var c = cf.value.replace(/[^A-Za-z0-9-]/g, '').toUpperCase().slice(0, 20); cf.value = c; keepCode(c);
        if (!c) { if (hint) { hint.textContent = hint0; hint.className = 'hint'; } return; }
        var sel = []; chips.forEach(function (x) { if (x.checked && NORMAL[x.value]) sel.push(x.value); });
        codeApi(c, sel, function (d) {
          if (!hint) return; applyPilot(d);
          var r = d && d.code;
          if (r && r.gueltig && r.art === 'pilot') { hint.textContent = (d.pilot ? T.pilotOn(T.eur(r.ersparnis), d.pilot_frei) : T.aktionOn(d.aktion ? d.aktion.titel : r.code, r.code, T.eur(r.ersparnis), d.aktion ? d.aktion.frei : 0)); hint.className = 'hint hint--ok'; }
          else if (r && r.gueltig) { hint.textContent = (r.ersparnis ? T.codeOk(r.code, r.prozent, T.eur(r.ersparnis)) : T.codeOkNoSum(r.code, r.prozent)) + (r.text ? ' – ' + r.text : ''); hint.className = 'hint hint--ok'; }
          else { hint.textContent = T.codeBad[(r && r.grund) || 'pruefen'] || T.codeBad.unbekannt; hint.className = 'hint hint--warn'; }
        });
      };
      cf.addEventListener('change', pruefe); if (cf.value) pruefe();
      chips.forEach(function (x) { x.addEventListener('change', function () { if (cf.value) pruefe(); }); });
    }
    if (/^[SML]$/.test(q.get('stufe') || '')) { var tr = form.querySelector('input[name="betreuung_stufe"][value="' + q.get('stufe') + '"]'); if (tr) tr.checked = true; }
    if (q.get('url')) $('f-url').value = q.get('url').slice(0, 200);
    if (q.get('anliegen') === 'individuell' && (q.get('b') || q.get('c'))) {
      var wish = ''; try { wish = sessionStorage.getItem('ld-wish') || ''; } catch (e) {}
      pkg.value = summary({ b: (q.get('b') || '').split(',').filter(Boolean), c: q.get('c') || 'none', x: (q.get('x') || '').split(',').filter(Boolean), w: q.get('w') || 'asap', wish: wish }).text;
      /* Betreuung aus dem Baukasten übernehmen (Stufe S, M oder L) */
      if (/^[SML]$/.test(q.get('c') || '')) {
        var cb = form.querySelector('input[name="pakete[]"][value="betreuung"]'), st = form.querySelector('input[name="betreuung_stufe"][value="' + q.get('c') + '"]');
        if (cb) cb.checked = true; if (st) st.checked = true;
      }
    }
    /* Zusammenstellung ganz zeigen, auch die Summe am Ende */
    var passe = function () { if (!pkg || pkg.offsetParent === null) return; pkg.style.height = 'auto'; pkg.style.height = (pkg.scrollHeight + 4) + 'px'; };
    var mitAuswahl = function () { return has('individuell') && !!(pkg && pkg.value.trim()); };
    var date = $('f-date');
    if (date) {
      var d1 = new Date(); d1.setDate(d1.getDate() + 1); var d2 = new Date(); d2.setDate(d2.getDate() + 60);
      var iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
      date.min = iso(d1); date.max = iso(d2);
    }
    var reveal = function (el, show) {
      if (!el || el.hidden === !show) return;
      el.hidden = !show;
      if (show && !reduce && el.animate) el.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 280, easing: 'cubic-bezier(.2,.7,.2,1)' });
    };
    var sync = function () {
      reveal($('tier-field'), has('betreuung'));
      reveal($('termin-field'), has('termin'));
      reveal($('pkg-field'), has('individuell') && !!pkg.value); passe();
      /* Mit Zusammenstellung ist die eigene Nachricht freiwillig */
      var mo = $('f-msg'); if (mo) { if (mitAuswahl()) mo.removeAttribute('required'); else mo.setAttribute('required', ''); }
      $('url-hint').textContent = has('check') ? (EN ? '(needed for the check)' : '(für den Check nötig)') : '(optional)';
      var only = has('termin') && form.querySelectorAll('input[name="pakete[]"]:checked').length === 1;
      btn.textContent = only ? (EN ? 'Request appointment' : 'Termin anfragen') : T.send;
    };
    chips.forEach(function (c) { c.addEventListener('change', sync); });
    if (date) date.addEventListener('change', function () { date.setAttribute('aria-invalid', 'false'); $('e-date').hidden = true; });
    sync();
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = $('f-name'), mail = $('f-mail'), url = $('f-url'), tel = $('f-tel'), ok = $('f-ok'), msg = $('f-msg');
      var uVal = url.value.trim(), tVal = tel.value.trim();
      var checks = [[name, name.value.trim().length > 1, 'e-name'], [mail, reMail.test(mail.value.trim()), 'e-mail'],
                    [url, uVal ? reUrl.test(uVal) : !has('check'), 'e-url'], [tel, !tVal || reTel.test(tVal), 'e-tel'], [msg, msg.value.trim().length >= 10 || (mitAuswahl() && !msg.value.trim()), 'e-msg']];
      if (has('termin')) { var dv = date.value, dd = dv ? new Date(dv + 'T12:00:00') : null; checks.push([date, !!dd && dv >= date.min && dv <= date.max && dd.getDay() !== 0, 'e-date']); }
      mark(checks);
      var cOk = consent(ok, 'e-ok');
      var allOk = checks.every(function (x) { return x[1]; }) && cOk;
      if (!allOk) { res.className = 'msg bad'; res.textContent = T.check; res.hidden = false; (form.querySelector('[aria-invalid="true"]') || ok).focus(); return; }
      var data = new FormData(form);
      data.set('sprache', EN ? 'en' : 'de'); data.set('seite', location.pathname);
      btn.disabled = true; btn.textContent = T.sending; res.hidden = true;
      var termin = has('termin'), ok0 = ok;
      post(form.getAttribute('action'), data).then(function () {
        track('f', termin ? 'termin' : 'kontakt');
        sentBox(form, termin ? T.okTermin : T.ok, T.okText);
      }).catch(function (er) {
        var f = er.data && er.data.fields;
        var map = { name: ['f-name', 'e-name'], email: ['f-mail', 'e-mail'], website_url: ['f-url', 'e-url'], telefon: ['f-tel', 'e-tel'], termin: ['f-date', 'e-date'], nachricht: ['f-msg', 'e-msg'] };
        if (f && f.indexOf('einwilligung') > -1) consent(ok0, 'e-ok');
        if (f && f.length) { mark(f.map(function (k) { var m = map[k]; return m ? [$(m[0]), false, m[1]] : null; }).filter(Boolean)); res.className = 'msg bad'; res.textContent = T.check; }
        else { res.className = 'msg bad'; res.innerHTML = T.fail + MAIL; }
        res.hidden = false; btn.disabled = false; sync();
      });
    });
  }

  /* ---------- Eigene Frage (/fragen) ---------- */
  var ask = $('ask');
  if (ask) {
    var aRes = $('a-res'), aBtn = $('a-send');
    ask.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = $('a-name'), m = $('a-mail'), g = $('a-msg'), ok = $('a-ok');
      var checks = [[n, n.value.trim().length > 1, 'ea-name'], [m, reMail.test(m.value.trim()), 'ea-mail'], [g, g.value.trim().length > 4, 'ea-msg']];
      mark(checks); var cOk = consent(ok, 'ea-ok');
      if (!(checks.every(function (x) { return x[1]; }) && cOk)) { aRes.className = 'msg bad'; aRes.textContent = T.check; aRes.hidden = false; (ask.querySelector('[aria-invalid="true"]') || ok).focus(); return; }
      var data = new FormData(ask); data.set('sprache', EN ? 'en' : 'de'); data.set('seite', location.pathname);
      aBtn.disabled = true; aBtn.textContent = T.sending;
      post(ask.getAttribute('action'), data).then(function () { track('f', 'frage'); sentBox(ask, T.askOk, T.okText); })
        .catch(function () { aRes.className = 'msg bad'; aRes.innerHTML = T.fail + MAIL; aRes.hidden = false; aBtn.disabled = false; aBtn.textContent = T.askSend; });
    });
  }

  /* ---------- Feedback-Lasche ---------- */
  var fbOpen = $('fb-open'), fbPanel = $('fb-panel'), fbVon = null;
  function closeFb() {
    if (!fbPanel || fbPanel.hidden) return;
    var ae = document.activeElement, drin = !ae || ae === document.body || fbPanel.contains(ae);
    fbPanel.classList.remove('show'); fbOpen.setAttribute('aria-expanded', 'false'); setTimeout(function () { fbPanel.hidden = true; }, reduce ? 0 : 260);
    /* Fokus zurück dorthin, wo das Feld geöffnet wurde (Lasche oder Link in der Fußzeile) */
    if (drin) { var z = fbVon && fbVon.getClientRects().length ? fbVon : fbOpen; if (z && z.getClientRects().length && getComputedStyle(z).visibility !== 'hidden') try { z.focus(); } catch (x) {} }
  }
  if (fbOpen && fbPanel) {
    var openFb = function (e) {
      if (!fbPanel.hidden) { closeFb(); return; }
      fbVon = e && e.currentTarget && e.currentTarget.focus ? e.currentTarget : fbOpen;
      fbPanel.hidden = false; void fbPanel.offsetWidth; fbPanel.classList.add('show'); fbOpen.setAttribute('aria-expanded', 'true');
      setTimeout(function () { var m = $('fb-msg'); if (m) m.focus(); }, 120);
    };
    fbOpen.addEventListener('click', openFb);
    document.querySelectorAll('[data-fb-open]').forEach(function (b) { b.addEventListener('click', openFb); });
    $('fb-close').addEventListener('click', closeFb);
    fbPanel.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = $('fb-msg'), mail = $('fb-mail'), ok = $('fb-ok'), r = $('fb-res'), b = $('fb-send');
      var mv = mail.value.trim();
      var checks = [[msg, msg.value.trim().length > 4, 'efb-msg'], [mail, !mv || reMail.test(mv), 'efb-mail']];
      mark(checks); var cOk = consent(ok, 'efb-ok');
      if (!(checks.every(function (x) { return x[1]; }) && cOk)) return;
      var data = new FormData(fbPanel); data.set('sprache', EN ? 'en' : 'de'); data.set('seite', location.pathname);
      b.disabled = true; b.textContent = T.sending;
      post(fbPanel.getAttribute('action'), data).then(function () {
        track('f', 'feedback');
        var body = fbPanel.querySelector('.fb-body'); body.innerHTML = '';
        var p = document.createElement('p'); p.className = 'fb-done'; p.textContent = T.fbOk; body.appendChild(p);
        setTimeout(closeFb, 2600);
      }).catch(function () { r.className = 'msg bad'; r.innerHTML = T.fail + MAIL; r.hidden = false; b.disabled = false; b.textContent = T.fbSend; });
    });
  }

  /* ---------- Sofort-Check ---------- */
  var qc = $('qcheck');
  if (qc) {
    var qIn = $('qc-url'), qBtn = $('qc-go'), qOut = $('qc-out'), qErr = $('qc-err');
    qc.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = qIn.value.trim();
      if (!reUrl.test(v)) { qErr.textContent = T.qcErr.url; qErr.hidden = false; qIn.setAttribute('aria-invalid', 'true'); qIn.focus(); return; }
      qIn.setAttribute('aria-invalid', 'false'); qErr.hidden = true;
      qBtn.disabled = true; qBtn.textContent = T.qcRun; qc.classList.add('busy');
      var data = new FormData(); data.set('url', v); data.set('sprache', EN ? 'en' : 'de');
      post('/api/check.php', data).then(function (r) {
        track('k', 'check');
        qOut.innerHTML = '';
        var wrapR = document.createElement('div'); wrapR.className = 'result qc-result';
        var head = document.createElement('div'); head.className = 'result-head';
        var hs = document.createElement('span'); hs.className = 'muted'; hs.textContent = T.qcFor + r.host;
        var sc = document.createElement('span'); sc.className = 'score'; sc.innerHTML = '<b>0</b><span>/5</span>';
        head.appendChild(hs); head.appendChild(sc); wrapR.appendChild(head);
        var barEl = document.createElement('div'); barEl.className = 'bar'; for (var i = 0; i < 5; i++) barEl.appendChild(document.createElement('i')); wrapR.appendChild(barEl);
        var ul = document.createElement('ul'); ul.className = 'checks';
        r.items.forEach(function (it) {
          var li = document.createElement('li'); li.className = it.ok ? 'ok' : 'no';
          li.innerHTML = '<b aria-hidden="true">' + (it.ok ? '✓' : '✕') + '</b><span></span><em></em><small></small>';
          li.querySelector('span').textContent = it.label; li.querySelector('em').textContent = it.ok ? T.qcPassed : T.qcMissing; li.querySelector('small').textContent = it.hint;
          ul.appendChild(li);
        });
        wrapR.appendChild(ul);
        var act = document.createElement('div'); act.className = 'row-actions';
        var a1 = document.createElement('a'); a1.className = 'btn btn--primary'; a1.href = kontaktUrl + '?pakete=check&url=' + encodeURIComponent(v); a1.textContent = T.qcTalk;
        var a2 = document.createElement('button'); a2.type = 'button'; a2.className = 'link'; a2.textContent = T.qcAgain;
        a2.addEventListener('click', function () { qOut.innerHTML = ''; qIn.value = ''; qIn.focus(); });
        act.appendChild(a1); act.appendChild(a2); wrapR.appendChild(act);
        qOut.appendChild(wrapR);
        var lis = ul.children, bars = barEl.children;
        [].forEach.call(lis, function (li, idx) { setTimeout(function () { li.classList.add('in'); }, reduce ? 0 : 120 + idx * 140); });
        for (var j = 0; j < r.score; j++) (function (j) { setTimeout(function () { bars[j].classList.add('on'); sc.querySelector('b').textContent = String(j + 1); }, reduce ? 0 : 200 + j * 160); })(j);
        go(wrapR);
      }).catch(function (er) {
        var code = (er.data && er.data.error) || 'server';
        qErr.textContent = T.qcErr[code] || T.qcErr.server; qErr.hidden = false;
      }).then(function () { qBtn.disabled = false; qBtn.textContent = T.qcGo; qc.classList.remove('busy'); });
    });
  }

  /* ---------- Blockregale (Version 26): gleitende Farbblöcke links und rechts ----------
     Nur wenn links und rechts genug Platz ist. Jede Spur gleitet langsam und endlos nach oben oder unten,
     beim Scrollen gehen die Spuren ein Stück mit (abwechselnd mit und gegen die Richtung).
     Die Stellung bleibt beim Seitenwechsel erhalten (Zeit + sessionStorage), damit nichts springt.
     Bewegung reduziert: Blöcke stehen still. Der Knopf unten links hält alles an (wird gemerkt). */
  (function () {
    var root0 = document.body;
    if (!root0 || !window.matchMedia) return;
    var MQ = matchMedia('(min-width: 1480px)'), RM = matchMedia('(prefers-reduced-motion: reduce)'), FC = matchMedia('(forced-colors: active)');
    var kannAnim = typeof Element !== 'undefined' && typeof Element.prototype.animate === 'function';
    /* Farbe (p Petrol, i Schwarz, l Gelbgrün, m Mint mit Punkten, g Lücke) und Höhe in Spurbreiten */
    var FOLGEN = [
      'p2.6 m1.3 g1.8 p1.6 g2.0 m2.2 l0.7 g1.6 p2.0 g2.4',
      'm2.4 g1.4 l0.8 p1.8 m1.2 g2.2 p2.8 g1.6 m1.6 g2.0',
      'i2.2 g1.6 m1.6 g1.2 i1.4 l0.9 m2.6 g2.4 i2.8 g1.8',
      'm1.8 i2.6 g2.0 m1.2 g1.4 l0.8 i1.6 g2.2 m2.4 g1.6',
      'p2.2 g1.8 i1.8 l0.8 g2.0 p2.8 g1.4 i1.2 g2.2',
      'i2.4 g1.6 p1.6 m1.4 g2.2 l0.8 p2.6 g1.8 i1.4 g1.6',
      'l0.9 m2.0 g1.8 p2.4 g1.2 i1.6 m1.2 g2.4 p1.6 g1.6',
      'p1.8 g2.0 m2.6 i1.4 g1.8 p2.2 l0.7 g2.2 m1.4 g1.4',
      'i1.8 g1.6 m1.6 p2.4 g2.2 m1.2 l0.7 g1.8 i2.2 g2.0',
      'm1.6 g2.2 i2.4 l0.8 g1.4 p1.4 m2.2 g2.0 i1.2 g1.6',
      'p2.4 g1.4 m1.4 i1.8 g2.4 p1.6 g1.8 l0.9 m2.4 g1.6',
      'm2.2 p1.4 g2.0 i2.6 l0.8 g1.6 m1.6 g1.4 p2.0 g2.2'
    ];
    var BREITE = [1, 1.08, .94, 1.04, .97, 1.06];   /* von innen nach außen */
    var TEMPO = [.24, .18, .21, .16, .19, .15];    /* Spurbreiten pro Sekunde (bei „weniger Bewegung“ ein Drittel davon) */
    var MIT = [.24, -.16, .14, -.12, .1, -.08];    /* Anteil der Scrollbewegung */
    var KEY = 'lou-regal', HALT = 'lou-regal-halt', LUECKE = 10, MIN = 40, MAX = 176;
    var box = null, spuren = [], knopf = null, lastY = 0, raf = 0, timer = 0, ruhe = false, ersterBau = true;
    try { ruhe = localStorage.getItem(HALT) === '1'; } catch (e) {}

    function mod(a, b) { return ((a % b) + b) % b; }
    /* Version 28: Bei „weniger Bewegung“ (Windows: Animationseffekte aus) gleiten die Blöcke langsam weiter,
       ohne Mitlaufen beim Scrollen und ohne Einflug. Der Knopf unten links hält alles an. */
    function still() { return !kannAnim; }
    function ruhig() { return RM.matches; }
    function bewegt() { return !still() && !ruhe && !ruhig(); }
    function lesen() { try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
    function merken() {
      if (!spuren.length) return;
      var o = {};
      spuren.forEach(function (l) { o[l.id] = Math.round(mod(l.o, l.p) / l.p * 10000) / 10000; });
      try { sessionStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {}
    }
    function zahl(w) {
      if (w < MIN) return 0;
      var n = w < 124 ? 1 : w < 236 ? 2 : 3;
      while ((w - (n - 1) * LUECKE) / n > MAX && n < 8) n++;
      return n;
    }
    function setzen(l) { l.zug.style.transform = 'translate3d(0,' + (-mod(l.o, l.p)).toFixed(2) + 'px,0)'; }

    function abbauen() {
      if (raf) { cancelAnimationFrame(raf); raf = 0; }
      spuren.forEach(function (l) { if (l.anim) l.anim.cancel(); });
      spuren = [];
      if (box && box.parentNode) box.parentNode.removeChild(box);
      box = null;
      doc.classList.remove('regal-an');
    }

    function bauen() {
      var alt = spuren.length ? (merken(), lesen()) : lesen();
      abbauen();
      if (!MQ.matches || FC.matches) return;
      box = document.createElement('div');
      box.className = 'regal';
      box.setAttribute('aria-hidden', 'true');
      var seiten = ['l', 'r'].map(function (s) { var el = document.createElement('div'); el.className = 'rg-seite rg-seite--' + s; box.appendChild(el); return el; });
      root0.appendChild(box);
      var n = zahl(seiten[0].clientWidth);
      if (!n) { abbauen(); return; }
      var hoch = Math.max(window.innerHeight || 0, doc.clientHeight || 0, 600);
      seiten.forEach(function (seite, s) {
        for (var i = 0; i < n; i++) {
          var spur = document.createElement('div'); spur.className = 'rg-spur';
          spur.style.setProperty('--f', String(BREITE[i % BREITE.length]));
          var zug = document.createElement('div'); zug.className = 'rg-zug';
          var band = document.createElement('div'); band.className = 'rg-band';
          zug.appendChild(band); spur.appendChild(zug); seite.appendChild(spur);
          spuren.push({ id: (s ? 'r' : 'l') + i, i: i, s: s, el: spur, zug: zug, band: band, folge: FOLGEN[(i * 2 + s) % FOLGEN.length].split(' ') });
        }
      });
      /* eine Folge je Spur, dann messen und so oft wiederholen, dass es nahtlos weitergeht */
      spuren.forEach(function (l) { anhaengen(l); });
      spuren.forEach(function (l) { l.b = l.el.getBoundingClientRect().width || 1; l.p = l.band.getBoundingClientRect().height + LUECKE; });
      spuren.forEach(function (l) {
        var mal = Math.ceil(hoch / l.p) + 2;
        for (var m = 1; m < mal; m++) anhaengen(l);
        var saat = ((l.i * 7 + l.s * 3) % 10) / 10;
        l.o = (alt && typeof alt[l.id] === 'number' ? alt[l.id] : saat) * l.p;
        l.k = MIT[l.i % MIT.length] * (l.s ? 1.08 : 1);
        setzen(l);
        if (!still()) {
          var hoch2 = l.i % 2 === 0, d = l.p / (TEMPO[l.i % TEMPO.length] * (l.s ? 1.07 : 1) * (ruhig() ? 0.34 : 1) * l.b) * 1000;
          var a = [{ transform: 'translate3d(0,0,0)' }, { transform: 'translate3d(0,' + (-l.p).toFixed(2) + 'px,0)' }];
          var kurve = ruhig() ? 'linear' : atemKurve(d, l.i, l.s);
          try { l.anim = l.band.animate(hoch2 ? a : a.reverse(), { duration: d, iterations: Infinity, easing: kurve }); }
          catch (x) { l.anim = l.band.animate(hoch2 ? a : a.reverse(), { duration: d, iterations: Infinity, easing: 'linear' }); }
          l.anim.currentTime = (Date.now() + saat * d) % d;
          if (ruhe) l.anim.pause();
        }
      });
      lastY = window.scrollY || 0;
      if (!still()) {
        doc.classList.add('regal-an');
        knopfBauen();
        if (ersterBau && !ruhig()) {
          if (!alt) spuren.forEach(function (l) {
            l.el.animate([{ opacity: 0, transform: 'translate3d(0,56px,0)' }, { opacity: 1, transform: 'none' }],
              { duration: 1400, delay: 150 + l.i * 110 + l.s * 60, easing: 'cubic-bezier(.05,.7,.1,1)', fill: 'backwards' });
          });
          else box.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 260, easing: 'ease-out' });
        }
      }
      ersterBau = false;
    }

    /* Atmen: jede Spur wird langsam schneller und wieder langsamer (±22 %, eigener Rhythmus von 17 bis 29 Sekunden).
       Version 28b: als Zeitkurve linear() der Animation statt eines Timers, damit nichts auf dem Hauptthread läuft.
       Ort p(t) = t + A·(sin(2πkt + φ) − sin φ) / 2πk, Tempo 1 + A·cos(2πkt + φ), am Übergang gleich (k ganzzahlig). */
    var LINEAR_OK = !!(window.CSS && CSS.supports && CSS.supports('animation-timing-function', 'linear(0, 0.5 40%, 1)'));
    function atemKurve(d, i, s) {
      if (!LINEAR_OK) return 'linear';
      var per = (17 + (i * 5 + s * 3) % 13) * 1000, ph = i * 1.7 + s * 2.9, A = 0.22, k = Math.max(1, Math.round(d / per));
      var n = Math.min(240, 24 * k), w = 2 * Math.PI * k, teile = ['0'];
      for (var j = 1; j < n; j++) {
        var t = j / n, p = t + A * (Math.sin(w * t + ph) - Math.sin(ph)) / w;
        teile.push(p.toFixed(5) + ' ' + (t * 100).toFixed(3) + '%');
      }
      teile.push('1');
      return 'linear(' + teile.join(', ') + ')';
    }

    function anhaengen(l) {
      var frag = document.createDocumentFragment();
      l.folge.forEach(function (t) {
        var b = document.createElement('div');
        b.className = 'rg-b rg-b--' + t.charAt(0);
        b.style.setProperty('--k', t.slice(1));
        frag.appendChild(b);
      });
      l.band.appendChild(frag);
    }

    function knopfBauen() {
      if (knopf) return;
      knopf = document.createElement('button');
      knopf.type = 'button';
      knopf.className = 'regal-halt';
      knopf.setAttribute('aria-label', T.halt);
      knopf.title = T.halt;
      knopf.setAttribute('data-track', 'Bewegung anhalten');
      knopf.setAttribute('aria-pressed', ruhe ? 'true' : 'false');
      knopf.innerHTML = '<svg viewBox="0 0 12 12" aria-hidden="true"><path class="ico-halt" d="M2 1h3v10H2zM7 1h3v10H7z"/><path class="ico-weiter" d="M3 1l8 5-8 5z"/></svg>';
      knopf.addEventListener('click', function () {
        ruhe = !ruhe;
        knopf.setAttribute('aria-pressed', ruhe ? 'true' : 'false');
        try { if (ruhe) localStorage.setItem(HALT, '1'); else localStorage.removeItem(HALT); } catch (e) {}
        spuren.forEach(function (l) { if (l.anim) { if (ruhe) l.anim.pause(); else l.anim.play(); } });
        lastY = window.scrollY || 0;
      });
      /* früh in der Tab-Reihenfolge, direkt nach dem Sprunglink */
      var sk = document.querySelector('.skip');
      if (sk && sk.parentNode === root0) root0.insertBefore(knopf, sk.nextSibling); else root0.insertBefore(knopf, root0.firstChild);
    }

    function schritt() {
      raf = 0;
      var y = window.scrollY || 0, d = y - lastY;
      lastY = y;
      if (!d || !bewegt()) return;
      for (var j = 0; j < spuren.length; j++) { var l = spuren[j]; l.o += d * l.k; setzen(l); }
    }
    addEventListener('scroll', function () { if (spuren.length && !raf) raf = requestAnimationFrame(schritt); }, { passive: true });
    addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(bauen, 180); });
    addEventListener('pageshow', function () { lastY = window.scrollY || 0; });
    addEventListener('pagehide', merken);
    addEventListener('pageswap', merken);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') merken(); });
    [MQ, RM, FC].forEach(function (m) { if (m.addEventListener) m.addEventListener('change', bauen); else if (m.addListener) m.addListener(bauen); });
    bauen();
  })();
  /* ---------- Version 28b: Abschnitte außer Sicht markieren (endlose Animationen halten dort an) ---------- */
  (function () {
    if (!('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { e.target.classList.toggle('aus-sicht', !e.isIntersecting); }); }, { rootMargin: '200px 0px' });
    document.querySelectorAll('body > main > section, body > main > .band, body > main > .tint').forEach(function (el) { io.observe(el); });
  })();
  /* ---------- Kopfzeile (Version 27): passt immer in eine Zeile ----------
     Reicht der Platz nicht (große Schrift unter Android, schmale Handys, Tablets quer), werden nacheinander
     Stufen gesetzt: Navigation ins Menü, Knopf „Check anfordern“ ausblenden, alles etwas enger, Schriftzug
     am Logo ausblenden (das Logo bleibt, sein Name steht im aria-label). */
  (function () {
    var w = document.querySelector('.top .wrap'); if (!w) return;
    var logo = w.querySelector('.logo'), act = w.querySelector('.top-actions'), nv = w.querySelector('.nav');
    if (!logo || !act) return;
    var STUFEN = ['kopf-nav', 'kopf-cta', 'kopf-eng', 'kopf-mini'];
    function passt() {
      if (w.scrollWidth > w.clientWidth + 1) return false;
      if (logo.scrollWidth > logo.clientWidth + 1) return false;
      var l = logo.getBoundingClientRect(), a = act.getBoundingClientRect();
      if (l.right > a.left - 6) return false;
      var wr = w.getBoundingClientRect(), rechts = Math.min(wr.right - (parseFloat(getComputedStyle(w).paddingRight) || 0), document.documentElement.clientWidth);
      if (a.right > rechts + 0.5) return false;
      if (nv && getComputedStyle(nv).position !== 'absolute' && getComputedStyle(nv).display !== 'none') {
        var n = nv.getBoundingClientRect();
        if (n.left < l.right + 6 || n.right > a.left - 6 || nv.scrollWidth > nv.clientWidth + 1) return false;
      }
      return true;
    }
    var stand = -1;
    function anpassen() {
      var vorher = stand;
      STUFEN.forEach(function (c) { doc.classList.remove(c); });
      stand = 0;
      while (!passt() && stand < STUFEN.length) { doc.classList.add(STUFEN[stand]); stand++; }
      // aus dem Menü-Zustand heraus: offenes Menü schließen
      if (vorher > 0 && stand === 0 && nv && nv.classList.contains('open')) closeMenu();
    }
    var plan = 0;
    function bald() { if (plan) return; plan = requestAnimationFrame(function () { plan = 0; anpassen(); }); }
    anpassen();
    window.addEventListener('resize', bald);
    window.addEventListener('orientationchange', bald);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(bald);
    if (window.ResizeObserver) { var ro = new ResizeObserver(bald); [w, logo, act].forEach(function (x) { ro.observe(x); }); }
    window.addEventListener('load', bald);
  })();
})();
