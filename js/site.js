/* Lou Digital, Version 10 (19.10.2026): Menü, Sprache, Hereinladen, Fragen, Paket-Baukasten, Anfrageformular */
(function () {
  'use strict';
  window.__ldReady = true;
  var doc = document.documentElement;
  var EN = doc.lang === 'en';
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var T = EN ? {
    free: 'free', month: '/mo', arr: 'by arrangement', wish: 'Request: ', note: 'Pilot prices, no VAT charged (§ 19 UStG).', noteX: ' We discuss extras together.',
    once: 'One-off', monthly: 'Monthly', time: 'Timeframe', plus: ' plus extras',
    check: 'Please check the highlighted fields.', sending: 'Sending', send: 'Send enquiry', reqSel: 'Request this selection', reqCheck: 'Request the free check', freeNote: 'The check is free and without obligation. Add building blocks for a price.', askOk: 'Thank you, your question has arrived.',
    fail: 'The enquiry could not be sent. Please try again in a few minutes or write to ', ok: 'Thank you, your enquiry has arrived.',
    eur: function (n) { return '€' + n.toLocaleString('en-GB'); }
  } : {
    free: 'kostenlos', month: '/M', arr: 'nach Absprache', wish: 'Wunsch: ', note: 'Pilotpreise, ohne Umsatzsteuer (§ 19 UStG).', noteX: ' Extras kläre ich mit Ihnen im Gespräch.',
    once: 'Einmalig', monthly: 'Monatlich', time: 'Zeitrahmen', plus: ' plus Extras',
    check: 'Bitte prüfen Sie die markierten Felder.', sending: 'Wird gesendet', send: 'Anfrage senden', reqSel: 'Zusammenstellung anfragen', reqCheck: 'Kostenlosen Check anfordern', freeNote: 'Der Check ist kostenlos und unverbindlich. Für einen Preis fügen Sie Bausteine hinzu.', askOk: 'Danke, Ihre Frage ist angekommen.',
    fail: 'Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es in einigen Minuten noch einmal oder schreiben Sie an ', ok: 'Danke, Ihre Anfrage ist angekommen.',
    eur: function (n) { return n.toLocaleString('de-DE') + ' €'; }
  };

  /* Weiches Scrollen */
  var lenis = null;
  if (!reduce && window.Lenis) { lenis = new Lenis({ lerp: 0.09, wheelMultiplier: 0.9 }); (function raf(t) { lenis.raf(t); requestAnimationFrame(raf); })(0); }
  var header = document.querySelector('.top');
  function go(el) { var off = -((header ? header.offsetHeight : 0) + 12); if (lenis) lenis.scrollTo(el, { offset: off, duration: 1.1 }); else window.scrollTo({ top: el.getBoundingClientRect().top + scrollY + off, behavior: reduce ? 'auto' : 'smooth' }); }

  /* Menü (mobil) */
  var nav = document.getElementById('nav'), menuBtn = document.getElementById('menu-btn');
  function closeMenu() { if (!nav) return; nav.classList.remove('open'); menuBtn.setAttribute('aria-expanded', 'false'); }
  if (menuBtn) menuBtn.addEventListener('click', function () { var o = nav.classList.toggle('open'); menuBtn.setAttribute('aria-expanded', String(o)); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });

  /* Sprung-Links innerhalb der Seite */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) { var el = document.getElementById(a.getAttribute('href').slice(1)); if (!el) return; e.preventDefault(); closeMenu(); go(el); });
  });
  var top = document.getElementById('to-top');
  if (top) top.addEventListener('click', function () { if (lenis) lenis.scrollTo(0, { duration: 1.2 }); else window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  /* Sprachumschalter: Punkt gleitet, dann Seitenwechsel */
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

  /* Hereinladen: jedes Element genau einmal, wenn es sichtbar wird */
  var items = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el) { io.observe(el); });
  } else { items.forEach(function (el) { el.classList.add('in'); }); }

  /* Fragen: weich auf- und zuklappen */
  document.querySelectorAll('.faq details').forEach(function (d) {
    var s = d.querySelector('summary'), body = d.querySelector('.a'), anim = null;
    if (d.open) d.classList.add('is-open');
    s.addEventListener('click', function (e) {
      e.preventDefault();
      if (anim) anim.cancel();
      var dur = reduce ? 160 : 420, ease = 'cubic-bezier(.2,.7,.2,1)';
      if (!d.open) {
        d.open = true; d.classList.add('is-open');
        var h = body.scrollHeight;
        anim = body.animate([{ height: '0px', opacity: 0 }, { height: h + 'px', opacity: 1 }], { duration: dur, easing: ease });
        anim.onfinish = function () { anim = null; };
      } else {
        d.classList.remove('is-open');
        anim = body.animate([{ height: body.offsetHeight + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], { duration: dur * 0.8, easing: ease });
        anim.onfinish = function () { d.open = false; anim = null; };
      }
    });
  });

  /* Paket-Baukasten */
  var B = EN ? { web: ['Website in 7 days', 390], auto: ['Enquiry automation', 290], survey: ['Customer survey', 190], check: ['Website check', 0] }
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

  var builder = document.getElementById('builder');
  if (builder) {
    var list = document.getElementById('sum-list'), empty = document.getElementById('sum-empty'), sendB = document.getElementById('sum-send');
    var freeOnly = false;
    var onceEl = document.getElementById('sum-once'), monthEl = document.getElementById('sum-month');
    function current() {
      return {
        b: [].map.call(builder.querySelectorAll('input[name="b"]:checked'), function (i) { return i.value; }),
        c: (builder.querySelector('input[name="care"]:checked') || {}).value || 'none',
        x: [].map.call(builder.querySelectorAll('input[name="x"]:checked'), function (i) { return i.value; }),
        w: (builder.querySelector('input[name="when"]:checked') || {}).value || 'asap',
        wish: document.getElementById('x-wish').value.trim().slice(0, 200)
      };
    }
    function bump(el, v) { if (el.textContent !== v) { el.textContent = v; el.classList.add('bump'); setTimeout(function () { el.classList.remove('bump'); }, 350); } }
    function calc() {
      var s = summary(current());
      list.innerHTML = '';
      s.lines.forEach(function (l) { var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('span'); a.textContent = l[0]; b.textContent = l[1]; li.appendChild(a); li.appendChild(b); list.appendChild(li); });
      var has = s.lines.length > 0, paid = s.lines.some(function (l) { return l[1] !== T.free; });
      freeOnly = has && !paid;
      empty.hidden = has; sendB.disabled = !has; sendB.textContent = freeOnly ? T.reqCheck : T.reqSel;
      bump(onceEl, T.eur(s.once)); bump(monthEl, T.eur(s.month));
      document.getElementById('sum-note').textContent = freeOnly ? T.freeNote : T.note + (s.extras ? T.noteX : '');
    }
    builder.addEventListener('change', calc); document.getElementById('x-wish').addEventListener('input', calc); calc();
    sendB.addEventListener('click', function () {
      var s = current(), q = freeOnly ? new URLSearchParams({ anliegen: 'check' }) : new URLSearchParams({ anliegen: 'individuell', b: s.b.join(','), c: s.c, x: s.x.join(','), w: s.w });
      if (freeOnly) { location.href = sendB.getAttribute('data-target') + '?' + q.toString(); return; }
      try { if (s.wish) sessionStorage.setItem('ld-wish', s.wish); else sessionStorage.removeItem('ld-wish'); } catch (e) {}
      location.href = sendB.getAttribute('data-target') + '?' + q.toString();
    });
  }

  /* Anfrageformular */
  var form = document.getElementById('contact');
  if (form) {
    var topic = document.getElementById('f-topic'), pkgField = document.getElementById('pkg-field'), pkg = document.getElementById('f-pkg');
    var res = document.getElementById('f-res'), btn = document.getElementById('f-send');
    var q = new URLSearchParams(location.search);
    if (q.get('anliegen') && topic.querySelector('option[value="' + q.get('anliegen').replace(/[^a-z-]/g, '') + '"]')) topic.value = q.get('anliegen');
    if (q.get('anliegen') === 'individuell' && (q.get('b') || q.get('c'))) {
      var wish = ''; try { wish = sessionStorage.getItem('ld-wish') || ''; } catch (e) {}
      var sel = { b: (q.get('b') || '').split(',').filter(Boolean), c: q.get('c') || 'none', x: (q.get('x') || '').split(',').filter(Boolean), w: q.get('w') || 'asap', wish: wish };
      pkg.value = summary(sel).text;
    }
    function sync() {
      var custom = topic.value === 'individuell';
      pkgField.hidden = !(custom && pkg.value);
      document.getElementById('url-hint').textContent = topic.value === 'check' ? (EN ? '(needed for the check)' : '(für den Check nötig)') : '(optional)';
    }
    topic.addEventListener('change', sync); sync();
    function show(t, bad, html) { res.className = 'msg' + (bad ? ' bad' : ''); if (html) res.innerHTML = t; else res.textContent = t; res.hidden = false; }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = document.getElementById('f-name'), mail = document.getElementById('f-mail'), url = document.getElementById('f-url'), tel = document.getElementById('f-tel'), ok = document.getElementById('f-ok');
      var needUrl = topic.value === 'check';
      var nOk = name.value.trim().length > 1, mOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mail.value.trim());
      var uVal = url.value.trim(), uOk = uVal ? /^(https?:\/\/)?[\w-]+(\.[\w-]+)+(\/\S*)?$/i.test(uVal) : !needUrl;
      var tVal = tel.value.trim(), tOk = !tVal || /^[+\d][\d\s\/()-]{5,24}$/.test(tVal);
      [[name, nOk, 'e-name'], [mail, mOk, 'e-mail'], [url, uOk, 'e-url'], [tel, tOk, 'e-tel']].forEach(function (x) { x[0].setAttribute('aria-invalid', String(!x[1])); document.getElementById(x[2]).hidden = x[1]; });
      document.getElementById('e-ok').hidden = ok.checked;
      if (!(nOk && mOk && uOk && tOk && ok.checked)) { show(T.check, true); var first = form.querySelector('[aria-invalid="true"]') || ok; first.focus(); return; }
      var data = new FormData(form);
      data.set('anliegen_text', topic.options[topic.selectedIndex].text);
      data.set('sprache', EN ? 'en' : 'de');
      btn.disabled = true; btn.textContent = T.sending;
      fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || r.status); return j; }); })
        .then(function () {
          var box = document.createElement('div'); box.className = 'sent msg'; box.setAttribute('role', 'status');
          var h = document.createElement('h3'); h.textContent = T.ok;
          var p = document.createElement('p'); p.textContent = EN ? 'You will receive a confirmation by email in a moment. I will reply personally, usually within 24 hours on working days.' : 'Sie bekommen gleich eine Bestätigung per E-Mail. Ich antworte Ihnen persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.';
          box.appendChild(h); box.appendChild(p);
          form.replaceWith(box); go(box);
        })
        .catch(function () { show(T.fail + '<a href="mailto:info@lou-digital.de">info@lou-digital.de</a>.', true, true); btn.disabled = false; btn.textContent = T.send; });
    });
  }

  /* Eigene Frage */
  var ask = document.getElementById('ask');
  if (ask) {
    var aRes = document.getElementById('a-res'), aBtn = document.getElementById('a-send');
    ask.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = document.getElementById('a-name'), m = document.getElementById('a-mail'), g = document.getElementById('a-msg'), ok = document.getElementById('a-ok');
      var nOk = n.value.trim().length > 1, mOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(m.value.trim()), gOk = g.value.trim().length > 4;
      [[n, nOk, 'ea-name'], [m, mOk, 'ea-mail'], [g, gOk, 'ea-msg']].forEach(function (x) { x[0].setAttribute('aria-invalid', String(!x[1])); document.getElementById(x[2]).hidden = x[1]; });
      document.getElementById('ea-ok').hidden = ok.checked;
      if (!(nOk && mOk && gOk && ok.checked)) { aRes.className = 'msg bad'; aRes.textContent = T.check; aRes.hidden = false; (ask.querySelector('[aria-invalid="true"]') || ok).focus(); return; }
      var data = new FormData(ask); data.set('sprache', EN ? 'en' : 'de');
      aBtn.disabled = true; aBtn.textContent = T.sending;
      fetch(ask.getAttribute('action'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || r.status); return j; }); })
        .then(function () {
          var box = document.createElement('div'); box.className = 'sent msg'; box.setAttribute('role', 'status');
          var h = document.createElement('h3'); h.textContent = T.askOk;
          var p = document.createElement('p'); p.textContent = EN ? 'You will receive a confirmation by email in a moment. I will answer personally, usually within 24 hours on working days.' : 'Sie bekommen gleich eine Bestätigung per E-Mail. Ich antworte Ihnen persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.';
          box.appendChild(h); box.appendChild(p); ask.replaceWith(box); go(box);
        })
        .catch(function () { aRes.className = 'msg bad'; aRes.innerHTML = T.fail + '<a href="mailto:info@lou-digital.de">info@lou-digital.de</a>.'; aRes.hidden = false; aBtn.disabled = false; aBtn.textContent = EN ? 'Send question' : 'Frage senden'; });
    });
  }
})();
