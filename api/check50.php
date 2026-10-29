<?php
/*
 * Lou Digital – 50-Punkte-Website-Check (Version 2, 27.10.2026)
 * POST url=…&sprache=de|en&modus=sofort|voll
 * Prüft nur, was jeder Besucher sieht: Antworten des Servers, Zertifikat, HTML der Startseite,
 * robots.txt, Sitemap, Fehlerseite, verlinkte Seiten und öffentliche DNS-Einträge. Keine Angriffe, keine Speicherung des Ergebnisses.
 * Schutz: nur öffentliche Adressen (keine internen Netze), feste IP je Abruf, Größen- und Zeitgrenzen, Begrenzung je Besucher.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
}
@set_time_limit(45);
ini_set('display_errors', '0');

const LC_UA = 'Mozilla/5.0 (compatible; LouDigital-Websitecheck/2.0; +https://lou-digital.de/website-check)';
const LC_MAX_BODY = 2500000;
define('LC_TEST', PHP_SAPI === 'cli' && getenv('LOU_CHECK_TEST') === '1');

function lc_out(array $a, int $code = 200): void
{
    if (PHP_SAPI !== 'cli') http_response_code($code);
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | (PHP_SAPI === 'cli' ? JSON_PRETTY_PRINT : 0));
    exit;
}

/* ---------------- Eingabe ---------------- */
$in = PHP_SAPI === 'cli' ? ['url' => $argv[1] ?? '', 'sprache' => $argv[2] ?? 'de', 'modus' => $argv[3] ?? 'sofort'] : $_POST;
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') lc_out(['ok' => false, 'error' => 'url'], 405);
$EN = (($in['sprache'] ?? 'de') === 'en');
$VOLL = (($in['modus'] ?? 'sofort') === 'voll');
$roh = trim((string)($in['url'] ?? ''));
if ($roh === '' || strlen($roh) > 200) lc_out(['ok' => false, 'error' => 'url'], 400);

/* ---------------- Begrenzung je Besucher (gekürzter Hash, höchstens 1 Stunde gespeichert) ---------------- */
function lc_limit(bool $voll): bool
{
    if (PHP_SAPI === 'cli') return true;
    $dir = dirname(__DIR__, 2) . '/check50-daten';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) { $dir = sys_get_temp_dir() . '/lou-check50'; @mkdir($dir, 0700, true); }
    $saltFile = $dir . '/salt-' . gmdate('Ymd');
    if (!is_file($saltFile)) { @file_put_contents($saltFile, bin2hex(random_bytes(16))); foreach (glob($dir . '/salt-*') ?: [] as $f) if ($f !== $saltFile) @unlink($f); }
    $salt = (string)@file_get_contents($saltFile);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $key = substr(hash('sha256', $salt . '|' . $ip), 0, 16);
    $now = time();
    $f = $dir . '/r-' . $key;
    $t = array_filter(array_map('intval', explode(',', (string)@file_get_contents($f))), function ($x) use ($now) { return $x > $now - 3600; });
    $max = $voll ? 6 : 15;
    if (count($t) >= $max) return false;
    $t[] = $now;
    @file_put_contents($f, implode(',', $t), LOCK_EX);
    // aufräumen: Dateien älter als 1 Stunde
    foreach (glob($dir . '/r-*') ?: [] as $g) if (@filemtime($g) < $now - 3600) @unlink($g);
    // Gesamtgrenze
    $gf = $dir . '/gesamt-' . gmdate('YmdH');
    $n = (int)@file_get_contents($gf) + 1;
    @file_put_contents($gf, (string)$n, LOCK_EX);
    foreach (glob($dir . '/gesamt-*') ?: [] as $g) if ($g !== $gf && @filemtime($g) < $now - 7200) @unlink($g);
    return $n <= 400;
}
if (!lc_limit($VOLL)) lc_out(['ok' => false, 'error' => 'rate'], 429);

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
        $p = strtolower($ip);
        if (strpos($p, 'fc') === 0 || strpos($p, 'fd') === 0 || strpos($p, 'fe80') === 0 || $p === '::1') return false;
    }
    return true;
}
$DNS_CACHE = [];
function lc_resolve(string $host): ?string
{
    global $DNS_CACHE;
    if (array_key_exists($host, $DNS_CACHE)) return $DNS_CACHE[$host];
    if (LC_TEST && getenv('LOU_CHECK_IP')) return $DNS_CACHE[$host] = getenv('LOU_CHECK_IP');
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
if (!lc_host_ok($HOST) && !LC_TEST) lc_out(['ok' => false, 'error' => 'url'], 400);
$BASE = preg_replace('/^www\./', '', $HOST);
$ALT = strpos($HOST, 'www.') === 0 ? $BASE : 'www.' . $HOST;
if (lc_resolve($HOST) === null) {
    $raw = @gethostbynamel($HOST);
    lc_out(['ok' => false, 'error' => $raw ? 'blocked' : 'dns'], 400);
}

/* ---------------- Abrufe ---------------- */
function lc_handle(string $url, string $method = 'GET', int $max = LC_MAX_BODY, int $timeout = 10)
{
    $pp = parse_url($url);
    $h = lc_norm_host($pp['host'] ?? '');
    $ip = $h !== '' ? lc_resolve($h) : null;
    if ($ip === null) return null;
    $port = isset($pp['port']) ? (int)$pp['port'] : (($pp['scheme'] ?? 'https') === 'http' ? 80 : 443);
    $ch = curl_init($url);
    $st = (object)['headers' => [], 'body' => '', 'cut' => false];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_CONNECTTIMEOUT => 5,
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
            elseif (strpos($l, ':') !== false) { [$k, $v] = explode(':', $l, 2); $st->headers[strtolower(trim($k))][] = trim($v); }
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
function lc_result($ch, $st, string $url): array
{
    $info = curl_getinfo($ch);
    $err = curl_errno($ch);
    if ($err === CURLE_WRITE_ERROR && $st->cut) $err = 0;
    $ver = defined('CURLINFO_HTTP_VERSION') ? (int)curl_getinfo($ch, CURLINFO_HTTP_VERSION) : 0;
    return ['url' => $url, 'status' => $err ? 0 : (int)$info['http_code'], 'err' => $err, 'errtxt' => $err ? curl_error($ch) : '', 'headers' => $st->headers,
        'body' => $st->body, 'cut' => $st->cut, 'ttfb' => (float)($info['starttransfer_time'] ?? 0), 'total' => (float)($info['total_time'] ?? 0),
        'ssl' => (int)($info['ssl_verify_result'] ?? 0), 'http2' => $ver >= 3, 'ver' => $ver];
}
/** mehrere Abrufe gleichzeitig */
function lc_multi(array $reqs): array
{
    $mh = curl_multi_init();
    $hs = [];
    foreach ($reqs as $k => $r) {
        $h = lc_handle($r[0], $r[1] ?? 'GET', $r[2] ?? LC_MAX_BODY, $r[3] ?? 10);
        if ($h === null) { $hs[$k] = null; continue; }
        $hs[$k] = $h;
        curl_multi_add_handle($mh, $h[0]);
    }
    do { $s = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 0.2); } while ($run && $s === CURLM_OK);
    $out = [];
    foreach ($reqs as $k => $r) {
        if ($hs[$k] === null) { $out[$k] = ['url' => $r[0], 'status' => 0, 'err' => -1, 'errtxt' => 'blocked', 'headers' => [], 'body' => '', 'cut' => false, 'ttfb' => 0, 'total' => 0, 'ssl' => 0, 'http2' => false, 'ver' => 0]; continue; }
        $out[$k] = lc_result($hs[$k][0], $hs[$k][1], $r[0]);
        curl_multi_remove_handle($mh, $hs[$k][0]);
        curl_close($hs[$k][0]);
    }
    curl_multi_close($mh);
    return $out;
}
function lc_cut(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n); }
function lc_hdr(array $r, string $k): string { return isset($r['headers'][$k]) ? (string)end($r['headers'][$k]) : ''; }
function lc_abs(string $base, string $rel): string
{
    $rel = trim($rel);
    if ($rel === '') return $base;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $rel)) return $rel;
    $b = parse_url($base);
    $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strpos($rel, '//') === 0) return $b['scheme'] . ':' . $rel;
    if ($rel[0] === '/') return $root . $rel;
    if ($rel[0] === '?' || $rel[0] === '#') return $root . ($b['path'] ?? '/') . $rel;
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $root . $dir . $rel;
}
/** Weiterleitungen einzeln verfolgen (jede Station wird neu geprüft) */
function lc_follow(string $url, int $hops = 6, string $method = 'GET'): array
{
    $chain = [];
    for ($i = 0; $i <= $hops; $i++) {
        $r = lc_multi([[$url, $method]])[0];
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

$T0 = microtime(true);
$https0 = 'https://' . $HOST . '/';
$r1 = lc_multi([
    'http' => ['http://' . $HOST . '/', 'GET', 200000, 8],
    'alt' => ['https://' . $ALT . '/', 'GET', 200000, 8],
    'robots' => ['https://' . $HOST . '/robots.txt', 'GET', 300000, 8],
    'p404' => ['https://' . $HOST . '/lou-check-' . bin2hex(random_bytes(4)) . '-nicht-vorhanden', 'GET', 300000, 8],
    'favicon' => ['https://' . $HOST . '/favicon.ico', 'GET', 300000, 8],
]);
$home = lc_follow($https0);
$httpsKaputt = $home['status'] === 0;
if ($httpsKaputt) {
    // kein HTTPS erreichbar: Startseite über http prüfen
    $homeHttp = lc_follow('http://' . $HOST . '/');
    if ($homeHttp['status'] === 0 || $homeHttp['status'] >= 500) lc_out(['ok' => false, 'error' => 'unreachable'], 400);
    $httpsErr = $home['errtxt'];
    $home = $homeHttp;
}
$FINAL = $home['chain'][count($home['chain']) - 1]['url'];
$FH = lc_norm_host((string)parse_url($FINAL, PHP_URL_HOST));
$HTML = (string)$home['body'];
$cs = '';
if (preg_match('/charset=["\']?([\w-]+)/i', lc_hdr($home, 'content-type'), $m)) $cs = strtolower($m[1]);
elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($HTML, 0, 4000), $m)) $cs = strtolower($m[1]);
if ($cs !== '' && $cs !== 'utf-8' && $cs !== 'utf8' && function_exists('mb_convert_encoding')) $HTML = (string)@mb_convert_encoding($HTML, 'UTF-8', $cs);
$RD = lc_regdom($HOST);

/* ---------------- HTML lesen ---------------- */
$dom = new DOMDocument();
libxml_use_internal_errors(true);
if ($HTML !== '') $dom->loadHTML('<?xml encoding="utf-8" ?>' . $HTML, LIBXML_NONET | LIBXML_COMPACT);
libxml_clear_errors();
$X = new DOMXPath($dom);
$q = function (string $xp) use ($X) { $n = $X->query($xp); return $n ? iterator_to_array($n) : []; };
$attr = function ($n, string $a): string { return $n instanceof DOMElement ? trim($n->getAttribute($a)) : ''; };
$meta = function (string $name) use ($q, $attr): string {
    foreach ($q('//meta') as $m) { $k = strtolower($attr($m, 'name') ?: $attr($m, 'property') ?: $attr($m, 'http-equiv')); if ($k === strtolower($name)) return $attr($m, 'content'); }
    return '';
};
$title = trim(preg_replace('/\s+/u', ' ', ($q('//title')[0] ?? null) ? $q('//title')[0]->textContent : ''));
$links = [];
foreach ($q('//a[@href]') as $a) {
    $href = $attr($a, 'href');
    if ($href === '' || preg_match('#^(javascript|data):#i', $href)) continue;
    $links[] = ['href' => $href, 'abs' => lc_abs($FINAL, $href), 'text' => trim(preg_replace('/\s+/u', ' ', $a->textContent)), 'el' => $a];
}
$findLink = function (string $re) use ($links) {
    foreach ($links as $l) if (preg_match($re, $l['text']) || preg_match($re, $l['href'])) return $l;
    return null;
};
$lImp = $findLink('/impressum|imprint|legal[\s_-]?notice|anbieterkennzeichnung/i');
$lDse = $findLink('/datenschutz|privacy|data[\s_-]?protection/i');
$lKon = $findLink('/kontakt|contact|anfrage/i');

/* ---------------- zweite Runde: Rechtstexte, interne Links, eine Datei für den Cache-Test, Sitemap ---------------- */
$intern = [];
foreach ($links as $l) {
    $pp = @parse_url($l['abs']);
    if (!$pp || empty($pp['host']) || !in_array($pp['scheme'] ?? '', ['http', 'https'], true)) continue;
    if (lc_regdom($pp['host']) !== lc_regdom($FH)) continue;
    $k = preg_replace('/#.*$/', '', $l['abs']);
    if ($k === $FINAL || isset($intern[$k])) continue;
    $intern[$k] = true;
}
$intern = array_slice(array_keys($intern), 0, $VOLL ? 30 : 10);
$asset = '';
foreach ($q('//link[@rel="stylesheet"][@href] | //script[@src]') as $n) {
    $s = lc_abs($FINAL, $attr($n, 'href') ?: $attr($n, 'src'));
    if (lc_regdom((string)parse_url($s, PHP_URL_HOST)) === lc_regdom($FH)) { $asset = $s; break; }
}
$robots = $r1['robots']['status'] === 200 ? (string)$r1['robots']['body'] : '';
$smUrl = '';
if (preg_match('/^\s*sitemap:\s*(\S+)/im', $robots, $m)) $smUrl = $m[1];
$req2 = ['sitemap' => [$smUrl !== '' ? $smUrl : 'https://' . $FH . '/sitemap.xml', 'GET', 400000, 8]];
if ($smUrl === '') $req2['sitemap2'] = ['https://' . $FH . '/sitemap_index.xml', 'GET', 200000, 8];
if ($lImp) $req2['imp'] = [$lImp['abs'], 'GET', 600000, 8];
if ($lDse) $req2['dse'] = [$lDse['abs'], 'GET', 800000, 8];
if ($asset !== '') $req2['asset'] = [$asset, 'GET', 400000, 8];
foreach ($intern as $i => $l) $req2['l' . $i] = [$l, 'GET', 150000, 8];
$r2 = lc_multi($req2);
// Weiterleitungen bei Rechtstexten auflösen
foreach (['imp', 'dse'] as $k) if (isset($r2[$k]) && $r2[$k]['status'] >= 300 && $r2[$k]['status'] < 400) $r2[$k] = lc_follow(lc_abs($r2[$k]['url'], lc_hdr($r2[$k], 'location')), 3);

/* ---------------- Zertifikat und DNS ---------------- */
$cert = null;
if (!$httpsKaputt) {
    $ip = lc_resolve($FH ?: $HOST);
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $FH ?: $HOST]]);
    $port = 443;
    $sock = $ip ? @stream_socket_client('ssl://' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip) . ':' . $port, $en, $es, 6, STREAM_CLIENT_CONNECT, $ctx) : false;
    if ($sock) {
        $pc = stream_context_get_params($sock)['options']['ssl']['peer_certificate'] ?? null;
        if ($pc) $cert = openssl_x509_parse($pc) ?: null;
        fclose($sock);
    }
}
$dnsName = $RD;
$txt = function (string $n): array { $o = []; foreach ((@dns_get_record($n, DNS_TXT) ?: []) as $r) $o[] = (string)($r['txt'] ?? implode('', $r['entries'] ?? [])); return $o; };
$mx = @dns_get_record($dnsName, DNS_MX) ?: [];
$spf = array_values(array_filter($txt($dnsName), function ($t) { return stripos($t, 'v=spf1') === 0; }));
$dmarc = array_values(array_filter($txt('_dmarc.' . $dnsName), function ($t) { return stripos($t, 'v=dmarc1') === 0; }));
$aaaa = @dns_get_record($FH ?: $HOST, DNS_AAAA) ?: [];

/* ---------------- Auswertung ---------------- */
$L = function (string $de, string $en) use ($EN) { return $EN ? $en : $de; };
$ITEMS = [];
$add = function (string $id, string $gruppe, int $stufe, string $titel, ?bool $ok, string $detail, string $tipp) use (&$ITEMS) {
    $ITEMS[] = ['id' => $id, 'gruppe' => $gruppe, 'stufe' => $stufe, 'titel' => $titel, 'ok' => $ok, 'detail' => $detail, 'warum' => $tipp];
};
$H = $home['headers'];
$isHttps = stripos($FINAL, 'https://') === 0;
$imgs = $q('//img');
$nImg = count($imgs);
$scriptsSrc = array_map(function ($n) use ($attr) { return ['src' => $attr($n, 'src'), 'type' => strtolower($attr($n, 'type'))]; }, $q('//script[@src]'));
$iframes = array_map(function ($n) use ($attr) { return $attr($n, 'src'); }, $q('//iframe'));
$styleLinks = array_map(function ($n) use ($attr) { return $attr($n, 'href'); }, $q('//link[@href]'));
$inlineAll = strtolower($HTML);
$csp = lc_hdr($home, 'content-security-policy') ?: $meta('content-security-policy');

/* A Sicherheit */
$sslOk = !$httpsKaputt && $isHttps && $home['ssl'] === 0;
$add('https', 'sicherheit', 1, $L('Verschlüsselt über HTTPS mit gültigem Zertifikat', 'Encrypted via HTTPS with a valid certificate'), $sslOk,
    $sslOk ? $L('Die Seite lädt verschlüsselt, das Zertifikat ist gültig.', 'The site loads encrypted and the certificate is valid.')
        : ($httpsKaputt ? $L('Über HTTPS ist die Seite nicht erreichbar oder das Zertifikat ist ungültig.', 'The site is not reachable via HTTPS or the certificate is invalid.') : $L('Die Seite endet nicht auf einer HTTPS-Adresse.', 'The site does not end on an HTTPS address.')),
    $L('Ohne gültiges Zertifikat warnen Browser Ihre Besucher mit „Nicht sicher“. Ein kostenloses Let’s-Encrypt-Zertifikat behebt das meist in Minuten.', 'Without a valid certificate, browsers warn visitors with “Not secure”. A free Let’s Encrypt certificate usually fixes this in minutes.'));
$hr = $r1['http'];
$loc = lc_hdr($hr, 'location');
$redirOk = in_array($hr['status'], [301, 308], true) && stripos(lc_abs('http://' . $HOST . '/', $loc), 'https://') === 0;
$add('http_https', 'sicherheit', 1, $L('Unverschlüsselte Adresse leitet dauerhaft auf HTTPS', 'Unencrypted address redirects permanently to HTTPS'), $redirOk,
    $redirOk ? $L('http:// leitet mit ' . $hr['status'] . ' auf https:// weiter.', 'http:// redirects to https:// with ' . $hr['status'] . '.')
        : ($hr['status'] >= 300 && $hr['status'] < 400 ? $L('Es gibt eine Weiterleitung, aber nur vorübergehend (' . $hr['status'] . ') oder nicht auf HTTPS.', 'There is a redirect, but only temporary (' . $hr['status'] . ') or not to HTTPS.') : $L('Die Adresse mit http:// bleibt unverschlüsselt (Status ' . $hr['status'] . ').', 'The http:// address stays unencrypted (status ' . $hr['status'] . ').')),
    $L('Eine dauerhafte Weiterleitung (301) sorgt dafür, dass alle Besucher und Google immer die sichere Seite sehen.', 'A permanent redirect (301) ensures visitors and Google always see the secure page.'));
$days = $cert && isset($cert['validTo_time_t']) ? (int)floor(($cert['validTo_time_t'] - time()) / 86400) : null;
$add('zertifikat', 'sicherheit', 2, $L('Zertifikat noch mindestens 21 Tage gültig', 'Certificate valid for at least 21 more days'), $days === null ? null : $days >= 21,
    $days === null ? $L('Zertifikat konnte nicht gelesen werden.', 'Certificate could not be read.') : $L('Noch ' . $days . ' Tage gültig' . (!empty($cert['issuer']['O']) ? ' (' . $cert['issuer']['O'] . ')' : '') . '.', 'Valid for ' . $days . ' more days' . (!empty($cert['issuer']['O']) ? ' (' . $cert['issuer']['O'] . ')' : '') . '.'),
    $L('Läuft das Zertifikat ab, ist die Seite für Besucher gesperrt. Automatische Verlängerung einrichten.', 'If the certificate expires, the site is blocked for visitors. Set up automatic renewal.'));
$hsts = lc_hdr($home, 'strict-transport-security');
$hstsAge = preg_match('/max-age=(\d+)/i', $hsts, $m) ? (int)$m[1] : 0;
$add('hsts', 'sicherheit', 2, $L('Browser merken sich die sichere Verbindung (HSTS)', 'Browsers remember the secure connection (HSTS)'), $isHttps ? $hstsAge >= 2592000 : false,
    $hsts !== '' ? $L('HSTS ist gesetzt (max-age ' . $hstsAge . ').', 'HSTS is set (max-age ' . $hstsAge . ').') : $L('Kein HSTS-Header.', 'No HSTS header.'),
    $L('Mit HSTS verbindet sich der Browser immer verschlüsselt, auch wenn jemand http:// eintippt.', 'With HSTS the browser always connects encrypted, even if someone types http://.'));
$mixed = [];
if ($isHttps) foreach ($q('//img[@src] | //script[@src] | //link[@rel="stylesheet"][@href] | //iframe[@src] | //source[@src] | //video[@src] | //audio[@src]') as $n) {
    $s = $attr($n, 'src') ?: $attr($n, 'href');
    if (stripos($s, 'http://') === 0) $mixed[] = $s;
}
$add('gemischt', 'sicherheit', 2, $L('Kein gemischter Inhalt (alle Dateien verschlüsselt)', 'No mixed content (all files encrypted)'), $isHttps ? !$mixed : null,
    $mixed ? $L(count($mixed) . ' Datei(en) werden unverschlüsselt geladen, z. B. ' . lc_cut($mixed[0], 80), count($mixed) . ' file(s) load unencrypted, e.g. ' . lc_cut($mixed[0], 80)) : $L('Alle Bilder, Skripte und Stile laden über HTTPS.', 'All images, scripts and styles load via HTTPS.'),
    $L('Unverschlüsselte Bilder oder Skripte machen das Schloss im Browser kaputt und werden teils blockiert.', 'Unencrypted images or scripts break the padlock and are partly blocked.'));
$nos = strtolower(lc_hdr($home, 'x-content-type-options')) === 'nosniff';
$add('nosniff', 'sicherheit', 2, $L('Schutz vor falsch erkannten Dateitypen', 'Protection against misdetected file types'), $nos,
    $nos ? 'X-Content-Type-Options: nosniff' : $L('Header X-Content-Type-Options fehlt.', 'X-Content-Type-Options header missing.'),
    $L('Ein einzeiliger Server-Header, der eine ganze Klasse von Angriffen verhindert.', 'A one-line server header that prevents a whole class of attacks.'));
$xfo = strtoupper(lc_hdr($home, 'x-frame-options'));
$frameOk = in_array($xfo, ['DENY', 'SAMEORIGIN'], true) || stripos($csp, 'frame-ancestors') !== false;
$add('clickjacking', 'sicherheit', 2, $L('Seite kann nicht in fremde Seiten eingebettet werden', 'Site cannot be embedded in other sites'), $frameOk,
    $frameOk ? $L('Einbetten ist begrenzt (X-Frame-Options oder CSP).', 'Embedding is restricted (X-Frame-Options or CSP).') : $L('Keine Begrenzung gefunden.', 'No restriction found.'),
    $L('Sonst kann eine fremde Seite Ihre Seite unsichtbar einbinden und Klicks abgreifen (Clickjacking).', 'Otherwise a foreign site can embed yours invisibly and hijack clicks (clickjacking).'));
$add('csp', 'sicherheit', 3, $L('Sicherheitsregeln für Inhalte (Content-Security-Policy)', 'Content security rules (Content-Security-Policy)'), $csp !== '',
    $csp !== '' ? $L('Eine Content-Security-Policy ist gesetzt.', 'A Content-Security-Policy is set.') : $L('Keine Content-Security-Policy.', 'No Content-Security-Policy.'),
    $L('Legt fest, woher Skripte und Bilder kommen dürfen, und bremst eingeschleusten Schadcode aus.', 'Defines where scripts and images may come from and stops injected malicious code.'));
$ref = lc_hdr($home, 'referrer-policy') ?: $meta('referrer');
$add('referrer', 'sicherheit', 3, $L('Sparsame Weitergabe der Herkunftsadresse', 'Limited sharing of the referring address'), $ref !== '',
    $ref !== '' ? 'Referrer-Policy: ' . $ref : $L('Keine Referrer-Policy.', 'No Referrer-Policy.'),
    $L('Verhindert, dass vollständige Adressen (mit Parametern) an fremde Seiten gehen.', 'Prevents full addresses (with parameters) being passed to other sites.'));
$srv = lc_hdr($home, 'server') . ' ' . lc_hdr($home, 'x-powered-by');
$gen = $meta('generator');
$versLeak = (bool)preg_match('#(apache|nginx|php|iis|litespeed|express|openresty)[/ ]?\d#i', $srv) || (bool)preg_match('/\d+\.\d+/', $gen);
$add('versionen', 'sicherheit', 3, $L('Keine Softwareversionen öffentlich sichtbar', 'No software versions publicly visible'), !$versLeak,
    $versLeak ? $L('Sichtbar: ' . trim(lc_cut(trim($srv . ' ' . $gen), 90)), 'Visible: ' . trim(lc_cut(trim($srv . ' ' . $gen), 90))) : $L('Server und System verraten keine Versionsnummern.', 'Server and system do not reveal version numbers.'),
    $L('Versionsnummern zeigen Angreifern, welche bekannten Lücken sie probieren können.', 'Version numbers show attackers which known flaws to try.'));

/* B Recht & Datenschutz */
$impOk = $lImp && isset($r2['imp']) && $r2['imp']['status'] === 200;
$impTxt = $impOk ? strtolower(strip_tags((string)$r2['imp']['body'])) : '';
$impDetail = $lImp ? ($impOk ? $L('Verlinkt und erreichbar.', 'Linked and reachable.') : $L('Verlinkt, aber nicht erreichbar (Status ' . ($r2['imp']['status'] ?? 0) . ').', 'Linked, but not reachable (status ' . ($r2['imp']['status'] ?? 0) . ').')) : $L('Kein Link zum Impressum auf der Startseite gefunden.', 'No link to the legal notice found on the homepage.');
if ($impOk && strpos($impTxt, 'tmg') !== false && strpos($impTxt, 'ddg') === false) $impDetail .= $L(' Hinweis: nennt noch das alte TMG, seit 2024 gilt § 5 DDG.', ' Note: still cites the old TMG; § 5 DDG applies since 2024.');
$add('impressum', 'recht', 1, $L('Impressum verlinkt und erreichbar', 'Legal notice linked and reachable'), (bool)$impOk, $impDetail,
    $L('Für geschäftliche Websites in Deutschland Pflicht und mit einem Klick erreichbar. Fehlt es, drohen Abmahnungen.', 'Mandatory for business websites in Germany and one click away. If missing, cease-and-desist letters are likely.'));
$dseOk = $lDse && isset($r2['dse']) && $r2['dse']['status'] === 200;
$add('datenschutz', 'recht', 1, $L('Datenschutzerklärung verlinkt und erreichbar', 'Privacy policy linked and reachable'), (bool)$dseOk,
    $lDse ? ($dseOk ? $L('Verlinkt und erreichbar.', 'Linked and reachable.') : $L('Verlinkt, aber nicht erreichbar.', 'Linked, but not reachable.')) : $L('Kein Link zur Datenschutzerklärung gefunden.', 'No link to the privacy policy found.'),
    $L('Nach DSGVO Pflicht, sobald Daten verarbeitet werden, also bei jeder Website.', 'Mandatory under GDPR as soon as data is processed, i.e. on every website.'));
$dritte = [];
$muster = ['fonts.googleapis.com' => 'Google Fonts', 'fonts.gstatic.com' => 'Google Fonts', 'googletagmanager.com' => 'Google Tag Manager', 'google-analytics.com' => 'Google Analytics',
    'connect.facebook.net' => 'Meta Pixel', 'youtube.com/embed' => 'YouTube', 'google.com/maps' => 'Google Maps', 'maps.google' => 'Google Maps', 'maps.googleapis.com' => 'Google Maps',
    'static.hotjar.com' => 'Hotjar', 'snap.licdn.com' => 'LinkedIn Insight', 'analytics.tiktok.com' => 'TikTok Pixel', 'clarity.ms' => 'Microsoft Clarity', 'use.typekit.net' => 'Adobe Fonts', 'player.vimeo.com' => 'Vimeo'];
$blockiert = function (string $type) { return $type !== '' && !in_array($type, ['text/javascript', 'application/javascript', 'module'], true); };
foreach ($scriptsSrc as $s) foreach ($muster as $k => $v) if (stripos($s['src'], $k) !== false && !$blockiert($s['type'])) $dritte[$v] = true;
foreach (array_merge($iframes, $styleLinks) as $s) foreach ($muster as $k => $v) if (stripos($s, $k) !== false) $dritte[$v] = true;
foreach (['@import url(https://fonts.googleapis' => 'Google Fonts', "gtag('config'" => 'Google Analytics', 'fbq(\'init\'' => 'Meta Pixel'] as $k => $v) if (stripos($HTML, $k) !== false) $dritte[$v] = true;
$add('dritte', 'recht', 1, $L('Keine fremden Dienste ohne Einwilligung', 'No third-party services without consent'), !$dritte,
    $dritte ? $L('Lädt direkt beim Aufruf: ' . implode(', ', array_keys($dritte)) . '.', 'Loads right away: ' . implode(', ', array_keys($dritte)) . '.') : $L('Beim Aufruf werden keine bekannten Dienste wie Google Fonts oder Analytics geladen.', 'No known services like Google Fonts or Analytics load on arrival.'),
    $L('Werden Google Fonts, Analytics oder Videos ohne Zustimmung geladen, geht die IP-Adresse an Dritte. Dafür gab es schon Abmahnwellen. Lösung: Schriften lokal einbinden und Dienste erst nach Einwilligung laden.', 'If Google Fonts, analytics or videos load without consent, the IP address goes to third parties. This has triggered waves of legal warnings. Fix: host fonts locally and load services only after consent.'));
$cmp = (bool)preg_match('/cookiebot|usercentrics|borlabs|complianz|klaro|consentmanager|ccm19|cookieyes|onetrust|iubenda|real-cookie-banner|cookie-script|cookiefirst|didomi|termly|osano|quantcast|cookie-law-info|moove_gdpr|cookie-notice/i', $HTML);
$tracker = (bool)array_intersect(array_keys($dritte), ['Google Analytics', 'Google Tag Manager', 'Meta Pixel', 'Hotjar', 'LinkedIn Insight', 'TikTok Pixel', 'Microsoft Clarity']) || (bool)preg_match('/googletagmanager|google-analytics|fbevents|hotjar|clarity\.ms/i', $HTML);
$add('einwilligung', 'recht', 2, $L('Einwilligung vor Tracking', 'Consent before tracking'), $tracker ? $cmp : true,
    $tracker ? ($cmp ? $L('Tracking erkannt, ein Einwilligungstool ist eingebunden.', 'Tracking detected; a consent tool is present.') : $L('Tracking erkannt, aber kein Einwilligungstool gefunden.', 'Tracking detected, but no consent tool found.')) : $L('Kein Tracking gefunden, ein Banner ist dafür nicht nötig.', 'No tracking found, so no banner is needed for it.'),
    $L('Analyse- und Werbedienste brauchen vorher eine Zustimmung, sonst drohen Bußgelder und Abmahnungen.', 'Analytics and advertising services need prior consent, otherwise fines and warnings are possible.'));
$cookies = $home['headers']['set-cookie'] ?? [];
$trackCookies = array_filter($cookies, function ($c) { return (bool)preg_match('/^(_ga|_gid|_gcl|_fbp|_hj|hubspotutk|_clck|_uet|ajs_)/i', $c); });
$add('cookies', 'recht', 3, $L('Keine Werbe-Cookies vor der Zustimmung', 'No advertising cookies before consent'), !$trackCookies,
    $cookies ? ($trackCookies ? $L('Setzt sofort Tracking-Cookies.', 'Sets tracking cookies right away.') : $L('Setzt nur ' . count($cookies) . ' technische(s) Cookie(s).', 'Sets only ' . count($cookies) . ' technical cookie(s).')) : $L('Die Startseite setzt keine Cookies.', 'The homepage sets no cookies.'),
    $L('Tracking-Cookies dürfen erst nach einer Einwilligung gesetzt werden.', 'Tracking cookies may only be set after consent.'));
$formHttp = 0; $nForm = 0;
foreach ($q('//form') as $f) { $nForm++; if (stripos($attr($f, 'action'), 'http://') === 0) $formHttp++; }
$add('formulare', 'recht', 3, $L('Formulare senden verschlüsselt', 'Forms submit encrypted'), $nForm ? (!$formHttp && $isHttps) : null,
    $nForm ? ($formHttp ? $L($formHttp . ' Formular(e) senden über http://.', $formHttp . ' form(s) submit via http://.') : $L('Alle Formulare senden verschlüsselt.', 'All forms submit encrypted.')) : $L('Kein Formular auf der Startseite.', 'No form on the homepage.'),
    $L('Eingaben wie Name und Telefonnummer müssen verschlüsselt übertragen werden.', 'Entries such as name and phone number must be transmitted encrypted.'));

/* C Adresse & Erreichbarkeit */
$alt = $r1['alt'];
$altLoc = $alt['status'] >= 300 && $alt['status'] < 400 ? lc_norm_host((string)parse_url(lc_abs('https://' . $ALT . '/', lc_hdr($alt, 'location')), PHP_URL_HOST)) : '';
$altGibt = lc_resolve($ALT) !== null;
$altOk = !$altGibt ? null : (($altLoc !== '' && $altLoc === $FH) || ($ALT === $FH));
$altKaputt = $altGibt && $alt['status'] === 0;
$fremd = lc_regdom($FH) !== $RD;
$einOk = !$fremd && ($altOk !== false);
$add('eine_adresse', 'adresse', 1, $L('Eine eindeutige Adresse (mit und ohne www)', 'One clear address (with and without www)'), $einOk,
    $fremd ? $L('Die Startseite landet auf einer anderen Domain: ' . $FH, 'The homepage ends up on another domain: ' . $FH)
        : ($altOk === null ? $L($ALT . ' gibt es nicht, ' . $FH . ' ist die einzige Adresse.', $ALT . ' does not exist; ' . $FH . ' is the only address.')
            : ($altOk ? $L($ALT . ' leitet auf ' . $FH . ' weiter.', $ALT . ' redirects to ' . $FH . '.') : ($altKaputt ? $L($ALT . ' ist nicht sauber erreichbar (Zertifikat oder Server).', $ALT . ' is not cleanly reachable (certificate or server).') : $L($ALT . ' und ' . $FH . ' zeigen getrennt Inhalte (Status ' . $alt['status'] . ').', $ALT . ' and ' . $FH . ' show content separately (status ' . $alt['status'] . ').')))),
    $L('Zwei gleiche Seiten unter verschiedenen Adressen verwirren Google und Besucher. Eine Variante wählen und die andere dauerhaft weiterleiten.', 'Two identical sites under different addresses confuse Google and visitors. Pick one and permanently redirect the other.'));
$hops = count($home['chain']) - 1;
$add('kette', 'adresse', 2, $L('Kurze Weiterleitungskette', 'Short redirect chain'), $hops <= 2,
    $hops === 0 ? $L('Die Startseite lädt direkt ohne Umweg.', 'The homepage loads directly.') : $L($hops . ' Weiterleitung(en) bis zur Startseite.', $hops . ' redirect(s) to the homepage.'),
    $L('Jede Weiterleitung kostet Zeit. Mehr als zwei deuten auf eine unsaubere Einrichtung hin.', 'Each redirect costs time. More than two suggest a messy setup.'));
$bau = '/(wixsite\.com|jimdo(site|free)\.com|webnode\.|business\.site|netlify\.app|vercel\.app|github\.io|herokuapp\.com|webflow\.io|squarespace\.com|wordpress\.com|blogspot\.|myshopify\.com|ionos\.de\/|strato-hosting|\.local\b|localhost|staging\.|dev\.|test\.|\/\/\d{1,3}(\.\d{1,3}){3})/i';
$fremdeAdr = [];
foreach ([['canonical', ($q('//link[@rel="canonical"]')[0] ?? null)], ['og:url', null]] as $c) {
    $v = $c[0] === 'canonical' ? $attr($c[1], 'href') : $meta('og:url');
    if ($v !== '' && (preg_match($bau, $v) || lc_regdom((string)parse_url(lc_abs($FINAL, $v), PHP_URL_HOST)) !== lc_regdom($FH))) $fremdeAdr[] = $c[0] . ': ' . lc_cut($v, 70);
}
foreach ($home['chain'] as $c) if (preg_match($bau, $c['url'])) $fremdeAdr[] = lc_cut($c['url'], 70);
foreach ($links as $l) if (preg_match($bau, $l['abs']) && count($fremdeAdr) < 4) $fremdeAdr[] = 'Link: ' . lc_cut($l['abs'], 70);
$add('fremde_adressen', 'adresse', 2, $L('Keine fremden oder Baukasten-Adressen sichtbar', 'No foreign or site-builder addresses visible'), !$fremdeAdr,
    $fremdeAdr ? $L('Gefunden: ' . implode('; ', array_slice($fremdeAdr, 0, 3)), 'Found: ' . implode('; ', array_slice($fremdeAdr, 0, 3))) : $L('Startseite, Canonical und Links zeigen nur auf Ihre Domain.', 'Homepage, canonical and links point only to your domain.'),
    $L('Test-, Baukasten- oder IP-Adressen wirken unprofessionell und können Besucher an falsche Orte schicken.', 'Test, site-builder or IP addresses look unprofessional and can send visitors to the wrong place.'));
$p404 = $r1['p404'];
$is404 = in_array($p404['status'], [404, 410], true);
$add('fehlerseite', 'adresse', 2, $L('Fehlerseite liefert einen echten Fehlercode', 'Error page returns a real error code'), $is404,
    $is404 ? $L('Unbekannte Adressen melden ' . $p404['status'] . '.', 'Unknown addresses return ' . $p404['status'] . '.') : $L('Unbekannte Adressen melden ' . $p404['status'] . ($p404['status'] >= 300 && $p404['status'] < 400 ? ' (Weiterleitung)' : '') . '.', 'Unknown addresses return ' . $p404['status'] . ($p404['status'] >= 300 && $p404['status'] < 400 ? ' (redirect)' : '') . '.'),
    $L('Sonst nimmt Google leere Fehlerseiten in den Index auf und Besucher landen ohne Hinweis auf der Startseite.', 'Otherwise Google indexes empty error pages and visitors land on the homepage without explanation.'));
$kaputt = [];
$geprueft = 0;
foreach ($intern as $i => $l) {
    $s = $r2['l' . $i]['status'] ?? 0;
    $geprueft++;
    if ($s === 0 || $s === 404 || $s === 410 || $s >= 500) $kaputt[] = preg_replace('#^https?://[^/]+#', '', $l) . ' (' . ($s ?: $L('keine Antwort', 'no response')) . ')';
}
$add('links', 'adresse', 2, $L('Keine kaputten Links', 'No broken links'), $geprueft ? !$kaputt : null,
    $geprueft ? ($kaputt ? $L(count($kaputt) . ' von ' . $geprueft . ' geprüften Links führen ins Leere: ' . implode(', ', array_slice($kaputt, 0, 3)), count($kaputt) . ' of ' . $geprueft . ' checked links are broken: ' . implode(', ', array_slice($kaputt, 0, 3))) : $L($geprueft . ' interne Links geprüft, alle erreichbar.', $geprueft . ' internal links checked, all reachable.')) : $L('Keine internen Links gefunden.', 'No internal links found.'),
    $L('Kaputte Links ärgern Besucher und kosten Vertrauen bei Google.', 'Broken links annoy visitors and cost trust with Google.'));
$add('ipv6', 'adresse', 3, $L('Auch über IPv6 erreichbar', 'Also reachable via IPv6'), (bool)$aaaa,
    $aaaa ? $L('IPv6-Adresse vorhanden.', 'IPv6 address present.') : $L('Keine IPv6-Adresse (AAAA-Eintrag).', 'No IPv6 address (AAAA record).'),
    $L('Viele Mobilfunknetze nutzen IPv6. Mit AAAA-Eintrag lädt die Seite dort ohne Umweg.', 'Many mobile networks use IPv6. With an AAAA record the site loads there directly.'));
$httpLinks = array_filter($links, function ($l) { return stripos($l['href'], 'http://') === 0; });
$add('links_http', 'adresse', 3, $L('Links führen auf verschlüsselte Seiten', 'Links point to encrypted pages'), !$httpLinks,
    $httpLinks ? $L(count($httpLinks) . ' Link(s) mit http://, z. B. ' . lc_cut(array_values($httpLinks)[0]['href'], 70), count($httpLinks) . ' link(s) with http://, e.g. ' . lc_cut(array_values($httpLinks)[0]['href'], 70)) : $L('Alle Links nutzen https:// oder relative Adressen.', 'All links use https:// or relative addresses.'),
    $L('Links auf http:// schicken Besucher auf unverschlüsselte Seiten, Browser warnen dann.', 'Links to http:// send visitors to unencrypted pages and browsers warn.'));

/* D Mobil & Darstellung */
$vp = strtolower($meta('viewport'));
$vpOk = strpos($vp, 'width=device-width') !== false;
$add('viewport', 'mobil', 1, $L('Für Handys eingerichtet (Viewport)', 'Set up for phones (viewport)'), $vpOk,
    $vpOk ? $L('Die Seite passt sich der Bildschirmbreite an.', 'The site adapts to the screen width.') : $L('Kein passender Viewport: auf dem Handy erscheint eine verkleinerte Desktop-Seite.', 'No suitable viewport: phones show a shrunken desktop page.'),
    $L('Über die Hälfte der Besucher kommt mit dem Handy. Ohne mobile Ansicht ist Text winzig und Knöpfe überlappen.', 'Over half of visitors use phones. Without a mobile view, text is tiny and buttons overlap.'));
$zoomAus = strpos($vp, 'user-scalable=no') !== false || strpos($vp, 'user-scalable=0') !== false || (preg_match('/maximum-scale=([\d.]+)/', $vp, $m) && (float)$m[1] < 2);
$add('zoom', 'mobil', 2, $L('Vergrößern auf dem Handy erlaubt', 'Zooming allowed on phones'), $vp === '' ? null : !$zoomAus,
    $zoomAus ? $L('Das Vergrößern mit zwei Fingern ist gesperrt.', 'Pinch zoom is disabled.') : $L('Besucher können vergrößern.', 'Visitors can zoom.'),
    $L('Gesperrtes Zoomen ist eine Barriere für alle, die schlecht sehen.', 'Disabled zoom is a barrier for anyone with poor eyesight.'));
$ohneMass = array_filter($imgs, function ($i) use ($attr) { return ($attr($i, 'width') === '' || $attr($i, 'height') === '') && stripos($attr($i, 'style'), 'aspect-ratio') === false; });
$quote = $nImg ? 1 - count($ohneMass) / $nImg : 1;
$add('bildmasse', 'mobil', 2, $L('Nichts verrutscht beim Laden (Bildmaße angegeben)', 'Nothing shifts while loading (image sizes set)'), $nImg ? $quote >= 0.8 : null,
    $nImg ? $L(round($quote * 100) . ' % der ' . $nImg . ' Bilder haben feste Maße.', round($quote * 100) . '% of ' . $nImg . ' images have fixed dimensions.') : $L('Keine Bilder im HTML.', 'No images in the HTML.'),
    $L('Ohne Breite und Höhe springt der Text beim Laden, Besucher tippen daneben. Das wertet auch Google ab.', 'Without width and height, text jumps while loading and visitors tap the wrong thing. Google also penalises this.'));
$fav = count($q('//link[contains(@rel,"icon")]')) > 0 || ($r1['favicon']['status'] === 200 && strlen((string)$r1['favicon']['body']) > 50);
$add('favicon', 'mobil', 3, $L('Eigenes Symbol im Browser-Tab (Favicon)', 'Own icon in the browser tab (favicon)'), $fav,
    $fav ? $L('Favicon vorhanden.', 'Favicon present.') : $L('Kein Favicon gefunden.', 'No favicon found.'),
    $L('Das Symbol erscheint im Tab, in Lesezeichen und bei Google neben Ihrem Eintrag.', 'The icon appears in tabs, bookmarks and next to your Google listing.'));
$lazy = count(array_filter($imgs, function ($i) use ($attr) { return strtolower($attr($i, 'loading')) === 'lazy' || $attr($i, 'srcset') !== '' || preg_match('/\.(webp|avif)(\?|$)/i', $attr($i, 'src')); })) + count($q('//picture'));
$add('bilder_modern', 'mobil', 3, $L('Bilder modern oder bei Bedarf geladen', 'Images modern or loaded on demand'), $nImg >= 4 ? $lazy >= 1 : null,
    $nImg >= 4 ? ($lazy ? $L('Moderne Formate, srcset oder Lazy Loading im Einsatz.', 'Modern formats, srcset or lazy loading in use.') : $L('Alle ' . $nImg . ' Bilder laden sofort in voller Größe.', 'All ' . $nImg . ' images load immediately at full size.')) : $L('Wenige Bilder, nicht relevant.', 'Few images, not relevant.'),
    $L('WebP und Lazy Loading sparen auf dem Handy oft die Hälfte der Ladezeit.', 'WebP and lazy loading often save half the loading time on phones.'));
$starr = 0;
foreach ($q('//*[@style]') as $n) if (preg_match('/(?:^|;)\s*(?:min-)?width\s*:\s*(\d{3,4})px/i', $attr($n, 'style'), $m) && (int)$m[1] >= 600) $starr++;
foreach ($q('//table[@width]') as $n) if ((int)$attr($n, 'width') >= 600) $starr++;
$add('starre_breiten', 'mobil', 3, $L('Nichts ragt über den Handy-Rand', 'Nothing sticks out on phones'), $starr === 0,
    $starr ? $L($starr . ' Element(e) mit fester Breite ab 600 Pixeln.', $starr . ' element(s) with fixed width of 600 px or more.') : $L('Keine starren Breiten im HTML gefunden.', 'No rigid widths found in the HTML.'),
    $L('Feste Breiten erzwingen seitliches Wischen und lassen Inhalte überlappen.', 'Fixed widths force sideways scrolling and make content overlap.'));

/* E Google & Sichtbarkeit */
$tl = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
$generisch = (bool)preg_match('/^(home|startseite|willkommen|welcome|index|untitled|neue seite|meine website|my (wordpress )?site|just another wordpress site)$/iu', $title);
$add('titel', 'google', 1, $L('Aussagekräftiger Seitentitel', 'Meaningful page title'), $tl >= 15 && $tl <= 65 && !$generisch,
    $title !== '' ? $L('„' . lc_cut($title, 70) . '“ (' . $tl . ' Zeichen).', '“' . lc_cut($title, 70) . '” (' . $tl . ' characters).') : $L('Kein Seitentitel.', 'No page title.'),
    $L('Der Titel ist die blaue Zeile bei Google. Er sollte sagen, was Sie anbieten und wo, in 30 bis 60 Zeichen.', 'The title is the blue line on Google. It should say what you offer and where, in 30 to 60 characters.'));
$desc = $meta('description');
$dl = function_exists('mb_strlen') ? mb_strlen($desc) : strlen($desc);
$add('beschreibung', 'google', 2, $L('Beschreibung für Google', 'Description for Google'), $dl >= 50 && $dl <= 170,
    $desc !== '' ? $L($dl . ' Zeichen.', $dl . ' characters.') : $L('Keine Meta-Beschreibung.', 'No meta description.'),
    $L('Der graue Text unter dem Titel. Gut formuliert bringt er mehr Klicks, ideal 120 bis 160 Zeichen.', 'The grey text under the title. Well written it brings more clicks, ideally 120 to 160 characters.'));
$h1 = count($q('//h1'));
$add('h1', 'google', 2, $L('Genau eine Hauptüberschrift (H1)', 'Exactly one main heading (H1)'), $h1 === 1,
    $L($h1 . ' H1-Überschrift(en).', $h1 . ' H1 heading(s).'),
    $L('Eine klare Hauptüberschrift zeigt Google und Vorlesehilfen, worum es auf der Seite geht.', 'One clear main heading shows Google and screen readers what the page is about.'));
$noindex = stripos($meta('robots') . ' ' . lc_hdr($home, 'x-robots-tag'), 'noindex') !== false;
$robSperre = false; $ua = []; $neuGruppe = true;
foreach (preg_split('/\R/', $robots) as $zeile) {
    $zeile = trim(preg_replace('/#.*/', '', $zeile));
    if (!preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $zeile, $m)) continue;
    $k = strtolower($m[1]); $v = trim($m[2]);
    if ($k === 'user-agent') { if ($neuGruppe) $ua = []; $ua[] = strtolower($v); $neuGruppe = false; continue; }
    $neuGruppe = true;
    if ($k === 'disallow' && $v === '/' && in_array('*', $ua, true)) $robSperre = true;
}
$add('indexierbar', 'google', 2, $L('Für Google freigegeben', 'Open to Google'), !$noindex && !$robSperre,
    $noindex ? $L('Die Startseite ist auf „noindex“ gestellt.', 'The homepage is set to “noindex”.') : ($robSperre ? $L('robots.txt sperrt die ganze Seite.', 'robots.txt blocks the whole site.') : $L('Google darf die Seite aufnehmen.', 'Google may index the site.')),
    $L('Eine gesperrte Seite erscheint bei Google gar nicht. Passiert oft, wenn nach dem Relaunch die Sperre vergessen wird.', 'A blocked site does not appear on Google at all. Often happens when the block is forgotten after a relaunch.'));
$sm = $r2['sitemap']; $smOk = $sm['status'] === 200 && (stripos((string)$sm['body'], '<urlset') !== false || stripos((string)$sm['body'], '<sitemapindex') !== false);
if (!$smOk && isset($r2['sitemap2'])) $smOk = $r2['sitemap2']['status'] === 200 && stripos((string)$r2['sitemap2']['body'], '<sitemap') !== false;
$smN = $smOk ? substr_count(strtolower((string)$sm['body']), '<loc>') : 0;
$add('sitemap', 'google', 2, $L('Sitemap für Google vorhanden', 'Sitemap for Google present'), $smOk,
    $smOk ? $L('Sitemap gefunden' . ($smN ? ' (' . $smN . ' Einträge)' : '') . '.', 'Sitemap found' . ($smN ? ' (' . $smN . ' entries)' : '') . '.') : $L('Keine Sitemap unter den üblichen Adressen.', 'No sitemap at the usual addresses.'),
    $L('Die Sitemap zeigt Google alle Unterseiten, so werden neue Seiten schneller gefunden.', 'The sitemap shows Google all subpages, so new pages are found faster.'));
$can = $attr($q('//link[@rel="canonical"]')[0] ?? null, 'href');
$canOk = $can !== '' && lc_regdom((string)parse_url(lc_abs($FINAL, $can), PHP_URL_HOST)) === lc_regdom($FH);
$add('canonical', 'google', 2, $L('Eindeutige Hauptadresse für Google (Canonical)', 'Clear main address for Google (canonical)'), $canOk,
    $can === '' ? $L('Keine Canonical-Angabe.', 'No canonical tag.') : ($canOk ? $L('Zeigt auf ' . lc_cut($can, 70), 'Points to ' . lc_cut($can, 70)) : $L('Zeigt auf eine fremde Adresse: ' . lc_cut($can, 70), 'Points to a foreign address: ' . lc_cut($can, 70))),
    $L('Verhindert, dass Google dieselbe Seite mehrfach unter verschiedenen Adressen führt.', 'Prevents Google from listing the same page several times under different addresses.'));
$ld = count($q('//script[@type="application/ld+json"]')) + count($q('//*[@itemtype]'));
$add('strukturdaten', 'google', 2, $L('Strukturierte Daten für Google', 'Structured data for Google'), $ld > 0,
    $ld ? $L('Strukturierte Angaben gefunden (z. B. Firma, Adresse).', 'Structured data found (e.g. business, address).') : $L('Keine strukturierten Daten.', 'No structured data.'),
    $L('Damit versteht Google Firma, Adresse, Öffnungszeiten und Bewertungen und kann sie hervorgehoben zeigen.', 'This lets Google understand business, address, opening hours and reviews and show them prominently.'));
$lang = $attr($q('//html')[0] ?? null, 'lang');
$add('sprache', 'google', 3, $L('Sprache der Seite angegeben', 'Page language specified'), $lang !== '',
    $lang !== '' ? 'lang="' . $lang . '"' : $L('Keine Sprachangabe.', 'No language specified.'),
    $L('Hilft Google, Übersetzungsdiensten und Vorlesehilfen.', 'Helps Google, translation services and screen readers.'));
$mitAlt = count(array_filter($imgs, function ($i) { return $i instanceof DOMElement && $i->hasAttribute('alt'); }));
$altQ = $nImg ? $mitAlt / $nImg : 1;
$add('alt', 'google', 3, $L('Bilder mit Alternativtext', 'Images with alt text'), $nImg ? $altQ >= 0.8 : null,
    $nImg ? $L(round($altQ * 100) . ' % der Bilder haben einen Alternativtext.', round($altQ * 100) . '% of images have alt text.') : $L('Keine Bilder im HTML.', 'No images in the HTML.'),
    $L('Alternativtexte beschreiben Bilder für Google und für Menschen mit Sehbehinderung.', 'Alt texts describe images for Google and for people with visual impairments.'));
$hs = []; foreach ($q('//h1|//h2|//h3|//h4|//h5|//h6') as $n) $hs[] = (int)substr($n->nodeName, 1);
$sprung = false; for ($i = 1; $i < count($hs); $i++) if ($hs[$i] > $hs[$i - 1] + 1) { $sprung = true; break; }
$add('ueberschriften', 'google', 3, $L('Überschriften in sinnvoller Reihenfolge', 'Headings in a sensible order'), $hs ? !$sprung : null,
    $hs ? ($sprung ? $L('Es werden Ebenen übersprungen (z. B. von H1 direkt zu H3).', 'Levels are skipped (e.g. from H1 straight to H3).') : $L('Die Gliederung ist durchgehend.', 'The outline is consistent.')) : $L('Keine Überschriften gefunden.', 'No headings found.'),
    $L('Eine saubere Gliederung hilft Google und Vorlesehilfen, die Seite zu verstehen.', 'A clean outline helps Google and screen readers understand the page.'));
$og = $meta('og:title') !== '' && $meta('og:image') !== '';
$add('vorschau', 'google', 3, $L('Vorschau beim Teilen (WhatsApp, LinkedIn)', 'Preview when sharing (WhatsApp, LinkedIn)'), $og,
    $og ? $L('Titel und Vorschaubild für geteilte Links vorhanden.', 'Title and preview image for shared links present.') : $L('Kein Vorschaubild oder Titel für geteilte Links.', 'No preview image or title for shared links.'),
    $L('Wer Ihre Seite per WhatsApp teilt, sieht sonst nur einen nackten Link statt Bild und Titel.', 'When someone shares your site via WhatsApp, they otherwise see only a bare link instead of image and title.'));
$body = $q('//body')[0] ?? null;
$text = '';
if ($body) { $c = $body->cloneNode(true); foreach (['script', 'style', 'noscript', 'svg'] as $t) foreach (iterator_to_array($c->getElementsByTagName($t)) as $x) $x->parentNode->removeChild($x); $text = trim(preg_replace('/\s+/u', ' ', $c->textContent)); }
$woerter = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
$add('textmenge', 'google', 3, $L('Genug Text auf der Startseite', 'Enough text on the homepage'), $woerter >= 250,
    $L('Etwa ' . $woerter . ' Wörter.', 'About ' . $woerter . ' words.'),
    $L('Google braucht Text, um zu verstehen, was Sie anbieten. Ab etwa 300 Wörtern wird es deutlich besser.', 'Google needs text to understand what you offer. From about 300 words it gets much better.'));

/* F Geschwindigkeit */
$ttfb = $home['ttfb']; $tot = $home['total'];
$add('tempo', 'tempo', 1, $L('Schnelle Antwort des Servers', 'Fast server response'), $ttfb > 0 ? ($ttfb <= 0.8 && $tot <= 2.5) : null,
    $L('Erste Antwort nach ' . number_format($ttfb, 2, ',', '') . ' s, Seite geladen nach ' . number_format($tot, 2, ',', '') . ' s.', 'First response after ' . number_format($ttfb, 2) . ' s, page loaded after ' . number_format($tot, 2) . ' s.'),
    $L('Jede Sekunde Wartezeit kostet Besucher. Ursachen sind oft langsames Hosting, fehlender Cache oder zu viele Erweiterungen.', 'Every second of waiting costs visitors. Causes are often slow hosting, no cache or too many plugins.'));
$enc = strtolower(lc_hdr($home, 'content-encoding'));
$add('komprimierung', 'tempo', 2, $L('Daten werden komprimiert übertragen', 'Data is transferred compressed'), in_array($enc, ['gzip', 'br', 'zstd', 'deflate'], true),
    $enc !== '' ? $L('Komprimierung: ' . $enc . '.', 'Compression: ' . $enc . '.') : $L('Keine Komprimierung.', 'No compression.'),
    $L('Mit gzip oder Brotli ist die Seite oft 70 % kleiner. Meist ein Schalter beim Hoster.', 'With gzip or Brotli the page is often 70% smaller. Usually a switch at the host.'));
$kb = (int)round(strlen($HTML) / 1024);
$dateien = count($q('//script[@src]')) + count($q('//link[@rel="stylesheet"]')) + $nImg;
$add('groesse', 'tempo', 2, $L('Schlanke Startseite', 'Lean homepage'), $kb <= 300 && $dateien <= 70,
    $L('HTML ' . $kb . ' KB, ' . $dateien . ' eingebundene Dateien.', 'HTML ' . $kb . ' KB, ' . $dateien . ' embedded files.'),
    $L('Zu viele Skripte und Bilder machen die Seite auf dem Handy träge.', 'Too many scripts and images make the site sluggish on phones.'));
$cc = isset($r2['asset']) ? strtolower(lc_hdr($r2['asset'], 'cache-control')) : '';
$age = preg_match('/max-age=(\d+)/', $cc, $m) ? (int)$m[1] : 0;
$cacheOk = isset($r2['asset']) ? ($age >= 86400 || strpos($cc, 'immutable') !== false || lc_hdr($r2['asset'], 'expires') !== '') : null;
$add('cache', 'tempo', 3, $L('Browser-Cache für Dateien', 'Browser cache for files'), $cacheOk,
    isset($r2['asset']) ? ($cacheOk ? $L('Dateien dürfen zwischengespeichert werden.', 'Files may be cached.') : $L('Dateien werden bei jedem Besuch neu geladen.', 'Files are reloaded on every visit.')) : $L('Keine eigene Datei zum Prüfen gefunden.', 'No own file found to check.'),
    $L('Wiederkehrende Besucher laden die Seite mit Cache deutlich schneller.', 'Returning visitors load the site much faster with caching.'));
$add('http2', 'tempo', 3, $L('Modernes Übertragungsprotokoll (HTTP/2)', 'Modern transfer protocol (HTTP/2)'), $home['ver'] ? $home['http2'] : null,
    $home['http2'] ? $L('HTTP/2 oder neuer.', 'HTTP/2 or newer.') : $L('Nur HTTP/1.1.', 'Only HTTP/1.1.'),
    $L('HTTP/2 lädt viele Dateien gleichzeitig und ist beim Hoster meist nur ein Schalter.', 'HTTP/2 loads many files at once and is usually just a switch at the host.'));

/* G Kontakt & Vertrauen */
$tel = count($q('//a[starts-with(translate(@href,"TEL","tel"),"tel:")]'));
$mail = count($q('//a[starts-with(translate(@href,"MAILTO","mailto"),"mailto:")]'));
$kontaktOk = $tel || $mail || $nForm || $lKon;
$add('kontakt', 'kontakt', 1, $L('Kontakt direkt von der Startseite', 'Contact directly from the homepage'), (bool)$kontaktOk,
    $kontaktOk ? $L(implode(', ', array_filter([$tel ? 'Telefon' : '', $mail ? 'E-Mail' : '', $nForm ? 'Formular' : '', $lKon ? 'Kontaktseite' : ''])) . ' erreichbar.', implode(', ', array_filter([$tel ? 'phone' : '', $mail ? 'email' : '', $nForm ? 'form' : '', $lKon ? 'contact page' : ''])) . ' available.') : $L('Kein Kontaktweg auf der Startseite gefunden.', 'No way to get in touch found on the homepage.'),
    $L('Besucher, die nicht sofort sehen, wie sie Sie erreichen, gehen zur Konkurrenz.', 'Visitors who don’t immediately see how to reach you go to the competition.'));
$add('telefon', 'kontakt', 3, $L('Telefonnummer zum Antippen', 'Tap-to-call phone number'), $tel > 0,
    $tel ? $L('Telefonnummer ist als Link hinterlegt.', 'Phone number is set as a link.') : $L('Keine antippbare Telefonnummer.', 'No tap-to-call phone number.'),
    $L('Auf dem Handy ruft ein Tipp direkt an. Das bringt messbar mehr Anfragen.', 'On phones, one tap calls directly. This measurably brings more inquiries.'));

/* H E-Mail der Domain */
$add('spf', 'email', 3, $L('E-Mail-Absender geschützt (SPF)', 'Email sender protected (SPF)'), $mx ? (bool)$spf : null,
    $spf ? 'SPF: ' . lc_cut($spf[0], 80) : ($mx ? $L('Kein SPF-Eintrag.', 'No SPF record.') : $L('Die Domain empfängt keine E-Mails.', 'The domain does not receive email.')),
    $L('Ohne SPF landen Ihre E-Mails öfter im Spam, und Betrüger können leichter in Ihrem Namen schreiben.', 'Without SPF your emails end up in spam more often and fraudsters can write in your name more easily.'));
$add('dmarc', 'email', 3, $L('Schutz vor gefälschten E-Mails (DMARC)', 'Protection against spoofed email (DMARC)'), $mx ? (bool)$dmarc : null,
    $dmarc ? 'DMARC: ' . lc_cut($dmarc[0], 80) : ($mx ? $L('Kein DMARC-Eintrag.', 'No DMARC record.') : $L('Die Domain empfängt keine E-Mails.', 'The domain does not receive email.')),
    $L('Seit 2024 verlangen Gmail und Yahoo von Absendern zunehmend DMARC. Fehlt es, kommen Mails schlechter an.', 'Since 2024 Gmail and Yahoo increasingly require DMARC. Without it, mail is delivered less reliably.'));

/* ---------------- Zusammenfassung ---------------- */
$GRUPPEN = [
    'sicherheit' => $L('Sicherheit', 'Security'), 'recht' => $L('Recht und Datenschutz', 'Legal and privacy'), 'adresse' => $L('Adresse und Weiterleitungen', 'Address and redirects'),
    'mobil' => $L('Handy und Darstellung', 'Mobile and display'), 'google' => $L('Google und Sichtbarkeit', 'Google and visibility'), 'tempo' => $L('Geschwindigkeit', 'Speed'),
    'kontakt' => $L('Kontakt', 'Contact'), 'email' => $L('E-Mail der Domain', 'Domain email'),
];
$gew = [1 => 3, 2 => 2, 3 => 1];
$sum = 0; $max = 0; $best = 0; $gepr = 0; $grp = []; $stu = [1 => ['ok' => 0, 'n' => 0], 2 => ['ok' => 0, 'n' => 0], 3 => ['ok' => 0, 'n' => 0]];
foreach ($ITEMS as $i => &$it) {
    $it['nr'] = $i + 1;
    if (!isset($grp[$it['gruppe']])) $grp[$it['gruppe']] = ['id' => $it['gruppe'], 'titel' => $GRUPPEN[$it['gruppe']], 'ok' => 0, 'n' => 0];
    if ($it['ok'] === null) continue;
    $gepr++; $max += $gew[$it['stufe']]; $grp[$it['gruppe']]['n']++; $stu[$it['stufe']]['n']++;
    if ($it['ok']) { $best++; $sum += $gew[$it['stufe']]; $grp[$it['gruppe']]['ok']++; $stu[$it['stufe']]['ok']++; }
}
unset($it);
$punkte = $max ? (int)round($sum / $max * 100) : 0;
$res = [
    'ok' => true, 'version' => 2, 'modus' => $VOLL ? 'voll' : 'sofort', 'host' => $HOST, 'final' => $FINAL,
    'gesamt' => count($ITEMS), 'geprueft' => $gepr, 'bestanden' => $best, 'punkte' => $punkte,
    'stufen' => $stu, 'gruppen' => array_values($grp), 'items' => $ITEMS,
    'dauer_ms' => (int)round((microtime(true) - $T0) * 1000), 'zeit' => gmdate('c'),
];
if ($httpsKaputt) $res['https_fehler'] = lc_cut((string)$httpsErr, 120);
if (count($ITEMS) !== 50) $res['warnung'] = 'Anzahl Prüfpunkte: ' . count($ITEMS);
lc_out($res);
