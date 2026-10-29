/* Lou Digital – Kundenportal (Version 30, 29.10.2026): QR-Code für 2FA, Kopieren, Rückfragen vor dem Löschen. Kein Tracking. */
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
})();
