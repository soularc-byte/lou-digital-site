/* Lou Digital – Abläufe als Knoten mit Kabeln (Version 28, 28.10.2026)
   Ein Impuls wandert über die Kabel von Knoten zu Knoten, jeder Knoten leuchtet beim Eintreffen auf,
   verbundene Vorschau-Karten (data-fl-ab, data-fl-bis) laufen mit. Knoten sind Knöpfe, ein Klick springt
   zum Schritt und hält an. Läuft nur, solange der Ablauf im Bild ist. Bewegung reduziert: dieselbe Fahrt,
   etwas ruhiger, kleinere Welle. Ohne JavaScript bleibt die Liste mit allen Texten stehen.
   Stellt außerdem window.LouFluss bereit (Kabel, Tween, Kurven) für wissen.js. */
(function () {
  'use strict';
  var EN = document.documentElement.lang === 'en';
  var RM = window.matchMedia ? matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var NS = 'http://www.w3.org/2000/svg';
  var T = EN ? { an: 'Pause', aus: 'Play', schritt: function (i, n) { return 'Step ' + i + ' of ' + n; } }
             : { an: 'Anhalten', aus: 'Weiter', schritt: function (i, n) { return 'Schritt ' + i + ' von ' + n; } };

  /* ---------- Kurven wie in CSS (cubic-bezier) ---------- */
  function bezier(x1, y1, x2, y2) {
    function a(p1, p2) { return 1 - 3 * p2 + 3 * p1; }
    function b(p1, p2) { return 3 * p2 - 6 * p1; }
    function c(p1) { return 3 * p1; }
    function kurve(t, p1, p2) { return ((a(p1, p2) * t + b(p1, p2)) * t + c(p1)) * t; }
    function steigung(t, p1, p2) { return 3 * a(p1, p2) * t * t + 2 * b(p1, p2) * t + c(p1); }
    return function (x) {
      if (x <= 0) return 0; if (x >= 1) return 1;
      var t = x;
      for (var i = 0; i < 8; i++) { var s = steigung(t, x1, x2); if (Math.abs(s) < 1e-6) break; t -= (kurve(t, x1, x2) - x) / s; }
      if (t < 0 || t > 1 || Math.abs(kurve(t, x1, x2) - x) > 1e-4) { var lo = 0, hi = 1; t = x; for (var j = 0; j < 30; j++) { var v = kurve(t, x1, x2); if (Math.abs(v - x) < 1e-5) break; if (v < x) lo = t; else hi = t; t = (lo + hi) / 2; } }
      return kurve(t, y1, y2);
    };
  }
  var BREMSEN = bezier(.16, .6, .2, 1);      /* schnell los, weich ankommen */
  var STANDARD = bezier(.2, .7, .2, 1);

  /* Zeitlich gesteuerter Ablauf mit requestAnimationFrame; stop() bricht ab */
  function tween(dauer, kurve, schritt) {
    var t0 = 0, raf = 0, aus = false, fertig;
    var p = new Promise(function (ok) { fertig = ok; });
    function lauf(t) {
      if (aus) return;
      if (!t0) t0 = t;
      var x = dauer > 0 ? Math.min(1, (t - t0) / dauer) : 1;
      schritt(kurve ? kurve(x) : x, x);
      if (x < 1) raf = requestAnimationFrame(lauf); else fertig(true);
    }
    raf = requestAnimationFrame(lauf);
    return { fertig: p, stop: function () { aus = true; cancelAnimationFrame(raf); fertig(false); } };
  }

  function svg(tag, attrs, eltern) {
    var e = document.createElementNS(NS, tag);
    for (var k in attrs) if (attrs.hasOwnProperty(k)) e.setAttribute(k, attrs[k]);
    if (eltern) eltern.appendChild(e);
    return e;
  }
  /* Kabel zwischen zwei Punkten: waagrecht leicht durchhängend, senkrecht mit kleinem Bogen */
  function kabel(a, b, senkrecht) {
    var f = function (n) { return Math.round(n * 10) / 10; };
    if (!senkrecht) {
      var dx = b.x - a.x, sag = Math.min(26, Math.abs(dx) * .24) + Math.abs(b.y - a.y) * .12 + 5;
      return 'M' + f(a.x) + ' ' + f(a.y) + ' C' + f(a.x + dx * .52) + ' ' + f(a.y + sag) + ' ' + f(b.x - dx * .52) + ' ' + f(b.y + sag) + ' ' + f(b.x) + ' ' + f(b.y);
    }
    var dy = b.y - a.y, bogen = Math.min(16, Math.abs(dy) * .4 + 5);
    return 'M' + f(a.x) + ' ' + f(a.y) + ' C' + f(a.x + bogen) + ' ' + f(a.y + dy * .45) + ' ' + f(b.x + bogen) + ' ' + f(b.y - dy * .45) + ' ' + f(b.x) + ' ' + f(b.y);
  }
  /* Pfeilspitze am Ende eines Pfads, ausgerichtet an der Kabelrichtung */
  function spitze(pfad, L, groesse) {
    var e = pfad.getPointAtLength(L), v = pfad.getPointAtLength(Math.max(0, L - 6));
    var w = Math.atan2(e.y - v.y, e.x - v.x), g = groesse || 6;
    var p1 = { x: e.x - g * Math.cos(w - .55), y: e.y - g * Math.sin(w - .55) }, p2 = { x: e.x - g * Math.cos(w + .55), y: e.y - g * Math.sin(w + .55) };
    return 'M' + p1.x.toFixed(1) + ' ' + p1.y.toFixed(1) + ' L' + e.x.toFixed(1) + ' ' + e.y.toFixed(1) + ' L' + p2.x.toFixed(1) + ' ' + p2.y.toFixed(1);
  }
  var nr = 0;
  /* Gemeinsame Teile für Kabel: Schatten, Draht, leuchtende Spur, Spitze, Stecker */
  function kabelBauen(eltern) {
    var g = svg('g', { 'class': 'fl-seg' }, eltern);
    var k = {
      g: g,
      schatten: svg('path', { 'class': 'fl-schatten' }, g),
      draht: svg('path', { 'class': 'fl-draht' }, g),
      glut: svg('path', { 'class': 'fl-glut' }, g),
      spitze: svg('path', { 'class': 'fl-spitze' }, g),
      aus: svg('circle', { 'class': 'fl-dose', r: 4.2 }, g),
      ein: svg('circle', { 'class': 'fl-dose fl-dose--ein', r: 4.2 }, g),
      L: 1, an: 0
    };
    return k;
  }
  function kabelSetzen(k, a, b, senkrecht) {
    var d = kabel(a, b, senkrecht);
    k.schatten.setAttribute('d', d); k.draht.setAttribute('d', d); k.glut.setAttribute('d', d);
    k.L = k.glut.getTotalLength() || 1;
    k.glut.style.strokeDasharray = k.L + ' ' + (k.L + 2);
    glutSetzen(k, k.an);
    k.spitze.setAttribute('d', spitze(k.draht, k.L));
    k.aus.setAttribute('cx', a.x); k.aus.setAttribute('cy', a.y); k.ein.setAttribute('cx', b.x); k.ein.setAttribute('cy', b.y);
  }
  function glutSetzen(k, anteil) { k.an = anteil; k.glut.style.strokeDashoffset = String(k.L * (1 - anteil)); k.g.classList.toggle('ist-an', anteil > .98); }
  function impulsBauen(eltern) {
    var id = 'flb' + (++nr);
    var defs = svg('defs', {}, eltern), fi = svg('filter', { id: id, x: '-100%', y: '-100%', width: '300%', height: '300%' }, defs);
    svg('feGaussianBlur', { stdDeviation: '3.2' }, fi);
    var g = svg('g', { 'class': 'fl-puls' }, eltern);
    svg('circle', { 'class': 'fl-puls-hof', r: 11, filter: 'url(#' + id + ')' }, g);
    svg('circle', { 'class': 'fl-puls-kern', r: 4.6 }, g);
    return g;
  }
  function impulsBei(g, p) { g.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ')'); }
  /* Fährt den Impuls über ein Kabel; die Spur leuchtet dahinter auf */
  function fahren(k, g, dauer) {
    g.classList.add('faehrt'); k.glut.style.transition = 'none';
    var t = tween(dauer, BREMSEN, function (e) { impulsBei(g, k.glut.getPointAtLength(k.L * e)); glutSetzen(k, e); });
    t.fertig.then(function (ok) { k.glut.style.transition = ''; g.classList.remove('faehrt'); if (ok) welle(k.ein); });
    return t;
  }
  /* Kleine Welle am Stecker, wenn der Impuls ankommt */
  function welle(dose) {
    var weit = RM.matches ? 6 : 12;   /* Bewegung reduziert: kleinere Welle */
    var w = svg('circle', { 'class': 'fl-welle', cx: dose.getAttribute('cx'), cy: dose.getAttribute('cy'), r: 5 }, dose.parentNode);
    var t = tween(650, bezier(0, 0, .2, 1), function (e) { w.setAttribute('r', (5 + weit * e).toFixed(2)); w.style.opacity = String(.7 * (1 - e)); });
    t.fertig.then(function () { if (w.parentNode) w.parentNode.removeChild(w); });
  }

  window.LouFluss = { bezier: bezier, tween: tween, kabel: kabel, svg: svg, kabelBauen: kabelBauen, kabelSetzen: kabelSetzen, glutSetzen: glutSetzen, impulsBauen: impulsBauen, impulsBei: impulsBei, fahren: fahren, BREMSEN: BREMSEN, STANDARD: STANDARD };

  /* ---------- Ablauf-Komponente ---------- */
  function Fluss(root) {
    var lw = root.querySelector('.fl-leinwand'), liste = root.querySelector('.fl-knoten');
    if (!lw || !liste) return;
    var knoten = Array.prototype.slice.call(liste.children), n = knoten.length;
    if (n < 2) return;
    var box = root.closest('.bsp') || root.parentNode;
    var mit = Array.prototype.slice.call(box.querySelectorAll('[data-fl-ab], [data-fl-bis]'));
    var pause = root.querySelector('.fl-pause'), dSchritt = root.querySelector('.fl-schritt'), dTitel = root.querySelector('.fl-dt'), dText = root.querySelector('.fl-dp'), detail = root.querySelector('.fl-detail');
    var flaeche = svg('svg', { 'class': 'fl-kabel', 'aria-hidden': 'true', focusable: 'false' });
    lw.appendChild(flaeche);
    var kabelTeile = [];
    for (var i = 0; i < n - 1; i++) kabelTeile.push(kabelBauen(flaeche));
    var puls = impulsBauen(flaeche);
    var schritt = -1, lauf = 0, angehalten = false, sichtbar = false, fokus = false, aktiv = null, achse = '';
    root.classList.add('fl-js'); box.classList.add('fl-an');

    function senkrecht() { return (getComputedStyle(liste).getPropertyValue('--fl-achse') || '').trim() === 'y'; }
    function messen() {
      var r = flaeche.getBoundingClientRect(), s = senkrecht(), py = parseFloat(getComputedStyle(liste).getPropertyValue('--fl-port')) || 30;
      achse = s ? 'y' : 'x';
      flaeche.setAttribute('viewBox', '0 0 ' + Math.max(1, r.width).toFixed(1) + ' ' + Math.max(1, r.height).toFixed(1));
      flaeche.setAttribute('width', r.width); flaeche.setAttribute('height', r.height);
      var kn = knoten.map(function (k) { var b = k.getBoundingClientRect(); return { l: b.left - r.left, t: b.top - r.top, r: b.right - r.left, b: b.bottom - r.top }; });
      for (var j = 0; j < n - 1; j++) {
        var a = kn[j], z = kn[j + 1], p, q;
        if (s) { p = { x: a.l + py, y: a.b }; q = { x: z.l + py, y: z.t }; }
        else { p = { x: a.r, y: a.t + py }; q = { x: z.l, y: z.t + py }; }
        kabelSetzen(kabelTeile[j], p, q, s);
      }
      if (schritt >= 0 && !puls.classList.contains('faehrt')) {
        var k0 = kabelTeile[Math.min(schritt, n - 2)];
        impulsBei(puls, schritt === 0 ? k0.draht.getPointAtLength(0) : kabelTeile[schritt - 1].draht.getPointAtLength(kabelTeile[schritt - 1].L));
      }
    }
    function zeigeDetail(i) {
      if (!detail) return;
      var k = knoten[i], b = k.querySelector('.fl-b b'), t = k.querySelector('.fl-text');
      if (dSchritt) dSchritt.textContent = T.schritt(i + 1, n);
      if (dTitel) dTitel.textContent = b ? b.textContent : '';
      if (dText) dText.textContent = t ? t.textContent : '';
      detail.classList.remove('wechsel'); void detail.offsetWidth; detail.classList.add('wechsel');
    }
    function setze(i, art) {
      schritt = i;
      knoten.forEach(function (k, j) {
        var war = k.classList.contains('ist-an');
        k.classList.toggle('ist-an', j === i); k.classList.toggle('ist-fertig', i >= 0 && j < i);
        var b = k.querySelector('.fl-b'); if (j === i) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        if (j === i && !war && art === 'kommt') { k.classList.remove('poppt'); void k.offsetWidth; k.classList.add('poppt'); }
      });
      if (art !== 'kommt') kabelTeile.forEach(function (k, j) { glutSetzen(k, i > j ? 1 : 0); });
      mit.forEach(function (el) {
        var ab = el.hasAttribute('data-fl-ab') ? +el.getAttribute('data-fl-ab') : -1, bis = el.hasAttribute('data-fl-bis') ? +el.getAttribute('data-fl-bis') : 99;
        el.classList.toggle('fl-da', i >= ab && i <= bis);
      });
      puls.classList.toggle('ist-weg', i < 0);
      if (i >= 0) {
        if (i === 0) impulsBei(puls, kabelTeile[0].draht.getPointAtLength(0));
        else impulsBei(puls, kabelTeile[i - 1].draht.getPointAtLength(kabelTeile[i - 1].L));
        zeigeDetail(i);
      }
    }
    function warte(ms, id) { return new Promise(function (ok, nein) { setTimeout(function () { if (id === lauf) ok(); else nein(0); }, ms); }); }
    function stopp() { lauf++; if (aktiv) { aktiv.stop(); aktiv = null; } puls.classList.remove('faehrt'); }
    function darfLaufen() { return !angehalten && sichtbar && !fokus && !document.hidden; }
    /* Verweildauer je Knoten etwas länger als die Fahrt; die Fahrten werden zum Ende hin kürzer */
    function fahrzeit(j) { return Math.round(1080 - j * (260 / Math.max(1, n - 2))); }
    function abspielen(von) {
      stopp(); var id = lauf;
      var start = von >= 0 && von < n ? von : 0;
      var kette = Promise.resolve();
      if (start === 0) {
        kette = kette.then(function () { setze(-1, 'reset'); return warte(650, id); })
          .then(function () { setze(0, 'kommt'); return warte(1250, id); });
      } else {
        kette = kette.then(function () { return warte(900, id); });
      }
      for (var j = start; j < n - 1; j++) (function (j) {
        kette = kette.then(function () {
          /* Bewegung reduziert: dieselbe Strecke, etwas ruhiger */
          aktiv = fahren(kabelTeile[j], puls, Math.round(fahrzeit(j) * (RM.matches ? 1.3 : 1)));
          return aktiv.fertig.then(function (ok) { aktiv = null; if (!ok || id !== lauf) throw 0; setze(j + 1, 'kommt'); return warte(j + 1 === n - 1 ? 2600 : 1250, id); });
        });
      })(j);
      kette.then(function () { if (id !== lauf) throw 0; if (darfLaufen()) abspielen(0); }).catch(function () {});
    }
    function weiter() { if (darfLaufen()) abspielen(schritt >= n - 1 ? 0 : schritt); else stopp(); }
    function pauseZeigen() {
      if (!pause) return;
      pause.classList.toggle('ist-pause', angehalten);
      var s = pause.querySelector('span'); if (s) s.textContent = angehalten ? T.aus : T.an;
    }
    knoten.forEach(function (k, j) {
      k.querySelector('.fl-b').addEventListener('click', function () {
        stopp(); angehalten = true; pauseZeigen();
        setze(j, 'sprung');
        k.classList.remove('poppt'); void k.offsetWidth; k.classList.add('poppt');
      });
    });
    if (pause) pause.addEventListener('click', function () {
      angehalten = !angehalten; pauseZeigen();
      if (angehalten) stopp(); else { fokus = false; weiter(); }
    });
    root.addEventListener('focusin', function (e) { if (e.target === pause) return; fokus = true; stopp(); });
    root.addEventListener('focusout', function () {
      setTimeout(function () { var a = document.activeElement; fokus = root.contains(a) && a !== pause; if (!fokus && !angehalten) weiter(); }, 0);
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stopp(); else weiter(); });
    var plan = 0;
    function neuMessen() { if (plan) return; plan = requestAnimationFrame(function () { plan = 0; var vorher = achse; messen(); if (vorher && vorher !== achse && aktiv) { stopp(); weiter(); } }); }
    if (window.ResizeObserver) new ResizeObserver(neuMessen).observe(lw); else window.addEventListener('resize', neuMessen);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(neuMessen);
    window.addEventListener('load', neuMessen);
    messen();
    setze(n - 1, 'sprung');            /* ohne Bewegung: fertiger Zustand */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        var v = en[0].isIntersecting; if (v === sichtbar) return; sichtbar = v;
        if (v) { neuMessen(); weiter(); } else stopp();
      }, { threshold: .35 }).observe(lw);
    } else { sichtbar = true; weiter(); }
    if (RM.addEventListener) RM.addEventListener('change', function () { stopp(); weiter(); });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-fluss]'), function (r) {
    try { Fluss(r); } catch (e) { r.classList.remove('fl-js'); var bx = r.closest('.bsp'); if (bx) bx.classList.remove('fl-an'); }   /* im Fehlerfall alles normal zeigen */
  });
})();
