/* Lou Digital – Angebote im Detail (Version 28, 28.10.2026)
   /pakete: i-Knopf am Paketnamen öffnet ein Detailfeld (Inhalt, Zusatzdienste, Betreuung im Vergleich), auch über #mehr-…
   /individuell: i-Knopf in der Ecke einer Option zeigt, was enthalten ist (schließt mit Esc, Klick daneben oder einem anderen i).
   Bewegung abbremsend, bei weniger Bewegung ohne Wege. */
(function () {
  'use strict';
  var RM = window.matchMedia ? matchMedia('(prefers-reduced-motion: reduce)') : { matches: true };
  var AUF = 'cubic-bezier(.05,.7,.1,1)', ZU = 'cubic-bezier(.2,.7,.2,1)';
  function still() { return RM.matches || !Element.prototype.animate; }

  /* ---------- Detailfelder in der Pakettabelle ---------- */
  function feld(btn) { return document.getElementById(btn.getAttribute('aria-controls')); }
  function klappen(btn, auf, sofort) {
    var p = feld(btn); if (!p) return;
    var ist = btn.getAttribute('aria-expanded') === 'true';
    if (auf === undefined) auf = !ist;
    if (auf === ist) return;
    btn.setAttribute('aria-expanded', String(auf));
    var zeile = btn.closest('.tr'); if (zeile) zeile.classList.toggle('is-mehr', auf);
    if (p._anim) { p._anim.cancel(); p._anim = null; }
    var innen = p.firstElementChild;
    if (auf) {
      p.hidden = false;
      if (sofort || still()) return;
      var h = p.getBoundingClientRect().height;
      p.style.overflow = 'hidden';
      p._anim = p.animate([{ height: '0px', opacity: 0 }, { height: h + 'px', opacity: 1 }], { duration: 560, easing: AUF });
      if (innen) innen.animate([{ transform: 'translateY(-10px) scale(.985)' }, { transform: 'none' }], { duration: 640, easing: AUF });
      p._anim.onfinish = function () { p._anim = null; p.style.overflow = ''; };
    } else {
      if (sofort || still()) { p.hidden = true; return; }
      var h2 = p.getBoundingClientRect().height;
      p.style.overflow = 'hidden';
      p._anim = p.animate([{ height: h2 + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], { duration: 300, easing: ZU });
      p._anim.onfinish = function () { p.hidden = true; p._anim = null; p.style.overflow = ''; };
    }
  }
  var knoepfe = [].slice.call(document.querySelectorAll('[data-mehr]'));
  knoepfe.forEach(function (b) { b.addEventListener('click', function () { klappen(b); }); });

  /* Sprungmarke #mehr-… (Paketzeile) öffnet das passende Feld, das Scrollen übernimmt site.js */
  function ausHash(weich) {
    var id = ''; try { id = decodeURIComponent((location.hash || '').slice(1)); } catch (e) { return; }
    if (!/^mehr-[a-z]+$/.test(id)) return;
    var b = document.querySelector('[data-mehr][aria-controls="' + id + '-feld"]');
    if (b) klappen(b, true, !weich);
  }
  if (knoepfe.length) {
    ausHash(false);
    window.addEventListener('hashchange', function () { ausHash(true); });
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a[href^="#mehr-"]'); if (!a) return;
      var b = document.querySelector('[data-mehr][aria-controls="' + a.getAttribute('href').slice(1) + '-feld"]');
      if (b) klappen(b, true);
    });
  }

  /* Betreuung: gewählte Stufe in der Vergleichstabelle hervorheben, „Individuell anpassen“ übernimmt sie */
  var vgl = document.querySelector('.vgl'), basisLink = document.querySelector('[data-basis-link]');
  function stufe() {
    var t = document.querySelector('[data-tier][aria-pressed="true"]'), s = t ? t.getAttribute('data-tier') : '';
    if (vgl) { if (s) vgl.setAttribute('data-wahl', s); else vgl.removeAttribute('data-wahl'); }
    if (basisLink) basisLink.setAttribute('href', basisLink.getAttribute('href').replace(/basis=[SML]\b/, 'basis=' + (s || 'S')));
  }
  if (vgl || basisLink) {
    document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-tier], #pb-clear')) setTimeout(stufe, 0); });
    stufe();
  }

  /* ---------- i-Knöpfe im Baukasten ---------- */
  var offen = null;
  function zeigen(btn, auf) {
    var p = feld(btn); if (!p) return;
    if (auf && offen && offen !== btn) zeigen(offen, false);
    btn.setAttribute('aria-expanded', String(auf));
    if (p._anim) { p._anim.cancel(); p._anim = null; }
    if (auf) {
      offen = btn; p.hidden = false;
      if (!still()) p._anim = p.animate([{ opacity: 0, transform: 'translateY(-8px) scale(.98)' }, { opacity: 1, transform: 'none' }], { duration: 380, easing: AUF });
    } else {
      if (offen === btn) offen = null;
      if (still()) { p.hidden = true; return; }
      p._anim = p.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-4px)' }], { duration: 180, easing: ZU });
      p._anim.onfinish = function () { p.hidden = true; p._anim = null; };
    }
  }
  var pops = [].slice.call(document.querySelectorAll('[data-pop]'));
  pops.forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); zeigen(b, b.getAttribute('aria-expanded') !== 'true'); }); });
  if (pops.length) {
    document.addEventListener('click', function (e) {
      if (!offen) return;
      var p = feld(offen);
      if (e.target === offen || offen.contains(e.target) || (p && p.contains(e.target))) return;
      zeigen(offen, false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !offen) return;
      var b = offen; zeigen(b, false); b.focus();
    });
  }
})();
