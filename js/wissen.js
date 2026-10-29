/* Lou Digital – Betriebswissen (28.10.2026): Weg einer Anfrage, Regler, Zeitrechner.
   Version 28 (28.10.2026): Beispiele oben, Kanäle und Betriebswissen anklickbar, Kabel mit Impuls zwischen den
   Bereichen (js/fluss.js), Drehknopf mit Zeiger, Teilstrichen und langsamem Einrasten, weiche Stufenwechsel.
   Bewegung reduziert: Ablauf und Impuls laufen ruhiger, der Knopf gleitet kurz ohne Nachschwingen, Wege höchstens 8 px.
   Alle Beispiele mit erfundenen Daten. Ohne JavaScript bleibt jeweils der erste Zustand sichtbar. */
(function () {
  'use strict';
  var EN = document.documentElement.lang === 'en';
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var F = window.LouFluss || null;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function warte(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }

  /* ---------- Weg einer Anfrage ---------- */
  var weg = $('[data-weg]');
  if (weg) {
    var BSP = EN ? [
      { k: 'wa', von: 'Mr Kramer', kanal: 'WhatsApp', text: 'Hi, how much would you charge to paint a facade of about 120 m²?', w: ['preis', 'regel', 'kalender', 'ton'],
        notiz: 'Price list and rule found', entwurf: '“Hello Mr Kramer, for about 120 m² the price is roughly €3,360 including materials. From 80 m² I inspect the facade for free first. Would Tuesday at 10 am work?”', fertig: 'Approved and sent at 9.14 am' },
      { k: 'mail', von: 'Ms Yılmaz', kanal: 'Email', text: 'Merhaba, randevumu perşembeye alabilir miyiz?', w: ['verlauf', 'kalender', 'sprache'],
        notiz: 'Translated from Turkish', entwurf: 'She would like to move her appointment to Thursday. Suggested slot Thursday 2 pm, the reply goes out in Turkish.', fertig: 'Approved, appointment moved' },
      { k: 'sprache', von: 'Mr Brandt', kanal: 'Voice message', text: '48 sec, “the heating in the basement is dripping, could you take a look this week?”', w: ['regel', 'verlauf', 'kalender'],
        notiz: 'Voice message as text, urgent case', entwurf: 'Urgent by your rule. Call back today before 5 pm. Customer since 2023, last visit in March.', fertig: 'Call back is in the daily plan' },
      { k: 'form', von: 'Hof Bakery', kanal: 'Form', text: 'We are looking for someone for our shop front, about 95 m², ideally in April.', w: ['preis', 'regel', 'kalender', 'ton'],
        notiz: 'New customer, price list and calendar', entwurf: '“Hello Ms Hof, for about 95 m² the price is roughly €2,660 including materials. From 80 m² I inspect the facade for free first. Would Wednesday at 9 am work?”', fertig: 'Approved, visit is in the calendar' },
      { k: 'tg', von: 'Mr Wagner', kanal: 'Telegram', text: 'Do you have time next week for a small water damage in the bathroom?', w: ['kalender', 'regel', 'ton'],
        notiz: 'Two free slots found', entwurf: '“Hello Mr Wagner, next week Monday at 8 am or Thursday at 3 pm would work. Which one suits you better?”', fertig: 'Approved, appointment reserved' },
      { k: 'sig', von: 'Ms Lenz', kanal: 'Signal', text: 'Has the invoice been sent yet?', w: ['verlauf', 'ton'],
        notiz: 'Invoice found in the customer history', entwurf: '“Hello Ms Lenz, the invoice went out to you by email on 21 October. I am happy to send it again.”', fertig: 'Approved, invoice sent again' }
    ] : [
      { k: 'wa', von: 'Herr Krämer', kanal: 'WhatsApp', text: 'Hallo, was kostet bei Ihnen ein Fassadenanstrich, etwa 120 m²?', w: ['preis', 'regel', 'kalender', 'ton'],
        notiz: 'Preisliste und Regel gefunden', entwurf: '„Guten Tag Herr Krämer, für etwa 120 m² liegt der Preis bei rund 3.360 € mit Material. Ab 80 m² schaue ich mir die Fassade vorher kostenlos an. Passt Ihnen Dienstag um 10 Uhr?“', fertig: 'Freigegeben und um 9.14 Uhr gesendet' },
      { k: 'mail', von: 'Frau Yılmaz', kanal: 'E-Mail', text: 'Merhaba, randevumu perşembeye alabilir miyiz?', w: ['verlauf', 'kalender', 'sprache'],
        notiz: 'Aus dem Türkischen übersetzt', entwurf: 'Sie möchte ihren Termin auf Donnerstag legen. Vorschlag Donnerstag um 14 Uhr, die Antwort geht auf Türkisch raus.', fertig: 'Freigegeben, Termin verschoben' },
      { k: 'sprache', von: 'Herr Brandt', kanal: 'Sprachnachricht', text: '48 Sek., „die Heizung im Keller tropft, können Sie sich das diese Woche anschauen?“', w: ['regel', 'verlauf', 'kalender'],
        notiz: 'Sprachnachricht als Text, dringend', entwurf: 'Dringend nach Ihrer Regel. Rückruf heute bis 17 Uhr. Kunde seit 2023, letzter Einsatz im März.', fertig: 'Rückruf steht im Tagesplan' },
      { k: 'form', von: 'Bäckerei Hof', kanal: 'Formular', text: 'Wir suchen jemanden für unsere Ladenfassade, etwa 95 m², gern im April.', w: ['preis', 'regel', 'kalender', 'ton'],
        notiz: 'Neuer Kunde, Preisliste und Kalender', entwurf: '„Guten Tag Frau Hof, für etwa 95 m² liegt der Preis bei rund 2.660 € mit Material. Ab 80 m² schaue ich mir die Fassade vorher kostenlos an. Passt Ihnen Mittwoch um 9 Uhr?“', fertig: 'Freigegeben, Besichtigung im Kalender' },
      { k: 'tg', von: 'Herr Wagner', kanal: 'Telegram', text: 'Haben Sie nächste Woche Zeit für einen kleinen Wasserschaden im Bad?', w: ['kalender', 'regel', 'ton'],
        notiz: 'Zwei freie Termine gefunden', entwurf: '„Hallo Herr Wagner, nächste Woche passt Montag um 8 Uhr oder Donnerstag um 15 Uhr. Welcher Termin ist Ihnen lieber?“', fertig: 'Freigegeben, Termin reserviert' },
      { k: 'sig', von: 'Frau Lenz', kanal: 'Signal', text: 'Ist die Rechnung schon raus?', w: ['verlauf', 'ton'],
        notiz: 'Rechnung im Kundenverlauf gefunden', entwurf: '„Hallo Frau Lenz, die Rechnung ist am 21.10. per E-Mail an Sie rausgegangen. Ich schicke sie Ihnen gern noch einmal.“', fertig: 'Freigegeben, Rechnung erneut gesendet' }
    ];
    var INFO = EN ? {
      preis: 'With your price list, the draft calculates with your real prices, for example per m² or per hour.',
      regel: 'Your rules and special cases decide what is urgent, when you visit first and what you don’t take on.',
      verlauf: 'The customer history shows earlier jobs, invoices and agreements, so nobody is asked twice.',
      kalender: 'Free slots come from your calendar, and the draft suggests them right away.',
      ton: 'Your tone and text snippets make sure the reply sounds like you.',
      sprache: 'Messages in other languages are translated, and the reply goes out in the customer’s language.'
    } : {
      preis: 'Mit Ihrer Preisliste rechnet der Entwurf mit echten Preisen, etwa pro m² oder pro Stunde.',
      regel: 'Ihre Regeln und Sonderfälle legen fest, was dringend ist, wann Sie zuerst vorbeikommen und was Sie nicht übernehmen.',
      verlauf: 'Der Kundenverlauf zeigt frühere Aufträge, Rechnungen und Absprachen, damit niemand doppelt gefragt wird.',
      kalender: 'Freie Termine kommen aus Ihrem Kalender, der Entwurf schlägt sie gleich vor.',
      ton: 'Ihr Ton und Ihre Textbausteine sorgen dafür, dass die Antwort klingt wie von Ihnen.',
      sprache: 'Nachrichten in anderen Sprachen werden übersetzt, die Antwort geht in der Sprache des Kunden raus.'
    };
    var karte = $('.weg-karte', weg), entwurf = $('.weg-entwurf', weg), notiz = $('.weg-notiz', weg), bahn = $('.weg-bahn', weg);
    var zonen = $$('.weg-zone', weg), kanaele = $$('.weg-kanaele li', weg), wissen = $$('.weg-wissen li', weg);
    var knoepfe = $$('[data-bsp]', weg), pause = $('.weg-pause', weg);
    var nr = 0, lauf = 0, angehalten = false, sichtbar = false, fokus = false;
    var T = EN ? { an: 'Pause', aus: 'Play', liest: 'Reading along' } : { an: 'Anhalten', aus: 'Weiter', liest: 'Liest mit' };

    /* Kabel mit Impuls zwischen den drei Bereichen */
    var kab = [], puls = null, fahrt = null;
    if (F && bahn && zonen.length === 3) {
      var fl = F.svg('svg', { 'class': 'fl-kabel weg-kabel', 'aria-hidden': 'true', focusable: 'false' });
      bahn.appendChild(fl);
      kab = [F.kabelBauen(fl), F.kabelBauen(fl)];
      puls = F.impulsBauen(fl); puls.classList.add('ist-weg');
      weg.classList.add('kabel-an');
      var messen = function () {
        var r = fl.getBoundingClientRect(); if (!r.width) return;
        fl.setAttribute('viewBox', '0 0 ' + r.width.toFixed(1) + ' ' + r.height.toFixed(1));
        var z = zonen.map(function (e) { var b = e.getBoundingClientRect(); return { l: b.left - r.left, t: b.top - r.top, r: b.right - r.left, b: b.bottom - r.top, w: b.width, h: b.height }; });
        var senk = z[1].t > z[0].b - 2;
        for (var i = 0; i < 2; i++) {
          var a = z[i], c = z[i + 1];
          if (senk) F.kabelSetzen(kab[i], { x: a.l + a.w / 2, y: a.b }, { x: c.l + c.w / 2, y: c.t }, true);
          else F.kabelSetzen(kab[i], { x: a.r, y: a.t + a.h * .5 }, { x: c.l, y: c.t + c.h * .5 }, false);
        }
        if (!fahrt && puls && !puls.classList.contains('ist-weg')) { var j = kab[1].an > .98 ? 1 : 0; F.impulsBei(puls, kab[j].draht.getPointAtLength(kab[j].an > .98 ? kab[j].L : 0)); }
      };
      var plan = 0, neu = function () { if (plan) return; plan = requestAnimationFrame(function () { plan = 0; messen(); }); };
      if (window.ResizeObserver) new ResizeObserver(neu).observe(bahn); else window.addEventListener('resize', neu);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(neu);
      window.addEventListener('load', neu);
      messen();
    }
    function kabelAus() { if (fahrt) { fahrt.stop(); fahrt = null; } kab.forEach(function (k) { F.glutSetzen(k, 0); }); if (puls) puls.classList.add('ist-weg'); }
    function kabelAn() { kab.forEach(function (k) { F.glutSetzen(k, 1); }); if (puls) { puls.classList.remove('ist-weg'); F.impulsBei(puls, kab[1].draht.getPointAtLength(kab[1].L)); } }
    function punkt(i, id) {
      if (!kab.length) return warte(600);
      puls.classList.remove('ist-weg');
      fahrt = F.fahren(kab[i], puls, reduce ? 1060 : 820);   /* Bewegung reduziert: etwas ruhiger */
      return fahrt.fertig.then(function (ok) { fahrt = null; if (!ok || id !== lauf) throw 0; });
    }
    function zeige(b, stufe) {
      // stufe 0 Nachricht da, 1 Wissen aktiv, 2 Entwurf, 3 freigegeben
      kanaele.forEach(function (li) { var an = li.getAttribute('data-k') === b.k; li.classList.toggle('ist-aktiv', an); var k = li.querySelector('button'); if (k) k.setAttribute('aria-pressed', String(an)); });
      $('.weg-von', karte).textContent = b.von; $('.weg-kanal', karte).textContent = b.kanal; $('p', karte).textContent = b.text;
      wissen.forEach(function (li) { li.classList.remove('ist-fokus'); li.classList.toggle('ist-an', stufe >= 1 && b.w.indexOf(li.getAttribute('data-w')) >= 0); });
      notiz.textContent = stufe >= 1 ? b.notiz : T.liest; notiz.classList.toggle('denkt', stufe < 1); notiz.classList.remove('erklaert');
      $('p', entwurf).textContent = b.entwurf; $('.weg-fertig', entwurf).textContent = b.fertig;
      entwurf.classList.toggle('ist-weg', stufe < 2); entwurf.classList.toggle('ist-frei', stufe >= 3);
      knoepfe.forEach(function (k) { k.setAttribute('aria-pressed', String(+k.getAttribute('data-bsp') === nr)); });
    }
    function laeuftNoch(id) { return id === lauf && !angehalten && sichtbar && !fokus && !document.hidden; }
    function stopp() { lauf++; if (fahrt) { fahrt.stop(); fahrt = null; } }
    function abspielen() {
      stopp(); var id = lauf;
      var b = BSP[nr];
      karte.classList.add('ist-weg'); entwurf.classList.add('ist-weg'); kabelAus();
      warte(reduce ? 260 : 460).then(function () { if (id !== lauf) throw 0; zeige(b, 0); karte.classList.remove('ist-weg'); return warte(1100); })
        .then(function () { if (id !== lauf) throw 0; return punkt(0, id); })
        .then(function () { if (id !== lauf) throw 0; var an = wissen.filter(function (li) { return b.w.indexOf(li.getAttribute('data-w')) >= 0; });
          an.forEach(function (li, i) { setTimeout(function () { if (id === lauf) li.classList.add('ist-an'); }, 80 + i * 300); });
          return warte(an.length * 300 + 520); })
        .then(function () { if (id !== lauf) throw 0; notiz.textContent = b.notiz; notiz.classList.remove('denkt'); return warte(900); })
        .then(function () { if (id !== lauf) throw 0; return punkt(1, id); })
        .then(function () { if (id !== lauf) throw 0; zeige(b, 2); return warte(2600); })
        .then(function () { if (id !== lauf) throw 0; zeige(b, 3); return warte(2800); })
        .then(function () { if (!laeuftNoch(id)) throw 0; nr = (nr + 1) % BSP.length; abspielen(); })
        .catch(function () {});
    }
    function weiter() { if (!angehalten && sichtbar && !fokus && !document.hidden) abspielen(); else stopp(); }
    function waehle(i) { nr = i; if (angehalten) { stopp(); zeige(BSP[nr], 3); if (kab.length) kabelAn(); } else abspielen(); }
    knoepfe.forEach(function (k) { k.addEventListener('click', function () { waehle(+k.getAttribute('data-bsp')); }); });
    kanaele.forEach(function (li) {
      var k = li.querySelector('button'); if (!k) return;
      k.addEventListener('click', function () { for (var i = 0; i < BSP.length; i++) if (BSP[i].k === li.getAttribute('data-k')) { waehle(i); return; } });
    });
    wissen.forEach(function (li) {
      var k = li.querySelector('button'), key = li.getAttribute('data-w'); if (!k) return;
      k.addEventListener('click', function () {
        stopp(); zeige(BSP[nr], 3); if (kab.length) kabelAn();
        li.classList.add('ist-fokus');
        notiz.textContent = INFO[key] || ''; notiz.classList.remove('denkt'); notiz.classList.add('erklaert');
        notiz.classList.remove('neu'); void notiz.offsetWidth; notiz.classList.add('neu');
      });
    });
    if (pause) {
      pause.addEventListener('click', function () {
        angehalten = !angehalten; pause.classList.toggle('ist-pause', angehalten);
        pause.querySelector('span').textContent = angehalten ? T.aus : T.an;
        if (angehalten) { stopp(); zeige(BSP[nr], 3); if (kab.length) kabelAn(); } else { fokus = false; abspielen(); }
      });
    }
    weg.addEventListener('focusin', function (e) { if (e.target === pause) return; fokus = true; });
    weg.addEventListener('focusout', function () { setTimeout(function () { fokus = weg.contains(document.activeElement) && document.activeElement !== pause; if (!fokus) weiter(); }, 0); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) weiter(); else stopp(); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { var v = en[0].isIntersecting; if (v === sichtbar) return; sichtbar = v; if (v) weiter(); else stopp(); }, { threshold: 0.35 }).observe(weg);
    } else { sichtbar = true; weiter(); }
    zeige(BSP[0], 0);
  }

  /* ---------- Regler: Drehknopf mit Zeiger, Teilstrichen und Rastung ----------
     Ziehen dreht den Knopf mit leichtem Widerstand und spürbaren Rasten. Beim Loslassen gleitet er langsam
     in die Stufe, in deren Richtung gedreht wurde. Klick auf den Knopf: nächste Stufe. Pfeiltasten, Pos1, Ende.
     Der Posteingang wechselt weich: erst die Farbe, dann die Ordnung, zuletzt die Entwürfe. */
  var reg = $('[data-regler]');
  if (reg) {
    var knopf = $('.regler-knopf', reg), pe = $('.pe', reg), stufenKn = $$('[data-stufe-btn]', reg);
    var marken = $$('.regler-skala .marke', reg), spur = $('.regler-skala .spur', reg), strichGr = $('.regler-skala .striche', reg);
    var WINKEL = [-110, 0, 110], WMIN = -110, WMAX = 110;
    var WERTE = EN ? [
      { offen: 5, min: 15, antwort: 'after 1 to 2 days', info: '5 open in 4 apps', txt: 'Today, everything by hand' },
      { offen: 3, min: 8, antwort: 'the same day', info: '3 open, all in one place', txt: 'With workflows, sorted and confirmed' },
      { offen: 0, min: 2, antwort: 'within minutes', info: '5 drafts ready to approve', txt: 'With business knowledge, prepared in your tone' }
    ] : [
      { offen: 5, min: 15, antwort: 'nach 1 bis 2 Tagen', info: '5 offen in 4 Apps', txt: 'Heute, alles von Hand' },
      { offen: 3, min: 8, antwort: 'am selben Tag', info: '3 offen, alles an einem Ort', txt: 'Mit Abläufen, sortiert und bestätigt' },
      { offen: 0, min: 2, antwort: 'nach wenigen Minuten', info: '5 Entwürfe zur Freigabe', txt: 'Mit Betriebswissen, vorbereitet in Ihrem Ton' }
    ];
    var REIHE = [[0, 1, 2, 3, 4], [3, 0, 2, 1, 4], [3, 0, 1, 2, 4]];
    var items = $$('.pe-m', pe);
    var stufe = -1, winkel = WMIN, zieht = false, gezogen = false, startW = 0, letzterZeiger = 0, summe = 0, dragZiel = WMIN, dragRaf = 0, gleiten = null, richtung = 0;
    var EINRASTEN = F ? F.bezier(.33, .05, .15, 1.012) : null, ABBREMSEN = F ? F.bezier(0, 0, .2, 1) : null;

    /* Teilstriche alle 10 Grad, an den Stufen länger */
    var striche = [];
    if (strichGr) {
      for (var a = WMIN; a <= WMAX + .1; a += 10) {
        var gross = a === WMIN || a === 0 || a === WMAX, r1 = gross ? 73 : 76.5, r2 = gross ? 85 : 82.5, rad = a * Math.PI / 180;
        var l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        l.setAttribute('x1', (100 + r1 * Math.sin(rad)).toFixed(2)); l.setAttribute('y1', (100 - r1 * Math.cos(rad)).toFixed(2));
        l.setAttribute('x2', (100 + r2 * Math.sin(rad)).toFixed(2)); l.setAttribute('y2', (100 - r2 * Math.cos(rad)).toFixed(2));
        l.setAttribute('class', gross ? 'strich gross' : 'strich');
        strichGr.appendChild(l); striche.push({ el: l, w: a });
      }
    }
    var spurL = spur && spur.getTotalLength ? spur.getTotalLength() : 300;
    if (spur) { spur.style.strokeDasharray = spurL; spur.style.strokeDashoffset = spurL; }

    function naechste(w) { var best = 0; WINKEL.forEach(function (x, i) { if (Math.abs(x - w) < Math.abs(WINKEL[best] - w)) best = i; }); return best; }
    /* Knopf, Spur und Teilstriche auf einen Winkel zeichnen */
    function zeichne(w) {
      winkel = w;
      knopf.style.setProperty('--w', w.toFixed(2) + 'deg');
      var anteil = Math.max(0, Math.min(1, (w - WMIN) / (WMAX - WMIN)));
      if (spur) spur.style.strokeDashoffset = String(spurL * (1 - anteil));
      for (var i = 0; i < striche.length; i++) { var an = striche[i].w <= w + .5; if (striche[i].an !== an) { striche[i].an = an; striche[i].el.classList.toggle('an', an); } }
      var n = naechste(w);
      marken.forEach(function (m, i) { m.classList.toggle('ist-an', WINKEL[i] <= w + .5); m.classList.toggle('nahe', i === n && zieht); });
    }
    function gleite(ziel) {
      if (gleiten) { gleiten.stop(); gleiten = null; }
      if (!F) { zeichne(ziel); return; }
      var von = winkel, weg = Math.abs(ziel - von); if (weg < .3) { zeichne(ziel); return; }
      var dauer = reduce ? Math.round(Math.min(600, 450 + weg * .7)) : Math.round(620 + weg * 2.6);
      gleiten = F.tween(dauer, reduce ? ABBREMSEN : EINRASTEN, function (e) { zeichne(von + (ziel - von) * e); });
      gleiten.fertig.then(function (ok) { if (ok) { gleiten = null; zeichne(ziel); } });
    }
    function zaehle(el, ziel) {
      var von = +el.textContent || 0; if (von === ziel) { el.textContent = ziel; return; }
      var t0 = performance.now(), d = reduce ? 300 : 760;
      (function schritt(t) { var p = Math.min(1, (t - t0) / d), e = 1 - Math.pow(1 - p, 3); el.textContent = Math.round(von + (ziel - von) * e); if (p < 1) requestAnimationFrame(schritt); })(t0);
    }
    function ordne(n, sofort) {
      // FLIP: neue Reihenfolge setzen und die Karten gestaffelt an den neuen Platz gleiten lassen
      var vorher = items.map(function (li) { return li.getBoundingClientRect().top; });
      REIHE[n].forEach(function (idx, pos) { items[idx].style.order = pos; items[idx].style.setProperty('--i', pos); });
      if (sofort || !items[0].animate) return;
      /* Bewegung reduziert: keine weiten Wege, die Karten blenden am neuen Platz kurz ein */
      if (reduce) { items.forEach(function (li, i) { if (Math.abs(vorher[i] - li.getBoundingClientRect().top) > 1) li.animate([{ opacity: .35 }, { opacity: 1 }], { duration: 300, easing: 'ease-out' }); }); return; }
      items.forEach(function (li, i) {
        var dy = vorher[i] - li.getBoundingClientRect().top;
        if (Math.abs(dy) > 1) li.animate([{ translate: '0 ' + dy + 'px' }, { translate: '0 0' }], { duration: 860, delay: (+li.style.getPropertyValue('--i') || 0) * 45, easing: 'cubic-bezier(.2,.7,.2,1)', fill: 'backwards' });
      });
    }
    function setze(n, sofort) {
      n = Math.max(0, Math.min(2, n));
      var neu = n !== stufe, vorher = stufe; stufe = n;
      knopf.setAttribute('aria-valuenow', String(n)); knopf.setAttribute('aria-valuetext', WERTE[n].txt);
      reg.setAttribute('data-stufe', String(n));
      pe.classList.toggle('dunkler', n === 2 && vorher !== 2 && !sofort);
      pe.classList.toggle('heller', vorher === 2 && n !== 2 && !sofort);
      pe.setAttribute('data-stufe', String(n));
      stufenKn.forEach(function (b) { b.setAttribute('aria-pressed', String(+b.getAttribute('data-stufe-btn') === n)); b.classList.remove('vorschau'); });
      $$('[data-st]', pe).forEach(function (el) { el.textContent = el.getAttribute('data-st').split('|')[n]; });
      zaehle($('[data-wert="offen"]', reg), WERTE[n].offen); zaehle($('[data-wert="min"]', reg), WERTE[n].min);
      $('[data-wert="antwort"]', reg).textContent = WERTE[n].antwort; $('[data-pe-info]', pe).textContent = WERTE[n].info;
      if (neu) ordne(n, sofort);
    }
    function vorschau(n) { stufenKn.forEach(function (b) { b.classList.toggle('vorschau', +b.getAttribute('data-stufe-btn') === n && n !== stufe); }); }
    function zu(n) { setze(n); gleite(WINKEL[Math.max(0, Math.min(2, n))]); }

    /* Greifen geht am ganzen Rad (Knopf und Skala), gemessen wird vom Mittelpunkt des Rads (nicht vom gedrehten Knopf) */
    var scheibe = knopf.parentElement, aufKnopf = false, startZeiger = 0;
    function mitte() { var r = scheibe.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2, r: r.width / 2 }; }
    function zeigerWinkel(e) { var m = mitte(), x = e.clientX - m.x, y = e.clientY - m.y; return Math.atan2(x, -y) * 180 / Math.PI; }
    function norm(d) { while (d > 180) d -= 360; while (d < -180) d += 360; return d; }
    /* Ziehen: Ziel folgt dem Finger mit Widerstand am Rand und leichtem Sog in die Rasten, der Knopf folgt weich */
    function zugSchritt() {
      dragRaf = 0; if (!zieht) return;
      var d = dragZiel - winkel; zeichne(Math.abs(d) < .05 ? dragZiel : winkel + d * .32);
      vorschau(naechste(winkel));
      if (Math.abs(dragZiel - winkel) > .05) dragRaf = requestAnimationFrame(zugSchritt);
    }
    scheibe.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      var m = mitte(), dx = e.clientX - m.x, dy = e.clientY - m.y;
      aufKnopf = !!(e.target.closest && e.target.closest('.regler-knopf'));
      if (!aufKnopf && Math.sqrt(dx * dx + dy * dy) > m.r * 1.04) return;   /* nur im Rad, nicht in den Ecken */
      zieht = true; gezogen = false; richtung = 0; letzterZeiger = startZeiger = zeigerWinkel(e); summe = 0; startW = winkel; dragZiel = winkel;
      if (gleiten) { gleiten.stop(); gleiten = null; }
      try { scheibe.setPointerCapture(e.pointerId); } catch (x) {}
      knopf.classList.add('zieht'); scheibe.classList.add('zieht');
      if (!aufKnopf) { e.preventDefault(); try { knopf.focus({ preventScroll: true }); } catch (x) {} }
    });
    scheibe.addEventListener('pointermove', function (e) {
      if (!zieht) return;
      /* Drehung schrittweise aufsummieren, damit auch mehr als eine halbe Umdrehung in einem Zug geht */
      var z = zeigerWinkel(e); summe += norm(z - letzterZeiger); letzterZeiger = z;
      summe = Math.max(WMIN - 30 - startW, Math.min(WMAX + 30 - startW, summe));   /* über den Rand hinaus nicht aufstauen */
      if (!gezogen && Math.abs(summe) < 5) return;
      gezogen = true;
      var roh = startW + summe;
      if (roh < WMIN) roh = WMIN - Math.pow(WMIN - roh, .72); else if (roh > WMAX) roh = WMAX + Math.pow(roh - WMAX, .72);
      roh = Math.max(WMIN - 16, Math.min(WMAX + 16, roh));
      var n = naechste(roh), ab = roh - WINKEL[n];
      if (Math.abs(ab) < 14) roh -= ab * .38 * (1 - Math.abs(ab) / 14);
      richtung = roh > dragZiel + .2 ? 1 : roh < dragZiel - .2 ? -1 : richtung;
      dragZiel = roh;
      if (!dragRaf) dragRaf = requestAnimationFrame(zugSchritt);
    });
    function loslassen() {
      if (!zieht) return;
      zieht = false; knopf.classList.remove('zieht'); scheibe.classList.remove('zieht'); vorschau(-1);
      if (dragRaf) { cancelAnimationFrame(dragRaf); dragRaf = 0; }
      if (gezogen) zu(naechste(dragZiel + richtung * 22));   /* Absicht: wohin der Finger gedreht hat, nicht wo der Knopf gerade nachläuft */
      else if (aufKnopf) zu((stufe + 1) % 3);
      else if (startZeiger >= WMIN - 25 && startZeiger <= WMAX + 25) zu(naechste(startZeiger));   /* Tippen auf die Skala: zur nächsten Stufe an dieser Stelle */
      else zu(stufe);
    }
    scheibe.addEventListener('pointerup', loslassen); scheibe.addEventListener('pointercancel', loslassen);
    scheibe.addEventListener('lostpointercapture', loslassen);
    knopf.addEventListener('keydown', function (e) {
      var k = e.key, n = null;
      if (k === 'ArrowRight' || k === 'ArrowUp') n = stufe + 1; else if (k === 'ArrowLeft' || k === 'ArrowDown') n = stufe - 1;
      else if (k === 'Home') n = 0; else if (k === 'End') n = 2; else if (k === 'Enter' || k === ' ') n = (stufe + 1) % 3;
      if (n === null) return; e.preventDefault(); zu(n);
    });
    stufenKn.forEach(function (b) { b.addEventListener('click', function () { zu(+b.getAttribute('data-stufe-btn')); }); });
    reg.classList.add('regler-js');
    zeichne(WINKEL[0]); setze(0, true);
  }

  /* ---------- Zeitrechner ---------- */
  var rech = $('[data-rechner]');
  if (rech) {
    var ein = { anz: $('#r-anz', rech), min: $('#r-min', rech), ant: $('#r-ant', rech) };
    var kaesten = $('[data-r="kaesten"]', rech), nf0 = new Intl.NumberFormat(EN ? 'en-GB' : 'de-DE', { maximumFractionDigits: 0 }), nf1 = new Intl.NumberFormat(EN ? 'en-GB' : 'de-DE', { maximumFractionDigits: 1 });
    var zahl = { format: function (x) { return x < 1 ? nf1.format(x) : nf0.format(x); } };
    var MAX = 40;
    for (var i = 0; i < MAX; i++) kaesten.appendChild(document.createElement('i'));
    var zellen = $$('i', kaesten);
    function rechne() {
      var anz = +ein.anz.value, min = +ein.min.value, ant = +ein.ant.value / 100;
      Object.keys(ein).forEach(function (k) { var el = ein[k]; el.style.setProperty('--p', ((el.value - el.min) / (el.max - el.min) * 100) + '%'); });
      $('#o-anz', rech).textContent = anz; $('#o-min', rech).textContent = min; $('#o-ant', rech).textContent = Math.round(ant * 100) + ' %';
      var std = anz * min * 52 / 12 / 60, frei = std * ant, tage = frei * 12 / 8;
      $('[data-r="std"]', rech).textContent = zahl.format(std); $('[data-r="frei"]', rech).textContent = zahl.format(frei); $('[data-r="tage"]', rech).textContent = zahl.format(tage);
      var faktor = std > MAX ? Math.ceil(std / MAX) : 1, voll = Math.round(std / faktor), ab = Math.round(frei / faktor);
      zellen.forEach(function (z, j) { z.className = j < voll ? (j >= voll - ab ? 'frei' : 'jetzt') : ''; });
      $('[data-r="einheit"]', rech).textContent = faktor === 1 ? (EN ? 'one box is one hour a month' : 'ein Kästchen ist eine Stunde im Monat') : (EN ? 'one box is ' + faktor + ' hours a month' : 'ein Kästchen sind ' + faktor + ' Stunden im Monat');
    }
    Object.keys(ein).forEach(function (k) { ein[k].addEventListener('input', rechne); });
    rechne();
  }
})();
