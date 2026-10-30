<?php
/*
 * Lou Digital – 100-Punkte-Website-Check (Version 5, 31.10.2026)
 * Version 5: nur von der eigenen Website, höchstens 4 Prüfungen gleichzeitig, Begrenzung je IPv6-/64-Netz, IPv6-Sonderbereiche per Präfix.
 * Version 4: jeder Punkt hat einen Status (ok, fehlt, na = trifft nicht zu, np = nicht prüfbar), gezählt wird immer von 100.
 * POST url=…&sprache=de|en&modus=sofort|voll
 * Prüft nur, was jeder Besucher sieht: Antworten des Servers, Zertifikat, HTML der Startseite und der Kontaktseite,
 * robots.txt, Sitemap, security.txt, Fehlerseite, verlinkte Unterseiten und öffentliche DNS-Einträge.
 * Keine Angriffe, kein Ausfüllen von Formularen, keine Speicherung des Ergebnisses.
 * Schutz: nur öffentliche Adressen (keine internen Netze), jede Weiterleitung wird einzeln geprüft, feste IP je Abruf,
 * kein Proxy, Größen- und Zeitgrenzen, Begrenzung je Besucher (gekürzte Prüfsumme der IP, höchstens 1 Stunde).
 * Texte der Prüfpunkte: check100_daten.php (erzeugt aus gen_punkte100.py).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
}
@set_time_limit(60);
ini_set('display_errors', '0');
ini_set('memory_limit', '256M');

const LC_UA = 'Mozilla/5.0 (compatible; LouDigital-Websitecheck/3.0; +https://lou-digital.de/website-check)';
const LC_MAX_BODY = 2500000;
define('LC_TEST', PHP_SAPI === 'cli' && getenv('LOU_CHECK_TEST') === '1');
define('LC_START', microtime(true));

$LC_FERTIG = false;
function lc_out(array $a, int $code = 200): void
{
    global $LC_FERTIG;
    $LC_FERTIG = true;
    if (PHP_SAPI !== 'cli') http_response_code($code);
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | (PHP_SAPI === 'cli' ? JSON_PRETTY_PRINT : 0));
    exit;
}
// bei einem harten Fehler (Zeit, Speicher) trotzdem eine lesbare Antwort geben
register_shutdown_function(function () {
    global $LC_FERTIG;
    if ($LC_FERTIG) return;
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (PHP_SAPI !== 'cli' && !headers_sent()) http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'intern']);
    }
});

$DATEN = require __DIR__ . '/check100_daten.php';

/* ---------------- Eingabe ---------------- */
$in = PHP_SAPI === 'cli' ? ['url' => $argv[1] ?? '', 'sprache' => $argv[2] ?? 'de', 'modus' => $argv[3] ?? 'sofort'] : $_POST;
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') lc_out(['ok' => false, 'error' => 'url'], 405);
$EN = (($in['sprache'] ?? 'de') === 'en');
$VOLL = (($in['modus'] ?? 'sofort') === 'voll');
$roh = trim((string)($in['url'] ?? ''));
define('LC_FRIST', $VOLL ? 40.0 : 20.0); // Gesamtzeit in Sekunden, danach wird nichts mehr abgerufen
function lc_rest(): float { return LC_START + LC_FRIST - microtime(true); }
if ($roh === '' || strlen($roh) > 200) lc_out(['ok' => false, 'error' => 'url'], 400);
/* Version 5: Aufrufe nur von lou-digital.de (stoppt das Auslösen über fremde Seiten) */
if (PHP_SAPI !== 'cli') {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '' || $o === 'null') $o = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($o !== '' && $o !== 'https://lou-digital.de' && strpos($o, 'https://lou-digital.de/') !== 0) lc_out(['ok' => false, 'error' => 'origin'], 403);
}

/* ---------------- Begrenzung je Besucher (gekürzte Prüfsumme, höchstens 1 Stunde gespeichert, Aufräumen bei jedem Aufruf) ---------------- */
function lc_limit(bool $voll): bool
{
    if (PHP_SAPI === 'cli') return true;
    $dir = dirname(__DIR__, 2) . '/check100-daten';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) { $dir = sys_get_temp_dir() . '/lou-check100'; @mkdir($dir, 0700, true); }
    $saltFile = $dir . '/salt-' . gmdate('Ymd');
    if (!is_file($saltFile)) { @file_put_contents($saltFile, bin2hex(random_bytes(16))); foreach (glob($dir . '/salt-*') ?: [] as $f) if ($f !== $saltFile) @unlink($f); }
    $salt = (string)@file_get_contents($saltFile);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { $b = @inet_pton($ip); if ($b !== false) $ip = bin2hex(substr($b, 0, 8)) . '::/64'; }   // Version 5: je /64-Netz
    $key = substr(hash('sha256', $salt . '|' . $ip), 0, 16);
    $now = time();
    $f = $dir . '/r-' . $key;
    $t = array_filter(array_map('intval', explode(',', (string)@file_get_contents($f))), function ($x) use ($now) { return $x > $now - 3600; });
    $max = $voll ? 6 : 15;
    if (count($t) >= $max) return false;
    $t[] = $now;
    @file_put_contents($f, implode(',', $t), LOCK_EX);
    foreach (glob($dir . '/r-*') ?: [] as $g) if (@filemtime($g) < $now - 3600) @unlink($g);
    $gf = $dir . '/gesamt-' . gmdate('YmdH');
    $n = (int)@file_get_contents($gf) + 1;
    @file_put_contents($gf, (string)$n, LOCK_EX);
    foreach (glob($dir . '/gesamt-*') ?: [] as $g) if ($g !== $gf && @filemtime($g) < $now - 7200) @unlink($g);
    return $n <= 400;
}
if (!lc_limit($VOLL)) lc_out(['ok' => false, 'error' => 'rate'], 429);
/* Version 5: höchstens 4 Prüfungen gleichzeitig, damit der Check nie alle PHP-Prozesse belegt */
$LC_SLOT = null;
if (PHP_SAPI !== 'cli') {
    $sd = sys_get_temp_dir();
    for ($i = 0; $i < 4; $i++) {
        $fh = @fopen($sd . '/lou-check100-slot-' . $i, 'c');
        if ($fh && flock($fh, LOCK_EX | LOCK_NB)) { $LC_SLOT = $fh; break; }
        if ($fh) fclose($fh);
    }
    if ($LC_SLOT === null) lc_out(['ok' => false, 'error' => 'busy'], 429);
}

/* ---------------- Adresse prüfen ---------------- */
function lc_host_ok(string $h): bool
{
    return (bool)preg_match('/^(?=.{3,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $h);
}
function lc_ip_public(string $ip): bool
{
    if (LC_TEST) return true;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $l = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['0.0.0.0', 8], ['192.0.0.0', 24], ['198.18.0.0', 15], ['224.0.0.0', 4]] as $n) {
            $m = -1 << (32 - $n[1]);
            if (($l & $m) === (ip2long($n[0]) & $m)) return false;
        }
    } else {
        /* Version 5: Sonderbereiche per Präfix: ::/96, ::ffff:0:0/96, 64:ff9b::/96, 100::/64, 2001::/23, 2002::/16, fc00::/7, fe80::/10, fec0::/10, ff00::/8 */
        $b = @inet_pton($ip); if ($b === false || strlen($b) !== 16) return false;
        $bits = ''; foreach (str_split($b) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        foreach ([['::', 96], ['::ffff:0:0', 96], ['64:ff9b::', 96], ['64:ff9b:1::', 48], ['100::', 64], ['2001::', 23], ['2001:db8::', 32], ['2002::', 16], ['fc00::', 7], ['fe80::', 10], ['fec0::', 10], ['ff00::', 8]] as $n) {
            $nb = ''; foreach (str_split((string)inet_pton($n[0])) as $c) $nb .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
            if (substr($bits, 0, $n[1]) === substr($nb, 0, $n[1])) return false;
        }
    }
    return true;
}
$DNS_CACHE = [];
function lc_resolve(string $host): ?string
{
    global $DNS_CACHE;
    if (array_key_exists($host, $DNS_CACHE)) return $DNS_CACHE[$host];
    if (LC_TEST && getenv('LOU_CHECK_IP')) return $DNS_CACHE[$host] = getenv('LOU_CHECK_IP');
    if (!lc_host_ok($host)) return $DNS_CACHE[$host] = null;
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) { $r = @dns_get_record($host, DNS_AAAA) ?: []; foreach ($r as $x) if (!empty($x['ipv6'])) $ips[] = $x['ipv6']; }
    $ok = null;
    foreach ($ips as $ip) { if (!lc_ip_public($ip)) return $DNS_CACHE[$host] = null; if ($ok === null) $ok = $ip; }
    return $DNS_CACHE[$host] = $ok;
}
function lc_norm_host(string $h): string
{
    $h = strtolower(rtrim($h, '.'));
    if (preg_match('/[^\x20-\x7e]/', $h) && function_exists('idn_to_ascii')) {
        $a = @idn_to_ascii($h, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($a) $h = $a;
    }
    return $h;
}
$u = preg_match('#^https?://#i', $roh) ? $roh : 'https://' . $roh;
$p = @parse_url($u);
if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && !in_array((int)$p['port'], [80, 443], true))) lc_out(['ok' => false, 'error' => 'url'], 400);
$HOST = lc_norm_host($p['host']);
if (!lc_host_ok($HOST)) lc_out(['ok' => false, 'error' => 'url'], 400);
$BASE = preg_replace('/^www\./', '', $HOST);
$ALT = strpos($HOST, 'www.') === 0 ? $BASE : 'www.' . $HOST;
if (lc_resolve($HOST) === null) {
    $raw = @gethostbynamel($HOST);
    lc_out(['ok' => false, 'error' => $raw ? 'blocked' : 'dns'], 400);
}

/* ---------------- Abrufe ---------------- */
function lc_handle(string $url, string $method = 'GET', int $max = LC_MAX_BODY, int $timeout = 10)
{
    $pp = @parse_url($url);
    if (!$pp || !in_array(strtolower($pp['scheme'] ?? ''), ['http', 'https'], true) || isset($pp['user']) || isset($pp['pass'])) return null;
    $port = isset($pp['port']) ? (int)$pp['port'] : (strtolower($pp['scheme']) === 'http' ? 80 : 443);
    if (!in_array($port, [80, 443], true)) return null;
    $h = lc_norm_host($pp['host'] ?? '');
    $ip = $h !== '' ? lc_resolve($h) : null;
    if ($ip === null) return null;
    $rest = lc_rest();
    if ($rest < 1.5) return false; // Zeit aufgebraucht
    $timeout = (int)max(2, min($timeout, floor($rest)));
    $ch = curl_init($url);
    $st = (object)['headers' => [], 'body' => '', 'cut' => false];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => LC_UA,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: de-DE,de;q=0.9,en;q=0.7'],
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
        CURLOPT_RESOLVE => [$h . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)],
        CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use ($st) {
            $l = trim($line);
            if (preg_match('#^HTTP/#', $l)) $st->headers = [];
            elseif (strpos($l, ':') !== false && count($st->headers) < 80) { [$k, $v] = explode(':', $l, 2); $st->headers[strtolower(trim($k))][] = lc_cut(trim($v), 2000); }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => function ($c, $chunk) use ($st, $max) {
            if (strlen($st->body) + strlen($chunk) > $max) { $st->body .= substr($chunk, 0, $max - strlen($st->body)); $st->cut = true; return -1; }
            $st->body .= $chunk; return strlen($chunk);
        },
    ]);
    if (LC_TEST) curl_setopt_array($ch, [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
    return [$ch, $st];
}
function lc_leer(string $url, string $txt = 'blocked'): array
{
    return ['url' => $url, 'status' => 0, 'err' => $txt === 'zeit' ? -2 : -1, 'errtxt' => $txt, 'headers' => [], 'body' => '', 'cut' => false, 'ttfb' => 0.0, 'total' => 0.0, 'pre' => 0.0, 'ssl' => 0, 'http2' => false, 'ver' => 0];
}
function lc_result($ch, $st, string $url): array
{
    $info = curl_getinfo($ch);
    $err = curl_errno($ch);
    if ($err === CURLE_WRITE_ERROR && $st->cut) $err = 0;
    $ver = defined('CURLINFO_HTTP_VERSION') ? (int)curl_getinfo($ch, CURLINFO_HTTP_VERSION) : 0;
    return ['url' => $url, 'status' => $err ? 0 : (int)$info['http_code'], 'err' => $err, 'errtxt' => $err ? curl_error($ch) : '', 'headers' => $st->headers,
        'body' => $st->body, 'cut' => $st->cut, 'ttfb' => (float)($info['starttransfer_time'] ?? 0), 'total' => (float)($info['total_time'] ?? 0),
        'pre' => (float)($info['pretransfer_time'] ?? 0), 'ssl' => (int)($info['ssl_verify_result'] ?? 0), 'http2' => $ver >= 3, 'ver' => $ver];
}
/** mehrere Abrufe gleichzeitig (höchstens 6 Verbindungen je Server) */
function lc_multi(array $reqs): array
{
    $mh = curl_multi_init();
    if (defined('CURLMOPT_MAX_HOST_CONNECTIONS')) curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, 6);
    $hs = [];
    foreach ($reqs as $k => $r) {
        $h = lc_handle($r[0], $r[1] ?? 'GET', $r[2] ?? LC_MAX_BODY, $r[3] ?? 10);
        if ($h === null || $h === false) { $hs[$k] = $h; continue; }
        $hs[$k] = $h;
        curl_multi_add_handle($mh, $h[0]);
    }
    do { $s = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 0.2); } while ($run && $s === CURLM_OK);
    $out = [];
    foreach ($reqs as $k => $r) {
        if ($hs[$k] === null || $hs[$k] === false) { $out[$k] = lc_leer($r[0], $hs[$k] === false ? 'zeit' : 'blocked'); continue; }
        $out[$k] = lc_result($hs[$k][0], $hs[$k][1], $r[0]);
        curl_multi_remove_handle($mh, $hs[$k][0]);
        curl_close($hs[$k][0]);
    }
    curl_multi_close($mh);
    return $out;
}
function lc_cut(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n); }
function lc_lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function lc_len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function lc_hdr(array $r, string $k): string { return isset($r['headers'][$k]) ? (string)end($r['headers'][$k]) : ''; }
// Aufzählung als Satz: „a, b und c“ bzw. „a, b and c“
function lc_und(array $a, bool $en = false): string { $a = array_values(array_filter(array_map('strval', $a), 'strlen')); if (count($a) < 2) return $a[0] ?? ''; $l = array_pop($a); return implode(', ', $a) . ($en ? ' and ' : ' und ') . $l; }
function lc_abs(string $base, string $rel): string
{
    $rel = trim($rel);
    if ($rel === '') return $base;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $rel)) return $rel;
    $b = parse_url($base);
    if (!$b || empty($b['host'])) return $rel;
    $root = ($b['scheme'] ?? 'https') . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strpos($rel, '//') === 0) return ($b['scheme'] ?? 'https') . ':' . $rel;
    if ($rel[0] === '/') return $root . $rel;
    if ($rel[0] === '?' || $rel[0] === '#') return $root . ($b['path'] ?? '/') . $rel;
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $root . $dir . $rel;
}
/** Weiterleitungen einzeln verfolgen (jede Station wird neu aufgelöst und geprüft) */
function lc_follow(string $url, int $hops = 6, string $method = 'GET', ?array $first = null, int $max = LC_MAX_BODY): array
{
    $chain = [];
    $r = lc_leer($url);
    for ($i = 0; $i <= $hops; $i++) {
        $r = ($i === 0 && $first !== null) ? $first : lc_multi([[$url, $method, $max, 8]])[0];
        $chain[] = ['url' => $url, 'status' => $r['status']];
        if ($r['status'] >= 300 && $r['status'] < 400 && lc_hdr($r, 'location') !== '') { $url = lc_abs($url, lc_hdr($r, 'location')); continue; }
        $r['chain'] = $chain;
        return $r;
    }
    $r['chain'] = $chain;
    return $r;
}
function lc_regdom(string $h): string
{
    $h = preg_replace('/^www\./', '', strtolower($h));
    $p = explode('.', $h);
    $n = count($p);
    if ($n <= 2) return $h;
    $zwei = ['co.uk', 'org.uk', 'com.au', 'co.at', 'or.at', 'com.br', 'co.jp', 'co.nz', 'com.tr', 'com.pl'];
    $last2 = $p[$n - 2] . '.' . $p[$n - 1];
    return in_array($last2, $zwei, true) ? $p[$n - 3] . '.' . $last2 : $last2;
}
function lc_html(array $r): string
{
    $html = (string)$r['body'];
    $cs = '';
    if (preg_match('/charset=["\']?([\w-]+)/i', lc_hdr($r, 'content-type'), $m)) $cs = strtolower($m[1]);
    elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($html, 0, 4000), $m)) $cs = strtolower($m[1]);
    if ($cs !== '' && $cs !== 'utf-8' && $cs !== 'utf8' && function_exists('mb_convert_encoding')) $html = (string)@mb_convert_encoding($html, 'UTF-8', $cs);
    return $html;
}
function lc_ist_html(array $r): bool
{
    $ct = strtolower(lc_hdr($r, 'content-type'));
    return $r['status'] === 200 && $r['body'] !== '' && ($ct === '' ? (bool)preg_match('/<html|<body|<!doctype html/i', substr((string)$r['body'], 0, 2000)) : strpos($ct, 'html') !== false);
}

/* ---------------- HTML lesen (Hilfen) ---------------- */
function lc_page(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if ($html !== '') $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    return new DOMXPath($dom);
}
function lc_q(DOMXPath $x, string $xp): array { $n = @$x->query($xp); return $n ? iterator_to_array($n) : []; }
function lc_at($n, string $a): string { return $n instanceof DOMElement ? trim($n->getAttribute($a)) : ''; }
function lc_meta(DOMXPath $x, string $name): string
{
    foreach (lc_q($x, '//meta') as $m) { $k = strtolower(lc_at($m, 'name') ?: lc_at($m, 'property') ?: lc_at($m, 'http-equiv')); if ($k === strtolower($name)) return lc_at($m, 'content'); }
    return '';
}
function lc_title(DOMXPath $x): string { $t = lc_q($x, '//title'); return $t ? trim(preg_replace('/\s+/u', ' ', $t[0]->textContent)) : ''; }
const LC_SICHTBAR = '[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::template or ancestor::svg or ancestor::*[@aria-hidden="true"] or ancestor::*[@hidden])]';
/** sichtbarer Text eines Elements (ohne versteckte Doppel-Beschriftungen), Wörter mit Leerzeichen getrennt */
function lc_ntext(DOMXPath $x, DOMNode $n): string
{
    $t = [];
    foreach (lc_q_in($x, './/text()' . LC_SICHTBAR, $n) as $tn) $t[] = $tn->nodeValue;
    return trim((string)preg_replace('/\s+/u', ' ', implode(' ', $t)));
}
function lc_q_in(DOMXPath $x, string $xp, DOMNode $ctx): array { $n = @$x->query($xp, $ctx); return $n ? iterator_to_array($n) : []; }
function lc_links(DOMXPath $x, string $base): array
{
    $o = [];
    foreach (lc_q($x, '//a[@href]') as $a) {
        $href = lc_at($a, 'href');
        if ($href === '' || preg_match('#^(javascript|data):#i', $href)) continue;
        $sicht = lc_ntext($x, $a);
        $o[] = ['href' => $href, 'abs' => lc_abs($base, $href), 'sicht' => $sicht, 'text' => trim($sicht . ' ' . lc_at($a, 'aria-label') . ' ' . lc_at($a, 'title')), 'el' => $a];
    }
    return $o;
}
function lc_text(DOMXPath $x, string $xp = '//body'): string
{
    $out = [];
    foreach (lc_q($x, $xp) as $b) $out[] = lc_ntext($x, $b);
    return trim(implode(' ', $out));
}
function lc_outer(DOMElement $n): string { return (string)$n->ownerDocument->saveHTML($n); }

/* ---------------- erste Runde: Startseite, alte Adresse, www-Variante, Hinweisdateien ---------------- */
$T0 = microtime(true);
$https0 = 'https://' . $HOST . '/';
$r1 = lc_multi([
    'home0' => [$https0, 'GET', LC_MAX_BODY, $VOLL ? 12 : 9],
    'http' => ['http://' . $HOST . '/', 'GET', 200000, 8],
    'alt' => ['https://' . $ALT . '/', 'GET', 200000, 8],
    'robots' => ['https://' . $HOST . '/robots.txt', 'GET', 300000, 8],
    'p404' => ['https://' . $HOST . '/lou-check-' . bin2hex(random_bytes(4)) . '-nicht-vorhanden', 'GET', 300000, 8],
    'favicon' => ['https://' . $HOST . '/favicon.ico', 'GET', 300000, 8],
    'sectxt' => ['https://' . $HOST . '/.well-known/security.txt', 'GET', 64000, 8],
    'apple' => ['https://' . $HOST . '/apple-touch-icon.png', 'GET', 300000, 8],
]);
$home = lc_follow($https0, 6, 'GET', $r1['home0']);
unset($r1['home0']);
$httpsKaputt = $home['status'] === 0;
$httpsErr = '';
if ($httpsKaputt) {
    $homeHttp = lc_follow('http://' . $HOST . '/');
    if ($homeHttp['status'] === 0 || $homeHttp['status'] >= 500) lc_out(['ok' => false, 'error' => 'unreachable'], 400);
    $httpsErr = $home['errtxt'];
    $home = $homeHttp;
}
$FINAL = $home['chain'][count($home['chain']) - 1]['url'];
$FH = lc_norm_host((string)parse_url($FINAL, PHP_URL_HOST));
$HTML = lc_html($home);
$RD = lc_regdom($HOST);
$X = lc_page($HTML);
$q = function (string $xp) use ($X) { return lc_q($X, $xp); };
$attr = 'lc_at';
$meta = function (string $name) use ($X) { return lc_meta($X, $name); };
$title = lc_title($X);
$links = lc_links($X, $FINAL);
$eigen = function (string $url) use ($FH): bool { $h = (string)@parse_url($url, PHP_URL_HOST); return $h !== '' && lc_regdom(lc_norm_host($h)) === lc_regdom($FH); };
$webLink = function (array $l) use ($eigen): bool { return (bool)preg_match('#^https?://#i', $l['abs']) && $eigen($l['abs']); };
$findLink = function (string $re, bool $nurWeb = false) use ($links, $webLink) {
    foreach ($links as $l) if ((!$nurWeb || $webLink($l)) && preg_match($re, $l['href'])) return $l;
    foreach ($links as $l) if ((!$nurWeb || $webLink($l)) && preg_match($re, $l['text'])) return $l;
    return null;
};
$RE_IMP = '/impressum|imprint|legal[\s_-]?notice|anbieterkennzeichnung/i';
$RE_DSE = '/datenschutz|privacy|data[\s_-]?protection/i';
$lImp = $findLink($RE_IMP, true) ?? $findLink($RE_IMP);
$lDse = $findLink($RE_DSE, true) ?? $findLink($RE_DSE);
$ohneAnker = function (string $u): string { return preg_replace('/#.*$/', '', $u); };
$lKon = $findLink('/kontakt|contact/i', true);
if ($lKon && $ohneAnker($lKon['abs']) === $ohneAnker($FINAL)) $lKon = null;

/* ---------------- zweite Runde: Rechtstexte, Kontaktseite, Unterseiten, Sitemap, Dateien ---------------- */
$intern = [];
foreach ($links as $l) {
    if (!$webLink($l)) continue;
    $k = $ohneAnker($l['abs']);
    if ($k === $ohneAnker($FINAL) || isset($intern[$k])) continue;
    if (preg_match('/\.(pdf|jpe?g|png|gif|webp|avif|svg|zip|docx?|xlsx?|pptx?|mp4|mp3|ics|vcf)(\?|$)/i', $k)) { $intern[$k] = 'datei'; continue; }
    $intern[$k] = 'seite';
}
$intern = array_slice($intern, 0, $VOLL ? 30 : 12, true);
$asset = '';
foreach ($q('//link[@rel="stylesheet"][@href] | //script[@src]') as $n) {
    $s = lc_abs($FINAL, $attr($n, 'href') ?: $attr($n, 'src'));
    if ($eigen($s)) { $asset = $s; break; }
}
$manifestHref = $attr($q('//link[@rel="manifest"]')[0] ?? null, 'href');
$robotsR = $r1['robots'];
$robots = ($robotsR['status'] === 200 && stripos(lc_hdr($robotsR, 'content-type'), 'html') === false && !preg_match('/^\s*</', (string)$robotsR['body'])) ? (string)$robotsR['body'] : '';
$smUrl = '';
if (preg_match('/^\s*sitemap:\s*(\S+)/im', $robots, $m)) $smUrl = $m[1];
$req2 = ['sitemap' => [$smUrl !== '' ? $smUrl : 'https://' . $FH . '/sitemap.xml', 'GET', 400000, 8]];
if ($smUrl === '') $req2['sitemap2'] = ['https://' . $FH . '/sitemap_index.xml', 'GET', 200000, 8];
if ($lImp && preg_match('#^https?://#i', $lImp['abs'])) $req2['imp'] = [$ohneAnker($lImp['abs']), 'GET', 800000, 8];
if ($lDse && preg_match('#^https?://#i', $lDse['abs'])) $req2['dse'] = [$ohneAnker($lDse['abs']), 'GET', 800000, 8];
if ($lKon) $req2['kon'] = [$ohneAnker($lKon['abs']), 'GET', 1200000, 8];
if ($asset !== '') $req2['asset'] = [$asset, 'GET', 400000, 8];
if ($manifestHref !== '') $req2['manifest'] = [lc_abs($FINAL, $manifestHref), 'GET', 100000, 8];
$iI = 0;
$internListe = [];
foreach ($intern as $l => $art) { $internListe[$iI] = ['url' => $l, 'art' => $art]; $req2['l' . $iI] = [$l, 'GET', $art === 'datei' ? 20000 : 300000, 8]; $iI++; }
$r2 = lc_multi($req2);
foreach (['imp', 'dse', 'kon'] as $k) if (isset($r2[$k]) && $r2[$k]['status'] >= 300 && $r2[$k]['status'] < 400) $r2[$k] = lc_follow(lc_abs($r2[$k]['url'], lc_hdr($r2[$k], 'location')), 3, 'GET', null, 1200000);

/* ---------------- Zertifikat und DNS ---------------- */
$cert = null;
if (!$httpsKaputt) {
    $ip = lc_resolve($FH ?: $HOST);
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $FH ?: $HOST]]);
    $sock = ($ip && lc_rest() > 2) ? @stream_socket_client('ssl://' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip) . ':443', $en, $es, max(1.0, min(6.0, lc_rest() - 1)), STREAM_CLIENT_CONNECT, $ctx) : false;
    if ($sock) {
        $pc = stream_context_get_params($sock)['options']['ssl']['peer_certificate'] ?? null;
        if ($pc) $cert = openssl_x509_parse($pc) ?: null;
        fclose($sock);
    }
}
$txt = function (string $n): array { $o = []; foreach ((@dns_get_record($n, DNS_TXT) ?: []) as $r) $o[] = (string)($r['txt'] ?? implode('', $r['entries'] ?? [])); return $o; };
$mx = LC_TEST ? [] : (@dns_get_record($RD, DNS_MX) ?: []);
$spf = LC_TEST ? [] : array_values(array_filter($txt($RD), function ($t) { return stripos($t, 'v=spf1') === 0; }));
$dmarc = LC_TEST ? [] : array_values(array_filter($txt('_dmarc.' . $RD), function ($t) { return stripos($t, 'v=dmarc1') === 0; }));
$aaaa = LC_TEST ? [] : (@dns_get_record($FH ?: $HOST, DNS_AAAA) ?: []);
$ns = LC_TEST ? false : @dns_get_record($RD, DNS_NS);
$caa = (LC_TEST || !defined('DNS_CAA')) ? false : @dns_get_record($RD, DNS_CAA);

/* ---------------- Seiten für die Auswertung ---------------- */
$isHttps = stripos($FINAL, 'https://') === 0;
$impOk = isset($r2['imp']) && $r2['imp']['status'] === 200;
$impHtml = $impOk ? lc_html($r2['imp']) : '';
$impX = $impOk ? lc_page($impHtml) : null;
$impTxt = $impX ? lc_text($impX) : '';
$dseOk = isset($r2['dse']) && $r2['dse']['status'] === 200;
$konOk = isset($r2['kon']) && lc_ist_html($r2['kon']);
$konUrl = $konOk ? (string)($r2['kon']['chain'][count($r2['kon']['chain'] ?? [1]) - 1]['url'] ?? $r2['kon']['url']) : '';
$konHtml = $konOk ? lc_html($r2['kon']) : '';
$KX = $konOk ? lc_page($konHtml) : null;
// Seiten, auf denen Formulare, Karten, Buchung usw. gesucht werden
$SEITEN = [['x' => $X, 'html' => $HTML, 'url' => $FINAL, 'name' => 'start']];
if ($KX) $SEITEN[] = ['x' => $KX, 'html' => $konHtml, 'url' => $konUrl, 'name' => 'kontakt'];
$alleLinks = $links;
if ($KX) $alleLinks = array_merge($alleLinks, lc_links($KX, $konUrl));
$alleHtml = $HTML . "\n" . $konHtml;
$homeText = lc_text($X);
$konText = $KX ? lc_text($KX) : '';
$recht = function (string $url) use ($lImp, $lDse, $ohneAnker): bool {
    if (preg_match('/impressum|imprint|legal|datenschutz|privacy|agb|terms|widerruf|cookie|barrierefrei|accessibility/i', (string)parse_url($url, PHP_URL_PATH))) return true;
    foreach ([$lImp, $lDse] as $l) if ($l && $ohneAnker($l['abs']) === $ohneAnker($url)) return true;
    return false;
};
// Unterseiten auswerten
$SUB = [];
foreach ($internListe as $i => $e) {
    $r = $r2['l' . $i] ?? lc_leer($e['url']);
    $s = ['url' => $e['url'], 'art' => $e['art'], 'status' => $r['status'], 'zeit_um' => $r['err'] === -2, 'html' => false, 'zeit' => max(0.0, $r['total'] - $r['pre']), 'recht' => $recht($e['url'])];
    if ($e['art'] === 'seite' && lc_ist_html($r)) {
        $sx = lc_page(lc_html($r));
        $s['html'] = true;
        $s['cut'] = $r['cut'];
        $s['titel'] = lc_title($sx);
        $s['noindex'] = stripos(lc_meta($sx, 'robots') . ' ' . lc_hdr($r, 'x-robots-tag'), 'noindex') !== false;
        $s['imp'] = false;
        foreach (lc_links($sx, $e['url']) as $l) if (preg_match($RE_IMP, $l['href']) || preg_match($RE_IMP, $l['text'])) { $s['imp'] = true; break; }
        unset($sx);
    }
    $SUB[] = $s;
}
$subHtml = [];
foreach ($SUB as $s) if ($s['html']) { $k = (string)parse_url($s['url'], PHP_URL_PATH); if (!isset($subHtml[$k])) $subHtml[$k] = $s; }
$subHtml = array_values($subHtml); // je Pfad nur einmal (z. B. /kontakt und /kontakt?paket=x)
$pfad = function (string $u): string { $p = (string)parse_url($u, PHP_URL_PATH); $qq = (string)parse_url($u, PHP_URL_QUERY); return lc_cut(($p === '' ? '/' : $p) . ($qq !== '' ? '?' . $qq : ''), 60); };

/* ---------------- Auswertung ---------------- */
$L = function (string $de, string $en) use ($EN): string { return $EN ? $en : $de; };
$zahl = function (float $x, int $d = 1) use ($EN): string { return $EN ? number_format($x, $d, '.', ',') : number_format($x, $d, ',', '.'); };
$ITEMS = [];
$add = function (string $id, ?bool $ok, string $de, string $en, ?string $grund = null) use (&$ITEMS, $DATEN, $EN) {
    $p = $DATEN['punkte'][$id] ?? null;
    if ($p === null) throw new RuntimeException('unbekannter Prüfpunkt ' . $id);
    $ITEMS[$id] = ['id' => $id, 'gruppe' => $p['gruppe'], 'stufe' => $p['stufe'], 'titel' => $EN ? $p['en'] : $p['de'], 'ok' => $ok,
        'status' => $ok === true ? 'ok' : ($ok === false ? 'fehlt' : ($grund === 'np' ? 'np' : 'na')),
        'detail' => $EN ? $en : $de, 'info' => $EN ? $p['info_en'] : $p['info_de'], 'leistung' => $EN ? $p['leistung_en'] : $p['leistung_de'], 'leistung_art' => $p['leistung']];
};
$FEHLER = [];
$H = $home['headers'];
$imgs = $q('//img');
$nImg = count($imgs);
$aktiv = function (string $type): bool { return $type === '' || in_array($type, ['text/javascript', 'application/javascript', 'module'], true); };
// Skripte, die ein Zustimmungs-Werkzeug zurückhält, gelten nicht als geladen
$autoBlock = (bool)preg_match('/data-blockingmode=["\']auto|uc-block\.bundle\.js|consentmanager\.net\/delivery\/autoblocking/i', $HTML);
$skriptAktiv = function ($n) use ($aktiv, $autoBlock, $eigen, $FINAL): bool {
    if (!$aktiv(strtolower(lc_at($n, 'type')))) return false;
    foreach (['data-cookieconsent', 'data-cookiecategory', 'data-category', 'data-usercentrics', 'data-cmplz-src', 'data-borlabs-cookie-script-blocker-src', 'data-consent'] as $a) if ($n instanceof DOMElement && $n->hasAttribute($a)) return false;
    $src = lc_at($n, 'src');
    if ($autoBlock && $src !== '' && !$eigen(lc_abs($FINAL, $src)) && !preg_match('/cookiebot|usercentrics|consentmanager/i', $src)) return false;
    return true;
};
$scriptsSrc = array_map(function ($n) use ($skriptAktiv) { return ['src' => lc_at($n, 'src'), 'type' => strtolower(lc_at($n, 'type')), 'aktiv' => $skriptAktiv($n)]; }, $q('//script[@src]'));
$iframesAlle = [];
foreach ($SEITEN as $s) foreach (lc_q($s['x'], '//iframe') as $n) $iframesAlle[] = ['src' => lc_at($n, 'src'), 'data' => lc_at($n, 'data-src') . lc_at($n, 'data-lazy-src'), 'seite' => $s['name']];
$scriptsAlle = [];
foreach ($SEITEN as $s) foreach (lc_q($s['x'], '//script[@src]') as $n) $scriptsAlle[] = ['src' => lc_at($n, 'src'), 'type' => strtolower(lc_at($n, 'type')), 'aktiv' => $skriptAktiv($n), 'seite' => $s['name']];
$styleLinks = array_map(function ($n) { return lc_at($n, 'href'); }, $q('//link[@href]'));
$csp = lc_hdr($home, 'content-security-policy') ?: $meta('content-security-policy');
// Formulare auf Start- und Kontaktseite
$FORMS = [];
foreach ($SEITEN as $s) foreach (lc_q($s['x'], '//form') as $f) {
    $fh = lc_outer($f);
    $kontaktForm = count(@$s['x']->query('.//textarea | .//input[@type="email"] | .//input[contains(translate(@name,"MAIL","mail"),"mail")]', $f) ?: []) > 0;
    $nurSuche = !$kontaktForm && (strtolower(lc_at($f, 'role')) === 'search' || count(@$s['x']->query('.//input[@type="search"]', $f) ?: []) > 0);
    $FORMS[] = ['html' => $fh, 'action' => lc_at($f, 'action'), 'kontakt' => $kontaktForm, 'suche' => $nurSuche, 'seite' => $s['name'], 'url' => $s['url'],
        'newsletter' => (bool)preg_match('/newsletter|list-manage\.com|sibforms\.com|cleverreach|rapidmail|klaviyo|mailerlite|getresponse|convertkit|kit\.com|sendfox|mailpoet|brevo|sendinblue|newsletter2go/i', $fh)];
}
$FORMS_K = array_values(array_filter($FORMS, function ($f) { return $f['kontakt'] && !$f['newsletter']; }));
$FORMS_N = array_values(array_filter($FORMS, function ($f) { return $f['newsletter']; }));
$formDienst = '';
foreach ($iframesAlle as $f) if (preg_match('/typeform\.com|jotform|docs\.google\.com\/forms|forms\.gle|hsforms|tally\.so|formstack|cognitoforms|123formbuilder|wufoo|zohopublic|forms\.office\.com|formular\.|form\.jotform/i', $f['src'] . $f['data'], $m)) { $formDienst = $m[0]; break; }
if ($formDienst === '') foreach ($scriptsAlle as $s) if (preg_match('/js\.hsforms\.net|embed\.typeform\.com|tally\.so\/widgets|form\.jotform/i', $s['src'], $m)) { $formDienst = $m[0]; break; }
// Shop erkennen
$shopRe = '/woocommerce|cdn\.shopify\.com|myshopify\.com|shopware|wixstores|wix-ecommerce|ecwid|jtl-shop|gambio|plentymarkets|oxid-esales|snipcart|js\.stripe\.com|paypal\.com\/sdk|paypalobjects\.com|klarna|mollie\.com|href=["\'][^"\']*\/(warenkorb|cart|checkout|kasse)(\/|["\'?])/i';
$istShop = (bool)preg_match($shopRe, $alleHtml, $shopM);

try {
/* ================= A Sicherheit (15) ================= */
$sslOk = !$httpsKaputt && $isHttps && (LC_TEST || $home['ssl'] === 0);
$add('https', $sslOk,
    $sslOk ? 'Ihre Seite lädt verschlüsselt, das Zertifikat ist gültig.' : ($httpsKaputt ? 'Über https:// ist Ihre Seite nicht erreichbar, oder der Browser lehnt das Zertifikat ab. Besucher sehen eine Warnung.' : ($isHttps ? 'Das Zertifikat wird vom Browser nicht anerkannt. Besucher sehen eine Warnung.' : 'Ihre Startseite landet am Ende auf einer unverschlüsselten Adresse (http://).')),
    $sslOk ? 'Your site loads encrypted and the certificate is valid.' : ($httpsKaputt ? 'Your site cannot be reached via https://, or the browser rejects the certificate. Visitors see a warning.' : ($isHttps ? 'The browser does not accept the certificate. Visitors see a warning.' : 'Your homepage ends up on an unencrypted address (http://).')));
$hr = $r1['http'];
$loc = lc_hdr($hr, 'location');
$redirOk = in_array($hr['status'], [301, 308], true) && stripos(lc_abs('http://' . $HOST . '/', $loc), 'https://') === 0;
$add('http_https', $redirOk,
    $redirOk ? 'Wer http:// eintippt, wird dauerhaft auf die sichere Adresse geschickt.' : ($hr['status'] >= 300 && $hr['status'] < 400 ? 'Es gibt eine Weiterleitung, aber nur vorübergehend oder nicht auf die sichere Adresse.' : 'Die Adresse mit http:// bleibt unverschlüsselt und leitet nicht weiter.'),
    $redirOk ? 'Anyone typing http:// is permanently sent to the secure address.' : ($hr['status'] >= 300 && $hr['status'] < 400 ? 'There is a redirect, but only temporary or not to the secure address.' : 'The http:// address stays unencrypted and does not redirect.'));
$days = $cert && isset($cert['validTo_time_t']) ? (int)floor(($cert['validTo_time_t'] - time()) / 86400) : null;
$aussteller = $cert['issuer']['O'] ?? '';
$add('zertifikat', $days === null ? null : $days >= 21,
    $days === null ? 'Das Zertifikat konnte nicht gelesen werden.' : ($days >= 21 ? 'Noch ' . $days . ' Tage gültig' . ($aussteller ? ' (ausgestellt von ' . $aussteller . ')' : '') . '.' : ($days < 0 ? 'Das Zertifikat ist abgelaufen.' : ($days === 0 ? 'Läuft heute ab.' : ($days === 1 ? 'Läuft morgen ab.' : 'Läuft in ' . $days . ' Tagen ab.')) . ' Bitte rechtzeitig erneuern.')),
    $days === null ? 'The certificate could not be read.' : ($days >= 21 ? 'Valid for ' . $days . ' more days' . ($aussteller ? ' (issued by ' . $aussteller . ')' : '') . '.' : ($days < 0 ? 'The certificate has expired.' : ($days === 0 ? 'Expires today.' : ($days === 1 ? 'Expires tomorrow.' : 'Expires in ' . $days . ' days.')) . ' Please renew it in time.')), 'np');
// Zertifikat passt zu beiden Varianten
$san = [];
if ($cert) {
    foreach (explode(',', (string)($cert['extensions']['subjectAltName'] ?? '')) as $e) if (preg_match('/DNS:\s*(\S+)/i', $e, $m)) $san[] = strtolower($m[1]);
    if (!$san && !empty($cert['subject']['CN'])) $san[] = strtolower((string)$cert['subject']['CN']);
}
$passt = function (string $h) use ($san): bool { foreach ($san as $s) { if ($s === $h) return true; if (strpos($s, '*.') === 0 && substr_count($h, '.') === substr_count($s, '.') && substr($h, strpos($h, '.')) === substr($s, 1)) return true; } return false; };
$altGibt = lc_resolve($ALT) !== null;
$altSslFehler = $altGibt && $r1['alt']['status'] === 0 && in_array($r1['alt']['err'], [35, 51, 58, 60], true);
$fehlt = [];
if ($cert) { if (!$passt($FH)) $fehlt[] = $FH; if ($altGibt && !$passt($ALT) && $ALT !== $FH) $fehlt[] = $ALT; }
if ($altSslFehler && !in_array($ALT, $fehlt, true)) $fehlt[] = $ALT;
$add('zertifikat_name', $cert ? !$fehlt : ($altSslFehler ? false : null),
    !$cert && !$altSslFehler ? 'Das Zertifikat konnte nicht gelesen werden.' : ($fehlt ? 'Das Zertifikat gilt nicht für ' . implode(' und ', $fehlt) . '. Wer die Adresse so eintippt, sieht eine Warnung.' : 'Das Zertifikat gilt für ' . $FH . ($altGibt && $ALT !== $FH ? ' und ' . $ALT : '') . '.'),
    !$cert && !$altSslFehler ? 'The certificate could not be read.' : ($fehlt ? 'The certificate does not cover ' . implode(' and ', $fehlt) . '. Anyone typing the address that way sees a warning.' : 'The certificate covers ' . $FH . ($altGibt && $ALT !== $FH ? ' and ' . $ALT : '') . '.'), 'np');
$hsts = lc_hdr($home, 'strict-transport-security');
$hstsAge = preg_match('/max-age=(\d+)/i', $hsts, $m) ? (int)$m[1] : 0;
$hstsTage = (int)floor($hstsAge / 86400);
$add('hsts', $isHttps ? $hstsAge >= 2592000 : false,
    $hsts === '' ? 'Fehlt. Browser merken sich nicht, dass Ihre Seite nur verschlüsselt geöffnet werden soll.' : ($hstsAge >= 2592000 ? 'Ja, Browser öffnen Ihre Seite ' . $hstsTage . ' Tage lang nur verschlüsselt.' : 'Gesetzt, aber nur für ' . $hstsTage . ' Tage. Empfohlen sind mindestens 30, besser 365.'),
    $hsts === '' ? 'Missing. Browsers do not remember that your site should only be opened encrypted.' : ($hstsAge >= 2592000 ? 'Yes, browsers open your site encrypted only for ' . $hstsTage . ' days.' : 'Set, but only for ' . $hstsTage . ' days. At least 30, ideally 365, is recommended.'));
$mixed = [];
if ($isHttps) foreach ($q('//img[@src] | //script[@src] | //link[@rel="stylesheet"][@href] | //iframe[@src] | //source[@src] | //video[@src] | //audio[@src]') as $n) {
    $s = $attr($n, 'src') ?: $attr($n, 'href');
    if (stripos($s, 'http://') === 0) $mixed[] = $s;
}
$add('gemischt', $isHttps ? !$mixed : null,
    !$isHttps ? 'Nicht bewertbar, weil die Seite selbst unverschlüsselt ist.' : ($mixed ? count($mixed) . ' Datei(en) laden noch unsicher, z. B. ' . lc_cut($mixed[0], 80) . '.' : 'Alle Bilder, Skripte und Gestaltungsdateien laden sicher.'),
    !$isHttps ? 'Cannot be assessed because the site itself is unencrypted.' : ($mixed ? count($mixed) . ' file(s) still load insecurely, e.g. ' . lc_cut($mixed[0], 80) . '.' : 'All images, scripts and design files load securely.'), 'np');
$xfo = strtoupper(lc_hdr($home, 'x-frame-options'));
$frameOk = in_array($xfo, ['DENY', 'SAMEORIGIN'], true) || stripos($csp, 'frame-ancestors') !== false;
$add('clickjacking', $frameOk,
    $frameOk ? 'Ja, fremde Seiten dürfen Ihre Seite nicht einbetten.' : 'Fehlt. Jede fremde Seite könnte Ihre Seite unsichtbar einbetten.',
    $frameOk ? 'Yes, other sites may not embed your site.' : 'Missing. Any other site could embed your site invisibly.');
$nos = strtolower(lc_hdr($home, 'x-content-type-options')) === 'nosniff';
$add('nosniff', $nos, $nos ? 'Ja, der Schutz ist eingeschaltet.' : 'Fehlt. Eine Zeile beim Server genügt.', $nos ? 'Yes, the protection is switched on.' : 'Missing. One line on the server is enough.');
$add('csp', $csp !== '',
    $csp !== '' ? 'Ja, es gibt Regeln, woher Inhalte geladen werden dürfen.' : 'Fehlt. Gelangt fremder Code auf die Seite, würde der Browser ihn ausführen.',
    $csp !== '' ? 'Yes, there are rules for where content may be loaded from.' : 'Missing. If foreign code got onto the site, the browser would run it.');
$ref = lc_hdr($home, 'referrer-policy') ?: $meta('referrer');
$add('referrer', $ref !== '',
    $ref !== '' ? 'Ja, festgelegt („' . lc_cut($ref, 40) . '“).' : 'Fehlt. Vollständige Adressen können an fremde Seiten weitergegeben werden.',
    $ref !== '' ? 'Yes, defined (“' . lc_cut($ref, 40) . '”).' : 'Missing. Full addresses may be passed on to other sites.');
$pp = lc_hdr($home, 'permissions-policy') ?: lc_hdr($home, 'feature-policy');
$add('permissions', $pp !== '',
    $pp !== '' ? 'Ja, nicht benötigte Funktionen sind gesperrt.' : 'Fehlt. Eingebettete Inhalte könnten Kamera, Mikrofon oder Standort anfragen.',
    $pp !== '' ? 'Yes, unneeded features are locked.' : 'Missing. Embedded content could request camera, microphone or location.');
$srv = trim(lc_hdr($home, 'server') . ' ' . lc_hdr($home, 'x-powered-by'));
$gen = $meta('generator');
$versLeak = (bool)preg_match('#(apache|nginx|php|iis|litespeed|express|openresty|asp\.net)[/ ]?\d#i', $srv) || (bool)preg_match('/\d+\.\d+/', $gen) || lc_hdr($home, 'x-aspnet-version') !== '';
$sichtbar = lc_cut(implode(' ', array_unique(preg_split('/\s+/', trim($srv . ' ' . $gen)) ?: [])), 80);
$add('versionen', !$versLeak,
    $versLeak ? 'Ihr Server verrät die Angaben „' . $sichtbar . '“.' : 'Server und System verraten keine Versionsnummern.',
    $versLeak ? 'Your server reveals “' . $sichtbar . '”.' : 'Server and system do not reveal version numbers.');
$st = $r1['sectxt'];
$secOk = $st['status'] === 200 && stripos(lc_hdr($st, 'content-type'), 'html') === false && (bool)preg_match('/^\s*contact\s*:/im', (string)$st['body']);
$add('securitytxt', $secOk,
    $secOk ? 'Ja, es gibt eine Kontaktdatei für Sicherheitshinweise.' : 'Fehlt. Wer eine Lücke findet, weiß nicht, wem er sie melden kann.',
    $secOk ? 'Yes, there is a contact file for security reports.' : 'Missing. Anyone who finds a flaw does not know whom to tell.');
$cookies = $H['set-cookie'] ?? [];
$schwach = [];
foreach ($cookies as $c) {
    $name = trim(explode('=', $c, 2)[0]);
    $sec = stripos($c, 'secure') !== false;
    $sess = (bool)preg_match('/sess|sid|auth|login|token|wordpress_|wp-settings|laravel|ci_session|jsession|csrf/i', $name);
    if (($isHttps && !$sec) || ($sess && stripos($c, 'httponly') === false)) $schwach[] = $name;
}
$add('cookies_sicher', $cookies ? !$schwach : null,
    !$cookies ? 'Ihre Startseite setzt keine Cookies, hier gibt es nichts zu schützen.' : ($schwach ? count($schwach) . ' von ' . count($cookies) . ' Cookie(s) sind nicht ausreichend geschützt, nämlich ' . lc_cut(lc_und($schwach), 80) . '.' : 'Alle ' . count($cookies) . ' Cookie(s) sind geschützt.'),
    !$cookies ? 'Your homepage sets no cookies, so there is nothing to protect.' : ($schwach ? count($schwach) . ' of ' . count($cookies) . ' cookie(s) are not protected enough, namely ' . lc_cut(lc_und($schwach, true), 80) . '.' : 'All ' . count($cookies) . ' cookie(s) are protected.'));
// System erkennen und Version vergleichen
$sys = ''; $sysVer = '';
if (preg_match('/(WordPress|Joomla!?|Drupal|TYPO3|Contao|Shopware|PrestaShop|Magento|Wix\.com|Jimdo|Squarespace|Webflow|Ghost|Hugo|Gatsby|Next\.js)\s*v?([\d.]*)/i', $gen . ' ' . lc_hdr($home, 'x-generator') . ' ' . lc_hdr($home, 'x-powered-by'), $m)) { $sys = $m[1]; $sysVer = trim($m[2], '.'); }
if ($sys === '' && preg_match('#/wp-(content|includes)/#i', $HTML)) $sys = 'WordPress';
if ($sys === 'WordPress' && $sysVer === '' && preg_match('#/wp-includes/[^"\']+\?ver=(\d+\.\d+(?:\.\d+)?)#i', $HTML, $m)) $sysVer = $m[1];
$mindest = ['wordpress' => '6.7', 'joomla' => '5.0', 'joomla!' => '5.0', 'drupal' => '10.0', 'typo3' => '12.4', 'contao' => '5.3', 'shopware' => '6.5', 'prestashop' => '8.0', 'magento' => '2.4'];
$alt = $sysVer !== '' && isset($mindest[strtolower($sys)]) && version_compare($sysVer, $mindest[strtolower($sys)], '<');
$add('system_aktuell', !$alt,
    $alt ? $sys . ' ' . $sysVer . ' erkannt. Diese Version ist veraltet und bekommt teils keine Sicherheitsupdates mehr.' : ($sys !== '' ? $sys . ($sysVer !== '' ? ' ' . $sysVer : '') . ' erkannt' . ($sysVer !== '' ? ', die Version ist aktuell genug.' : ', die Version ist nicht sichtbar, und das ist gut so.') : 'Kein System mit Versionsnummer erkennbar.'),
    $alt ? $sys . ' ' . $sysVer . ' detected. This version is outdated and partly no longer receives security updates.' : ($sys !== '' ? $sys . ($sysVer !== '' ? ' ' . $sysVer : '') . ' detected' . ($sysVer !== '' ? ', and the version is recent enough.' : ', and the version is not visible, which is good.') : 'No system with a version number visible.'));

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= B Recht und Datenschutz (11) ================= */
$add('impressum', $impOk,
    $lImp ? ($impOk ? 'Ja, verlinkt und erreichbar.' : 'Verlinkt, aber die Seite lässt sich nicht öffnen.') : 'Auf der Startseite gibt es keinen Link zum Impressum.',
    $lImp ? ($impOk ? 'Yes, linked and reachable.' : 'Linked, but the page cannot be opened.') : 'There is no link to the legal notice on the homepage.');
$add('datenschutz', $dseOk,
    $lDse ? ($dseOk ? 'Ja, verlinkt und erreichbar.' : 'Verlinkt, aber die Seite lässt sich nicht öffnen.') : 'Auf der Startseite gibt es keinen Link zur Datenschutzerklärung.',
    $lDse ? ($dseOk ? 'Yes, linked and reachable.' : 'Linked, but the page cannot be opened.') : 'There is no link to the privacy policy on the homepage.');
$dritte = [];
$muster = ['fonts.googleapis.com' => 'Google Fonts', 'fonts.gstatic.com' => 'Google Fonts', 'googletagmanager.com' => 'Google Tag Manager', 'google-analytics.com' => 'Google Analytics',
    'connect.facebook.net' => 'Meta (Facebook)', 'youtube.com/embed' => 'YouTube', 'google.com/maps' => 'Google Maps', 'maps.google' => 'Google Maps', 'maps.googleapis.com' => 'Google Maps',
    'static.hotjar.com' => 'Hotjar', 'snap.licdn.com' => 'LinkedIn', 'analytics.tiktok.com' => 'TikTok', 'clarity.ms' => 'Microsoft Clarity', 'use.typekit.net' => 'Adobe Fonts', 'player.vimeo.com' => 'Vimeo',
    'googleadservices.com' => 'Google Ads', 'doubleclick.net' => 'Google Ads', 'cdn.jsdelivr.net' => 'jsDelivr', 'cdnjs.cloudflare.com' => 'Cloudflare CDN', 'unpkg.com' => 'unpkg', 'code.jquery.com' => 'jQuery CDN',
    'stackpath.bootstrapcdn.com' => 'Bootstrap CDN', 'maxcdn.bootstrapcdn.com' => 'Bootstrap CDN', 'kit.fontawesome.com' => 'Font Awesome', 'use.fontawesome.com' => 'Font Awesome', 'platform.twitter.com' => 'X (Twitter)', 'instagram.com/embed' => 'Instagram'];
foreach ($scriptsSrc as $s) foreach ($muster as $k => $v) if (stripos($s['src'], $k) !== false && $s['aktiv']) $dritte[$v] = true;
foreach (array_merge(array_column(array_filter($iframesAlle, function ($f) { return $f['seite'] === 'start'; }), 'src'), $styleLinks) as $s) foreach ($muster as $k => $v) if (stripos($s, $k) !== false) $dritte[$v] = true;
foreach (['@import url(https://fonts.googleapis' => 'Google Fonts', '@import url("https://fonts.googleapis' => 'Google Fonts', "gtag('config'" => 'Google Analytics', "fbq('init'" => 'Meta (Facebook)'] as $k => $v) if (stripos($HTML, $k) !== false) $dritte[$v] = true;
$add('dritte', !$dritte,
    $dritte ? lc_und(array_keys($dritte)) . (count($dritte) === 1 ? ' lädt' : ' laden') . ' schon beim ersten Aufruf. Dabei geht die IP-Adresse Ihrer Besucher an diese Anbieter.' : 'Beim Aufruf werden keine bekannten fremden Dienste geladen.',
    $dritte ? lc_und(array_keys($dritte), true) . (count($dritte) === 1 ? ' loads' : ' load') . ' on the very first visit. This sends your visitors’ IP address to these providers.' : 'No known third-party services load on arrival.');
$cmp = (bool)preg_match('/cookiebot|usercentrics|borlabs|complianz|klaro|consentmanager|ccm19|cookieyes|onetrust|iubenda|real-cookie-banner|cookie-script|cookiefirst|didomi|termly|osano|quantcast|cookie-law-info|moove_gdpr|cookie-notice|cookiehub|axeptio|tarteaucitron|orestbida|cookieconsent/i', $HTML);
$trackerNamen = array_values(array_intersect(array_keys($dritte), ['Google Analytics', 'Google Tag Manager', 'Meta (Facebook)', 'Hotjar', 'LinkedIn', 'TikTok', 'Microsoft Clarity', 'Google Ads']));
$tracker = $trackerNamen || (bool)preg_match('/googletagmanager|google-analytics|fbevents|hotjar|clarity\.ms/i', $HTML);
$add('einwilligung', $tracker ? $cmp : true,
    $tracker ? ($cmp ? 'Statistik oder Werbung erkannt, ein Zustimmungs-Banner ist eingebunden.' : 'Statistik oder Werbung erkannt' . ($trackerNamen ? ' (' . implode(', ', $trackerNamen) . ')' : '') . ', aber kein Zustimmungs-Banner gefunden.') : 'Keine Statistik oder Werbung gefunden, ein Banner ist dafür nicht nötig.',
    $tracker ? ($cmp ? 'Statistics or advertising detected, and a consent banner is in place.' : 'Statistics or advertising detected' . ($trackerNamen ? ' (' . implode(', ', $trackerNamen) . ')' : '') . ', but no consent banner found.') : 'No statistics or advertising found, so no banner is needed for it.');
$impPlz = (bool)preg_match('/\b(?:D-|A-|CH-)?\d{4,5}\s+\p{Lu}/u', $impTxt);
$impMail = (bool)preg_match('/[a-z0-9._%+-]+\s*(?:@|\(at\)|\[at\]|\s+at\s+)\s*[a-z0-9.-]+\s*(?:\.|\(dot\)|\[dot\])\s*[a-z]{2,}/i', $impTxt) || stripos($impHtml, 'mailto:') !== false;
$add('impressum_inhalt', $impOk ? ($impPlz && $impMail) : null,
    !$impOk ? 'Nicht prüfbar, weil kein erreichbares Impressum gefunden wurde.' : ($impPlz && $impMail ? 'Anschrift und E-Mail-Adresse sind angegeben.' : 'Es fehlt ' . implode(' und ', array_filter([$impPlz ? '' : 'eine vollständige Anschrift mit Postleitzahl', $impMail ? '' : 'eine E-Mail-Adresse'])) . '.'),
    !$impOk ? 'Cannot be checked because no reachable legal notice was found.' : ($impPlz && $impMail ? 'Address and email address are given.' : 'The legal notice lacks ' . implode(' and ', array_filter([$impPlz ? '' : 'a full address with postcode', $impMail ? '' : 'an email address'])) . '.'), 'np');
$trackCookies = array_values(array_filter($cookies, function ($c) { return (bool)preg_match('/^\s*(_ga|_gid|_gcl|_fbp|_fbc|_hj|hubspotutk|_clck|_clsk|_uet|ajs_|_pin_|_tt_|IDE|NID)/i', $c); }));
$add('cookies', !$trackCookies,
    $cookies ? ($trackCookies ? 'Setzt sofort Werbe- oder Statistik-Cookies, bevor jemand zustimmen kann.' : 'Setzt nur ' . count($cookies) . ' technisch nötige(s) Cookie(s).') : 'Ihre Startseite setzt keine Cookies.',
    $cookies ? ($trackCookies ? 'Sets advertising or statistics cookies right away, before anyone can consent.' : 'Sets only ' . count($cookies) . ' technically necessary cookie(s).') : 'Your homepage sets no cookies.');
$formHttp = 0;
foreach ($FORMS as $f) if (stripos($f['action'], 'http://') === 0 || (!$isHttps && !preg_match('#^https://#i', $f['action']))) $formHttp++;
$add('formulare', $FORMS ? $formHttp === 0 : null,
    $FORMS ? ($formHttp ? $formHttp . ' Formular(e) senden Eingaben unverschlüsselt.' : 'Alle ' . count($FORMS) . ' Formular(e) senden verschlüsselt.') : 'Auf Start- und Kontaktseite gibt es kein Formular.',
    $FORMS ? ($formHttp ? $formHttp . ' form(s) send entries unencrypted.' : 'All ' . count($FORMS) . ' form(s) send encrypted.') : 'There is no form on the homepage or contact page.');
$tmg = (bool)preg_match('/\bTMG\b|telemediengesetz/iu', $impTxt);
$ddg = (bool)preg_match('/\bDDG\b|digitale[- ]dienste[- ]gesetz/iu', $impTxt);
$add('impressum_aktuell', $impOk ? !($tmg && !$ddg) : null,
    !$impOk ? 'Nicht prüfbar, weil kein erreichbares Impressum gefunden wurde.' : ($tmg && !$ddg ? 'Ihr Impressum nennt noch das Telemediengesetz (TMG). Seit Mai 2024 gilt § 5 DDG.' : ($ddg ? 'Ja, Ihr Impressum nennt das aktuelle Gesetz (DDG).' : 'Ihr Impressum nennt kein veraltetes Gesetz.')),
    !$impOk ? 'Cannot be checked because no reachable legal notice was found.' : ($tmg && !$ddg ? 'Your legal notice still cites the Telemedia Act (TMG). § 5 DDG applies since May 2024.' : ($ddg ? 'Yes, your legal notice cites the current law (DDG).' : 'Your legal notice cites no outdated law.')), 'np');
$ohneHinweis = 0;
foreach ($FORMS_K as $f) if (!preg_match('/datenschutz|privacy|einwillig|consent|dsgvo|gdpr/i', $f['html'])) $ohneHinweis++;
$add('formular_hinweis', $FORMS_K ? $ohneHinweis === 0 : null,
    !$FORMS_K ? 'Kein Kontaktformular gefunden, trifft nicht zu.' : ($ohneHinweis ? $ohneHinweis . ' Formular(e) ohne Hinweis zum Datenschutz.' : 'Ja, am Formular steht ein Hinweis zum Datenschutz.'),
    !$FORMS_K ? 'No contact form found, not applicable.' : ($ohneHinweis ? $ohneHinweis . ' form(s) without a privacy notice.' : 'Yes, the form has a privacy notice.'));
$subPruef = array_values(array_filter($subHtml, function ($s) { return !$s['recht'] && empty($s['cut']); }));
$ohneImp = array_values(array_filter($subPruef, function ($s) { return !$s['imp']; }));
$add('rechtstexte_ueberall', $subPruef ? !$ohneImp : null,
    !$subPruef ? 'Keine Unterseiten zum Vergleichen gefunden.' : ($ohneImp ? 'Auf ' . count($ohneImp) . ' von ' . count($subPruef) . ' Unterseiten fehlt der Link zum Impressum, z. B. ' . $pfad($ohneImp[0]['url']) . '.' : 'Ja, auf allen ' . count($subPruef) . ' geprüften Unterseiten verlinkt.'),
    !$subPruef ? 'No subpages found to compare.' : ($ohneImp ? count($ohneImp) . ' of ' . count($subPruef) . ' subpages lack the link to the legal notice, e.g. ' . $pfad($ohneImp[0]['url']) . '.' : 'Yes, linked on all ' . count($subPruef) . ' checked subpages.'));
$bfLink = false;
foreach ($alleLinks as $l) if (preg_match('/barrierefrei|accessibility/i', $l['href'] . ' ' . $l['text'])) { $bfLink = true; break; }
$add('barrierefreiheit', $istShop ? $bfLink : null,
    !$istShop ? 'Kein Online-Shop erkannt, trifft vermutlich nicht zu.' : ($bfLink ? 'Shop erkannt, eine Erklärung zur Barrierefreiheit ist verlinkt.' : 'Shop erkannt, aber keine Erklärung zur Barrierefreiheit gefunden.'),
    !$istShop ? 'No online shop detected, probably not applicable.' : ($bfLink ? 'Shop detected, and an accessibility statement is linked.' : 'Shop detected, but no accessibility statement found.'));

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= C Adresse und Erreichbarkeit (10) ================= */
$altR = $r1['alt'];
$altLoc = $altR['status'] >= 300 && $altR['status'] < 400 ? lc_norm_host((string)parse_url(lc_abs('https://' . $ALT . '/', lc_hdr($altR, 'location')), PHP_URL_HOST)) : '';
$altOk = !$altGibt ? null : (($altLoc !== '' && $altLoc === $FH) || ($ALT === $FH));
$altKaputt = $altGibt && $altR['status'] === 0;
$fremd = lc_regdom($FH) !== $RD;
$einOk = !$fremd && ($altOk !== false);
$add('eine_adresse', $einOk,
    $fremd ? 'Ihre Startseite landet auf der fremden Domain ' . $FH . '.' : ($altOk === null ? 'Ihre Seite ist nur unter ' . $FH . ' erreichbar, eine zweite Variante gibt es nicht.' : ($altOk ? ($ALT === $FH ? $HOST . ' leitet auf ' . $FH . ' weiter.' : $ALT . ' leitet auf ' . $FH . ' weiter.') : ($altKaputt ? $ALT . ' lässt sich nicht sauber öffnen (Zertifikat oder Server).' : $ALT . ' und ' . $FH . ' zeigen getrennt Inhalte, statt auf eine Adresse weiterzuleiten.'))),
    $fremd ? 'Your homepage ends up on ' . $FH . ', which is a different domain.' : ($altOk === null ? 'Your site is only reachable at ' . $FH . ', and there is no second variant.' : ($altOk ? ($ALT === $FH ? $HOST . ' redirects to ' . $FH . '.' : $ALT . ' redirects to ' . $FH . '.') : ($altKaputt ? $ALT . ' cannot be opened cleanly (certificate or server).' : $ALT . ' and ' . $FH . ' show content separately instead of redirecting to one address.'))));
$hops = count($home['chain']) - 1;
$add('kette', $hops <= 2,
    $hops === 0 ? 'Ihre Startseite lädt direkt ohne Umweg.' : $hops . ' Weiterleitung(en) bis zur Startseite' . ($hops <= 2 ? ', das ist in Ordnung.' : '. Das kostet Ladezeit.'),
    $hops === 0 ? 'Your homepage loads directly without a detour.' : $hops . ' redirect(s) to the homepage' . ($hops <= 2 ? ', which is fine.' : '. This costs loading time.'));
$bau = '/(wixsite\.com|jimdo(site|free)\.com|webnode\.|business\.site|netlify\.app|vercel\.app|github\.io|herokuapp\.com|webflow\.io|squarespace\.com|wordpress\.com|blogspot\.|myshopify\.com|strato-hosting|\.local\b|localhost|\/\/staging\.|\/\/dev\.|\/\/test\.|\/\/\d{1,3}(\.\d{1,3}){3})/i';
$fremdeAdr = [];
$canEl = $q('//link[@rel="canonical"]')[0] ?? null;
foreach ([['Hauptadresse', $attr($canEl, 'href')], ['Vorschau', $meta('og:url')]] as $c) {
    if ($c[1] !== '' && (preg_match($bau, $c[1]) || !$eigen(lc_abs($FINAL, $c[1])))) $fremdeAdr[] = lc_cut($c[1], 70);
}
foreach ($home['chain'] as $c) if (preg_match($bau, $c['url'])) $fremdeAdr[] = lc_cut($c['url'], 70);
foreach ($links as $l) if (preg_match($bau, $l['abs']) && count($fremdeAdr) < 4) $fremdeAdr[] = lc_cut($l['abs'], 70);
$fremdeAdr = array_values(array_unique($fremdeAdr));
$add('fremde_adressen', !$fremdeAdr,
    $fremdeAdr ? (count($fremdeAdr) === 1 ? 'Die Angabe ' . $fremdeAdr[0] . ' zeigt nach außen.' : 'Die Angaben ' . lc_und(array_slice($fremdeAdr, 0, 3)) . ' zeigen nach außen.') : 'Weiterleitungen, Links und unsichtbare Angaben zeigen nur auf Ihre eigene Domain.',
    $fremdeAdr ? (count($fremdeAdr) === 1 ? 'The reference ' . $fremdeAdr[0] . ' points elsewhere.' : 'The references ' . lc_und(array_slice($fremdeAdr, 0, 3), true) . ' point elsewhere.') : 'Redirects, links and hidden tags point only to your own domain.');
$p404 = $r1['p404'];
$is404 = in_array($p404['status'], [404, 410], true);
$add('fehlerseite', $is404,
    $is404 ? 'Ja, nicht vorhandene Seiten melden „nicht gefunden“.' : ($p404['status'] >= 300 && $p404['status'] < 400 ? 'Nicht vorhandene Seiten leiten einfach weiter, statt „nicht gefunden“ zu melden.' : ($p404['status'] === 200 ? 'Nicht vorhandene Seiten melden „alles in Ordnung“. Google kann dann leere Seiten aufnehmen.' : 'Nicht vorhandene Seiten melden einen Serverfehler (' . $p404['status'] . ').')),
    $is404 ? 'Yes, missing pages report “not found”.' : ($p404['status'] >= 300 && $p404['status'] < 400 ? 'Missing pages simply redirect instead of reporting “not found”.' : ($p404['status'] === 200 ? 'Missing pages report “all fine”. Google may then index empty pages.' : 'Missing pages report a server error (' . $p404['status'] . ').')));
$kaputt = [];
$geprueft = 0;
foreach ($SUB as $s) {
    if ($s['zeit_um']) continue;
    $geprueft++;
    if ($s['status'] === 0 || $s['status'] === 404 || $s['status'] === 410 || $s['status'] >= 500) $kaputt[] = $pfad($s['url']);
}
$add('links', $geprueft ? !$kaputt : null,
    $geprueft ? ($kaputt ? count($kaputt) . ' von ' . $geprueft . ' geprüften Links ' . (count($kaputt) === 1 ? 'führt' : 'führen') . ' ins Leere, zum Beispiel ' . lc_und(array_slice($kaputt, 0, 3)) . '.' : $geprueft . ' Links geprüft, alle funktionieren.') : 'Keine Links auf eigene Seiten gefunden.',
    $geprueft ? ($kaputt ? count($kaputt) . ' of ' . $geprueft . ' checked links lead nowhere, for example ' . lc_und(array_slice($kaputt, 0, 3), true) . '.' : $geprueft . ' links checked, all work.') : 'No links to your own pages found.', ($SUB ? 'np' : 'na'));
$hilfe = $is404 ? (bool)preg_match('/<a\s[^>]*href=|<nav\b/i', (string)$p404['body']) : null;
$add('fehlerseite_hilfe', $hilfe,
    $hilfe === null ? 'Wird unter „Fehlerseite meldet sich richtig“ bewertet.' : ($hilfe ? 'Ja, die Fehlerseite bietet Links zum Weiterklicken.' : 'Die Fehlerseite bietet keinen Link zurück, Besucher stehen in einer Sackgasse.'),
    $hilfe === null ? 'Assessed under “Error page reports correctly”.' : ($hilfe ? 'Yes, the error page offers links to click on.' : 'The error page offers no link back, so visitors hit a dead end.'), 'np');
$add('ipv6', LC_TEST ? null : (bool)$aaaa,
    $aaaa ? 'Ja, Ihre Domain hat eine IPv6-Adresse.' : (LC_TEST ? 'Im Testlauf nicht geprüft.' : 'Ihre Domain hat keine IPv6-Adresse.'),
    $aaaa ? 'Yes, your domain has an IPv6 address.' : (LC_TEST ? 'Not checked in the test run.' : 'Your domain has no IPv6 address.'), 'np');
$httpLinks = array_values(array_filter($links, function ($l) { return stripos($l['href'], 'http://') === 0; }));
$add('links_http', !$httpLinks,
    $httpLinks ? count($httpLinks) . ' Link(s) beginnen noch mit http://, z. B. ' . lc_cut($httpLinks[0]['href'], 70) . '.' : 'Alle Links führen auf sichere Adressen.',
    $httpLinks ? count($httpLinks) . ' link(s) still start with http://, e.g. ' . lc_cut($httpLinks[0]['href'], 70) . '.' : 'All links point to secure addresses.');
$nsN = is_array($ns) ? count($ns) : null;
$add('nameserver', $nsN === null ? null : $nsN >= 2,
    $nsN === null ? 'Die Nameserver konnten nicht abgefragt werden.' : ($nsN >= 2 ? 'Ja, ' . $nsN . ' Nameserver sind eingetragen.' : ($nsN === 1 ? 'Nur ein Nameserver ist eingetragen. Fällt er aus, ist Ihre Seite weg.' : 'Es wurde kein Nameserver gefunden.')),
    $nsN === null ? 'The name servers could not be queried.' : ($nsN >= 2 ? 'Yes, ' . $nsN . ' name servers are set.' : ($nsN === 1 ? 'Only one name server is set. If it fails, your site is gone.' : 'No name server was found.')), 'np');
$caaN = is_array($caa) ? count($caa) : null;
$add('caa', $caaN === null ? null : $caaN > 0,
    $caaN === null ? 'Konnte nicht abgefragt werden.' : ($caaN ? 'Ja, festgelegt.' : 'Nicht festgelegt. Jede Zertifizierungsstelle dürfte Zertifikate für Ihre Adresse ausstellen.'),
    $caaN === null ? 'Could not be queried.' : ($caaN ? 'Yes, defined.' : 'Not defined. Any certificate authority could issue certificates for your address.'), 'np');

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= D Handy und Darstellung (9) ================= */
$vp = strtolower($meta('viewport'));
$vpOk = strpos($vp, 'width=device-width') !== false;
$add('viewport', $vpOk,
    $vpOk ? 'Ja, Ihre Seite passt sich der Bildschirmbreite an.' : 'Fehlt. Auf dem Handy erscheint eine winzige Desktop-Seite.',
    $vpOk ? 'Yes, your site adapts to the screen width.' : 'Missing. Phones show a tiny desktop page.');
$zoomAus = strpos($vp, 'user-scalable=no') !== false || strpos($vp, 'user-scalable=0') !== false || (preg_match('/maximum-scale=([\d.]+)/', $vp, $m) && (float)$m[1] < 2);
$add('zoom', $vp === '' ? null : !$zoomAus,
    $vp === '' ? 'Nicht prüfbar, weil die Handy-Einstellung fehlt.' : ($zoomAus ? 'Das Vergrößern mit zwei Fingern ist gesperrt.' : 'Ja, Besucher können vergrößern.'),
    $vp === '' ? 'Cannot be checked because the phone setting is missing.' : ($zoomAus ? 'Pinch-to-zoom is blocked.' : 'Yes, visitors can zoom.'), 'np');
$ohneMass = array_filter($imgs, function ($i) { return (lc_at($i, 'width') === '' || lc_at($i, 'height') === '') && stripos(lc_at($i, 'style'), 'aspect-ratio') === false; });
$quote = $nImg ? 1 - count($ohneMass) / $nImg : 1;
$add('bildmasse', $nImg ? $quote >= 0.8 : null,
    $nImg ? ($quote >= 0.8 ? round($quote * 100) . ' % der ' . $nImg . ' Bilder haben feste Maße.' : 'Nur ' . round($quote * 100) . ' % der ' . $nImg . ' Bilder haben feste Maße. Beim Laden springt der Text.') : 'Keine Bilder auf der Startseite.',
    $nImg ? ($quote >= 0.8 ? round($quote * 100) . '% of the ' . $nImg . ' images have fixed dimensions.' : 'Only ' . round($quote * 100) . '% of the ' . $nImg . ' images have fixed dimensions. Text jumps while loading.') : 'No images on the homepage.');
$starr = 0;
foreach ($q('//*[@style]') as $n) if (preg_match('/(?:^|;)\s*(?:min-)?width\s*:\s*(\d{3,4})px/i', $attr($n, 'style'), $m) && (int)$m[1] >= 600) $starr++;
foreach ($q('//table[@width] | //img[@width]') as $n) if ((int)$attr($n, 'width') >= 600 && $n->nodeName === 'table') $starr++;
$add('starre_breiten', $starr === 0,
    $starr ? $starr . ' Element(e) haben eine feste Breite ab 600 Pixeln und ragen auf dem Handy über den Rand.' : 'Keine starren Breiten gefunden.',
    $starr ? $starr . ' element(s) have a fixed width of 600 pixels or more and stick out on phones.' : 'No rigid widths found.');
$fav = count($q('//link[contains(@rel,"icon")]')) > 0 || ($r1['favicon']['status'] === 200 && strlen((string)$r1['favicon']['body']) > 50 && stripos(lc_hdr($r1['favicon'], 'content-type'), 'html') === false);
$add('favicon', $fav, $fav ? 'Ja, ein Symbol ist vorhanden.' : 'Kein Symbol gefunden. Im Tab erscheint ein leeres Standardsymbol.', $fav ? 'Yes, an icon is present.' : 'No icon found. The tab shows an empty default icon.');
$appleLink = count($q('//link[contains(@rel,"apple-touch-icon")]')) > 0;
$appleOk = $appleLink || ($r1['apple']['status'] === 200 && stripos(lc_hdr($r1['apple'], 'content-type'), 'image') === 0);
$add('apple_icon', $appleOk, $appleOk ? 'Ja, ein Symbol für den Startbildschirm ist vorhanden.' : 'Fehlt. Auf dem Startbildschirm erscheint ein Bildschirmfoto statt Ihres Logos.', $appleOk ? 'Yes, a home-screen icon is present.' : 'Missing. The home screen shows a screenshot instead of your logo.');
$manOk = $manifestHref !== '' && isset($r2['manifest']) && $r2['manifest']['status'] === 200;
$add('manifest', $manOk,
    $manOk ? 'Ja, Name, Farben und Symbole sind hinterlegt.' : ($manifestHref !== '' ? 'Verlinkt, aber die Datei lässt sich nicht öffnen.' : 'Fehlt.'),
    $manOk ? 'Yes, name, colours and icons are set.' : ($manifestHref !== '' ? 'Linked, but the file cannot be opened.' : 'Missing.'));
$lazy = count(array_filter($imgs, function ($i) { return strtolower(lc_at($i, 'loading')) === 'lazy' || lc_at($i, 'srcset') !== '' || preg_match('/\.(webp|avif)(\?|$)/i', lc_at($i, 'src')); })) + count($q('//picture'));
$add('bilder_modern', $nImg >= 4 ? $lazy >= 1 : null,
    $nImg >= 4 ? ($lazy ? 'Ja, Bilder werden in modernen Formaten oder erst bei Bedarf geladen.' : 'Alle ' . $nImg . ' Bilder laden sofort in voller Größe.') : 'Nur wenige Bilder, hier nicht wichtig.',
    $nImg >= 4 ? ($lazy ? 'Yes, images load in modern formats or only when needed.' : 'All ' . $nImg . ' images load immediately at full size.') : 'Only a few images, not important here.');
$klein = 0;
foreach ($q('//*[@style]') as $n) if (preg_match('/font-size\s*:\s*([\d.]+)px/i', $attr($n, 'style'), $m) && (float)$m[1] < 12) $klein++;
$klein += count($q('//font[@size="1"]'));
$add('schrift', $klein <= 2,
    $klein <= 2 ? 'Keine winzige Schrift gefunden.' : $klein . ' Textstellen sind kleiner als 12 Pixel.',
    $klein <= 2 ? 'No tiny text found.' : $klein . ' text passages are smaller than 12 pixels.');

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= E Google und Sichtbarkeit (20) ================= */
$tl = lc_len($title);
$generisch = (bool)preg_match('/^(home|startseite|willkommen|welcome|index|untitled|neue seite|meine website|my (wordpress )?site|just another wordpress site)$/iu', $title);
$titelOk = $tl >= 15 && $tl <= 65 && !$generisch;
$add('titel', $titelOk,
    $title === '' ? 'Ihre Startseite hat keinen Titel.' : '„' . lc_cut($title, 70) . '“ (' . $tl . ' Zeichen)' . ($titelOk ? '.' : ($generisch ? ', zu allgemein.' : ($tl < 15 ? ', zu kurz.' : ', zu lang, Google schneidet ihn ab.'))),
    $title === '' ? 'Your homepage has no title.' : '“' . lc_cut($title, 70) . '” (' . $tl . ' characters)' . ($titelOk ? '.' : ($generisch ? ', too generic.' : ($tl < 15 ? ', too short.' : ', too long, Google cuts it off.'))));
$noindex = stripos($meta('robots') . ' ' . $meta('googlebot') . ' ' . lc_hdr($home, 'x-robots-tag'), 'noindex') !== false;
$robSperre = false; $ua = []; $neuGruppe = true;
foreach (preg_split('/\R/', $robots) as $zeile) {
    $zeile = trim(preg_replace('/#.*/', '', $zeile));
    if (!preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $zeile, $m)) continue;
    $k = strtolower($m[1]); $v = trim($m[2]);
    if ($k === 'user-agent') { if ($neuGruppe) $ua = []; $ua[] = strtolower($v); $neuGruppe = false; continue; }
    $neuGruppe = true;
    if ($k === 'disallow' && $v === '/' && (in_array('*', $ua, true) || in_array('googlebot', $ua, true))) $robSperre = true;
}
$add('indexierbar', !$noindex && !$robSperre,
    $noindex ? 'Ihre Startseite ist für Suchmaschinen gesperrt (noindex). Sie erscheint nicht bei Google.' : ($robSperre ? 'Die Hinweisdatei robots.txt sperrt die ganze Seite für Google.' : 'Ja, Google darf Ihre Seite aufnehmen.'),
    $noindex ? 'Your homepage is blocked for search engines (noindex). It does not appear on Google.' : ($robSperre ? 'The robots.txt file blocks the whole site for Google.' : 'Yes, Google may index your site.'));
$desc = $meta('description');
$dl = lc_len($desc);
$add('beschreibung', $dl >= 50 && $dl <= 170,
    $desc === '' ? 'Fehlt. Google zeigt dann einen beliebigen Textschnipsel.' : $dl . ' Zeichen' . ($dl < 50 ? ', zu kurz.' : ($dl > 170 ? ', zu lang, Google schneidet sie ab.' : ', gut.')),
    $desc === '' ? 'Missing. Google then shows a random text snippet.' : $dl . ' characters' . ($dl < 50 ? ', too short.' : ($dl > 170 ? ', too long, Google cuts it off.' : ', good.')));
$h1 = count($q('//h1'));
$add('h1', $h1 === 1,
    $h1 === 1 ? 'Ja, genau eine Hauptüberschrift.' : ($h1 === 0 ? 'Keine Hauptüberschrift gefunden.' : $h1 . ' Hauptüberschriften, richtig wäre eine.'),
    $h1 === 1 ? 'Yes, exactly one main heading.' : ($h1 === 0 ? 'No main heading found.' : $h1 . ' main headings, but one would be right.'));
$sm = $r2['sitemap'];
$smOk = $sm['status'] === 200 && (stripos((string)$sm['body'], '<urlset') !== false || stripos((string)$sm['body'], '<sitemapindex') !== false);
if (!$smOk && isset($r2['sitemap2'])) { $smOk = $r2['sitemap2']['status'] === 200 && stripos((string)$r2['sitemap2']['body'], '<sitemap') !== false; if ($smOk) $sm = $r2['sitemap2']; }
$smN = $smOk ? substr_count(strtolower((string)$sm['body']), '<loc>') : 0;
$add('sitemap', $smOk,
    $smOk ? 'Ja, gefunden' . ($smN ? ' (' . $smN . ' Einträge)' : '') . '.' : 'Keine Sitemap unter den üblichen Adressen gefunden.',
    $smOk ? 'Yes, found' . ($smN ? ' (' . $smN . ' entries)' : '') . '.' : 'No sitemap found at the usual addresses.');
$can = $attr($canEl, 'href');
$canOk = $can !== '' && $eigen(lc_abs($FINAL, $can));
$add('canonical', $canOk,
    $can === '' ? 'Fehlt.' : ($canOk ? 'Ja, zeigt auf ' . lc_cut($can, 70) . '.' : 'Zeigt auf die fremde Adresse ' . lc_cut($can, 70) . '.'),
    $can === '' ? 'Missing.' : ($canOk ? 'Yes, points to ' . lc_cut($can, 70) . '.' : 'Points to the foreign address ' . lc_cut($can, 70) . '.'));
$ldBlocks = array_map(function ($n) { return (string)$n->textContent; }, $q('//script[@type="application/ld+json"]'));
$ld = count($ldBlocks) + count($q('//*[@itemtype]'));
$add('strukturdaten', $ld > 0,
    $ld ? 'Ja, Google findet maschinenlesbare Angaben zu Ihrer Seite.' : 'Fehlt. Google muss alles aus dem Text erraten.',
    $ld ? 'Yes, Google finds machine-readable information about your site.' : 'Missing. Google has to guess everything from the text.');
$lang = $attr($q('//html')[0] ?? null, 'lang');
$add('sprache', $lang !== '', $lang !== '' ? 'Ja, angegeben („' . lc_cut($lang, 10) . '“).' : 'Fehlt. Vorlesehilfen lesen den Text dann womöglich mit falscher Aussprache vor.', $lang !== '' ? 'Yes, specified (“' . lc_cut($lang, 10) . '”).' : 'Missing. Screen readers may then read the text with the wrong pronunciation.');
$mitAlt = count(array_filter($imgs, function ($i) { return $i instanceof DOMElement && $i->hasAttribute('alt'); }));
$altQ = $nImg ? $mitAlt / $nImg : 1;
$add('alt', $nImg ? $altQ >= 0.8 : null,
    $nImg ? round($altQ * 100) . ' % der ' . $nImg . ' Bilder sind beschrieben.' : 'Keine Bilder auf der Startseite.',
    $nImg ? round($altQ * 100) . '% of the ' . $nImg . ' images are described.' : 'No images on the homepage.');
$hs = []; foreach ($q('//h1|//h2|//h3|//h4|//h5|//h6') as $n) $hs[] = (int)substr($n->nodeName, 1);
$sprung = ''; for ($i = 1; $i < count($hs); $i++) if ($hs[$i] > $hs[$i - 1] + 1) { $sprung = 'H' . $hs[$i - 1] . ' → H' . $hs[$i]; break; }
$add('ueberschriften', $hs ? $sprung === '' : null,
    $hs ? ($sprung !== '' ? 'Eine Ebene wird übersprungen (' . $sprung . ').' : 'Ja, die Gliederung ist durchgehend.') : 'Keine Überschriften gefunden.',
    $hs ? ($sprung !== '' ? 'A level is skipped (' . $sprung . ').' : 'Yes, the outline is consistent.') : 'No headings found.');
$og = $meta('og:title') !== '' && $meta('og:image') !== '';
$add('vorschau', $og,
    $og ? 'Ja, Titel und Vorschaubild sind hinterlegt.' : ($meta('og:title') !== '' ? 'Titel vorhanden, aber kein Vorschaubild.' : 'Fehlt. Geteilte Links erscheinen ohne Bild.'),
    $og ? 'Yes, title and preview image are set.' : ($meta('og:title') !== '' ? 'Title present, but no preview image.' : 'Missing. Shared links appear without an image.'));
$woerter = $homeText === '' ? 0 : count(preg_split('/\s+/u', $homeText));
$add('textmenge', $woerter >= 250,
    'Etwa ' . $woerter . ' Wörter' . ($woerter >= 250 ? '.' : ', das ist wenig für Google.'),
    'About ' . $woerter . ' words' . ($woerter >= 250 ? '.' : ', which is little for Google.'));
$titel = array_map(function ($s) { return lc_lower(trim($s['titel'])); }, $subHtml);
$titelAlle = array_merge([lc_lower($title)], $titel);
$doppelt = 0; $zaehl = array_count_values(array_filter($titelAlle, 'strlen'));
foreach ($titel as $t) if ($t === '' || ($zaehl[$t] ?? 0) > 1) $doppelt++;
$add('titel_einmalig', count($subHtml) >= 2 ? $doppelt <= max(0, (int)floor(count($subHtml) * 0.2)) : null,
    count($subHtml) < 2 ? 'Zu wenige Unterseiten zum Vergleichen.' : ($doppelt ? $doppelt . ' von ' . count($subHtml) . ' Unterseiten tragen denselben oder keinen Titel.' : 'Ja, alle ' . count($subHtml) . ' geprüften Unterseiten haben einen eigenen Titel.'),
    count($subHtml) < 2 ? 'Too few subpages to compare.' : ($doppelt ? $doppelt . ' of ' . count($subHtml) . ' subpages share a title or have none.' : 'Yes, all ' . count($subHtml) . ' checked subpages have their own title.'));
$add('robots_txt', $robots !== '', $robots !== '' ? 'Ja, vorhanden.' : 'Fehlt.', $robots !== '' ? 'Yes, present.' : 'Missing.');
$add('sitemap_robots', $robots !== '' ? $smUrl !== '' : null,
    $robots === '' ? 'Nicht prüfbar, weil die robots.txt fehlt.' : ($smUrl !== '' ? 'Ja, die Sitemap ist genannt.' : 'Die robots.txt nennt keine Sitemap.'),
    $robots === '' ? 'Cannot be checked because robots.txt is missing.' : ($smUrl !== '' ? 'Yes, the sitemap is named.' : 'robots.txt names no sitemap.'), 'np');
$hreflang = count($q('//link[@rel="alternate"][@hreflang]'));
$schalter = count($q('//a[@hreflang]')) > 0;
if (!$schalter) foreach ($links as $l) if ($webLink($l) && (preg_match('#^/(en|de|fr|it|es|nl|pl|tr)(/|$)#i', (string)parse_url($l['abs'], PHP_URL_PATH)) || preg_match('/^(english|deutsch|français|italiano|español|en|de)$/iu', $l['sicht']))) { $schalter = true; break; }
$add('sprachversionen', $hreflang >= 2 ? true : ($schalter ? false : null),
    $hreflang >= 2 ? 'Ja, ' . $hreflang . ' Sprachversionen sind verknüpft.' : ($schalter ? 'Es gibt mehrere Sprachen, aber Google erfährt nicht, welche Seiten zusammengehören.' : 'Nur eine Sprache erkannt, trifft nicht zu.'),
    $hreflang >= 2 ? 'Yes, ' . $hreflang . ' language versions are linked.' : ($schalter ? 'There are several languages, but Google is not told which pages belong together.' : 'Only one language detected, not applicable.'));
$ortTreffer = '';
$fussText = lc_text($X, '//footer | //address');
if (preg_match('/\b(?:D-)?(\d{5})\s+(\p{Lu}[\p{Ll}ß]+(?:[\s-]\p{Lu}[\p{Ll}ß]+)?)/u', $fussText, $m)) $ortTreffer = $m[1] . ' ' . $m[2];
if ($ortTreffer === '' && preg_match('/(?:str\.|straße|strasse|weg|platz|allee|gasse|ring|damm|ufer|markt)\s*\d+\s*[a-z]?\s*,?\s*(?:D-)?(\d{5})\s+(\p{Lu}[\p{Ll}ß]+)/iu', $homeText, $m)) $ortTreffer = $m[1] . ' ' . $m[2];
if ($ortTreffer === '') foreach ($ldBlocks as $b) if (preg_match('/"addressLocality"\s*:\s*"([^"]{2,40})"/', $b, $m)) { $ortTreffer = $m[1]; break; }
$add('lokal', $ortTreffer !== '',
    $ortTreffer !== '' ? 'Ja, „' . lc_cut($ortTreffer, 40) . '“ steht auf der Startseite.' : 'Auf der Startseite steht keine Anschrift oder Ortsangabe.',
    $ortTreffer !== '' ? 'Yes, “' . lc_cut($ortTreffer, 40) . '” appears on the homepage.' : 'There is no address or location on the homepage.');
$typen = [];
foreach ($ldBlocks as $b) if (preg_match_all('/"@type"\s*:\s*(?:\[\s*)?"([A-Za-z]+)"/', $b, $mm)) foreach ($mm[1] as $t) $typen[$t] = true;
foreach ($q('//*[@itemtype]') as $n) if (preg_match('#schema\.org/([A-Za-z]+)#', $attr($n, 'itemtype'), $m)) $typen[$m[1]] = true;
$firma = '';
foreach (array_keys($typen) as $t) if (preg_match('/^(Organization|LocalBusiness|Corporation|NGO|Person|ProfessionalService|Store|Restaurant|Dentist|Physician|Attorney|LegalService|Electrician|Plumber|HairSalon|BeautySalon|AutoRepair|RealEstateAgent|AccountingService|InsuranceAgency|TravelAgency|MedicalClinic|Hotel|Bakery|CafeOrCoffeeShop)$|(Business|Service|Store|Shop|Salon|Organization|Office|Clinic|Agency|Contractor)$/', $t)) { $firma = $t; break; }
$add('strukturdaten_firma', $firma !== '',
    $firma !== '' ? 'Ja, Ihre Seite beschreibt sich als „' . $firma . '“.' : ($typen ? 'Es gibt strukturierte Daten, aber keinen Eintrag als Firma oder Geschäft.' : 'Fehlt.'),
    $firma !== '' ? 'Yes, your site describes itself as “' . $firma . '”.' : ($typen ? 'There is structured data, but no entry as a business.' : 'Missing.'));
$vage = 0; $vageBsp = '';
foreach ($links as $l) { $t = lc_lower($l['sicht']);
    if (preg_match('/^(hier|hier klicken|klicken sie hier|klick hier|mehr|weiter|weiterlesen|link|click here|here|more|read more|learn more|this|details|›|»|→)$/u', $t) && $attr($l['el'], 'aria-label') === '') { $vage++; if ($vageBsp === '') $vageBsp = $t; } }
$add('linktexte', $vage <= 2,
    $vage <= 2 ? 'Ja, die Links sagen, wohin sie führen.' : $vage . ' Links heißen nur „' . $vageBsp . '“ oder ähnlich.',
    $vage <= 2 ? 'Yes, links say where they lead.' : $vage . ' links are only called “' . $vageBsp . '” or similar.');
$subOffen = array_values(array_filter($subHtml, function ($s) { return !$s['recht']; }));
$gesperrt = array_values(array_filter($subOffen, function ($s) { return $s['noindex']; }));
$add('unterseiten_offen', $subOffen ? !$gesperrt : null,
    !$subOffen ? 'Keine Unterseiten zum Prüfen gefunden.' : ($gesperrt ? count($gesperrt) . ' Unterseite(n) sind für Google gesperrt, z. B. ' . $pfad($gesperrt[0]['url']) . '.' : 'Ja, alle ' . count($subOffen) . ' geprüften Unterseiten sind für Google offen.'),
    !$subOffen ? 'No subpages found to check.' : ($gesperrt ? count($gesperrt) . ' subpage(s) are blocked for Google, e.g. ' . $pfad($gesperrt[0]['url']) . '.' : 'Yes, all ' . count($subOffen) . ' checked subpages are open to Google.'));

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= F Geschwindigkeit (10) ================= */
$ttfb = $home['ttfb']; $tot = $home['total'];
$add('tempo', $ttfb > 0 ? $ttfb <= 0.8 : null,
    $ttfb > 0 ? 'Erste Antwort nach ' . $zahl($ttfb, 2) . ' Sekunden' . ($ttfb <= 0.8 ? '.' : ', das ist langsam.') : 'Die Antwortzeit ließ sich diesmal nicht messen.',
    $ttfb > 0 ? 'First response after ' . $zahl($ttfb, 2) . ' seconds' . ($ttfb <= 0.8 ? '.' : ', which is slow.') : 'The response time could not be measured this time.', 'np');
$add('ladezeit', $tot > 0 ? $tot <= 2.5 : null,
    $tot > 0 ? 'Startseite nach ' . $zahl($tot, 2) . ' Sekunden übertragen' . ($tot <= 2.5 ? '.' : ', das dauert zu lange.') : 'Die Ladezeit ließ sich diesmal nicht messen.',
    $tot > 0 ? 'Homepage transferred after ' . $zahl($tot, 2) . ' seconds' . ($tot <= 2.5 ? '.' : ', which takes too long.') : 'The loading time could not be measured this time.', 'np');
$enc = strtolower(lc_hdr($home, 'content-encoding'));
$gepackt = in_array($enc, ['gzip', 'br', 'zstd', 'deflate'], true);
$add('komprimierung', $gepackt, $gepackt ? 'Ja, Ihre Seite wird gepackt übertragen (' . $enc . ').' : 'Nein, Ihre Seite wird ungepackt übertragen.', $gepackt ? 'Yes, your site is sent compressed (' . $enc . ').' : 'No, your site is sent uncompressed.');
$kb = (int)round(strlen($HTML) / 1024);
$nCss = count($q('//link[contains(concat(" ",normalize-space(@rel)," ")," stylesheet ")]'));
$dateien = count($q('//script[@src]')) + $nCss + $nImg;
$add('groesse', $kb <= 300 && $dateien <= 70,
    'Die Startseite ist ' . $kb . ' KB groß und lädt ' . $dateien . ' Dateien nach' . ($kb <= 300 && $dateien <= 70 ? '.' : ', das ist viel.'),
    'The homepage is ' . $kb . ' KB and loads ' . $dateien . ' additional files' . ($kb <= 300 && $dateien <= 70 ? '.' : ', which is a lot.'));
$cc = isset($r2['asset']) ? strtolower(lc_hdr($r2['asset'], 'cache-control')) : '';
$age = preg_match('/max-age=(\d+)/', $cc, $m) ? (int)$m[1] : 0;
$cacheOk = isset($r2['asset']) && $r2['asset']['status'] === 200 ? ($age >= 86400 || strpos($cc, 'immutable') !== false || (lc_hdr($r2['asset'], 'expires') !== '' && strpos($cc, 'no-') === false)) : null;
$add('cache', $cacheOk,
    $cacheOk === null ? 'Keine eigene Datei zum Prüfen gefunden.' : ($cacheOk ? 'Ja, Browser dürfen Ihre Dateien speichern.' : 'Nein, Ihre Dateien werden bei jedem Besuch neu geladen.'),
    $cacheOk === null ? 'No own file found to check.' : ($cacheOk ? 'Yes, browsers may store your files.' : 'No, your files are reloaded on every visit.'));
$add('http2', $home['ver'] ? $home['http2'] : null,
    !$home['ver'] ? 'Das Protokoll ließ sich diesmal nicht feststellen.' : ($home['http2'] ? 'Ja, Ihr Server nutzt HTTP/2 oder neuer.' : 'Nein, Ihr Server nutzt nur das alte HTTP/1.1.'),
    !$home['ver'] ? 'The protocol could not be determined this time.' : ($home['http2'] ? 'Yes, your server uses HTTP/2 or newer.' : 'No, your server only uses the old HTTP/1.1.'), 'np');
$block = 0;
foreach ($q('//head/script[@src]') as $n) { $t = strtolower($attr($n, 'type')); if ($n->hasAttribute('async') || $n->hasAttribute('defer') || !$aktiv($t) || $t === 'module') continue; $block++; }
$add('blockierend', $block <= 2,
    $block <= 2 ? ($block ? $block . ' Skript(e) halten den Aufbau kurz an, das ist noch in Ordnung.' : 'Ja, kein Skript hält den Seitenaufbau an.') : $block . ' Skripte halten den Seitenaufbau an. So lange sehen Besucher eine leere Seite.',
    $block <= 2 ? ($block ? $block . ' script(s) briefly pause rendering, which is still fine.' : 'Yes, no script pauses rendering.') : $block . ' scripts pause rendering. Until then visitors see a blank page.');
$add('stylesheets', $nCss <= 6,
    $nCss . ' Gestaltungsdatei(en)' . ($nCss <= 6 ? '.' : ', das sind zu viele.'),
    $nCss . ' design file(s)' . ($nCss <= 6 ? '.' : ', which is too many.'));
$fremdSk = [];
foreach ($scriptsSrc as $s) { $h = (string)parse_url(lc_abs($FINAL, $s['src']), PHP_URL_HOST); if ($h !== '' && !$eigen('https://' . $h . '/')) $fremdSk[lc_regdom($h)] = true; }
$add('fremde_skripte', count($fremdSk) <= 5,
    $fremdSk ? 'Skripte von ' . count($fremdSk) . ' fremden Adressen' . (count($fremdSk) <= 5 ? '.' : ', darunter ' . lc_cut(lc_und(array_slice(array_keys($fremdSk), 0, 4)), 90) . '.') : 'Keine fremden Skripte.',
    $fremdSk ? 'Scripts from ' . count($fremdSk) . ' foreign addresses' . (count($fremdSk) <= 5 ? '.' : ', including ' . lc_cut(lc_und(array_slice(array_keys($fremdSk), 0, 4), true), 90) . '.') : 'No third-party scripts.');
$zeiten = array_map(function ($s) { return $s['zeit']; }, array_filter($SUB, function ($s) { return $s['status'] === 200 && $s['art'] === 'seite'; }));
sort($zeiten);
$median = $zeiten ? $zeiten[(int)floor((count($zeiten) - 1) / 2)] : null;
$add('unterseiten_tempo', $median === null ? null : $median <= 1.0,
    $median === null ? 'Keine Unterseiten zum Messen gefunden.' : 'Unterseiten kommen im Schnitt nach ' . $zahl($median, 2) . ' Sekunden' . ($median <= 1.0 ? '.' : ', das ist langsam.'),
    $median === null ? 'No subpages found to measure.' : 'Subpages arrive after ' . $zahl($median, 2) . ' seconds on average' . ($median <= 1.0 ? '.' : ', which is slow.'));

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= G Kontakt und Vertrauen (8) ================= */
$telHome = count($q('//a[starts-with(translate(@href,"TEL","tel"),"tel:")]'));
$mailHome = count($q('//a[starts-with(translate(@href,"MAILTO","mailto"),"mailto:")]'));
$lKonAll = $findLink('/kontakt|contact/i');
$kontaktOk = $telHome || $mailHome || $FORMS_K || $lKonAll || $formDienst !== '';
$wege = array_filter([$telHome ? $L('Telefon', 'phone') : '', $mailHome ? $L('E-Mail', 'email') : '', ($FORMS_K || $formDienst !== '') ? $L('Formular', 'form') : '', $lKonAll ? $L('Kontaktseite', 'contact page') : '']);
$add('kontakt', (bool)$kontaktOk,
    $kontaktOk ? 'Ja, erreichbar über ' . implode(', ', $wege) . '.' : 'Auf der Startseite ist kein Weg zu Ihnen zu finden.',
    $kontaktOk ? 'Yes, reachable via ' . implode(', ', $wege) . '.' : 'There is no way to reach you on the homepage.');
$telKon = $KX ? count(lc_q($KX, '//a[starts-with(translate(@href,"TEL","tel"),"tel:")]')) : 0;
$nummerText = (bool)preg_match('/(?:\+49|\+43|\+41|\b0\d{2,5})[\s\/().-]*\d{2,}[\s\/-]?\d{2,}/', $homeText . ' ' . $konText);
$add('telefon', ($telHome + $telKon) > 0,
    $telHome ? 'Ja, auf der Startseite antippbar.' : ($telKon ? 'Antippbar auf der Kontaktseite, auf der Startseite nicht.' : ($nummerText ? 'Die Nummer steht da, lässt sich auf dem Handy aber nicht antippen.' : 'Keine Telefonnummer gefunden.')),
    $telHome ? 'Yes, tap-to-call on the homepage.' : ($telKon ? 'Tap-to-call on the contact page, not on the homepage.' : ($nummerText ? 'The number is there but cannot be tapped on a phone.' : 'No phone number found.')));
$cta = ''; $ctaLang = '';
foreach (array_merge($links, array_map(function ($b) use ($X) { $t = lc_ntext($X, $b); return ['text' => $t, 'sicht' => $t, 'href' => '', 'abs' => '', 'el' => $b]; }, $q('//button'))) as $l)
    if (preg_match('/termin|anfrage|anfragen|angebot|beratung|kontakt aufnehmen|jetzt\s|buchen|bestellen|reservier|rückruf|anrufen|book|request|quote|get in touch|contact us|schedule|get started|call now|enquire|inquire/iu', $l['sicht'])) { if (lc_len($l['sicht']) <= 40) { $cta = $l['sicht']; break; } if ($ctaLang === '') $ctaLang = lc_cut($l['sicht'], 37) . '…'; }
if ($cta === '') $cta = $ctaLang;
$add('handlung', $cta !== '',
    $cta !== '' ? 'Ja, z. B. „' . $cta . '“.' : 'Kein klarer Knopf wie „Termin buchen“ oder „Angebot anfordern“ gefunden.',
    $cta !== '' ? 'Yes, e.g. “' . $cta . '”.' : 'No clear button like “Book an appointment” or “Request a quote” found.');
$mailImp = stripos($impHtml, 'mailto:') !== false;
$mailKon = $KX ? count(lc_q($KX, '//a[starts-with(translate(@href,"MAILTO","mailto"),"mailto:")]')) > 0 : false;
$add('email_link', $mailHome || $mailImp || $mailKon,
    $mailHome ? 'Ja, auf der Startseite anklickbar.' : ($mailKon || $mailImp ? 'Anklickbar auf der ' . ($mailKon ? 'Kontaktseite' : 'Impressumsseite') . '.' : 'Keine anklickbare E-Mail-Adresse gefunden.'),
    $mailHome ? 'Yes, clickable on the homepage.' : ($mailKon || $mailImp ? 'Clickable on the ' . ($mailKon ? 'contact page' : 'legal notice page') . '.' : 'No clickable email address found.'));
$zeitOk = (bool)preg_match('/öffnungszeit|sprechzeit|geschäftszeit|erreichbar(keit)?\s|mo\.?\s*(bis|[-–])\s*fr|montag\s*(bis|[-–])|opening hours|office hours|business hours|mon(day)?\s*[-–]\s*fri/iu', $homeText . ' ' . $konText) || (bool)preg_match('/openingHours/i', implode(' ', $ldBlocks));
$add('zeiten', $zeitOk, $zeitOk ? 'Ja, Zeiten sind angegeben.' : 'Keine Öffnungs- oder Erreichbarkeitszeiten gefunden.', $zeitOk ? 'Yes, hours are shown.' : 'No opening hours or availability found.');
$vertrauen = (bool)preg_match('/bewertung|kundenstimme|referenz|erfahrungsbericht|testimonial|review|★|⭐|sterne|kunden sagen|das sagen|what (our )?(clients|customers) say|provenexpert|trustpilot|trusted shops|google-bewertung/iu', $homeText . ' ' . $HTML) || (bool)preg_match('/aggregateRating|"Review"/i', implode(' ', $ldBlocks));
$add('vertrauen', $vertrauen, $vertrauen ? 'Ja, Bewertungen oder Referenzen sind zu sehen.' : 'Keine Bewertungen, Kundenstimmen oder Referenzen auf der Startseite.', $vertrauen ? 'Yes, reviews or references are visible.' : 'No reviews, testimonials or references on the homepage.');
$ueber = $findLink('/über[\s-]?uns|ueber-uns|uber-uns|über[\s-]?mich|ueber-mich|\bteam\b|about|wer wir sind|who we are/iu');
// auch kurze Adressen wie /ueber, /about.html oder /unternehmen (Linktext z. B. „Grundsätze“ oder „Mehr über …“)
if (!$ueber) $ueber = $findLink('~(^|/)(ueber|uber|about|unternehmen|philosophie)(\.html?|\.php)?/?([?#]|$)~iu', true) ?? $findLink('/^(?i:mehr )?[üÜ]ber ((die|den|das|uns|mich)\b|\p{Lu})/u', true);
if ($ueber && lc_len($ueber['sicht']) < 5) $ueber['sicht'] = $pfad($ueber['abs']);
$add('ueber_uns', (bool)$ueber, $ueber ? 'Ja, verlinkt („' . lc_cut($ueber['sicht'] !== '' ? $ueber['sicht'] : $ueber['href'], 30) . '“).' : 'Kein Link zu „Über uns“ oder „Team“ gefunden.', $ueber ? 'Yes, linked (“' . lc_cut($ueber['sicht'] !== '' ? $ueber['sicht'] : $ueber['href'], 30) . '”).' : 'No link to “About us” or “Team” found.');
$jahr = 0;
if (preg_match_all('/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?((?:19|20)\d{2})/iu', $homeText, $mm)) foreach ($mm[1] as $y) $jahr = max($jahr, (int)$y);
$jetzt = (int)gmdate('Y');
$add('gepflegt', $jahr ? $jahr >= $jetzt - 1 : null,
    !$jahr ? 'Keine Jahreszahl in der Fußzeile gefunden, nicht bewertet.' : ($jahr >= $jetzt - 1 ? 'Ja, „© ' . $jahr . '“.' : 'In der Fußzeile steht „© ' . $jahr . '“. Das wirkt, als würde die Seite nicht mehr gepflegt.'),
    !$jahr ? 'No year found in the footer, not assessed.' : ($jahr >= $jetzt - 1 ? 'Yes, “© ' . $jahr . '”.' : 'The footer says “© ' . $jahr . '”. This looks as if the site is no longer maintained.'), 'np');

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= H Anbindungen und Schnittstellen (11) ================= */
$formOrt = '';
foreach ($FORMS_K as $f) { $formOrt = $f['seite']; if ($formOrt === 'start') break; }
$formDa = $FORMS_K || $formDienst !== '';
$add('formular', $formDa,
    $FORMS_K ? 'Ja, ein Kontaktformular auf der ' . ($formOrt === 'start' ? 'Startseite' : 'Kontaktseite') . '.' : ($formDienst !== '' ? 'Ja, ein eingebundenes Formular (' . $formDienst . ').' : 'Kein Kontaktformular gefunden. Wer schreiben möchte, muss sein E-Mail-Programm öffnen.'),
    $FORMS_K ? 'Yes, a contact form on the ' . ($formOrt === 'start' ? 'homepage' : 'contact page') . '.' : ($formDienst !== '' ? 'Yes, an embedded form (' . $formDienst . ').' : 'No contact form found. Anyone wanting to write has to open their email app.'));
$karteSofort = []; $karteKlick = false;
foreach ($iframesAlle as $f) {
    if (preg_match('/google\.[a-z.]+\/maps|maps\.google|openstreetmap\.org|bing\.com\/maps|here\.com|mapbox/i', $f['src'])) $karteSofort[] = preg_match('/openstreetmap/i', $f['src']) ? 'OpenStreetMap' : 'Google Maps';
    elseif (preg_match('/maps/i', $f['data'])) $karteKlick = true;
}
foreach ($scriptsAlle as $s) if (preg_match('/maps\.googleapis\.com\/maps\/api\/js/i', $s['src']) && $s['aktiv']) $karteSofort[] = 'Google Maps';
$karteSofort = array_values(array_unique($karteSofort));
if (!$karteSofort && !$karteKlick && preg_match('/data-(?:src|lazy-src|cmplz-src|borlabs|consent)[^>]*maps|maps[^>]*data-(?:placeholder|consent)/i', $alleHtml)) $karteKlick = true;
$add('karte', $karteSofort ? false : ($karteKlick ? true : null),
    $karteSofort ? 'Die Karte (' . implode(', ', $karteSofort) . ') lädt sofort beim Aufruf, ohne Zustimmung.' : ($karteKlick ? 'Ja, die Karte lädt erst nach Klick oder Zustimmung.' : 'Keine eingebettete Karte gefunden, trifft nicht zu.'),
    $karteSofort ? 'The map (' . implode(', ', $karteSofort) . ') loads immediately on arrival, without consent.' : ($karteKlick ? 'Yes, the map loads only after a click or consent.' : 'No embedded map found, not applicable.'));
$ohneSchutz = 0;
foreach ($FORMS_K as $f) if (!preg_match('/captcha|turnstile|altcha|honeypot|honey|akismet|cleantalk|antispam|anti-spam|tabindex=["\']-1["\'][^>]*autocomplete=["\']off|autocomplete=["\']off["\'][^>]*tabindex=["\']-1|class=["\'][^"\']*\b(hp|hidden-field|ohnohoney|wpcf7-honeypot)\b|display\s*:\s*none/i', $f['html'])) $ohneSchutz++;
$add('formular_schutz', $FORMS_K ? $ohneSchutz === 0 : ($formDienst !== '' ? true : null),
    $FORMS_K ? ($ohneSchutz ? 'Am Formular ist kein Spam-Schutz zu erkennen.' : 'Ja, das Formular hat einen Spam-Schutz.') : ($formDienst !== '' ? 'Der Formular-Dienst bringt seinen eigenen Schutz mit.' : 'Kein Formular gefunden, trifft nicht zu.'),
    $FORMS_K ? ($ohneSchutz ? 'No spam protection visible on the form.' : 'Yes, the form has spam protection.') : ($formDienst !== '' ? 'The form service brings its own protection.' : 'No form found, not applicable.'));
$buchDienst = '';
if (preg_match('/calendly\.com|cal\.com\/|doctolib|etermin|shore\.com|simplybook|timify|bookingkit|treatwell|planity|acuityscheduling|youcanbook\.me|tidycal|zcal\.co|setmore|terminland|samedi\.de|outlook\.office365\.com\/(owa\/calendar|book)|bookings\.cloud\.microsoft|appointlet|hubspot\.com\/meetings|meetings\.hubspot|booksy|fresha|salonkee|studiobookr|supersaas|termin-direkt|terminpilot|zeeg\.me|koalendar|calendar\.app\.google|calendar\.google\.com\/calendar\/appointments/i', $alleHtml, $m)) $buchDienst = $m[0];
$buchFeld = false;
foreach ($FORMS_K as $f) if (preg_match('/type=["\'](date|datetime-local)["\']|name=["\'][^"\']*(termin|appointment|booking|wunschtermin)|wunschtermin|terminwunsch|preferred (date|appointment)/i', $f['html'])) { $buchFeld = true; break; }
$add('buchung', $buchDienst !== '' || $buchFeld,
    $buchDienst !== '' ? 'Ja, eine Online-Buchung ist eingebunden (' . preg_replace('#[/\\\\]+$#', '', $buchDienst) . ').' : ($buchFeld ? 'Ja, im Formular lässt sich ein Wunschtermin wählen.' : 'Keine Online-Terminbuchung gefunden. Kunden müssen anrufen oder schreiben.'),
    $buchDienst !== '' ? 'Yes, online booking is embedded (' . preg_replace('#[/\\\\]+$#', '', $buchDienst) . ').' : ($buchFeld ? 'Yes, the form lets visitors choose a preferred date.' : 'No online booking found. Customers have to call or write.'));
$msg = [];
foreach ($alleLinks as $l) {
    if (preg_match('#wa\.me/|api\.whatsapp\.com|whatsapp://|chat\.whatsapp\.com#i', $l['abs'])) $msg['WhatsApp'] = true;
    if (preg_match('#(^|//)t\.me/|telegram\.me/#i', $l['abs'])) $msg['Telegram'] = true;
    if (preg_match('#signal\.me/|signal\.group/#i', $l['abs'])) $msg['Signal'] = true;
    if (preg_match('#(^|//)m\.me/#i', $l['abs'])) $msg['Messenger'] = true;
    if (preg_match('#threema\.id/#i', $l['abs'])) $msg['Threema'] = true;
}
$add('messenger', (bool)$msg, $msg ? 'Ja, erreichbar über ' . implode(', ', array_keys($msg)) . '.' : 'Kein Link zu WhatsApp oder einem anderen Messenger gefunden.', $msg ? 'Yes, reachable via ' . implode(', ', array_keys($msg)) . '.' : 'No link to WhatsApp or another messenger found.');
$vidSofort = []; $vidSparsam = false; $vidKlick = false;
foreach ($iframesAlle as $f) {
    if (preg_match('/youtube-nocookie\.com/i', $f['src'])) $vidSparsam = true;
    elseif (preg_match('/youtube\.com\/embed|youtu\.be/i', $f['src'])) $vidSofort['YouTube'] = true;
    elseif (preg_match('/player\.vimeo\.com/i', $f['src'])) { if (preg_match('/[?&]dnt=1/', $f['src'])) $vidSparsam = true; else $vidSofort['Vimeo'] = true; }
    elseif (preg_match('/youtube|vimeo/i', $f['data'])) $vidKlick = true;
}
if (!$vidSofort && !$vidSparsam && !$vidKlick && preg_match('/data-(?:src|lazy-src|cmplz-src|borlabs|consent|youtube|video-id)[^>]*(youtube|vimeo)|lite-youtube|lyte-wrapper|borlabs-cookie-video/i', $alleHtml)) $vidKlick = true;
$add('video', $vidSofort ? false : (($vidSparsam || $vidKlick) ? true : null),
    $vidSofort ? implode(', ', array_keys($vidSofort)) . '-Videos laden sofort beim Aufruf und übertragen dabei Daten.' : ($vidKlick ? 'Ja, Videos laden erst nach Klick.' : ($vidSparsam ? 'Videos nutzen den datensparsamen Modus. Noch besser wäre es, sie erst nach einem Klick zu laden.' : 'Keine eingebetteten Videos gefunden, trifft nicht zu.')),
    $vidSofort ? implode(', ', array_keys($vidSofort)) . ' videos load immediately on arrival and send data.' : ($vidKlick ? 'Yes, videos load only after a click.' : ($vidSparsam ? 'Videos use the privacy-enhanced mode. It would be even better to load them only after a click.' : 'No embedded videos found, not applicable.')));
$soz = [];
$sozMuster = ['instagram.com' => 'Instagram', 'facebook.com' => 'Facebook', 'fb.com' => 'Facebook', 'linkedin.com' => 'LinkedIn', 'tiktok.com' => 'TikTok', 'youtube.com' => 'YouTube', 'xing.com' => 'Xing', 'x.com' => 'X', 'twitter.com' => 'X', 'pinterest.' => 'Pinterest', 'threads.net' => 'Threads', 'bsky.app' => 'Bluesky', 'mastodon.' => 'Mastodon'];
foreach ($alleLinks as $l) {
    if (preg_match('#sharer|share\?|intent/tweet|shareArticle|/share/|/embed/|watch\?v=|/plugins/#i', $l['abs'])) continue;
    $h = strtolower((string)parse_url($l['abs'], PHP_URL_HOST));
    foreach ($sozMuster as $k => $v) if ($h === $k || substr($h, -strlen('.' . $k)) === '.' . $k || (substr($k, -1) === '.' && strpos($h, $k) !== false)) $soz[$v] = true;
}
$add('social', (bool)$soz, $soz ? 'Ja, es gibt Links zu ' . lc_und(array_keys($soz)) . '.' : 'Keine Social-Media-Profile verlinkt.', $soz ? 'Yes, there are links to ' . lc_und(array_keys($soz), true) . '.' : 'No social media profiles linked.');
$bew = '';
foreach ($alleLinks as $l) if (preg_match('#g\.page/|maps\.app\.goo\.gl|goo\.gl/maps|google\.[a-z.]+/maps/place|search\.google\.com/local|g\.co/kgs|provenexpert\.com|trustpilot\.com|jameda\.de|kununu\.com|yelp\.|trustedshops\.|golocal\.de|werkenntdenbesten\.de|ausgezeichnet\.org|11880\.com|check24\.de/profis|my-hammer\.de|treatwell\.|doctolib\.de#i', $l['abs'], $m)) { $bew = rtrim($m[0], '/.'); break; }
$add('bewertungsprofil', $bew !== '', $bew !== '' ? 'Ja, verlinkt (' . $bew . ').' : 'Kein Link zum Google-Profil oder zu einem Bewertungsportal gefunden.', $bew !== '' ? 'Yes, linked (' . $bew . ').' : 'No link to a Google profile or review site found.');
$nlAnbieter = '';
$nlDoi = false;
foreach ($FORMS_N as $f) {
    if (preg_match('/list-manage\.com|sibforms\.com|cleverreach|rapidmail|mailerlite|klaviyo|getresponse|convertkit|kit\.com|sendfox|mailpoet|newsletter2go|brevo/i', $f['html'], $m)) $nlAnbieter = $m[0];
    if ($nlAnbieter !== '' || preg_match('/bestätig|confirm|double[- ]?opt|opt[- ]?in|aktivierungs/i', $f['html'])) $nlDoi = true;
}
if ($FORMS_N && !$nlDoi && preg_match('/newsletter.{0,300}(bestätig|confirm|double[- ]?opt|opt[- ]?in)/isu', $homeText . ' ' . $konText)) $nlDoi = true;
$add('newsletter', $FORMS_N ? $nlDoi : null,
    !$FORMS_N ? 'Keine Newsletter-Anmeldung gefunden, trifft nicht zu.' : ($nlDoi ? 'Ja, die Anmeldung wird per E-Mail bestätigt' . ($nlAnbieter !== '' ? ' (über ' . $nlAnbieter . ')' : '') . '.' : 'Bei der Anmeldung fehlt der Hinweis auf eine Bestätigung per E-Mail.'),
    !$FORMS_N ? 'No newsletter sign-up found, not applicable.' : ($nlDoi ? 'Yes, sign-up is confirmed by email' . ($nlAnbieter !== '' ? ' (via ' . $nlAnbieter . ')' : '') . '.' : 'The sign-up lacks a note about email confirmation.'));
$chatMuster = ['embed.tawk.to' => 'Tawk.to', 'widget.intercom.io' => 'Intercom', 'js.intercomcdn.com' => 'Intercom', 'client.crisp.chat' => 'Crisp', 'code.tidio.co' => 'Tidio', 'static.zdassets.com' => 'Zendesk',
    'js.driftt.com' => 'Drift', 'cdn.livechatinc.com' => 'LiveChat', 'smartsupp.com' => 'Smartsupp', 'userlike-cdn' => 'Userlike', 'userlike.com' => 'Userlike', 'call.chatra.io' => 'Chatra', 'olark.com' => 'Olark',
    'wchat.freshchat.com' => 'Freshchat', 'code.jivosite.com' => 'Jivo', 'cdn.botpress.cloud' => 'Botpress', 'landbot.io' => 'Landbot', 'js-eu1.hs-scripts.com' => 'HubSpot', 'js.hs-scripts.com' => 'HubSpot', 'chatbase.co' => 'Chatbase', 'cdn.voiceflow.com' => 'Voiceflow'];
$chatSofort = []; $chatGeblockt = false;
foreach ($scriptsAlle as $s) foreach ($chatMuster as $k => $v) if (stripos($s['src'], $k) !== false) { if ($s['aktiv']) $chatSofort[$v] = true; else $chatGeblockt = true; }
foreach ($SEITEN as $sx) foreach (lc_q($sx['x'], '//script[not(@src)]') as $n) { $code = (string)$n->textContent; foreach ($chatMuster as $k => $v) if (stripos($code, $k) !== false) { if ($skriptAktiv($n)) $chatSofort[$v] = true; else $chatGeblockt = true; } }
$add('chat', $chatSofort ? false : ($chatGeblockt ? true : null),
    $chatSofort ? 'Das Chat-Fenster (' . implode(', ', array_keys($chatSofort)) . ') lädt sofort und überträgt Daten an den Anbieter.' : ($chatGeblockt ? 'Ja, der Chat lädt erst nach Zustimmung.' : 'Kein Chat-Fenster gefunden, trifft nicht zu.'),
    $chatSofort ? 'The chat widget (' . implode(', ', array_keys($chatSofort)) . ') loads immediately and sends data to the provider.' : ($chatGeblockt ? 'Yes, the chat loads only after consent.' : 'No chat widget found, not applicable.'));
$agb = false; $wid = false;
foreach ($alleLinks as $l) { $t = $l['href'] . ' ' . $l['text']; if (preg_match('/\bagb\b|allgemeine[- ]gesch|terms|conditions/i', $t)) $agb = true; if (preg_match('/widerruf|cancellation|withdrawal|right[- ]of[- ]withdrawal|return[- ]policy|rückgabe/i', $t)) $wid = true; }
$add('shop_recht', $istShop ? ($agb && $wid) : null,
    !$istShop ? 'Kein Shop und keine Bezahlung erkannt, trifft nicht zu.' : ($agb && $wid ? 'Ja, AGB und Widerrufsbelehrung sind verlinkt.' : 'Shop erkannt, aber es fehlt der Link zu ' . implode(' und ', array_filter([$agb ? '' : 'den AGB', $wid ? '' : 'der Widerrufsbelehrung'])) . '.'),
    !$istShop ? 'No shop or payment detected, not applicable.' : ($agb && $wid ? 'Yes, terms and cancellation policy are linked.' : 'Shop detected, but the link to ' . implode(' and ', array_filter([$agb ? '' : 'the terms', $wid ? '' : 'the cancellation policy'])) . ' is missing.'));

} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}
try {
/* ================= I E-Mail der Domain (6) ================= */
$mxDa = (bool)$mx;
$add('spf', $mxDa || $spf ? (bool)$spf : null,
    $spf ? 'Ja, festgelegt.' : ($mxDa ? 'Fehlt. Ihre E-Mails landen leichter im Spam.' : (LC_TEST ? 'Im Testlauf nicht geprüft.' : 'Ihre Domain empfängt keine E-Mails, trifft nicht zu.')),
    $spf ? 'Yes, defined.' : ($mxDa ? 'Missing. Your emails land in spam more easily.' : (LC_TEST ? 'Not checked in the test run.' : 'Your domain receives no email, not applicable.')), (LC_TEST ? 'np' : 'na'));
$add('dmarc', $mxDa || $dmarc ? (bool)$dmarc : null,
    $dmarc ? 'Ja, vorhanden.' : ($mxDa ? 'Fehlt. Gefälschte E-Mails in Ihrem Namen werden nicht aussortiert.' : (LC_TEST ? 'Im Testlauf nicht geprüft.' : 'Ihre Domain empfängt keine E-Mails, trifft nicht zu.')),
    $dmarc ? 'Yes, present.' : ($mxDa ? 'Missing. Fake emails in your name are not filtered out.' : (LC_TEST ? 'Not checked in the test run.' : 'Your domain receives no email, not applicable.')), (LC_TEST ? 'np' : 'na'));
$add('mx', LC_TEST ? null : $mxDa,
    $mxDa ? 'Ja, ' . count($mx) . ' Posteingangs-Server eingetragen.' : (LC_TEST ? 'Im Testlauf nicht geprüft.' : 'Ihre Domain kann keine E-Mails empfangen.'),
    $mxDa ? 'Yes, ' . count($mx) . ' incoming mail server(s) set.' : (LC_TEST ? 'Not checked in the test run.' : 'Your domain cannot receive email.'), 'np');
$spfEnde = $spf && preg_match('/([-~?+])all\b/i', $spf[0], $m) ? $m[1] : '';
$add('spf_streng', $spf ? in_array($spfEnde, ['-', '~'], true) : null,
    !$spf ? ($mxDa ? 'Nicht prüfbar, weil kein Absenderschutz eingetragen ist.' : 'Ihre Domain empfängt keine E-Mails, trifft nicht zu.') : (in_array($spfEnde, ['-', '~'], true) ? 'Ja, fremde Server werden abgewiesen.' : 'Der Eintrag erlaubt praktisch jedem Server, in Ihrem Namen zu senden.'),
    !$spf ? ($mxDa ? 'Cannot be checked because no sender protection is set.' : 'Your domain receives no email, not applicable.') : (in_array($spfEnde, ['-', '~'], true) ? 'Yes, foreign servers are rejected.' : 'The record allows practically any server to send in your name.'), ($mxDa ? 'np' : 'na'));
$dmarcP = $dmarc && preg_match('/\bp=(\w+)/i', $dmarc[0], $m) ? strtolower($m[1]) : '';
$add('dmarc_streng', $dmarc ? in_array($dmarcP, ['quarantine', 'reject'], true) : null,
    !$dmarc ? ($mxDa ? 'Nicht prüfbar, weil kein DMARC-Eintrag vorhanden ist.' : 'Ihre Domain empfängt keine E-Mails, trifft nicht zu.') : (in_array($dmarcP, ['quarantine', 'reject'], true) ? 'Ja, Fälschungen landen im Spam oder werden abgelehnt.' : 'Der Eintrag beobachtet nur. Fälschungen kommen trotzdem an.'),
    !$dmarc ? ($mxDa ? 'Cannot be checked because there is no DMARC record.' : 'Your domain receives no email, not applicable.') : (in_array($dmarcP, ['quarantine', 'reject'], true) ? 'Yes, fakes go to spam or are rejected.' : 'The record only observes. Fakes still get through.'), ($mxDa ? 'np' : 'na'));
$adressen = [];
foreach ($alleLinks as $l) if (stripos($l['href'], 'mailto:') === 0 && preg_match('/^mailto:\s*([^?]+)/i', $l['href'], $m)) foreach (explode(',', rawurldecode($m[1])) as $a) $adressen[] = strtolower(trim($a));
if (preg_match_all('/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/i', $impTxt . ' ' . $impHtml, $mm)) foreach ($mm[0] as $a) $adressen[] = strtolower($a);
$adressen = array_values(array_unique(array_filter($adressen, function ($a) { return strpos($a, '@') !== false && !preg_match('/\.(png|jpe?g|gif|webp|svg)$/', $a) && !preg_match('/@(sentry|example|domain|beispiel)\./', $a); })));
$eigeneAdr = array_values(array_filter($adressen, function ($a) use ($RD) { $d = substr($a, strpos($a, '@') + 1); return lc_regdom($d) === $RD; }));
$freemail = '/@(gmail|googlemail|web|gmx|t-online|outlook|hotmail|live|yahoo|icloud|me|aol|freenet|posteo|mail|arcor|online|1und1|mailbox|proton|protonmail)\.[a-z.]+$/i';
$frei = array_values(array_filter($adressen, function ($a) use ($freemail) { return (bool)preg_match($freemail, $a); }));
$add('email_eigen', $adressen ? (bool)$eigeneAdr : null,
    !$adressen ? 'Keine E-Mail-Adresse gefunden, nicht bewertet.' : ($eigeneAdr ? 'Ja, z. B. ' . $eigeneAdr[0] . '.' : ($frei ? 'Angegeben ist eine Adresse bei einem Gratis-Anbieter (' . lc_cut($frei[0], 50) . ').' : 'Die angegebene Adresse gehört zu einer anderen Domain (' . lc_cut($adressen[0], 50) . ').')),
    !$adressen ? 'No email address found, not assessed.' : ($eigeneAdr ? 'Yes, e.g. ' . $eigeneAdr[0] . '.' : ($frei ? 'The address shown is at a free provider (' . lc_cut($frei[0], 50) . ').' : 'The address shown belongs to another domain (' . lc_cut($adressen[0], 50) . ').')));
} catch (Throwable $e) {
    $FEHLER[] = lc_cut($e->getMessage() . ' (Zeile ' . $e->getLine() . ')', 160);
}

/* ---------------- Zusammenfassung ---------------- */
$GEW = [1 => 5, 2 => 2, 3 => 1];
$GR = [];
foreach ($DATEN['gruppen'] as $gid => $gn) $GR[$gid] = ['id' => $gid, 'titel' => $EN ? $gn[1] : $gn[0], 'ok' => 0, 'n' => 0, 'gesamt' => 0, 'fehlt' => 0, 'na' => 0, 'np' => 0];
$liste = [];
$nr = 0;
foreach ($DATEN['punkte'] as $id => $p) {
    $nr++;
    $it = $ITEMS[$id] ?? ['id' => $id, 'gruppe' => $p['gruppe'], 'stufe' => $p['stufe'], 'titel' => $EN ? $p['en'] : $p['de'], 'ok' => null, 'status' => 'np',
        'detail' => $L('Konnte diesmal nicht geprüft werden.', 'Could not be checked this time.'), 'info' => $EN ? $p['info_en'] : $p['info_de'], 'leistung' => $EN ? $p['leistung_en'] : $p['leistung_de'], 'leistung_art' => $p['leistung']];
    $it['nr'] = $nr;
    $liste[] = $it;
}
$sum = 0; $max = 0; $best = 0; $gepr = 0;
$stu = [];
foreach ([1, 2, 3] as $k) $stu[$k] = ['ok' => 0, 'n' => 0, 'gesamt' => 0, 'fehlt' => 0, 'na' => 0, 'np' => 0];
$ZAHL = ['ok' => 0, 'fehlt' => 0, 'na' => 0, 'np' => 0];
$leist = [];
foreach ($liste as $it) {
    $GR[$it['gruppe']]['gesamt']++;
    $stu[$it['stufe']]['gesamt']++;
    $ZAHL[$it['status']]++;
    if ($it['status'] !== 'ok') { $GR[$it['gruppe']][$it['status']]++; $stu[$it['stufe']][$it['status']]++; }
    if ($it['ok'] === null) continue;
    $gepr++; $max += $GEW[$it['stufe']]; $GR[$it['gruppe']]['n']++; $stu[$it['stufe']]['n']++;
    if ($it['ok']) { $best++; $sum += $GEW[$it['stufe']]; $GR[$it['gruppe']]['ok']++; $stu[$it['stufe']]['ok']++; }
    else {
        $a = $it['leistung_art'];
        if (!isset($leist[$a])) { $pd = $DATEN['punkte'][$it['id']]; $leist[$a] = ['art' => $a, 'name' => $EN ? $pd['leistung_name_en'] : $pd['leistung_name_de'], 'text' => $it['leistung'], 'punkte' => [], 'gewicht' => 0]; }
        $leist[$a]['punkte'][] = $it['id'];
        $leist[$a]['gewicht'] += $GEW[$it['stufe']];
    }
}
usort($leist, function ($a, $b) { return $b['gewicht'] <=> $a['gewicht']; });
$punkte = $max ? (int)round($sum / $max * 100) : 0;
$res = [
    'ok' => true, 'version' => 4, 'modus' => $VOLL ? 'voll' : 'sofort', 'host' => $HOST, 'final' => $FINAL,
    'gesamt' => count($liste), 'geprueft' => $gepr, 'bestanden' => $best, 'punkte' => $punkte, 'gewichte' => $GEW,
    'fehlt' => $ZAHL['fehlt'], 'nicht_anwendbar' => $ZAHL['na'], 'nicht_pruefbar' => $ZAHL['np'],
    'stufen' => $stu, 'gruppen' => array_values($GR), 'items' => $liste, 'leistungen' => array_values($leist),
    'unterseiten' => count($SUB), 'dauer_ms' => (int)round((microtime(true) - $T0) * 1000), 'zeit' => gmdate('c'),
];
if ($httpsKaputt) $res['https_fehler'] = lc_cut((string)$httpsErr, 120);
if (count($ITEMS) !== 100 || count($liste) !== 100 || $FEHLER) $res['warnung'] = trim('Ausgewertet wurden ' . count($ITEMS) . ' Prüfpunkte. ' . implode(' ', $FEHLER));
lc_out($res);
