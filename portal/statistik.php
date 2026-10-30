<?php
/* Lou Digital – Statistik (Reichweite ohne Cookies) im Büro-Bereich des Portals (Version 32, 30.10.2026).
   Wird von admin.php eingebunden (nur Rolle admin). Liest die Ereignisdateien aus ld-data (api/t.php schreibt sie).
   Vorher lag die Auswertung unter /statistik und verließ sich auf den Passwortschutz von Plesk; jetzt gilt der Portal-Login. */
declare(strict_types=1);
if (!defined('LD') || !isset($me) || $me['rolle'] !== 'admin') { http_response_code(404); exit; }

function a_statistik(int $days, string $seite): string {
  $from = strtotime('today -' . ($days - 1) . ' days');
  $dir = ld_dir('ld-data');
  $files = [];
  for ($m = strtotime(date('Y-m-01', $from)); $m <= time(); $m = strtotime('+1 month', $m)) $files[] = $dir . '/events-' . date('Y-m', $m) . '.jsonl';
  $pv = 0; $vis = []; $daily = []; $pages = []; $pageVis = []; $clicks = []; $refs = []; $devs = []; $oss = []; $brs = []; $langs = []; $forms = []; $checks = 0; $scroll = []; $map = []; $camp = [];
  foreach ($files as $f) {
    if (!is_file($f)) continue;
    $hd = fopen($f, 'r');
    if (!$hd) continue;
    while (($line = fgets($hd)) !== false) {
      $e = json_decode($line, true); if (!is_array($e) || ($e['ts'] ?? 0) < $from) continue;
      $day = date('Y-m-d', (int)$e['ts']); $vk = $day . ($e['v'] ?? '');
      $p = (string)($e['p'] ?? '/');
      if (($e['t'] ?? '') === 'pv') {
        $pv++; $vis[$vk] = 1; $daily[$day]['pv'] = ($daily[$day]['pv'] ?? 0) + 1; $daily[$day]['v'][$e['v'] ?? ''] = 1;
        $pages[$p] = ($pages[$p] ?? 0) + 1; $pageVis[$p][$vk] = 1;
        if (!empty($e['r'])) $refs[$e['r']] = ($refs[$e['r']] ?? 0) + 1;
        if (!empty($e['q'])) $camp[$e['q']] = ($camp[$e['q']] ?? 0) + 1;
        $dv = (string)($e['dev'] ?? '?'); $ov = (string)($e['os'] ?? '?'); $bv = (string)($e['br'] ?? '?'); $lv = (string)($e['l'] ?? '?');
        $devs[$dv] = ($devs[$dv] ?? 0) + 1; $oss[$ov] = ($oss[$ov] ?? 0) + 1; $brs[$bv] = ($brs[$bv] ?? 0) + 1; $langs[$lv] = ($langs[$lv] ?? 0) + 1;
      } elseif (($e['t'] ?? '') === 'c') {
        $k = ($e['k'] ?? '') . (isset($e['h']) ? '  →  ' . $e['h'] : ''); $ck = $p . "\t" . $k; $clicks[$ck] = ($clicks[$ck] ?? 0) + 1;
        if ($p === $seite && count($map) < 1500) $map[] = [(int)($e['x'] ?? 0), (int)($e['y'] ?? 0), (string)($e['dev'] ?? '')];
      } elseif (($e['t'] ?? '') === 's') { $scroll[$p][] = (int)($e['d'] ?? 0); }
      elseif (($e['t'] ?? '') === 'f') { $forms[$e['k'] ?? '?'] = ($forms[$e['k'] ?? '?'] ?? 0) + 1; }
      elseif (($e['t'] ?? '') === 'k') { $checks++; }
    }
    fclose($hd);
  }
  $visitors = count($vis); $sent = array_sum($forms);
  arsort($pages); arsort($clicks); arsort($refs); arsort($devs); arsort($oss); arsort($brs); arsort($forms); arsort($camp);
  $list = function (array $a, int $n = 12, ?callable $label = null) {
    if (!$a) return '<p class="p-muted">Noch keine Daten.</p>';
    $max = max($a); $o = '<table class="st-table">';
    foreach (array_slice($a, 0, $n, true) as $k => $v) $o .= '<tr><td>' . ($label ? $label((string)$k) : h((string)$k)) . '<div class="st-bar"><i style="width:' . round($v / max(1, $max) * 100) . '%"></i></div></td><td class="st-n">' . (int)$v . '</td></tr>';
    return $o . '</table>';
  };
  $svg = ''; $n = min($days, 90); $vals = [];
  for ($i = $n - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("today -$i days")); $vals[$d] = isset($daily[$d]['v']) ? count($daily[$d]['v']) : 0; }
  $mx = max(1, max($vals ?: [1])); $bw = 1000 / max(1, count($vals)); $x = 0;
  foreach ($vals as $d => $v) { $hgt = round($v / $mx * 120); $svg .= '<rect x="' . round($x + 1, 1) . '" y="' . (130 - $hgt) . '" width="' . round(max(1, $bw - 2), 1) . '" height="' . max(1, $hgt) . '" rx="2" fill="#0b6e69"><title>' . h($d) . ': ' . $v . ' Besucher</title></rect>'; $x += $bw; }
  $scrollAvg = []; foreach ($scroll as $p => $arr) $scrollAvg[$p] = (int)round(array_sum($arr) / max(1, count($arr))); arsort($scrollAvg);
  $pageOpts = ''; foreach (array_keys($pages) as $p) $pageOpts .= '<option' . ($p === $seite ? ' selected' : '') . '>' . h((string)$p) . '</option>';
  $dots = ''; foreach ($map as [$cx, $cy, $dv]) $dots .= '<i class="' . ($dv === 'mobil' ? 'm' : '') . '" style="left:' . max(0, min(100, $cx)) . '%;top:' . max(0, min(100, $cy)) . '%"></i>';
  $seg = ''; foreach ([1 => 'Heute', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $k => $t) $seg .= '<a class="' . ($k === $days ? 'on' : '') . '" href="' . P_BASE . '/admin/statistik?d=' . $k . '&amp;seite=' . rawurlencode($seite) . '">' . $t . '</a>';
  $css = '<style>
.st-seg a{display:inline-block;padding:7px 12px;border:1px solid #dde2df;border-radius:999px;color:#11191b;text-decoration:none;font-size:14px;margin:0 4px 6px 0;background:#fff}.st-seg a.on{background:#11191b;color:#fff;border-color:#11191b}
.st-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;margin-top:12px}
.st-table{width:100%;border-collapse:collapse;font-size:14px}.st-table td{padding:6px 0;border-bottom:1px solid #e3e8e6;vertical-align:top}.st-table td.st-n{text-align:right;white-space:nowrap;padding-left:10px;font-variant-numeric:tabular-nums}
.st-bar{height:6px;border-radius:3px;background:#e4f3f0;margin-top:4px}.st-bar i{display:block;height:6px;border-radius:3px;background:#0b6e69}
.st-map{position:relative;width:100%;aspect-ratio:3/4;max-height:520px;background:linear-gradient(#fff,#f2f7f6);border:1px solid #e3e8e6;border-radius:12px;overflow:hidden}
.st-map i{position:absolute;width:10px;height:10px;margin:-5px 0 0 -5px;border-radius:50%;background:rgba(11,110,105,.45)}.st-map i.m{background:rgba(196,140,0,.5)}
.st-svg{display:block;width:100%;height:140px}
</style>';
  $kpi = function (string $v, string $t) { return '<div class="p-kpi"><b>' . $v . '</b><span>' . $t . '</span></div>'; };
  return $css . '<h1 class="p-h1">Statistik</h1><p class="p-lead">lou-digital.de · ohne Cookies · Stand ' . date('d.m.Y H:i') . '</p><div class="st-seg">' . $seg . '</div>'
    . '<div class="p-kpis">' . $kpi((string)$visitors, 'Besucher (pro Tag gezählt)') . $kpi((string)$pv, 'Seitenaufrufe') . $kpi($visitors ? number_format($pv / $visitors, 1, ',', '') : '0', 'Seiten pro Besuch') . $kpi((string)$sent, 'Formulare gesendet') . $kpi(($visitors ? number_format($sent / $visitors * 100, 1, ',', '') : '0') . ' %', 'Conversion-Rate') . $kpi((string)$checks, 'Sofort-Checks') . '</div>'
    . '<section class="p-card"><h2>Besucher pro Tag</h2><svg class="st-svg" viewBox="0 0 1000 132" preserveAspectRatio="none">' . $svg . '</svg></section>'
    . '<div class="st-grid">'
    . '<section class="p-card"><h2>Seiten</h2>' . $list($pages, 15, function ($p) use ($pageVis) { return h($p) . ' <span class="p-muted">· ' . count($pageVis[$p] ?? []) . ' Besucher</span>'; }) . '</section>'
    . '<section class="p-card"><h2>Was geklickt wird</h2>' . $list($clicks, 15, function ($ck) { [$p, $k] = array_pad(explode("\t", $ck, 2), 2, ''); return h($k) . ' <span class="p-muted">· ' . h($p) . '</span>'; }) . '</section>'
    . '<section class="p-card"><h2>Herkunft</h2>' . $list($refs, 12) . ($camp ? '<h2 style="margin-top:16px">Kampagnen (utm_source)</h2>' . $list($camp, 8) : '') . '</section>'
    . '<section class="p-card"><h2>Formulare</h2>' . $list($forms, 8) . '<h2 style="margin-top:16px">Scrolltiefe im Schnitt</h2>' . $list($scrollAvg, 10, function ($p) use ($scrollAvg) { return h($p) . ' <span class="p-muted">· ' . (int)($scrollAvg[$p] ?? 0) . ' %</span>'; }) . '</section>'
    . '<section class="p-card"><h2>Geräte</h2>' . $list($devs, 5) . '<h2 style="margin-top:16px">Sprache</h2>' . $list($langs, 3) . '</section>'
    . '<section class="p-card"><h2>System und Browser</h2>' . $list($oss, 6) . '<div style="height:10px"></div>' . $list($brs, 6) . '</section>'
    . '<section class="p-card"><h2>Klickkarte</h2><form method="get" action="' . P_BASE . '/admin/statistik" class="p-inline"><input type="hidden" name="d" value="' . $days . '"><label>Seite <select name="seite">' . ($pageOpts ?: '<option>/</option>') . '</select></label> <button class="btn btn--line btn--small" type="submit">Zeigen</button></form>'
    . '<p class="p-small p-muted">Jeder Punkt ist ein Klick: links/rechts wie auf dem Bildschirm, oben/unten wie weit unten auf der Seite. Grün Computer, Gelb Handy.</p><div class="st-map">' . $dots . '</div></section>'
    . '</div><p class="p-small p-muted" style="margin-top:20px">Gespeichert werden keine IP-Adressen und keine Cookies. Besucher werden über einen Hash gezählt, der jeden Tag neu gebildet wird. Rohdaten werden nach 14 Monaten gelöscht.</p>';
}
