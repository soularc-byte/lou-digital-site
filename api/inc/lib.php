<?php
/* Lou Digital – gemeinsame Funktionen für Formulare, Mails, Website-Check und Statistik (Version 33, 31.10.2026)
   Version 33: SSRF-Schutz mit echtem Port (nur 80/443, nur IPv4), Rate-Limits je IPv6-/64-Netz, ld_rate_key(), ld_same_origin(),
   IPv6-Sonderbereiche per Präfix, Fehlermeldungen nie anzeigen, SMTP nur über TLS-Ports.
   Version 32: Rate-Limit-Dateien werden aufgeräumt, strengere Prüfung öffentlicher Adressen (Carrier-NAT, Multicast, IPv6-Sonderbereiche), ld_resolve_public(). */
declare(strict_types=1);
if (!defined('LD')) { http_response_code(404); exit; }
@ini_set('display_errors', '0'); @ini_set('log_errors', '1');
date_default_timezone_set('Europe/Berlin');

const LD_TO        = 'info@lou-digital.de';
const LD_FROM      = 'noreply@lou-digital.de';
const LD_FROM_NAME = 'Lou Digital';
const LD_PHONE     = '+49 156 30163539';
const LD_SITE      = 'https://lou-digital.de';

function ld_root(): string { return rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2)), '/'); }
/* Daten und private Einstellungen liegen außerhalb von httpdocs */
function ld_dir(string $name): string {
  $d = dirname(ld_root()) . '/' . $name;
  if (!is_dir($d)) @mkdir($d, 0750, true);
  if (!is_dir($d) || !is_writable($d)) { error_log('Lou Digital: Datenordner ' . $d . ' nicht beschreibbar'); ld_json(503, ['ok' => false, 'error' => 'storage']); }   // Version 33: lieber abbrechen als Daten im temporären Ordner ablegen
  return $d;
}
function ld_json(int $code, array $data): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
/* Version 33: IPv6-Adressen zählen je /64-Netz (sonst bekäme jede Adresse eines Anschlusses einen eigenen Zähler) */
function ld_ip(): string {
  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { $b = @inet_pton($ip); if ($b !== false) return bin2hex(substr($b, 0, 8)) . '::/64'; }
  return $ip;
}
/* Zähler mit eigenem Schlüssel und Zeitfenster (z. B. je Empfänger-Adresse oder gesamt), unabhängig von der IP */
function ld_rate_key(string $key, int $max, int $window): bool {
  $f = sys_get_temp_dir() . '/ld_rk_' . hash('sha256', $key . '|' . intdiv(time(), max(60, $window)));
  $n = is_file($f) ? (int)file_get_contents($f) : 0;
  if ($n >= $max) return false;
  @file_put_contents($f, (string)($n + 1), LOCK_EX);
  if (random_int(1, 40) === 1) foreach (glob(sys_get_temp_dir() . '/ld_rk_*') ?: [] as $g) if (@filemtime($g) < time() - 2 * 86400) @unlink($g);
  return true;
}
/* Öffentliche POST-Schnittstellen nur von der eigenen Website (stoppt das Absenden über fremde Seiten) */
function ld_same_origin(): void {
  $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
  if ($o === '' || $o === 'null') { $r = (string)($_SERVER['HTTP_REFERER'] ?? ''); if ($r === '') return; $o = $r; }
  if ($o !== LD_SITE && strpos($o, LD_SITE . '/') !== 0) ld_json(403, ['ok' => false, 'error' => 'origin']);
}
/* Begrenzung pro IP und Stunde; gespeichert wird nur ein Hash, der nach einer Stunde wertlos ist.
   Version 32: alte Zähldateien werden gelegentlich aufgeräumt, damit sich im temporären Ordner nichts ansammelt. */
function ld_rate(string $bucket, int $max): bool {
  $bucket = preg_replace('/[^a-z0-9_-]/i', '', $bucket) ?? '';
  $f = sys_get_temp_dir() . '/ld_rl_' . $bucket . '_' . hash('sha256', ld_ip() . date('YmdH') . $bucket);
  $n = is_file($f) ? (int)file_get_contents($f) : 0;
  if ($n >= $max) return false;
  @file_put_contents($f, (string)($n + 1));
  if (random_int(1, 40) === 1) foreach (glob(sys_get_temp_dir() . '/ld_rl_*') ?: [] as $g) if (@filemtime($g) < time() - 7200) @unlink($g);
  return true;
}
/* Erste öffentliche IPv4-Adresse eines Hostnamens oder null (interne Netze, lokale Adressen und unbekannte Namen werden abgelehnt) */
function ld_resolve_public(string $host): ?string {
  $host = strtolower(trim($host));
  if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) return null;
  $ips = @gethostbynamel($host) ?: [];
  if (!$ips) return null;
  foreach ($ips as $ip) if (!ld_public_ip($ip)) return null;
  return $ips[0];
}
function ld_post(string $k, int $max): string {
  $v = $_POST[$k] ?? '';
  if (!is_string($v)) return '';
  $v = str_replace(["\r\n", "\r"], "\n", $v);
  $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '';
  return trim(mb_substr($v, 0, $max));
}
/* GET-Wert als Zeichenkette (Arrays wie ?t[]=1 ergeben einen leeren Text, keine Warnung) */
function ld_get(string $k): string { $v = $_GET[$k] ?? ''; return is_string($v) ? $v : ''; }
function ld_line(string $v): string { return trim(preg_replace('/\s+/u', ' ', $v) ?? ''); }
function ld_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ld_enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

/* Private Einstellung aus ld-private/<name>.php (liegt außerhalb von httpdocs und nicht im Repo) */
function ld_private(string $name) {
  $f = dirname(ld_root()) . '/ld-private/' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.php';
  return is_file($f) ? (include $f) : null;
}
/* Webhook-Adresse für n8n (Telegram-Meldung + Leads-Tabelle), nur https auf der eigenen n8n-Instanz */
function ld_n8n_hook(): string {
  $u = ld_private('n8n');
  return (is_string($u) && preg_match('~^https://n8n\.oblivion-hq\.duckdns\.org/webhook/[A-Za-z0-9_-]+$~', $u)) ? $u : '';
}

/* ---------- Mailversand: SMTP mit Anmeldung, wenn ld-private/smtp.php existiert, sonst PHP mail() ---------- */
function ld_smtp_cfg(): ?array {
  $f = dirname(ld_root()) . '/ld-private/smtp.php';
  if (!is_file($f)) return null;
  $c = include $f;
  return (is_array($c) && !empty($c['host']) && !empty($c['user']) && isset($c['pass'])) ? $c : null;
}
function ld_send(string $to, string $subject, string $text, ?string $html = null, string $replyTo = ''): bool {
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
  if (($dump = getenv('LD_MAILDUMP')) && is_string($dump) && $dump !== '') { @file_put_contents($dump, "TO: $to\nSUBJECT: $subject\n$text\n-----\n", FILE_APPEND); return true; }   // nur für lokale Tests
  $h = [
    'From: ' . ld_enc(LD_FROM_NAME) . ' <' . LD_FROM . '>',
    'Date: ' . date('r'),
    'Message-ID: <' . bin2hex(random_bytes(12)) . '@lou-digital.de>',
    'MIME-Version: 1.0',
    'X-Mailer: lou-digital.de',
    'Auto-Submitted: auto-generated',
  ];
  if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: ' . $replyTo;
  if ($html !== null) {
    $b = 'ld_' . bin2hex(random_bytes(10));
    $h[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
          . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
          . "--$b--\r\n";
  } else {
    $h[] = 'Content-Type: text/plain; charset=UTF-8';
    $h[] = 'Content-Transfer-Encoding: base64';
    $body = chunk_split(base64_encode($text));
  }
  $cfg = ld_smtp_cfg();
  if ($cfg) {
    $msg = 'To: <' . $to . ">\r\nSubject: " . ld_enc($subject) . "\r\n" . implode("\r\n", $h) . "\r\n\r\n" . $body;
    if (ld_smtp($cfg, $to, $msg)) return true;
  }
  return @mail($to, ld_enc($subject), $body, implode("\r\n", $h), '-f' . LD_FROM);
}
function ld_smtp(array $c, string $to, string $msg, ?array &$log = null): bool {
  $log = [];
  $port = (int)($c['port'] ?? 465);
  if (!in_array($port, [465, 587], true)) { $log[] = 'port'; return false; }   // Version 33: Anmeldung nur verschlüsselt
  $host = ($port === 465 ? 'ssl://' : '') . $c['host'];
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['host']]]);
  $s = @stream_socket_client($host . ':' . $port, $en, $es, 8, STREAM_CLIENT_CONNECT, $ctx);
  if (!$s) { $log[] = "connect: $es"; return false; }
  stream_set_timeout($s, 10);
  $read = function () use ($s, &$log) { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } $log[] = trim($r); return $r; };
  $cmd = function (string $c, string $expect) use ($s, $read, &$log) { fwrite($s, $c . "\r\n"); $log[] = '> ' . (stripos($c, 'AUTH') === 0 || preg_match('/^[A-Za-z0-9+\/=]{8,}$/', $c) ? '[…]' : $c); $r = $read(); return strpos($r, $expect) === 0; };
  $read();
  $ok = $cmd('EHLO lou-digital.de', '250');
  if ($ok && $port === 587) { $ok = $cmd('STARTTLS', '220') && stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $cmd('EHLO lou-digital.de', '250'); }
  $ok = $ok && $cmd('AUTH LOGIN', '334') && $cmd(base64_encode($c['user']), '334') && $cmd(base64_encode((string)$c['pass']), '235')
        && $cmd('MAIL FROM:<' . LD_FROM . '>', '250') && $cmd('RCPT TO:<' . $to . '>', '25') && $cmd('DATA', '354');
  if ($ok) { fwrite($s, preg_replace('/^\./m', '..', $msg) . "\r\n.\r\n"); $ok = strpos($read(), '250') === 0; }
  @fwrite($s, "QUIT\r\n"); fclose($s);
  return $ok;
}

/* ---------- HTML-Mail im Look der Website ---------- */
function ld_mail_html(string $lang, string $title, string $intro, array $rows = [], string $extra = ''): string {
  $de = $lang !== 'en';
  $tr = '';
  foreach ($rows as $k => $v) {
    if ($v === '') continue;
    $tr .= '<tr><td style="padding:8px 12px 8px 0;color:#5d6b69;font-size:14px;vertical-align:top;white-space:nowrap">' . ld_h($k) . '</td><td style="padding:8px 0;font-size:14px;color:#11191b">' . nl2br(ld_h($v)) . '</td></tr>';
  }
  $table = $tr ? '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0;border-top:1px solid #e3e8e6;border-bottom:1px solid #e3e8e6">' . $tr . '</table>' : '';
  $tel = str_replace(' ', '', LD_PHONE);
  $ps = $de ? 'PS: Diese E-Mail wurde automatisch versendet. Wenn Sie auf „Antworten“ klicken, geht Ihre Nachricht direkt an ' . LD_TO . '.'
            : 'PS: This email was sent automatically. If you click “Reply”, your message goes straight to ' . LD_TO . '.';
  return '<!doctype html><html lang="' . ($de ? 'de' : 'en') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . ld_h($title) . '</title></head>'
    . '<body style="margin:0;padding:0;background:#f5f6f3;font-family:Arial,Helvetica,sans-serif;color:#11191b">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6f3"><tr><td align="center" style="padding:28px 12px">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden">'
    . '<tr><td style="background:#0b6e69;padding:22px 28px"><img src="' . LD_SITE . '/img/mail-logo.png" width="30" height="30" alt="" style="display:inline-block;width:30px;height:30px;border-radius:8px;vertical-align:middle;border:0">'
    . '<span style="font-size:20px;font-weight:bold;color:#ffffff;vertical-align:middle;margin-left:10px;letter-spacing:-0.3px">Lou Digital</span></td></tr>'
    . '<tr><td style="padding:30px 28px 10px">'
    . '<h1 style="margin:0 0 14px;font-size:24px;line-height:1.2;letter-spacing:-0.4px;color:#11191b">' . ld_h($title) . '</h1>'
    . '<div style="font-size:15px;line-height:1.6;color:#2b3634">' . $intro . '</div>' . $table . $extra
    . '<p style="font-size:15px;line-height:1.6;margin:22px 0 0;color:#2b3634">' . ($de ? 'Viele Grüße' : 'Best regards') . '<br><strong>Adrian Semlinger</strong><br>Lou Digital, Karlsruhe<br>'
    . '<a href="tel:' . $tel . '" style="color:#0b6e69">' . LD_PHONE . '</a> · <a href="' . LD_SITE . ($de ? '' : '/en/') . '" style="color:#0b6e69">lou-digital.de</a></p>'
    . '</td></tr>'
    . '<tr><td style="padding:22px 28px 26px"><p style="margin:0;padding-top:14px;border-top:1px solid #e3e8e6;font-size:11px;line-height:1.5;color:#8a9694">' . ld_h($ps) . '</p></td></tr>'
    . '</table></td></tr></table></body></html>';
}

/* ---------- Website-Check: fünf Punkte ---------- */
function ld_public_ip(string $ip): bool {
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $l = ip2long($ip);
    foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15], ['224.0.0.0', 4]] as $n) { $m = -1 << (32 - $n[1]); if (($l & $m) === (ip2long($n[0]) & $m)) return false; }   // Carrier-NAT, Sonderbereiche, Multicast
    return true;
  }
  /* IPv6: Sonderbereiche per Präfix (Version 33): ::/96, ::ffff:0:0/96, 64:ff9b::/96, 100::/64, 2001::/23, 2002::/16, fc00::/7, fe80::/10, fec0::/10, ff00::/8 */
  $b = @inet_pton($ip); if ($b === false || strlen($b) !== 16) return false;
  $bits = ''; foreach (str_split($b) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
  foreach ([['::', 96], ['::ffff:0:0', 96], ['64:ff9b::', 96], ['64:ff9b:1::', 48], ['100::', 64], ['2001::', 23], ['2001:db8::', 32], ['2002::', 16], ['fc00::', 7], ['fe80::', 10], ['fec0::', 10], ['ff00::', 8]] as $n) {
    $nb = ''; foreach (str_split((string)inet_pton($n[0])) as $c) $nb .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    if (substr($bits, 0, $n[1]) === substr($nb, 0, $n[1])) return false;
  }
  return true;
}
function ld_norm_url(string $u): ?array {
  $u = trim($u);
  if ($u === '' || mb_strlen($u) > 200) return null;
  if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
  $p = parse_url($u);
  if (!$p || empty($p['host'])) return null;
  $host = strtolower($p['host']);
  if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) return null;
  if (isset($p['port']) && !in_array((int)$p['port'], [80, 443], true)) return null;
  $path = $p['path'] ?? '/';
  return ['host' => $host, 'path' => $path === '' ? '/' : $path];
}
function ld_fetch(string $url, int $hops = 4, bool $verify = true): array {
  $res = ['ok' => false, 'url' => $url, 'tls' => false, 'html' => '', 'ms' => 0, 'err' => ''];
  $t0 = microtime(true);
  for ($i = 0; $i <= $hops; $i++) {
    $p = parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) { $res['err'] = 'url'; return $res; }
    $host = strtolower($p['host']); $https = strtolower($p['scheme']) === 'https';
    /* Version 33: Port aus der Adresse (auch nach Umleitungen), nur 80/443; nur Hostnamen, keine IP-Literale */
    $port = isset($p['port']) ? (int)$p['port'] : ($https ? 443 : 80);
    if (!in_array($port, [80, 443], true)) { $res['err'] = 'blocked'; return $res; }
    if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) { $res['err'] = 'blocked'; return $res; }
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) { $res['err'] = 'dns'; return $res; }
    foreach ($ips as $ip) if (!ld_public_ip($ip)) { $res['err'] = 'blocked'; return $res; }
    $buf = ''; $tooBig = false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]], CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
      CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 9,
      CURLOPT_SSL_VERIFYPEER => $verify, CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
      CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; LouDigital-Check/1.0; +https://lou-digital.de/website-check)',
      CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: de-DE,de;q=0.9,en;q=0.8'],
      CURLOPT_ENCODING => '',
      CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf, &$tooBig) { $buf .= $d; if (strlen($buf) > 1600000) { $tooBig = true; return 0; } return strlen($d); },
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $errno = curl_errno($ch); curl_close($ch);
    if ($errno && !$tooBig) { $res['err'] = in_array($errno, [51, 60], true) ? 'tls' : ($errno === 35 ? 'tls35' : 'net'); $res['code'] = $errno; $res['ms'] = (int)((microtime(true) - $t0) * 1000); return $res; }
    if ($code >= 300 && $code < 400 && $loc !== '') { $url = $loc; continue; }
    $res['ok'] = $code >= 200 && $code < 400; $res['url'] = $url; $res['tls'] = $https; $res['html'] = $buf; $res['code'] = $code;
    $res['ms'] = (int)((microtime(true) - $t0) * 1000);
    if (!$res['ok']) $res['err'] = 'http' . $code;
    return $res;
  }
  $res['err'] = 'redirects'; return $res;
}
function ld_check(string $input, string $lang): array {
  $de = $lang !== 'en';
  $n = ld_norm_url($input);
  if (!$n) return ['ok' => false, 'error' => 'url'];
  if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'server'];
  $r = ld_fetch('https://' . $n['host'] . $n['path']);
  if (!$r['ok'] && in_array($r['err'], ['tls', 'tls35', 'net'], true)) { usleep(400000); $r = ld_fetch('https://' . $n['host'] . $n['path']); }   // zweiter Versuch gegen kurze Aussetzer
  if (!$r['ok'] && $r['err'] === 'tls35') $r['err'] = 'net';
  $httpsOk = $r['ok'] && $r['tls'] && stripos($r['url'], 'https://') === 0;
  $tlsBad = false;
  if (!$r['ok']) {
    if (in_array($r['err'], ['blocked', 'url'], true)) return ['ok' => false, 'error' => 'url'];
    if ($r['err'] === 'dns') return ['ok' => false, 'error' => 'dns'];
    if ($r['err'] === 'tls') { $tlsBad = true; $r = ld_fetch('https://' . $n['host'] . $n['path'], 4, false); }
    if (!$r['ok']) {
      $r2 = ld_fetch('http://' . $n['host'] . $n['path']);
      if (!$r2['ok'] && $r2['err'] === 'tls') { $tlsBad = true; $r2 = ld_fetch('http://' . $n['host'] . $n['path'], 4, false); }
      if (!$r2['ok']) return ['ok' => false, 'error' => 'unreachable'];
      $r = $r2;
    }
    $httpsOk = !$tlsBad && stripos($r['url'], 'https://') === 0 && $r['tls'];
  }
  $html = $r['html'];
  $mobile = false;
  if (preg_match_all('~<meta\b[^>]*>~i', $html, $m)) foreach ($m[0] as $tag) if (preg_match('~name\s*=\s*["\']?viewport~i', $tag) && preg_match('~width\s*=\s*device-width~i', $tag)) $mobile = true;
  $links = '';
  if (preg_match_all('~<a\b([^>]*)>(.*?)</a>~is', $html, $am, PREG_SET_ORDER)) foreach ($am as $a) $links .= ' ' . mb_strtolower(html_entity_decode(strip_tags($a[1] . ' ' . $a[2]), ENT_QUOTES, 'UTF-8'));
  $imprint = (bool)preg_match('/impressum|imprint|legal[\s_-]?notice|mentions[\s-]l[ée]gales/u', $links);
  $privacy = (bool)preg_match('/datenschutz|privacy|data[\s_-]?protection|dsgvo|gdpr/u', $links);
  $title = '';
  if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $tm)) $title = ld_line(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
  $generic = ['home', 'startseite', 'start', 'willkommen', 'welcome', 'index', 'untitled', 'homepage', 'meine website', 'my website'];
  $tl = mb_strlen($title);
  $titleOk = $tl >= 15 && $tl <= 70 && !in_array(mb_strtolower($title), $generic, true) && count(preg_split('/\s+/u', $title) ?: []) >= 2;
  $items = [
    ['id' => 'https', 'ok' => $httpsOk, 'label' => $de ? 'Verschlüsselt über HTTPS' : 'Encrypted via HTTPS',
     'hint' => $httpsOk ? ($de ? 'Das Schloss im Browser ist da.' : 'The browser shows the padlock.') : ($tlsBad ? ($de ? 'Das Sicherheitszertifikat ist ungültig oder abgelaufen. Browser zeigen Ihren Besuchern eine Warnseite.' : 'The security certificate is invalid or expired. Browsers show your visitors a warning page.') : ($de ? 'Ohne HTTPS warnen Browser Ihre Besucher, und Google stuft die Seite ab.' : 'Without HTTPS, browsers warn your visitors and Google ranks the site lower.'))],
    ['id' => 'mobile', 'ok' => $mobile, 'label' => $de ? 'Auf dem Handy gut lesbar' : 'Easy to read on a phone',
     'hint' => $mobile ? ($de ? 'Die Seite passt sich der Bildschirmbreite an.' : 'The page adapts to the screen width.') : ($de ? 'Die Seite ist nicht für Handys eingerichtet. Dort kommen die meisten Besucher her.' : 'The page isn’t set up for phones, where most visitors come from.')],
    ['id' => 'imprint', 'ok' => $imprint, 'label' => $de ? 'Impressum verlinkt' : 'Legal notice linked',
     'hint' => $imprint ? ($de ? 'Ein Link zum Impressum ist auf der Startseite.' : 'A link to the legal notice is on the home page.') : ($de ? 'Auf der Startseite fehlt ein Link zum Impressum. Für Unternehmen ist es Pflicht.' : 'The home page has no link to a legal notice, which is mandatory for businesses in Germany.')],
    ['id' => 'privacy', 'ok' => $privacy, 'label' => $de ? 'Datenschutzerklärung verlinkt' : 'Privacy policy linked',
     'hint' => $privacy ? ($de ? 'Die Datenschutzerklärung ist erreichbar.' : 'The privacy policy is reachable.') : ($de ? 'Ein Link zur Datenschutzerklärung fehlt. Das kann Abmahnungen nach sich ziehen.' : 'A link to the privacy policy is missing, which can lead to formal warnings.')],
    ['id' => 'title', 'ok' => $titleOk, 'label' => $de ? 'Aussagekräftiger Seitentitel' : 'Meaningful page title',
     'hint' => $title === '' ? ($de ? 'Die Seite hat keinen Titel. Google weiß nicht, was Sie anbieten.' : 'The page has no title, so Google doesn’t know what you offer.')
             : ($titleOk ? ($de ? 'Titel: „' . $title . '“' : 'Title: “' . $title . '”') : ($de ? 'Titel „' . mb_substr($title, 0, 70) . '“ sagt zu wenig über Angebot und Ort.' : 'Title “' . mb_substr($title, 0, 70) . '” says too little about offer and location.'))],
  ];
  $score = count(array_filter($items, function ($i) { return $i['ok']; }));
  return ['ok' => true, 'host' => $n['host'], 'url' => $r['url'], 'score' => $score, 'max' => 5, 'items' => $items, 'ms' => $r['ms'], 'kb' => (int)round(strlen($html) / 1024)];
}
function ld_check_text(array $c, string $lang): string {
  if (empty($c['ok'])) return '';
  $de = $lang !== 'en';
  $t = ($de ? 'Ergebnis für ' : 'Result for ') . $c['host'] . ': ' . $c['score'] . '/5' . "\n";
  foreach ($c['items'] as $i) $t .= ($i['ok'] ? '✓ ' : '✕ ') . $i['label'] . ' – ' . $i['hint'] . "\n";
  return $t;
}
function ld_check_html(array $c, string $lang): string {
  if (empty($c['ok'])) return '';
  $de = $lang !== 'en';
  $li = '';
  foreach ($c['items'] as $i) {
    $li .= '<tr><td style="width:28px;padding:8px 0;vertical-align:top"><span style="display:inline-block;width:20px;height:20px;border-radius:50%;text-align:center;line-height:20px;font-size:12px;font-weight:bold;'
         . ($i['ok'] ? 'background:#dff3ef;color:#0b6e69">✓' : 'background:#fde8e4;color:#b33a26">✕') . '</span></td>'
         . '<td style="padding:8px 0;font-size:14px;line-height:1.5"><strong>' . ld_h($i['label']) . '</strong><br><span style="color:#5d6b69">' . ld_h($i['hint']) . '</span></td></tr>';
  }
  return '<div style="margin:18px 0;padding:18px;border-radius:12px;background:#f2f7f6"><p style="margin:0 0 8px;font-size:14px;color:#5d6b69">' . ($de ? 'Website-Check für ' : 'Website check for ') . '<strong style="color:#11191b">' . ld_h($c['host']) . '</strong></p>'
       . '<p style="margin:0 0 10px;font-size:28px;font-weight:bold;color:#0b6e69">' . (int)$c['score'] . '<span style="font-size:16px;color:#8a9694">/5</span></p>'
       . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%">' . $li . '</table></div>';
}
