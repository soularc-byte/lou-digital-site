<?php
/* Lou Digital – Prüfung aller Kunden-Websites und Monatsberichte (Version 30, 29.10.2026)
   Aufruf: GET /api/portal_cron.php (aus n8n, Schedule alle 5 Minuten). Kein Geheimnis nötig:
   läuft höchstens alle 4 Minuten (n8n ruft alle 5 Minuten), prüft nur hinterlegte Kunden-Websites, gibt nur Zähler zurück.
   Bei Störung: Mail an info@ (einmal je Störung, nicht bei jeder Prüfung). */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/portal.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
try {
  $letzte = pd_get('cron_zuletzt');
  if ($letzte !== '' && strtotime($letzte) > time() - 240) ld_json(200, ['ok' => true, 'uebersprungen' => true, 'zuletzt' => $letzte]);
  pd_set('cron_zuletzt', pd_now());
  $ks = pd_all("SELECT id, firma, name, website FROM kunden WHERE rolle = 'kunde' AND aktiv = 1 AND website <> ''");
  $n = 0; $stoer = [];
  foreach ($ks as $k) {
    $vorher = pd_row('SELECT ok FROM pruefungen WHERE kunde_id = ? ORDER BY id DESC LIMIT 1', [(int)$k['id']]);
    $r = ld_website_pruefen((int)$k['id'], $k['website']); if (!$r) continue; $n++;
    if (!$r['ok'] && (!$vorher || (int)$vorher['ok'] === 1)) { $stoer[] = $k['website']; $txt = ($k['firma'] !== '' ? $k['firma'] : $k['name']) . ': ' . $k['website'] . " antwortet nicht (Antwort " . $r['code'] . ", " . $r['ms'] . " ms).\n\n" . LD_SITE . '/portal/admin/kunde?id=' . $k['id']; ld_send(LD_TO, 'Störung: ' . $k['website'] . ' nicht erreichbar', $txt); ld_portal_hook("🔴 Störung: " . $txt); }
    if ($r['ok'] && $vorher && (int)$vorher['ok'] === 0) { ld_send(LD_TO, 'Wieder online: ' . $k['website'], $k['website'] . ' ist wieder erreichbar (' . $r['ms'] . ' ms).'); ld_portal_hook('🟢 Wieder online: ' . $k['website'] . ' (' . $r['ms'] . ' ms)'); }
    if ($r['ok'] && $r['ssl_bis'] !== '' && strtotime($r['ssl_bis']) < time() + 10 * 86400 && pd_get('ssl_warn_' . $k['id']) !== $r['ssl_bis']) { pd_set('ssl_warn_' . $k['id'], $r['ssl_bis']); ld_send(LD_TO, 'Zertifikat läuft ab: ' . $k['website'], 'Das Zertifikat von ' . $k['website'] . ' läuft am ' . $r['ssl_bis'] . ' ab.'); }
  }
  /* Monatsberichte: am Monatsanfang für den Vormonat, für alle Kunden mit Betreuung */
  $vormonat = date('Y-m', strtotime('first day of last month'));
  if (pd_get('bericht_monat') !== $vormonat) {
    pd_set('bericht_monat', $vormonat);
    foreach (pd_all("SELECT * FROM kunden WHERE rolle = 'kunde' AND aktiv = 1 AND stufe <> ''") as $k) { try { ld_bericht_ablegen($k, $vormonat, true); } catch (Throwable $e) {} }
  }
  /* Tokens und alte Sitzungen aufräumen */
  pd_q('DELETE FROM tokens WHERE bis < ?', [pd_now()]);
  pd_q('DELETE FROM sitzungen WHERE zuletzt < ?', [date('Y-m-d H:i:s', time() - 12 * 3600)]);
  ld_json(200, ['ok' => true, 'geprueft' => $n, 'stoerungen' => count($stoer)]);
} catch (Throwable $e) { ld_json(500, ['ok' => false]); }
