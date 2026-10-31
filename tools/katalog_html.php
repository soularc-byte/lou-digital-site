<?php
/* Lou Digital – baut die Katalog-Blöcke in die deutschen HTML-Seiten ein (Version 34, 31.10.2026)
   Aufruf: php tools/katalog_html.php   (aus dem Repo-Wurzelverzeichnis; liest api/inc/katalog.php und schreibt die Blöcke
   zwischen <!-- katalog:NAME --> und <!-- /katalog:NAME --> in pakete.html neu). Danach tools/fingerprint.py laufen lassen.
   Die englische Seite (en/packages.html) hält die Übersetzung von Hand, Preise kommen dort aus denselben Konstanten über site.js. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // nur von der Kommandozeile
define('LD', 1);
$root = dirname(__DIR__);
require $root . '/api/inc/lib.php';
require $root . '/api/inc/portal.php';
require $root . '/api/inc/katalog.php';
$K = ld_katalog(); $h = 'ld_h';
$eur = function (int $n): string { return number_format($n, 0, ',', '.') . ' €'; };
$li = function (array $a) use ($h): string { return implode('', array_map(function ($x) use ($h) { return '<li>' . $h($x) . '</li>'; }, $a)); };
$blocks = [];

/* Betreuung im Vergleich: S / M / L / E, Preiszeilen reagieren auf den Umschalter monatlich/jährlich */
$t = '<table class="vgl vgl--4"><thead><tr><th scope="col"><span class="sr">Leistung</span></th><th scope="col">S</th><th scope="col">M</th><th scope="col">L</th><th scope="col">E</th></tr></thead><tbody>';
foreach ($K['betreuung']['zeilen'] as $z) {
  $t .= '<tr><th scope="row">' . $h($z[0]) . '</th>';
  foreach ([1, 2, 3, 4] as $i) { $v = $z[$i]; $t .= $v === 'ja' ? '<td><span class="ja" role="img" aria-label="enthalten"></span></td>' : ($v === '–' ? '<td><span class="nein" role="img" aria-label="nicht enthalten">–</span></td>' : '<td class="vgl-wert">' . $h($v) . '</td>'); }
  $t .= '</tr>';
}
$t .= '<tr class="vgl-preis is-on" data-zw-row="monat"><th scope="row">Preis im Monat, monatlich kündbar</th><td><span data-tier-preis-monat="S">' . $eur(LD_TIERS['S']) . '</span></td><td><span data-tier-preis-monat="M">' . $eur(LD_TIERS['M']) . '</span></td><td><span data-tier-preis-monat="L">' . $eur(LD_TIERS['L']) . '</span></td><td class="vgl-wert">nach Absprache</td></tr>'
   . '<tr class="vgl-preis vgl-preis--jahr" data-zw-row="jahr"><th scope="row">Preis im Jahr (12 Monate zum Preis von 10)</th><td><span data-tier-preis-jahr="S">' . $eur(LD_TIERS['S'] * LD_JAHR_MONATE) . '</span></td><td><span data-tier-preis-jahr="M">' . $eur(LD_TIERS['M'] * LD_JAHR_MONATE) . '</span></td><td><span data-tier-preis-jahr="L">' . $eur(LD_TIERS['L'] * LD_JAHR_MONATE) . '</span></td><td class="vgl-wert">nach Absprache</td></tr>'
   . '</tbody><tfoot><tr><td></td>';
foreach (['S', 'M', 'L'] as $s) $t .= '<td><button class="pick vgl-wahl" type="button" data-pick="betreuung" data-tier="' . $s . '" aria-pressed="false" aria-label="Betreuung ' . $s . ' wählen" data-track="Betreuung wählen: ' . $s . '"><span class="pk-off">Wählen</span><span class="pk-on" aria-hidden="true">✓ Gewählt</span></button></td>';
$t .= '<td><button class="pick vgl-wahl" type="button" data-pick="betreuung" data-tier="E" aria-pressed="false" aria-label="Betreuung E anfragen" data-track="Betreuung wählen: E"><span class="pk-off">Anfragen</span><span class="pk-on" aria-hidden="true">✓ Gewählt</span></button></td></tr></tfoot></table>';
$blocks['vgl'] = $t;

/* Änderungszeit: zählt / zählt nicht / Regeln / Beispiele */
$a = $K['betreuung']['aenderung'];
$blocks['aenderung'] = '<div class="ae-grid"><div><h4>Zählt als Änderung</h4><ul class="haken">' . $li($a['zaehlt']) . '</ul></div><div><h4>Zählt nicht (Festpreis vorab)</h4><ul class="minus">' . $li($a['zaehlt_nicht']) . '</ul></div><div><h4>So wird gerechnet</h4><ul class="punkte">' . $li($a['regeln']) . '</ul></div></div>'
  . '<p class="mehr-bsp">Beispiele: ' . $h(implode(' · ', array_map(function ($b) { return $b[0] . ' ≈ ' . $b[1] . ' Min.'; }, $a['beispiele']))) . '</p>';

/* Stufen kurz (für Karten auf der Startseite und im Baukasten) */
$blocks['stufen'] = '';
foreach ($K['betreuung']['stufen'] as $s => $st) $blocks['stufen'] .= '<article class="stufe"><h3>' . $h($st['name']) . ' <span class="stufe-m">' . $h($st['motto']) . '</span></h3><p class="stufe-p">' . ($st['preis'] ? '<b data-tier-preis-monat="' . $s . '">' . $eur($st['preis']) . '</b> im Monat' : '<b>nach Absprache</b>') . '</p><ul class="haken">' . $li($st['punkte']) . '</ul></article>';

/* Tiefen-Check: drin / nicht drin */
$tc = $K['leistungen']['tiefencheck'];
$blocks['tiefencheck'] = '<div class="mehr-teil"><h3>Im Preis enthalten</h3><ul class="haken">' . $li($tc['drin']) . '</ul></div><div class="mehr-teil"><h3>Nicht enthalten</h3><ul class="minus">' . $li($tc['nicht']) . '</ul><h3>Erweitern mit</h3><ul class="zusatz"><li><a href="/individuell?basis=tcheck&amp;x=wettbewerb" data-track="Erweitern: wettbewerb"><span class="zu-t"><b>Vergleich mit 3 Wettbewerbern</b><small>was andere besser machen, mit Beispielen</small></span><span class="zu-p">90 €</span></a></li><li><a href="/individuell?basis=tcheck&amp;x=umsetzung" data-track="Erweitern: umsetzung"><span class="zu-t"><b>Umsetzung der Maßnahmen</b><small>Festpreis aus dem Bericht, Tiefen-Check wird angerechnet</small></span><span class="zu-p wort">Festpreis</span></a></li></ul></div>';

/* Marktvergleich */
$m = '<table class="mk"><thead><tr><th scope="col">Leistung</th><th scope="col">Freelancer</th><th scope="col">Agentur</th><th scope="col">Lou Digital</th></tr></thead><tbody>';
foreach ($K['markt'] as $z) { $pt = explode(', ', $z[3], 2); $m .= '<tr><th scope="row">' . $h($z[0]) . '</th><td>' . $h($z[1]) . '</td><td>' . $h($z[2]) . '</td><td><b>' . $h($pt[0]) . '</b>' . (isset($pt[1]) ? ', ' . $h($pt[1]) : '') . '</td></tr>'; }
$m .= '</tbody></table>';
$blocks['markt'] = $m;
$blocks['markt-quellen'] = 'Quellen: ' . implode(' · ', array_map(function ($q) use ($h) { return '<a href="' . $h($q[1]) . '" rel="nofollow noopener" target="_blank">' . $h($q[0]) . '</a>'; }, $K['markt_quellen'])) . '. Spannen gerundet, abgerufen am 31.10.2026, ohne Gewähr.';

/* Rabatte */
$blocks['rabatte'] = '<ul class="rab-liste">' . implode('', array_map(function ($r) use ($h) { return '<li><b>' . $h($r[0]) . ':</b> ' . $h($r[1]) . '</li>'; }, $K['rabatte'])) . '</ul>';

/* einbauen */
$files = ['pakete.html', 'start.html', 'individuell.html', 'fragen.html'];
foreach ($files as $f) {
  $p = $root . '/' . $f; if (!is_file($p)) continue;
  $s = file_get_contents($p); $n = 0;
  foreach ($blocks as $name => $html) {
    $s = preg_replace_callback('~<!-- katalog:' . preg_quote($name, '~') . ' -->.*?<!-- /katalog:' . preg_quote($name, '~') . ' -->~s', function () use ($name, $html, &$n) { $n++; return '<!-- katalog:' . $name . ' -->' . $html . '<!-- /katalog:' . $name . ' -->'; }, $s);
  }
  if ($n) { file_put_contents($p, $s); echo $f . ': ' . $n . " Blöcke\n"; } else echo $f . ": keine Marken\n";
}
