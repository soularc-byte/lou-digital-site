/* Lou Digital – Kundenportal (Version 34, 31.10.2026): QR-Code für 2FA, Kopieren, Rückfragen vor dem Löschen, Tiefen-Check-Werkzeug (Sofort-Speichern, automatischer Check). Kein Tracking. */
(function () {
  'use strict';
  /* QR-Code für die Authenticator-App (qrcode.min.js, MIT) */
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    try { if (typeof QRCode === 'function') el.innerHTML = new QRCode({ content: el.getAttribute('data-qr'), padding: 0, width: 184, height: 184, color: '#11191b', background: '#ffffff', ecl: 'M' }).svg(); } catch (e) { el.textContent = 'QR-Code nicht verfügbar – bitte den Schlüssel eintippen.'; }
  });
  /* Kopieren-Knöpfe */
  document.querySelectorAll('[data-copy-btn]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = b.parentNode.querySelector('[data-copy]'); if (!f) return;
      var done = function () { var t = b.textContent; b.textContent = 'Kopiert'; setTimeout(function () { b.textContent = t; }, 1400); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(f.value).then(done, function () { f.select(); done(); });
      else { f.select(); try { document.execCommand('copy'); } catch (e) {} done(); }
    });
  });
  /* Rückfrage vor endgültigen Aktionen */
  document.querySelectorAll('[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  /* Chat: ans Ende scrollen, Enter sendet (Shift+Enter = neue Zeile) */
  var chat = document.querySelector('.p-chat textarea');
  if (chat) { chat.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chat.form.requestSubmit ? chat.form.requestSubmit() : chat.form.submit(); } }); var ende = document.getElementById('ende'); if (ende && location.hash === '#ende') ende.scrollIntoView({ block: 'end' }); }

  /* ---------- Version 34: Tiefen-Check-Werkzeug ---------- */
  var tcForm = document.getElementById('tc-form');
  if (tcForm) {
    var tcId = tcForm.getAttribute('data-id'), tcTok = (tcForm.querySelector('input[name="_t"]') || {}).value || '', base = '/portal/admin/tiefencheck';
    var saveEl = document.getElementById('tc-save');
    var note = function (t) { if (saveEl) saveEl.textContent = t; };
    var zahl = function (id, v) { var el = document.getElementById(id); if (el && v !== undefined) el.textContent = String(v); };
    var kpis = function (d) {
      if (!d) return;
      zahl('tc-score', d.beurteilt ? d.score : '–'); zahl('tc-beurteilt', d.beurteilt); zahl('tc-sofort', d.sofort); zahl('tc-bald', d.bald); zahl('tc-spaeter', d.spaeter); zahl('tc-aufwand', d.aufwand);
      if (d.preis !== undefined) zahl('tc-preis', d.preis.toLocaleString('de-DE') + ' €');
      var bar = document.getElementById('tc-bar'); if (bar && d.gesamt) bar.style.width = Math.round(d.beurteilt / d.gesamt * 100) + '%';
    };
    var bereichStand = function () {
      tcForm.querySelectorAll('.tc-bereich').forEach(function (sec) {
        var pts = sec.querySelectorAll('.tc-punkt'), n = 0; pts.forEach(function (pt) { var c = pt.querySelector('input[type="radio"]:checked'); if (c && c.value !== 'offen') n++; });
        var b = sec.querySelector('[data-bereich-stand]'); if (b) b.textContent = n + ' / ' + pts.length + ' beurteilt';
      });
    };
    var post = function (data) {
      var fd = new FormData(); fd.append('_t', tcTok); fd.append('id', tcId); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      return fetch(base + '?id=' + encodeURIComponent(tcId), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || r.status); return j; }); });
    };
    var timers = {};
    var saveItem = function (pt) {
      var pid = pt.getAttribute('data-pid'); if (!pid) return;
      var st = pt.querySelector('input[type="radio"]:checked'), bf = pt.querySelector('textarea[name^="bf"]'), em = pt.querySelector('textarea[name^="em"]'), aw = pt.querySelector('input[name^="aw"]');
      pt.classList.add('is-saving'); pt.classList.remove('is-saved');
      var d = { op: 'item', pid: pid }; d['st[' + pid + ']'] = st ? st.value : 'offen'; d['bf[' + pid + ']'] = bf ? bf.value : ''; d['em[' + pid + ']'] = em ? em.value : ''; d['aw[' + pid + ']'] = aw ? aw.value : '0';
      post(d).then(function (j) { pt.classList.remove('is-saving'); pt.classList.add('is-saved'); kpis(j); bereichStand(); note('Gespeichert ' + new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })); })
        .catch(function () { pt.classList.remove('is-saving'); note('Konnte nicht speichern – bitte unten „Alles speichern“ klicken.'); });
    };
    tcForm.addEventListener('change', function (e) { var pt = e.target.closest && e.target.closest('.tc-punkt'); if (!pt) return; clearTimeout(timers[pt.getAttribute('data-pid')]); timers[pt.getAttribute('data-pid')] = setTimeout(function () { saveItem(pt); }, 250); });
    tcForm.addEventListener('input', function (e) { var pt = e.target.closest && e.target.closest('.tc-punkt'); if (!pt || e.target.tagName !== 'TEXTAREA') return; clearTimeout(timers[pt.getAttribute('data-pid')]); timers[pt.getAttribute('data-pid')] = setTimeout(function () { saveItem(pt); }, 1200); });
    /* Zusammenfassung und Empfehlung: beim Verlassen des Feldes speichern */
    ['zusammenfassung', 'empfehlung', 'notiz'].forEach(function (n) { var el = tcForm.querySelector('[name="' + n + '"]'); if (el) el.addEventListener('blur', function () { var d = { op: 'meta' }; d[n] = el.value; post(d).then(function () { note('Gespeichert ' + new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })); }).catch(function () {}); }); });
    /* So prüfen / Warum ein- und ausblenden */
    tcForm.querySelectorAll('.tc-info-btn').forEach(function (b) { b.addEventListener('click', function () { var box = b.closest('.tc-punkt').querySelector('.tc-info'); var on = box.hidden; box.hidden = !on; b.setAttribute('aria-expanded', String(on)); b.textContent = on ? 'Hinweise ausblenden' : 'So prüfen'; }); });
    /* Vorschläge einfügen */
    [['tc-vorschlag', 'zusammenfassung'], ['tc-empf-vorschlag', 'empfehlung']].forEach(function (x) { var b = document.getElementById(x[0]); if (!b) return; b.addEventListener('click', function () { var ta = tcForm.querySelector('[name="' + x[1] + '"]'); if (!ta) return; if (ta.value.trim() && !window.confirm('Vorhandenen Text ersetzen?')) return; ta.value = b.getAttribute('data-text') || ''; ta.dispatchEvent(new Event('blur')); }); });
    /* Automatischen 100-Punkte-Check sammeln: Browser ruft /api/check100.php, Ergebnis wird im Check gespeichert und belegt die Technik-Punkte vor */
    var autoBtn = document.getElementById('tc-auto');
    if (autoBtn) autoBtn.addEventListener('click', function () {
      var url = autoBtn.getAttribute('data-url'), t = autoBtn.textContent; autoBtn.disabled = true; autoBtn.textContent = 'Prüfe … (bis zu 40 Sekunden)';
      var fd = new FormData(); fd.append('url', url); fd.append('sprache', 'de'); fd.append('modus', 'voll');
      fetch('/api/check100.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.ok) throw new Error((j && j.error) || 'check');
          return post({ op: 'auto', daten: JSON.stringify(j) });
        })
        .then(function (j) {
          note('Automatischer Check: ' + j.punkte + ' / 100, ' + j.vorbelegt + ' Punkte vorbelegt. Seite wird neu geladen …');
          setTimeout(function () { location.reload(); }, 900);
        })
        .catch(function (e) { autoBtn.disabled = false; autoBtn.textContent = t; var m = String(e && e.message || ''); note(m === 'rate' || m === 'busy' ? 'Zu viele Checks gerade – in einer Stunde noch einmal.' : (m === 'unreachable' || m === 'dns' ? 'Die Website hat nicht geantwortet.' : 'Automatischer Check fehlgeschlagen (' + m + ').')); });
    });
    bereichStand();
  }
})();
