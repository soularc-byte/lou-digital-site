<?php
/* Lou Digital – Kundenportal (Version 30, 29.10.2026)
   Aufruf über /portal/… (Rewrite in .htaccess). Anmeldung mit E-Mail + Passwort, freiwillige 2FA (TOTP),
   Erstpasswort wird erzeugt und per Mail geschickt, spätere Passwortwechsel werden per Mail bestätigt.
   Daten: ../ld-data/portal.sqlite (siehe api/inc/portal.php). Keine Cookies außer der Sitzung (ldp). */
declare(strict_types=1);
define('LD', 1);
require dirname(__DIR__) . '/api/inc/lib.php';
require dirname(__DIR__) . '/api/inc/portal.php';
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow, noarchive');
const P_BASE = '/portal';
const P_COOKIE = 'ldp';
const P_IDLE = 12 * 3600;     // Sitzung endet nach 12 h ohne Aktivität
const P_MAX = 30 * 86400;     // und spätestens nach 30 Tagen

/* ---------- Pfad ---------- */
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$route = trim(preg_replace('~^/portal/?~', '', $path) ?? '', '/');
$route = preg_replace('~[^a-z0-9/_-]~', '', $route) ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$post = $method === 'POST';

try { pd(); } catch (Throwable $e) {
  http_response_code(503);
  echo p_layout('Portal nicht verfügbar', '<section class="p-card"><h1>Portal gerade nicht verfügbar</h1><p>Die Datenbank konnte nicht geöffnet werden. Bitte versuchen Sie es in einigen Minuten noch einmal oder schreiben Sie an <a href="mailto:' . LD_TO . '">' . LD_TO . '</a>.</p></section>', null);
  exit;
}

/* ---------- Sitzung ---------- */
$me = null; $sess = null;
$tok = (string)($_COOKIE[P_COOKIE] ?? '');
if (preg_match('/^[a-f0-9]{48}$/', $tok)) {
  $sess = pd_row('SELECT * FROM sitzungen WHERE id = ?', [hash('sha256', $tok)]);
  if ($sess && (strtotime($sess['zuletzt']) < time() - P_IDLE || strtotime($sess['angelegt']) < time() - P_MAX)) { pd_q('DELETE FROM sitzungen WHERE id = ?', [$sess['id']]); $sess = null; }
  if ($sess) {
    $me = pd_row('SELECT * FROM kunden WHERE id = ? AND aktiv = 1', [(int)$sess['kunde_id']]);
    if (!$me) { pd_q('DELETE FROM sitzungen WHERE id = ?', [$sess['id']]); $sess = null; }
    else pd_q('UPDATE sitzungen SET zuletzt = ? WHERE id = ?', [pd_now(), $sess['id']]);
  }
}
if (!$sess) { $me = null; if ($tok !== '') p_cookie(''); }
$csrf = $sess ? $sess['csrf'] : '';
$exempt = in_array($route, ['login', 'vergessen', 'reset', 'einrichten'], true);
if ($post && !$exempt && (!$sess || !hash_equals($csrf, (string)($_POST['_t'] ?? '')))) { p_flash('Formular abgelaufen, bitte noch einmal senden.', 'warn'); p_go($sess ? P_BASE . '/' . $route : P_BASE . '/login'); }

$admin = $me && $me['rolle'] === 'admin';
$zfaOffen = $me && (int)$me['totp_aktiv'] === 1 && (int)$sess['zfa'] === 0;
$pwOffen = $me && (int)$me['pass_wechsel'] === 1;

/* ---------- Helfer ---------- */
function p_cookie(string $v): void { setcookie(P_COOKIE, $v, ['expires' => $v === '' ? time() - 3600 : time() + P_MAX, 'path' => P_BASE, 'secure' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Strict']); }
function p_go(string $to): void { header('Location: ' . $to, true, 303); exit; }
function p_flash(string $msg, string $art = 'ok'): void { setcookie('ldp_m', rawurlencode($art . '|' . mb_substr($msg, 0, 300)), ['expires' => time() + 60, 'path' => P_BASE, 'httponly' => true, 'samesite' => 'Strict', 'secure' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')]); }
function p_flash_lesen(): string {
  $m = (string)($_COOKIE['ldp_m'] ?? ''); if ($m === '') return '';
  setcookie('ldp_m', '', ['expires' => time() - 3600, 'path' => P_BASE]);
  [$art, $txt] = array_pad(explode('|', rawurldecode($m), 2), 2, '');
  return '<div class="p-flash p-flash--' . ld_h(preg_replace('/[^a-z]/', '', $art) ?: 'ok') . '" role="status">' . ld_h($txt) . '</div>';
}
function h(string $s): string { return ld_h($s); }
function p_datum(string $ts, bool $zeit = false): string { if ($ts === '') return '–'; $t = strtotime($ts); return $t ? date($zeit ? 'd.m.Y, H:i' : 'd.m.Y', $t) : h($ts); }
function p_eur(int $n): string { return number_format($n, 0, ',', '.') . ' €'; }
function p_pw_ok(string $p): bool { return mb_strlen($p) >= 10 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d|[^A-Za-z0-9]/', $p); }
function p_login(array $k, bool $zfa): void {
  $t = bin2hex(random_bytes(24));
  $ua = mb_substr(preg_replace('/\s+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '', 0, 160);
  $ger = (preg_match('/iPhone|iPad/i', $ua) ? 'iPhone/iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Gerät')))))
       . ' · ' . (preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser'))));
  pd_q('INSERT INTO sitzungen (id, kunde_id, zfa, csrf, geraet, angelegt, zuletzt) VALUES (?, ?, ?, ?, ?, ?, ?)', [hash('sha256', $t), (int)$k['id'], $zfa ? 0 : 1, bin2hex(random_bytes(16)), $ger, pd_now(), pd_now()]);
  pd_q('UPDATE kunden SET letzter_login = ? WHERE id = ?', [pd_now(), (int)$k['id']]);
  pd_q('DELETE FROM sitzungen WHERE zuletzt < ?', [date('Y-m-d H:i:s', time() - P_IDLE)]);
  p_cookie($t);
}
function p_mail(array $k, string $betreff, string $intro, array $rows = [], string $extra = ''): bool {
  $text = strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], $intro));
  foreach ($rows as $a => $b) $text .= "\n$a: $b";
  $text .= "\n\n" . strip_tags(str_replace(['</p>', '<br>'], ["\n", "\n"], $extra)) . "\n\nViele Grüße\nAdrian Semlinger\nLou Digital, Karlsruhe\n" . LD_PHONE . "\n" . LD_SITE . P_BASE;
  return ld_send((string)$k['email'], $betreff, html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ld_mail_html('de', $betreff, $intro, $rows, $extra), LD_TO);
}
function p_btn_link(string $url, string $txt): string { return '<p style="margin:16px 0"><a href="' . h($url) . '" style="display:inline-block;background:#0b6e69;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:999px;font-weight:bold;font-size:15px">' . h($txt) . '</a></p><p style="margin:0;color:#4b585a;font-size:13px">Falls der Knopf nicht geht: ' . h($url) . '</p>'; }
function p_adrian(string $betreff, string $text): void { ld_send(LD_TO, $betreff, $text . "\n\n" . LD_SITE . P_BASE . '/admin', null, ''); }
function p_form(string $csrf): string { return '<input type="hidden" name="_t" value="' . h($csrf) . '">'; }
function p_status_badge(string $s, array $map): string { return '<span class="p-badge p-badge--' . h(preg_replace('/[^a-z]/', '', $s) ?: 'x') . '">' . h($map[$s] ?? $s) . '</span>'; }
function p_empty(string $txt): string { return '<p class="p-empty">' . $txt . '</p>'; }
function p_tickets_zaehlen(int $kunde): int { $r = pd_row("SELECT COUNT(*) c FROM tickets WHERE kunde_id = ? AND status <> 'erledigt' AND art <> 'bot'", [$kunde]); return (int)($r['c'] ?? 0); }
function p_nav(?array $me, bool $admin, string $route): string {
  if (!$me) return '';
  $k = [['', 'Übersicht'], ['projekt', 'Projekt'], ['website', 'Website'], ['betreuung', 'Betreuung'], ['workflows', 'Automatik'], ['dokumente', 'Dokumente'], ['nachrichten', 'Nachrichten'], ['hilfe', 'Hilfe'], ['empfehlen', 'Empfehlen'], ['berichte', 'Berichte'], ['einstellungen', 'Einstellungen']];
  $a = [['admin', 'Büro'], ['admin/auftraege', 'Aufträge'], ['admin/kunden', 'Kunden'], ['admin/codes', 'Codes'], ['admin/tickets', 'Tickets'], ['admin/einstellungen', 'Einstellungen']];
  $o = '<nav class="p-nav" aria-label="Portal"><ul>';
  foreach ($admin ? $a : $k as [$r, $t]) { $on = $route === $r || ($r !== '' && $r !== 'admin' && strpos($route, $r) === 0); $o .= '<li><a href="' . P_BASE . '/' . $r . '"' . ($on ? ' aria-current="page"' : '') . '>' . $t . '</a></li>'; }
  if ($admin) $o .= '<li class="p-nav-sep"><a href="' . P_BASE . '/einstellungen">Mein Konto</a></li>';
  return $o . '</ul></nav>';
}
function p_layout(string $title, string $body, ?array $me, bool $admin = false, string $route = '', string $csrf = ''): string {
  global $csrfG; $csrf = $csrf ?: ($csrfG ?? '');
  $css = (string)@filemtime(__DIR__ . '/portal.css'); $js = (string)@filemtime(__DIR__ . '/portal.js');
  $head = '<header class="p-top"><div class="p-wrap"><a class="p-logo" href="' . ($me ? P_BASE : LD_SITE) . '"><span>Lou Digital</span><small>' . ($admin ? 'Büro' : 'Kundenportal') . '</small></a>'
        . ($me ? '<div class="p-me"><span>' . h($me['name'] !== '' ? $me['name'] : $me['email']) . '</span><form method="post" action="' . P_BASE . '/logout">' . p_form($csrf) . '<button class="p-link" type="submit">Abmelden</button></form></div>' : '<a class="p-link" href="' . LD_SITE . '">zur Website</a>')
        . '</div></header>';
  return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="color-scheme" content="light"><meta name="theme-color" content="#0b6e69">'
       . '<title>' . h($title) . ' · Lou Digital Portal</title><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/css/site.css"><link rel="stylesheet" href="' . P_BASE . '/portal.css?v=' . $css . '"></head>'
       . '<body class="p-body">' . $head . '<div class="p-wrap p-main' . ($me ? '' : ' p-main--solo') . '">' . p_nav($me, $admin, $route) . '<main class="p-content" id="main">' . p_flash_lesen() . $body . '</main></div>'
       . '<footer class="p-foot"><div class="p-wrap"><span>Lou Digital · Adrian Semlinger · Karlsruhe</span><span><a href="' . LD_SITE . '/impressum">Impressum</a> · <a href="' . LD_SITE . '/datenschutz">Datenschutz</a> · <a href="mailto:' . LD_TO . '">' . LD_TO . '</a></span></div></footer>'
       . '<script src="/js/qrcode.min.js" defer></script><script src="' . P_BASE . '/portal.js?v=' . $js . '" defer></script></body></html>';
}
$csrfG = $csrf;
function out(string $title, string $body): void { global $me, $admin, $route; echo p_layout($title, $body, $me, (bool)$admin, $route); exit; }

/* ---------- Öffentliche Seiten: Anmeldung, Passwort vergessen, Einrichtung ---------- */
if ($route === 'logout') { if ($post && $sess) { pd_q('DELETE FROM sitzungen WHERE id = ?', [$sess['id']]); } p_cookie(''); p_go(P_BASE . '/login'); }

if ($route === 'login' || ($route === '' && !$me)) {
  if ($me) p_go(P_BASE);
  $err = '';
  if ($post) {
    $email = strtolower(trim(ld_post('email', 150))); $pw = (string)($_POST['passwort'] ?? '');
    if (!ld_rate('plogin', 25)) $err = 'Zu viele Versuche. Bitte in einer Stunde noch einmal.';
    else {
      $k = pd_row('SELECT * FROM kunden WHERE email = ? AND aktiv = 1', [$email]);
      usleep(random_int(80000, 200000));
      if ($k && $k['pass_hash'] !== '' && password_verify($pw, $k['pass_hash'])) {
        p_login($k, (int)$k['totp_aktiv'] === 1);
        pd_log((int)$k['id'], 'login', '');
        p_go(P_BASE . ((int)$k['totp_aktiv'] === 1 ? '/zfa' : ((int)$k['pass_wechsel'] === 1 ? '/passwort' : '')));
      }
      $err = 'E-Mail oder Passwort stimmen nicht.';
      if ($k) pd_log((int)$k['id'], 'login_fehl', '');
    }
  }
  $hatAdmin = (bool)pd_row("SELECT 1 FROM kunden WHERE rolle = 'admin' LIMIT 1");
  out('Anmelden', '<section class="p-card p-card--narrow"><h1>Kundenportal</h1><p class="p-lead">Hier sehen Sie den Stand Ihres Projekts, Ihre Website, Ihre Automatik, Dokumente und Nachrichten.</p>'
    . ($err ? '<div class="p-flash p-flash--warn">' . h($err) . '</div>' : '')
    . '<form method="post" action="' . P_BASE . '/login" class="p-form"><label>E-Mail<input name="email" type="email" required autocomplete="username" maxlength="150" value="' . h((string)($_POST['email'] ?? '')) . '"></label>'
    . '<label>Passwort<input name="passwort" type="password" required autocomplete="current-password" maxlength="200"></label>'
    . '<button class="btn btn--primary" type="submit">Anmelden</button></form>'
    . '<p class="p-small"><a href="' . P_BASE . '/vergessen">Passwort vergessen?</a>' . (!$hatAdmin ? ' · <a href="' . P_BASE . '/einrichten">Portal einrichten</a>' : '') . '</p>'
    . '<p class="p-small p-muted">Noch kein Zugang? Den bekommen Sie automatisch, sobald wir zusammenarbeiten. Fragen vorab: <a href="' . LD_SITE . '/kontakt">Anfrage senden</a>.</p></section>');
}

if ($route === 'vergessen') {
  $ok = false;
  if ($post) {
    $email = strtolower(trim(ld_post('email', 150)));
    if (ld_rate('pverg', 6)) {
      $k = pd_row('SELECT * FROM kunden WHERE email = ? AND aktiv = 1', [$email]);
      if ($k) { $t = ld_token_neu((int)$k['id'], 'reset', '', 2); p_mail($k, 'Passwort zurücksetzen – Lou Digital Portal', '<p style="margin:0">Guten Tag ' . h($k['name']) . ',</p><p style="margin:10px 0 0">mit dem Knopf unten legen Sie ein neues Passwort für Ihr Kundenportal fest. Der Link gilt zwei Stunden. Wenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail einfach.</p>', [], p_btn_link(LD_SITE . P_BASE . '/reset?t=' . $t, 'Neues Passwort festlegen')); pd_log((int)$k['id'], 'pw_reset_mail', ''); }
      $ok = true;
    }
  }
  out('Passwort vergessen', '<section class="p-card p-card--narrow"><h1>Passwort vergessen</h1>' . ($ok ? '<div class="p-flash p-flash--ok">Wenn es zu dieser E-Mail ein Konto gibt, ist jetzt eine Nachricht mit einem Link unterwegs.</div>' : '<p class="p-lead">Sie bekommen einen Link per E-Mail, mit dem Sie ein neues Passwort festlegen.</p>')
    . '<form method="post" class="p-form"><label>E-Mail<input name="email" type="email" required maxlength="150" autocomplete="username"></label><button class="btn btn--primary" type="submit">Link senden</button></form><p class="p-small"><a href="' . P_BASE . '/login">Zur Anmeldung</a></p></section>');
}

if ($route === 'reset') {
  $t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
  $tk = ld_token_lesen($t, 'reset', false);
  if (!$tk) out('Link ungültig', '<section class="p-card p-card--narrow"><h1>Link ungültig oder abgelaufen</h1><p><a href="' . P_BASE . '/vergessen">Neuen Link anfordern</a></p></section>');
  $err = '';
  if ($post) {
    $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
    if ($p1 !== $p2) $err = 'Die Passwörter sind nicht gleich.'; elseif (!p_pw_ok($p1)) $err = 'Mindestens 10 Zeichen, mit Buchstaben und einer Zahl oder einem Sonderzeichen.';
    else {
      ld_token_lesen($t, 'reset', true);
      pd_q('UPDATE kunden SET pass_hash = ?, pass_wechsel = 0 WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), (int)$tk['kunde_id']]);
      pd_q('DELETE FROM sitzungen WHERE kunde_id = ?', [(int)$tk['kunde_id']]);
      $k = pd_row('SELECT * FROM kunden WHERE id = ?', [(int)$tk['kunde_id']]);
      if ($k) p_mail($k, 'Ihr Passwort wurde geändert – Lou Digital Portal', '<p style="margin:0">Guten Tag ' . h($k['name']) . ',</p><p style="margin:10px 0 0">das Passwort für Ihr Kundenportal wurde gerade neu festgelegt. Falls Sie das nicht waren, antworten Sie bitte sofort auf diese E-Mail.</p>');
      pd_log((int)$tk['kunde_id'], 'pw_reset', '');
      p_flash('Passwort festgelegt. Bitte melden Sie sich an.'); p_go(P_BASE . '/login');
    }
  }
  out('Neues Passwort', '<section class="p-card p-card--narrow"><h1>Neues Passwort</h1>' . ($err ? '<div class="p-flash p-flash--warn">' . h($err) . '</div>' : '') . '<form method="post" class="p-form"><input type="hidden" name="t" value="' . h($t) . '"><label>Neues Passwort<input name="p1" type="password" required minlength="10" autocomplete="new-password"></label><label>Noch einmal<input name="p2" type="password" required minlength="10" autocomplete="new-password"></label><button class="btn btn--primary" type="submit">Speichern</button></form></section>');
}

if ($route === 'einrichten') {
  /* Erster Admin: nur, wenn es noch keinen gibt. Der Link geht an info@lou-digital.de, also nur an den Inhaber. */
  if (pd_row("SELECT 1 FROM kunden WHERE rolle = 'admin' LIMIT 1")) p_go(P_BASE . '/login');
  $t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
  if ($t !== '') {
    $tk = ld_token_lesen($t, 'einrichten', false);
    if (!$tk) out('Link ungültig', '<section class="p-card p-card--narrow"><h1>Link ungültig oder abgelaufen</h1><p><a href="' . P_BASE . '/einrichten">Neu anfordern</a></p></section>');
    $err = '';
    if ($post) {
      $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
      if ($p1 !== $p2) $err = 'Die Passwörter sind nicht gleich.'; elseif (!p_pw_ok($p1)) $err = 'Mindestens 10 Zeichen, mit Buchstaben und einer Zahl oder einem Sonderzeichen.';
      else {
        ld_token_lesen($t, 'einrichten', true);
        pd_q("INSERT INTO kunden (email, name, firma, rolle, pass_hash, pass_wechsel, angelegt) VALUES (?, 'Adrian Semlinger', 'Lou Digital', 'admin', ?, 0, ?) ON CONFLICT(email) DO UPDATE SET rolle = 'admin', pass_hash = excluded.pass_hash, pass_wechsel = 0, aktiv = 1", [LD_TO, password_hash($p1, PASSWORD_DEFAULT), pd_now()]);
        pd_log(0, 'admin_eingerichtet', LD_TO);
        p_flash('Büro-Zugang eingerichtet. Anmelden mit ' . LD_TO . '.'); p_go(P_BASE . '/login');
      }
    }
    out('Portal einrichten', '<section class="p-card p-card--narrow"><h1>Büro-Zugang festlegen</h1><p class="p-lead">Konto: ' . LD_TO . '</p>' . ($err ? '<div class="p-flash p-flash--warn">' . h($err) . '</div>' : '') . '<form method="post" class="p-form"><input type="hidden" name="t" value="' . h($t) . '"><label>Passwort<input name="p1" type="password" required minlength="10" autocomplete="new-password"></label><label>Noch einmal<input name="p2" type="password" required minlength="10" autocomplete="new-password"></label><button class="btn btn--primary" type="submit">Einrichten</button></form></section>');
  }
  if ($post && ld_rate('peinr', 3)) {
    $tk = ld_token_neu(0, 'einrichten', '', 2);
    ld_send(LD_TO, 'Portal einrichten – Lou Digital', "Mit diesem Link legen Sie das Passwort für den Büro-Zugang des Kundenportals fest (gilt zwei Stunden):\n\n" . LD_SITE . P_BASE . '/einrichten?t=' . $tk, ld_mail_html('de', 'Portal einrichten', '<p style="margin:0">Mit dem Knopf unten legen Sie das Passwort für den Büro-Zugang des Kundenportals fest. Der Link gilt zwei Stunden.</p>', [], p_btn_link(LD_SITE . P_BASE . '/einrichten?t=' . $tk, 'Büro-Zugang einrichten')));
    out('Portal einrichten', '<section class="p-card p-card--narrow"><h1>Link ist unterwegs</h1><p>Eine E-Mail mit dem Einrichtungs-Link ging an ' . LD_TO . '.</p></section>');
  }
  out('Portal einrichten', '<section class="p-card p-card--narrow"><h1>Portal einrichten</h1><p class="p-lead">Es gibt noch keinen Büro-Zugang. Der Einrichtungs-Link wird an ' . LD_TO . ' geschickt.</p><form method="post" class="p-form"><button class="btn btn--primary" type="submit">Link an ' . LD_TO . ' senden</button></form></section>');
}

if (!$me) p_go(P_BASE . '/login');

/* ---------- Zweiter Faktor ---------- */
if ($zfaOffen) {
  if ($route !== 'zfa') p_go(P_BASE . '/zfa');
  $err = '';
  if ($post) {
    if (!ld_rate('pzfa', 30)) $err = 'Zu viele Versuche.';
    elseif (ld_totp_ok((string)$me['totp_secret'], (string)($_POST['code'] ?? ''))) { pd_q('UPDATE sitzungen SET zfa = 1 WHERE id = ?', [$sess['id']]); p_go(P_BASE . ($pwOffen ? '/passwort' : '')); }
    else $err = 'Der Code stimmt nicht. Bitte den aktuellen Code aus der Authenticator-App eingeben.';
  }
  out('Zweiter Faktor', '<section class="p-card p-card--narrow"><h1>Zweiter Faktor</h1><p class="p-lead">Bitte den 6-stelligen Code aus Ihrer Authenticator-App eingeben.</p>' . ($err ? '<div class="p-flash p-flash--warn">' . h($err) . '</div>' : '')
    . '<form method="post" class="p-form">' . p_form($csrf) . '<label>Code<input name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" required autofocus></label><button class="btn btn--primary" type="submit">Bestätigen</button></form>'
    . '<form method="post" action="' . P_BASE . '/logout" class="p-small">' . p_form($csrf) . '<button class="p-link" type="submit">Abbrechen und abmelden</button></form><p class="p-small p-muted">Kein Zugriff auf die App? Schreiben Sie an ' . LD_TO . ', ich setze den zweiten Faktor nach Rückfrage zurück.</p></section>');
}

/* ---------- Erstes Passwort ersetzen (Pflicht nach dem erzeugten Startpasswort) ---------- */
if ($pwOffen) {
  if ($route !== 'passwort') p_go(P_BASE . '/passwort');
  $err = '';
  if ($post) {
    $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
    if ($p1 !== $p2) $err = 'Die Passwörter sind nicht gleich.'; elseif (!p_pw_ok($p1)) $err = 'Mindestens 10 Zeichen, mit Buchstaben und einer Zahl oder einem Sonderzeichen.';
    elseif (password_verify($p1, $me['pass_hash'])) $err = 'Bitte ein neues Passwort wählen, nicht das Startpasswort.';
    else {
      pd_q('UPDATE kunden SET pass_hash = ?, pass_wechsel = 0 WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), (int)$me['id']]);
      pd_q('DELETE FROM sitzungen WHERE kunde_id = ? AND id <> ?', [(int)$me['id'], $sess['id']]);
      p_mail($me, 'Ihr eigenes Passwort ist gesetzt – Lou Digital Portal', '<p style="margin:0">Guten Tag ' . h($me['name']) . ',</p><p style="margin:10px 0 0">Sie haben das Startpasswort durch Ihr eigenes ersetzt. Ab jetzt melden Sie sich damit an. Jede weitere Änderung bestätigen Sie per E-Mail.</p>');
      pd_log((int)$me['id'], 'pw_erstwechsel', '');
      p_flash('Willkommen! Ihr eigenes Passwort ist gesetzt.'); p_go(P_BASE);
    }
  }
  out('Eigenes Passwort', '<section class="p-card p-card--narrow"><h1>Eigenes Passwort festlegen</h1><p class="p-lead">Sie sind mit dem Startpasswort aus der E-Mail angemeldet. Bitte ersetzen Sie es jetzt durch ein eigenes.</p>' . ($err ? '<div class="p-flash p-flash--warn">' . h($err) . '</div>' : '')
    . '<form method="post" class="p-form">' . p_form($csrf) . '<label>Neues Passwort<input name="p1" type="password" required minlength="10" autocomplete="new-password"></label><label>Noch einmal<input name="p2" type="password" required minlength="10" autocomplete="new-password"></label><button class="btn btn--primary" type="submit">Speichern</button></form></section>');
}

/* ---------- Bestätigungs-Links (Passwortwechsel, E-Mail-Wechsel) ---------- */
if ($route === 'bestaetigen') {
  $t = (string)($_GET['t'] ?? ''); $z = preg_replace('/[^a-z_]/', '', (string)($_GET['z'] ?? '')) ?? '';
  $tk = in_array($z, ['pw', 'email'], true) ? ld_token_lesen($t, $z, true) : null;
  if (!$tk || (int)$tk['kunde_id'] !== (int)$me['id']) out('Link ungültig', '<section class="p-card p-card--narrow"><h1>Link ungültig oder abgelaufen</h1><p>Bitte den Vorgang unter <a href="' . P_BASE . '/einstellungen">Einstellungen</a> neu starten.</p></section>');
  if ($z === 'pw') { pd_q('UPDATE kunden SET pass_hash = ? WHERE id = ?', [$tk['daten'], (int)$me['id']]); pd_q('DELETE FROM sitzungen WHERE kunde_id = ? AND id <> ?', [(int)$me['id'], $sess['id']]); pd_log((int)$me['id'], 'pw_wechsel', ''); p_mail($me, 'Passwort geändert – Lou Digital Portal', '<p style="margin:0">Ihr Passwort wurde soeben geändert. Falls Sie das nicht waren, antworten Sie bitte sofort auf diese E-Mail.</p>'); p_flash('Neues Passwort bestätigt. Andere Geräte wurden abgemeldet.'); }
  if ($z === 'email') { $neu = strtolower($tk['daten']); if (filter_var($neu, FILTER_VALIDATE_EMAIL) && !pd_row('SELECT 1 FROM kunden WHERE email = ?', [$neu])) { pd_q('UPDATE kunden SET email = ? WHERE id = ?', [$neu, (int)$me['id']]); pd_log((int)$me['id'], 'email_wechsel', $neu); p_flash('E-Mail-Adresse geändert: ' . $neu); } else p_flash('Diese E-Mail-Adresse kann nicht verwendet werden.', 'warn'); }
  p_go(P_BASE . '/einstellungen');
}

/* ---------- Seiten ---------- */
require __DIR__ . '/' . ($admin && strpos($route, 'admin') === 0 ? 'admin.php' : 'kunde.php');
