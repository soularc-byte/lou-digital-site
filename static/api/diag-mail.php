<?php
/* Vorübergehende Mail-Diagnose (Version 12). Schaltet sich am 26.10.2026 ab und läuft höchstens 6-mal pro Stunde. */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
if (time() > strtotime('2026-10-26 23:59:59')) { http_response_code(404); exit; }
if (!ld_rate('diag', 6)) ld_json(429, ['ok' => false, 'error' => 'rate']);
$out = ['php' => PHP_VERSION, 'sendmail_path' => ini_get('sendmail_path'), 'curl' => function_exists('curl_init'), 'open_basedir' => ini_get('open_basedir'),
        'data_dir' => ld_dir('ld-data'), 'data_dir_writable' => is_writable(ld_dir('ld-data')), 'smtp_cfg' => ld_smtp_cfg() ? 'vorhanden' : 'keine', 'server_ip' => $_SERVER['SERVER_ADDR'] ?? ''];
$out['dns'] = [
  'mx' => array_map(function ($r) { return ($r['pri'] ?? '') . ' ' . ($r['target'] ?? ''); }, @dns_get_record('lou-digital.de', DNS_MX) ?: []),
  'txt' => array_map(function ($r) { return $r['txt'] ?? ''; }, @dns_get_record('lou-digital.de', DNS_TXT) ?: []),
  'dmarc' => array_map(function ($r) { return $r['txt'] ?? ''; }, @dns_get_record('_dmarc.lou-digital.de', DNS_TXT) ?: []),
  'a' => array_map(function ($r) { return $r['ip'] ?? ''; }, @dns_get_record('lou-digital.de', DNS_A) ?: []),
];
$id = date('H:i:s');
$out['mail()'] = ld_send(LD_TO, "Diagnose 1 ($id): PHP mail()", "Test über PHP mail() vom Webserver.\n" . $id);
// direkter SMTP-Weg zum eigenen MX (ohne Anmeldung, Zustellung an info@)
$mx = $out['dns']['mx'] ? trim(explode(' ', $out['dns']['mx'][0])[1] ?? '') : '';
$log = [];
if ($mx !== '') {
  $s = @stream_socket_client('tcp://' . $mx . ':25', $en, $es, 8);
  if (!$s) $log[] = "connect $mx:25 fehlgeschlagen: $es";
  else {
    stream_set_timeout($s, 10);
    $rd = function () use ($s) { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return trim($r); };
    $c = function ($x) use ($s, $rd, &$log) { fwrite($s, $x . "\r\n"); $log[] = '> ' . $x; $r = $rd(); $log[] = $r; return $r; };
    $log[] = $rd();
    $c('EHLO lou-digital.de'); $c('MAIL FROM:<' . LD_FROM . '>'); $r = $c('RCPT TO:<' . LD_TO . '>');
    if (strpos($r, '25') === 0) { $r = $c('DATA'); if (strpos($r, '354') === 0) { fwrite($s, "From: Lou Digital <" . LD_FROM . ">\r\nTo: <" . LD_TO . ">\r\nSubject: Diagnose 2 ($id): direkt per SMTP\r\nDate: " . date('r') . "\r\n\r\nTest direkt an den MX.\r\n.\r\n"); $log[] = $rd(); } }
    $c('QUIT'); fclose($s);
  }
}
$out['smtp_direct'] = $log;
// Anmeldung am Mailserver prüfen (nur Verbindung, ohne Passwort)
foreach ([465, 587] as $port) {
  $s = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . ($mx ?: 'mx980a.netcup.net') . ':' . $port, $en, $es, 6);
  $out['submission_' . $port] = $s ? trim((string)fgets($s, 512)) : "nicht erreichbar: $es";
  if ($s) fclose($s);
}
ld_json(200, $out);
