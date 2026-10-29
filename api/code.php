<?php
/* Lou Digital – Rabattcode prüfen und Pilotstand abfragen (Version 30, 29.10.2026)
   GET /api/code.php?code=LD-ABCD&pakete=website,automatik  -> gueltig, art, prozent, ersparnis, text
   GET /api/code.php                                        -> nur pilot_frei und aktuelle Preise
   Verbraucht nichts; verbraucht wird ein Code erst, wenn Adrian den Auftrag im Portal auf „bezahlt“ setzt. */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/portal.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
if (!ld_rate('code', 120)) ld_json(429, ['ok' => false, 'error' => 'rate']);
$codeIn = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_GET['code'] ?? '')) ?? '');
$pakete = array_values(array_filter(array_map(function ($s) { return preg_replace('/[^a-z]/', '', strtolower($s)); }, explode(',', (string)($_GET['pakete'] ?? ''))), function ($k) { return isset(LD_PREISE[$k]); }));
try {
  $frei = ld_pilot_frei();
  $out = ['ok' => true, 'pilot_frei' => $frei, 'pilot' => $frei > 0, 'preise' => ld_preise($frei > 0)];
  if ($codeIn !== '') {
    $c = ld_code_pruefen($codeIn, $pakete);
    $out['code'] = ['code' => $c['code'], 'gueltig' => $c['gueltig'], 'art' => $c['art'], 'prozent' => $c['prozent'], 'ersparnis' => $c['ersparnis'], 'text' => $c['text'], 'grund' => $c['grund']];
  }
  ld_json(200, $out);
} catch (Throwable $e) {
  ld_json(200, ['ok' => true, 'pilot_frei' => LD_PILOT_MAX, 'pilot' => true, 'preise' => ld_preise(true), 'code' => $codeIn !== '' ? ['code' => $codeIn, 'gueltig' => false, 'art' => '', 'prozent' => 0, 'ersparnis' => 0, 'text' => '', 'grund' => 'pruefen'] : null]);
}
