<?php
/* Lou Digital – Statistik-Dashboard (Version 12, 24.10.2026)
   Zeigt die Reichweitenmessung ohne Cookies. Nur sichtbar, wenn das Verzeichnis in Plesk mit Passwort geschützt ist. */
declare(strict_types=1);
define('LD', 1);
require dirname(__DIR__) . '/api/inc/lib.php';
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$user = (string)($_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? $_SERVER['PHP_AUTH_USER'] ?? '');
$auth = $user !== '' || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) || getenv('LD_STATS_OPEN') === '1';
$css = '<style>
:root{--ink:#11191b;--muted:#5d6b69;--line:#e3e8e6;--brand:#0b6e69;--soft:#e4f3f0;--lime:#d2f05a;--bg:#f5f6f3}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:28px 16px 60px}h1{font-size:32px;letter-spacing:-.03em;margin:0}h2{font-size:17px;margin:0 0 12px}
.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:22px}
.seg a{display:inline-block;padding:7px 12px;border:1px solid var(--line);border-radius:999px;color:var(--ink);text-decoration:none;font-size:14px;margin-left:4px;background:#fff}
.seg a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px}
.kpi,.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px}
.kpi b{display:block;font-size:28px;letter-spacing:-.03em}.kpi span{color:var(--muted);font-size:13px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:12px;margin-top:12px}
table{width:100%;border-collapse:collapse;font-size:14px}td{padding:6px 0;border-bottom:1px solid var(--line);vertical-align:top}td.n{text-align:right;white-space:nowrap;padding-left:10px;font-variant-numeric:tabular-nums}
.bar{height:6px;border-radius:3px;background:var(--soft);margin-top:4px}.bar i{display:block;height:6px;border-radius:3px;background:var(--brand)}
.muted{color:var(--muted)}.map{position:relative;width:100%;aspect-ratio:3/4;max-height:520px;background:linear-gradient(#fff,#f2f7f6);border:1px solid var(--line);border-radius:12px;overflow:hidden}
.map i{position:absolute;width:10px;height:10px;margin:-5px 0 0 -5px;border-radius:50%;background:rgba(11,110,105,.45)}.map i.m{background:rgba(196,140,0,.5)}
select{font:inherit;padding:6px 10px;border-radius:10px;border:1px solid var(--line);background:#fff}svg{display:block;width:100%;height:140px}
</style>';
if (!$auth) {
  http_response_code(403);
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Statistik – noch nicht geschützt</title>' . $css . '</head><body><div class="wrap" style="max-width:640px">'
     . '<h1>Statistik</h1><p>Diese Seite zeigt Daten erst, wenn der Ordner <code>/statistik</code> mit Passwort geschützt ist.</p>'
     . '<ol><li>Plesk öffnen: Websites &amp; Domains, lou-digital.de</li><li>„Passwortgeschützte Verzeichnisse“ (unter Sicherheit oder über die Suche)</li><li>„Geschütztes Verzeichnis hinzufügen“, Name <code>statistik</code>, Titel „Statistik“</li><li>Einen Benutzer mit eigenem Passwort anlegen</li><li>Diese Seite neu laden und anmelden</li></ol></div></body></html>';
  exit;
}
$days = (int)($_GET['d'] ?? 30); if (!in_array($days, [1, 7, 30, 90, 365], true)) $days = 30;
$from = strtotime('today -' . ($days - 1) . ' days');
$dir = ld_dir('ld-data');
$files = [];
for ($m = strtotime(date('Y-m-01', $from)); $m <= time(); $m = strtotime('+1 month', $m)) $files[] = $dir . '/events-' . date('Y-m', $m) . '.jsonl';
$pv = 0; $vis = []; $daily = []; $pages = []; $pageVis = []; $clicks = []; $refs = []; $devs = []; $oss = []; $brs = []; $langs = []; $forms = []; $checks = 0; $scroll = []; $map = []; $camp = [];
$seite = (string)($_GET['seite'] ?? '/');
foreach ($files as $f) {
  if (!is_file($f)) continue;
  $h = fopen($f, 'r');
  while (($line = fgets($h)) !== false) {
    $e = json_decode($line, true); if (!is_array($e) || ($e['ts'] ?? 0) < $from) continue;
    $day = date('Y-m-d', (int)$e['ts']); $vk = $day . $e['v'];
    $p = (string)$e['p'];
    if ($e['t'] === 'pv') {
      $pv++; $vis[$vk] = 1; $daily[$day]['pv'] = ($daily[$day]['pv'] ?? 0) + 1; $daily[$day]['v'][$e['v']] = 1;
      $pages[$p] = ($pages[$p] ?? 0) + 1; $pageVis[$p][$vk] = 1;
      if (!empty($e['r'])) $refs[$e['r']] = ($refs[$e['r']] ?? 0) + 1;
      if (!empty($e['q'])) $camp[$e['q']] = ($camp[$e['q']] ?? 0) + 1;
      $devs[$e['dev']] = ($devs[$e['dev']] ?? 0) + 1; $oss[$e['os']] = ($oss[$e['os']] ?? 0) + 1; $brs[$e['br']] = ($brs[$e['br']] ?? 0) + 1; $langs[$e['l']] = ($langs[$e['l']] ?? 0) + 1;
    } elseif ($e['t'] === 'c') {
      $k = ($e['k'] ?? '') . (isset($e['h']) ? '  →  ' . $e['h'] : ''); $ck = $p . "\t" . $k; $clicks[$ck] = ($clicks[$ck] ?? 0) + 1;
      if ($p === $seite && count($map) < 1500) $map[] = [(int)$e['x'], (int)$e['y'], $e['dev']];
    } elseif ($e['t'] === 's') { $scroll[$p][] = (int)$e['d']; }
    elseif ($e['t'] === 'f') { $forms[$e['k'] ?? '?'] = ($forms[$e['k'] ?? '?'] ?? 0) + 1; }
    elseif ($e['t'] === 'k') { $checks++; }
  }
  fclose($h);
}
$visitors = count($vis); $sent = array_sum($forms);
arsort($pages); arsort($clicks); arsort($refs); arsort($devs); arsort($oss); arsort($brs); arsort($forms); arsort($camp);
$esc = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$list = function (array $a, int $n = 12, ?callable $label = null) use ($esc) {
  if (!$a) return '<p class="muted">Noch keine Daten.</p>';
  $max = max($a); $o = '<table>';
  foreach (array_slice($a, 0, $n, true) as $k => $v) $o .= '<tr><td>' . ($label ? $label($k) : $esc($k)) . '<div class="bar"><i style="width:' . round($v / $max * 100) . '%"></i></div></td><td class="n">' . $v . '</td></tr>';
  return $o . '</table>';
};
// Tagesverlauf
$svg = ''; $n = min($days, 90); $vals = [];
for ($i = $n - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("today -$i days")); $vals[$d] = isset($daily[$d]['v']) ? count($daily[$d]['v']) : 0; }
$mx = max(1, max($vals ?: [1])); $bw = 1000 / max(1, count($vals)); $x = 0;
foreach ($vals as $d => $v) { $hgt = round($v / $mx * 120); $svg .= '<rect x="' . round($x + 1, 1) . '" y="' . (130 - $hgt) . '" width="' . round(max(1, $bw - 2), 1) . '" height="' . max(1, $hgt) . '" rx="2" fill="#0b6e69"><title>' . $esc($d) . ': ' . $v . ' Besucher</title></rect>'; $x += $bw; }
$scrollAvg = []; foreach ($scroll as $p => $arr) $scrollAvg[$p] = (int)round(array_sum($arr) / count($arr)); arsort($scrollAvg);
$pageOpts = ''; foreach (array_keys($pages) as $p) $pageOpts .= '<option' . ($p === $seite ? ' selected' : '') . '>' . $esc($p) . '</option>';
$dots = ''; foreach ($map as [$cx, $cy, $dv]) $dots .= '<i class="' . ($dv === 'mobil' ? 'm' : '') . '" style="left:' . $cx . '%;top:' . $cy . '%"></i>';
$seg = ''; foreach ([1 => 'Heute', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $k => $t) $seg .= '<a class="' . ($k === $days ? 'on' : '') . '" href="?d=' . $k . '&amp;seite=' . rawurlencode($seite) . '">' . $t . '</a>';
$clickRows = [];
foreach ($clicks as $ck => $v) { [$p, $k] = explode("\t", $ck, 2); $clickRows[$ck] = $v; }
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Statistik – Lou Digital</title><?= $css ?></head><body><div class="wrap">
<div class="top"><div><h1>Statistik</h1><span class="muted">lou-digital.de · ohne Cookies · Stand <?= date('d.m.Y H:i') ?></span></div><div class="seg"><?= $seg ?></div></div>
<div class="kpis">
 <div class="kpi"><b><?= $visitors ?></b><span>Besucher (pro Tag gezählt)</span></div>
 <div class="kpi"><b><?= $pv ?></b><span>Seitenaufrufe</span></div>
 <div class="kpi"><b><?= $visitors ? number_format($pv / $visitors, 1, ',', '') : '0' ?></b><span>Seiten pro Besuch</span></div>
 <div class="kpi"><b><?= $sent ?></b><span>Formulare gesendet</span></div>
 <div class="kpi"><b><?= $visitors ? number_format($sent / $visitors * 100, 1, ',', '') : '0' ?> %</b><span>Conversion-Rate</span></div>
 <div class="kpi"><b><?= $checks ?></b><span>Sofort-Checks</span></div>
</div>
<div class="card"><h2>Besucher pro Tag</h2><svg viewBox="0 0 1000 132" preserveAspectRatio="none"><?= $svg ?></svg></div>
<div class="grid">
 <div class="card"><h2>Seiten</h2><?= $list($pages, 15, function ($p) use ($esc, $pageVis) { return $esc($p) . ' <span class="muted">· ' . count($pageVis[$p] ?? []) . ' Besucher</span>'; }) ?></div>
 <div class="card"><h2>Was geklickt wird</h2><?= $list($clickRows, 15, function ($ck) use ($esc) { [$p, $k] = explode("\t", $ck, 2); return $esc($k) . ' <span class="muted">· ' . $esc($p) . '</span>'; }) ?></div>
 <div class="card"><h2>Herkunft</h2><?= $list($refs, 12) ?><?php if ($camp): ?><h2 style="margin-top:16px">Kampagnen (utm_source)</h2><?= $list($camp, 8) ?><?php endif; ?></div>
 <div class="card"><h2>Formulare</h2><?= $list($forms, 8) ?><h2 style="margin-top:16px">Scrolltiefe im Schnitt</h2><?= $list($scrollAvg, 10, function ($p) use ($esc, $scrollAvg) { return $esc($p) . ' <span class="muted">· ' . $scrollAvg[$p] . ' %</span>'; }) ?></div>
 <div class="card"><h2>Geräte</h2><?= $list($devs, 5) ?><h2 style="margin-top:16px">Sprache</h2><?= $list($langs, 3) ?></div>
 <div class="card"><h2>System und Browser</h2><?= $list($oss, 6) ?><div style="height:10px"></div><?= $list($brs, 6) ?></div>
 <div class="card"><h2>Klickkarte</h2><form method="get"><input type="hidden" name="d" value="<?= $days ?>"><select name="seite"><?= $pageOpts ?: '<option>/</option>' ?></select> <button style="font:inherit;padding:6px 12px;border-radius:10px;border:1px solid #11191b;background:#11191b;color:#fff">Zeigen</button></form>
  <p class="muted" style="font-size:13px">Jeder Punkt ist ein Klick: links/rechts wie auf dem Bildschirm, oben/unten wie weit unten auf der Seite. Grün Computer, Gelb Handy.</p><div class="map"><?= $dots ?></div></div>
</div>
<p class="muted" style="margin-top:24px;font-size:13px">Gespeichert werden keine IP-Adressen und keine Cookies. Besucher werden über einen Hash gezählt, der jeden Tag neu gebildet wird. Rohdaten werden nach 14 Monaten gelöscht.</p>
</div></body></html>
