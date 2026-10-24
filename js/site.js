/* Lou Digital, Version 12 (24.10.2026): Menü, Sprache, Hereinladen, Fragen, Paketauswahl, Baukasten,
   Anfrage mit Terminwunsch, Sofort-Check, Feedback, Reichweitenmessung ohne Cookies */
(function () {
  'use strict';
  window.__ldReady = true;
  var doc = document.documentElement;
  var EN = doc.lang === 'en';
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var T = EN ? {
    free: 'free', month: '/mo', arr: 'by arrangement', wish: 'Request: ', note: 'Pilot prices, no VAT charged (§ 19 UStG).', noteX: ' We discuss extras together.',
    once: 'One-off', monthly: 'Monthly', time: 'Timeframe', plus: ' plus extras',
    check: 'Please check the highlighted fields.', sending: 'Sending', send: 'Send inquiry',
    fail: 'The message could not be sent. Please try again in a few minutes or write to ', ok: 'Thank you, your inquiry has arrived.',
    okTermin: 'Thank you, your appointment request has arrived.',
    okText: 'You will receive a confirmation by email in a moment. I will reply personally, usually within 24 hours on working days.',
    reqSel: 'Request this selection', reqCheck: 'Request the free check', freeNote: 'The check is free and without obligation. Add building blocks for a price.',
    askOk: 'Thank you, your question has arrived.', askSend: 'Send question',
    picked: function (n) { return n === 1 ? '1 package selected' : n + ' packages selected'; }, perMonth: '/month', oneOff: ' one-off',
    fbOk: 'Thank you! Your feedback has arrived.', fbSend: 'Send',
    qcRun: 'Checking', qcGo: 'Check now', qcPassed: 'passed', qcMissing: 'missing',
    qcErr: { url: 'Please enter an address like your-company.com.', dns: 'This address doesn’t seem to exist.', unreachable: 'The website did not respond. Is the address correct?', rate: 'Too many checks in a short time. Please try again later.', server: 'The check is not available right now. Please send an inquiry instead.' },
    qcTalk: 'Discuss the result', qcAgain: 'Check another website', qcFor: 'Result for ',
    eur: function (n) { return '€' + n.toLocaleString('en-GB'); }
  } : {
    free: 'kostenlos', month: '/M', arr: 'nach Absprache', wish: 'Wunsch: ', note: 'Pilotpreise, ohne Umsatzsteuer (§ 19 UStG).', noteX: ' Extras kläre ich mit Ihnen im Gespräch.',
    once: 'Einmalig', monthly: 'Monatlich', time: 'Zeitrahmen', plus: ' plus Extras',
    check: 'Bitte prüfen Sie die markierten Felder.', sending: 'Wird gesendet', send: 'Anfrage senden',
    fail: 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es in einigen Minuten noch einmal oder schreiben Sie an ', ok: 'Danke, Ihre Anfrage ist angekommen.',
    okTermin: 'Danke, Ihr Terminwunsch ist angekommen.',
    okText: 'Sie bekommen gleich eine Bestätigung per E-Mail. Ich antworte Ihnen persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.',
    reqSel: 'Zusammenstellung anfragen', reqCheck: 'Kostenlosen Check anfordern', freeNote: 'Der Check ist kostenlos und unverbindlich. Für einen Preis fügen Sie Bausteine hinzu.',
    askOk: 'Danke, Ihre Frage ist angekommen.', askSend: 'Frage senden',
    picked: function (n) { return n === 1 ? '1 Paket gewählt' : n + ' Pakete gewählt'; }, perMonth: '/Monat', oneOff: ' einmalig',
    fbOk: 'Danke! Ihr Feedback ist angekommen.', fbSend: 'Senden',
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
  if (!reduce && window.Lenis) { lenis = new Lenis({ lerp: 0.09, wheelMultiplier: 0.9 }); (function raf(t) { lenis.raf(t); requestAnimationFrame(raf); })(0); }
  var header = document.querySelector('.top');
  function go(el) { var off = -((header ? header.offsetHeight : 0) + 12); if (lenis) lenis.scrollTo(el, { offset: off, duration: 1.1 }); else window.scrollTo({ top: el.getBoundingClientRect().top + scrollY + off, behavior: reduce ? 'auto' : 'smooth' }); }
  var nav = $('nav'), menuBtn = $('menu-btn');
  function closeMenu() { if (!nav) return; nav.classList.remove('open'); menuBtn.setAttribute('aria-expanded', 'false'); }
  if (menuBtn) menuBtn.addEventListener('click', function () { var o = nav.classList.toggle('open'); menuBtn.setAttribute('aria-expanded', String(o)); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); closeFb(); } });
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) { var el = $(a.getAttribute('href').slice(1)); if (!el) return; e.preventDefault(); closeMenu(); go(el); });
  });
  var top = $('to-top');
  if (top) top.addEventListener('click', function () { if (lenis) lenis.scrollTo(0, { duration: 1.2 }); else window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
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
  var B = EN ? { web: ['Website in 7 days', 390], auto: ['Inquiry automation', 290], survey: ['Customer survey', 190], check: ['Website check', 0] }
             : { web: ['Website in 7 Tagen', 390], auto: ['Anfrage-Automatik', 290], survey: ['Kundenumfrage', 190], check: ['Website-Check', 0] };
  var C = EN ? { none: null, S: ['Maintenance S', 29], M: ['Maintenance M', 49], L: ['Maintenance L', 79] }
             : { none: null, S: ['Betreuung S', 29], M: ['Betreuung M', 49], L: ['Betreuung L', 79] };
  var X = EN ? { pages: 'More than 5 pages', lang: 'Second language', booking: 'Online booking integration', move: 'Migration of existing website' }
             : { pages: 'Mehr als 5 Seiten', lang: 'Zweite Sprache', booking: 'Online-Terminbuchung einbinden', move: 'Umzug bestehender Website' };
  var W = EN ? { asap: 'As soon as possible', later: 'In 1 to 2 months' } : { asap: 'So bald wie möglich', later: 'In 1 bis 2 Monaten' };
  function summary(sel) {
    var once = 0, month = 0, lines = [];
    sel.b.forEach(function (k) { var b = B[k]; if (!b) return; once += b[1]; lines.push([b[0], b[1] ? T.eur(b[1]) : T.free]); });
    var c = C[sel.c]; if (c) { month += c[1]; lines.push([c[0], T.eur(c[1]) + T.month]); }
    var extras = sel.x.map(function (k) { return X[k]; }).filter(Boolean);
    if (sel.wish) extras.push(T.wish + sel.wish);
    extras.forEach(function (x) { lines.push([x, T.arr]); });
    var text = lines.map(function (l) { return '- ' + l[0] + ': ' + l[1]; });
    text.push(T.once + ': ' + T.eur(once) + (extras.length ? T.plus : ''), T.monthly + ': ' + T.eur(month), T.time + ': ' + (W[sel.w] || W.asap));
    return { once: once, month: month, lines: lines, extras: extras.length, text: text.join('\n') };
  }
  function bump(el, v) { if (el.textContent !== v) { el.textContent = v; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); } }
  var builder = $('builder');
  if (builder) {
    var list = $('sum-list'), empty = $('sum-empty'), sendB = $('sum-send'), onceEl = $('sum-once'), monthEl = $('sum-month'), freeOnly = false;
    var current = function () {
      return {
        b: [].map.call(builder.querySelectorAll('input[name="b"]:checked'), function (i) { return i.value; }),
        c: (builder.querySelector('input[name="care"]:checked') || {}).value || 'none',
        x: [].map.call(builder.querySelectorAll('input[name="x"]:checked'), function (i) { return i.value; }),
        w: (builder.querySelector('input[name="when"]:checked') || {}).value || 'asap',
        wish: $('x-wish').value.trim().slice(0, 200)
      };
    };
    var calc = function () {
      var s = summary(current());
      list.innerHTML = '';
      s.lines.forEach(function (l) { var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('span'); a.textContent = l[0]; b.textContent = l[1]; li.appendChild(a); li.appendChild(b); list.appendChild(li); });
      var has = s.lines.length > 0, paid = s.lines.some(function (l) { return l[1] !== T.free; });
      freeOnly = has && !paid;
      empty.hidden = has; sendB.disabled = !has; sendB.textContent = freeOnly ? T.reqCheck : T.reqSel;
      bump(onceEl, T.eur(s.once)); bump(monthEl, T.eur(s.month));
      $('sum-note').textContent = freeOnly ? T.freeNote : T.note + (s.extras ? T.noteX : '');
    };
    builder.addEventListener('change', calc); $('x-wish').addEventListener('input', calc); calc();
    sendB.addEventListener('click', function () {
      var s = current();
      if (freeOnly) { location.href = kontaktUrl + '?pakete=check'; return; }
      var q = new URLSearchParams({ anliegen: 'individuell', b: s.b.join(','), c: s.c, x: s.x.join(','), w: s.w });
      try { if (s.wish) sessionStorage.setItem('ld-wish', s.wish); else sessionStorage.removeItem('ld-wish'); } catch (e) {}
      location.href = kontaktUrl + '?' + q.toString();
    });
  }

  /* ---------- Pakete auswählen (Tabelle) mit Leiste unten ---------- */
  var bar = $('pickbar');
  if (bar) {
    var picks = {}, tier = null, tierPrice = { S: 29, M: 49, L: 79 };
    var render = function () {
      var keys = Object.keys(picks), n = keys.length + (tier ? 1 : 0), once = 0;
      keys.forEach(function (k) { once += picks[k]; });
      document.querySelectorAll('[data-pick]').forEach(function (b) {
        var on = b.getAttribute('data-pick') === 'betreuung' ? b.getAttribute('data-tier') === tier : picks.hasOwnProperty(b.getAttribute('data-pick'));
        b.setAttribute('aria-pressed', String(on));
      });
      document.querySelectorAll('.tr[data-row]').forEach(function (row) { row.classList.toggle('is-picked', !!row.querySelector('[data-pick][aria-pressed="true"]')); });
      $('pb-count').textContent = T.picked(n);
      var parts = []; if (once || !tier) parts.push(T.eur(once) + T.oneOff); if (tier) parts.push(T.eur(tierPrice[tier]) + T.perMonth);
      $('pb-sum').textContent = parts.join(' · ');
      var q = keys.slice(); if (tier) q.push('betreuung');
      $('pb-go').setAttribute('href', kontaktUrl + '?pakete=' + q.join(',') + (tier ? '&stufe=' + tier : ''));
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
    $('pb-clear').addEventListener('click', function () { picks = {}; tier = null; render(); });
    render();
  }

  /* ---------- Anfrageformular mit Auswahl und Terminwunsch ---------- */
  var form = $('contact');
  if (form) {
    var res = $('f-res'), btn = $('f-send'), pkg = $('f-pkg'), chips = form.querySelectorAll('input[name="pakete[]"]');
    var has = function (v) { var el = form.querySelector('input[name="pakete[]"][value="' + v + '"]'); return !!(el && el.checked); };
    var q = new URLSearchParams(location.search);
    var want = (q.get('pakete') || '').split(',');
    if (q.get('anliegen')) want.push(q.get('anliegen'));
    want.forEach(function (v) { v = (v || '').replace(/[^a-z]/g, ''); var el = v && form.querySelector('input[name="pakete[]"][value="' + v + '"]'); if (el) el.checked = true; });
    if (/^[SML]$/.test(q.get('stufe') || '')) { var tr = form.querySelector('input[name="betreuung_stufe"][value="' + q.get('stufe') + '"]'); if (tr) tr.checked = true; }
    if (q.get('url')) $('f-url').value = q.get('url').slice(0, 200);
    if (q.get('anliegen') === 'individuell' && (q.get('b') || q.get('c'))) {
      var wish = ''; try { wish = sessionStorage.getItem('ld-wish') || ''; } catch (e) {}
      pkg.value = summary({ b: (q.get('b') || '').split(',').filter(Boolean), c: q.get('c') || 'none', x: (q.get('x') || '').split(',').filter(Boolean), w: q.get('w') || 'asap', wish: wish }).text;
    }
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
      reveal($('pkg-field'), has('individuell') && !!pkg.value);
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
                    [url, uVal ? reUrl.test(uVal) : !has('check'), 'e-url'], [tel, !tVal || reTel.test(tVal), 'e-tel'], [msg, msg.value.trim().length >= 10, 'e-msg']];
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
  var fbOpen = $('fb-open'), fbPanel = $('fb-panel');
  function closeFb() { if (!fbPanel || fbPanel.hidden) return; fbPanel.classList.remove('show'); fbOpen.setAttribute('aria-expanded', 'false'); setTimeout(function () { fbPanel.hidden = true; }, reduce ? 0 : 260); }
  if (fbOpen && fbPanel) {
    var openFb = function () {
      if (!fbPanel.hidden) { closeFb(); return; }
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
})();
