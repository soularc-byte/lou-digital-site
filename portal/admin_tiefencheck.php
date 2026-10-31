<?php
/* Lou Digital – Büro: Tiefen-Check-Werkzeug (Version 34, 31.10.2026). Wird von admin.php eingebunden (case 'tiefencheck'), nur Rolle admin.
   Ablauf: Check anlegen → automatischen 100-Punkte-Check sammeln (Browser ruft /api/check100.php, Ergebnis wird hier gespeichert) →
   8 Bereiche von Hand beurteilen (wird je Punkt sofort gespeichert) → Zusammenfassung → PDF → als Dokument beim Kunden ablegen. */
declare(strict_types=1);
if (!defined('LD') || !isset($me) || $me['rolle'] !== 'admin') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/inc/tiefencheck.php';

$tcId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$op = preg_replace('/[^a-z]/', '', (string)($_POST['op'] ?? '')) ?? '';
$TC_LIMITS = ['bf' => 2000, 'em' => 1500];
$tcStatus = ['entwurf' => 'Entwurf', 'fertig' => 'Fertig', 'gesendet' => 'An Kunden gesendet'];

/* Punkte aus POST übernehmen: st[id], bf[id], em[id], aw[id] (nur Punkte, die im Formular vorkommen) */
$tcLesen = function (array $punkte, ?string $nur = null) use ($TC_LIMITS): array {
  $alle = tc_alle_punkte();
  $st = $_POST['st'] ?? []; $bf = $_POST['bf'] ?? []; $em = $_POST['em'] ?? []; $aw = $_POST['aw'] ?? [];
  foreach ($alle as $pid => $p) {
    if ($nur !== null && $pid !== $nur) continue;
    if (!is_array($st) || !isset($st[$pid])) continue;
    $s = is_string($st[$pid]) ? $st[$pid] : 'offen'; if (!isset(TC_STATUS[$s])) $s = 'offen';
    $b = is_array($bf) && isset($bf[$pid]) && is_string($bf[$pid]) ? $bf[$pid] : ''; $e = is_array($em) && isset($em[$pid]) && is_string($em[$pid]) ? $em[$pid] : '';
    $clean = function (string $v, int $max): string { $v = str_replace(["\r\n", "\r"], "\n", $v); $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? ''; return trim(mb_substr($v, 0, $max)); };
    $punkte[$pid] = ['st' => $s, 'bf' => $clean($b, $TC_LIMITS['bf']), 'em' => $clean($e, $TC_LIMITS['em']), 'aw' => max(0, min(2000, (int)(is_array($aw) && isset($aw[$pid]) ? $aw[$pid] : $p['minuten'])))];
  }
  return $punkte;
};
$tcSpeichern = function (int $id, array $punkte, array $extra = []): array {
  $aw = tc_auswertung($punkte);
  $sets = ['punkte = ?', 'score = ?', 'aktualisiert = ?']; $vals = [json_encode($punkte, JSON_UNESCAPED_UNICODE), $aw['score'], pd_now()];
  foreach ($extra as $k => $v) { $sets[] = $k . ' = ?'; $vals[] = $v; }
  $vals[] = $id;
  pd_q('UPDATE tiefenchecks SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
  return $aw;
};

/* ---------- JSON-Aufrufe aus dem Werkzeug (fetch): item, auto, meta ---------- */
if ($post && $tcId && in_array($op, ['item', 'auto', 'meta'], true)) {
  $c = pd_row('SELECT * FROM tiefenchecks WHERE id = ?', [$tcId]); if (!$c) ld_json(404, ['ok' => false, 'error' => 'unbekannt']);
  $punkte = json_decode((string)$c['punkte'], true) ?: [];
  if ($op === 'item') {
    $pid = preg_replace('/[^a-z0-9_]/', '', p_str('pid')) ?? '';
    if (!isset(tc_alle_punkte()[$pid])) ld_json(422, ['ok' => false, 'error' => 'punkt']);
    $punkte = $tcLesen($punkte, $pid); $aw = $tcSpeichern($tcId, $punkte);
    ld_json(200, ['ok' => true, 'score' => $aw['score'], 'beurteilt' => $aw['beurteilt'], 'gesamt' => $aw['gesamt'], 'sofort' => $aw['sofort'], 'bald' => $aw['bald'], 'spaeter' => $aw['spaeter'], 'aufwand' => $aw['aufwand'], 'preis' => tc_angebot($aw['aufwand'])['preis']]);
  }
  if ($op === 'auto') {
    $raw = p_str('daten'); $d = strlen($raw) < 900000 ? json_decode($raw, true) : null;
    if (!is_array($d) || empty($d['ok']) || !isset($d['items']) || !is_array($d['items'])) ld_json(422, ['ok' => false, 'error' => 'daten']);
    /* nur die Felder behalten, die der Bericht braucht */
    $items = []; foreach ($d['items'] as $it) if (is_array($it) && isset($it['id'])) $items[] = ['id' => preg_replace('/[^a-z0-9_]/', '', (string)$it['id']), 'nr' => (int)($it['nr'] ?? 0), 'stufe' => (int)($it['stufe'] ?? 3), 'gruppe' => (string)($it['gruppe'] ?? ''), 'titel' => mb_substr((string)($it['titel'] ?? ''), 0, 160), 'status' => in_array($it['status'] ?? '', ['ok', 'fehlt', 'na', 'np'], true) ? $it['status'] : 'np', 'detail' => mb_substr((string)($it['detail'] ?? ''), 0, 500)];
    $gruppen = []; foreach ($d['gruppen'] ?? [] as $g) if (is_array($g)) $gruppen[] = ['id' => (string)($g['id'] ?? ''), 'titel' => mb_substr((string)($g['titel'] ?? ''), 0, 80), 'ok' => (int)($g['ok'] ?? 0), 'fehlt' => (int)($g['fehlt'] ?? 0), 'na' => (int)($g['na'] ?? 0), 'np' => (int)($g['np'] ?? 0)];
    $auto = ['ok' => true, 'host' => mb_substr((string)($d['host'] ?? ''), 0, 200), 'final' => mb_substr((string)($d['final'] ?? ''), 0, 300), 'punkte' => (int)($d['punkte'] ?? 0), 'bestanden' => (int)($d['bestanden'] ?? 0), 'geprueft' => (int)($d['geprueft'] ?? 0), 'gesamt' => (int)($d['gesamt'] ?? 100), 'fehlt' => (int)($d['fehlt'] ?? 0), 'dauer_ms' => (int)($d['dauer_ms'] ?? 0), 'gruppen' => $gruppen, 'items' => $items, 'warnung' => mb_substr((string)($d['warnung'] ?? ''), 0, 300)];
    $n = tc_auto_uebernehmen($auto, $punkte);
    $aw = $tcSpeichern($tcId, $punkte, ['auto' => json_encode($auto, JSON_UNESCAPED_UNICODE), 'auto_zeit' => pd_now()]);
    ld_json(200, ['ok' => true, 'vorbelegt' => $n, 'punkte' => $auto['punkte'], 'bestanden' => $auto['bestanden'], 'geprueft' => $auto['geprueft'], 'score' => $aw['score'], 'beurteilt' => $aw['beurteilt'], 'gesamt' => $aw['gesamt'], 'werte' => $punkte]);
  }
  if ($op === 'meta') {
    $felder = ['zusammenfassung' => 4000, 'empfehlung' => 1000, 'notiz' => 4000];
    $extra = []; foreach ($felder as $f => $max) if (isset($_POST[$f])) $extra[$f] = ld_post($f, $max);
    $aw = $tcSpeichern($tcId, $punkte, $extra);
    ld_json(200, ['ok' => true, 'score' => $aw['score']]);
  }
}

/* ---------- Formulare ohne JS: anlegen, Stammdaten, alles speichern, PDF ablegen, löschen ---------- */
if ($post && $op === 'neu') {
  $url = ld_line(ld_post('url', 200)); $n = ld_norm_url($url);
  if (!$n) { p_flash('Bitte eine gültige Website-Adresse eintragen.', 'warn'); p_go(P_BASE . '/admin/tiefencheck'); }
  $kunde = (int)($_POST['kunde_id'] ?? 0); $k = $kunde ? pd_row("SELECT * FROM kunden WHERE id = ? AND rolle = 'kunde'", [$kunde]) : null;
  $firma = ld_line(ld_post('firma', 120)); if ($firma === '' && $k) $firma = $k['firma'] !== '' ? $k['firma'] : $k['name'];
  pd_q('INSERT INTO tiefenchecks (kunde_id, auftrag_id, firma, ansprech, email, url, host, ziel, suche, wettbewerb, status, angelegt, aktualisiert) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$k ? (int)$k['id'] : 0, (int)($_POST['auftrag_id'] ?? 0), $firma, ld_line(ld_post('ansprech', 100)) ?: ($k['name'] ?? ''), ld_line(ld_post('email', 150)) ?: ($k['email'] ?? ''), 'https://' . $n['host'] . $n['path'], $n['host'], ld_post('ziel', 300), ld_post('suche', 300), ld_post('wettbewerb', 300), 'entwurf', pd_now(), pd_now()]);
  $neu = (int)pd()->lastInsertId(); pd_log($k ? (int)$k['id'] : 0, 'tiefencheck_neu', $n['host']);
  p_flash('Tiefen-Check angelegt. Erster Schritt: automatischen Check sammeln.'); p_go(P_BASE . '/admin/tiefencheck?id=' . $neu);
}
if ($post && $tcId && in_array($op, ['stamm', 'alle', 'ablegen', 'weg', 'status'], true)) {
  $c = pd_row('SELECT * FROM tiefenchecks WHERE id = ?', [$tcId]); if (!$c) p_go(P_BASE . '/admin/tiefencheck');
  $punkte = json_decode((string)$c['punkte'], true) ?: [];
  if ($op === 'weg') { pd_q('DELETE FROM tiefenchecks WHERE id = ?', [$tcId]); p_flash('Tiefen-Check gelöscht.'); p_go(P_BASE . '/admin/tiefencheck'); }
  if ($op === 'stamm') {
    $kunde = (int)($_POST['kunde_id'] ?? 0); $k = $kunde ? pd_row("SELECT id FROM kunden WHERE id = ? AND rolle = 'kunde'", [$kunde]) : null;
    $url = ld_line(ld_post('url', 200)); $n = ld_norm_url($url);
    $tcSpeichern($tcId, $punkte, ['kunde_id' => $k ? (int)$k['id'] : 0, 'auftrag_id' => (int)($_POST['auftrag_id'] ?? 0), 'firma' => ld_line(ld_post('firma', 120)), 'ansprech' => ld_line(ld_post('ansprech', 100)), 'email' => ld_line(ld_post('email', 150)),
      'url' => $n ? 'https://' . $n['host'] . $n['path'] : $c['url'], 'host' => $n ? $n['host'] : $c['host'], 'ziel' => ld_post('ziel', 300), 'suche' => ld_post('suche', 300), 'wettbewerb' => ld_post('wettbewerb', 300), 'status' => isset($tcStatus[p_str('status')]) ? p_str('status') : $c['status']]);
    p_flash('Gespeichert.');
  }
  if ($op === 'alle') { $punkte = $tcLesen($punkte); $tcSpeichern($tcId, $punkte, ['zusammenfassung' => ld_post('zusammenfassung', 4000), 'empfehlung' => ld_post('empfehlung', 1000), 'notiz' => ld_post('notiz', 4000)]); p_flash('Alle Punkte gespeichert.'); }
  if ($op === 'status') { $s = isset($tcStatus[p_str('status')]) ? p_str('status') : $c['status']; $tcSpeichern($tcId, $punkte, ['status' => $s]); p_flash('Status: ' . $tcStatus[$s] . '.'); }
  if ($op === 'ablegen') {
    $kid = (int)$c['kunde_id']; $k = $kid ? pd_row("SELECT * FROM kunden WHERE id = ? AND rolle = 'kunde'", [$kid]) : null;
    if (!$k) p_flash('Erst einen Kunden mit Portal-Konto zuordnen (Stammdaten).', 'warn');
    else {
      $dir = ld_dir('ld-data') . '/portal-dokumente/' . $kid; if (!is_dir($dir)) @mkdir($dir, 0750, true);
      $name = 'tiefencheck-' . date('Ymd') . '-' . bin2hex(random_bytes(3)) . '.pdf'; $pdf = tc_pdf($c);
      if (@file_put_contents($dir . '/' . $name, $pdf) === false) p_flash('PDF konnte nicht gespeichert werden.', 'warn');
      else {
        pd_q('INSERT INTO dokumente (kunde_id, titel, datei, art, groesse, angelegt) VALUES (?, ?, ?, ?, ?, ?)', [$kid, 'Tiefen-Check ' . date('d.m.Y'), $name, 'bericht', strlen($pdf), pd_now()]);
        $did = (int)pd()->lastInsertId(); $tcSpeichern($tcId, $punkte, ['dokument_id' => $did, 'status' => 'gesendet']);
        $mailOk = true;
        if (isset($_POST['mail'])) $mailOk = p_mail($k, 'Ihr Tiefen-Check liegt im Kundenportal', '<p style="margin:0">Guten Tag ' . h($k['name']) . ',</p><p style="margin:10px 0 0">der Tiefen-Check Ihrer Website ' . h($c['host']) . ' ist fertig. Den Bericht mit Ampel je Bereich, allen Befunden und den wichtigsten Maßnahmen finden Sie im Kundenportal unter „Dokumente“. Ich melde mich für das Gespräch zum Bericht; antworten Sie gern mit zwei Terminvorschlägen.</p>', [], p_btn_link(LD_SITE . P_BASE . '/dokumente', 'Zum Bericht'));
        pd_log($kid, 'tiefencheck_abgelegt', $c['host']);
        p_flash('PDF im Kundenportal abgelegt' . (isset($_POST['mail']) ? ($mailOk ? ' und Kunde per Mail informiert.' : ', Mail ging nicht raus.') : '.'), $mailOk ? 'ok' : 'warn');
      }
    }
  }
  p_go(P_BASE . '/admin/tiefencheck?id=' . $tcId);
}

/* ---------- PDF ansehen ---------- */
if ($tcId && isset($_GET['pdf'])) {
  $c = pd_row('SELECT * FROM tiefenchecks WHERE id = ?', [$tcId]); if (!$c) p_go(P_BASE . '/admin/tiefencheck');
  $pdf = tc_pdf($c);
  header('Content-Type: application/pdf'); header('Content-Length: ' . strlen($pdf)); header('Content-Disposition: inline; filename="tiefencheck-' . preg_replace('/[^a-z0-9.-]/', '_', $c['host']) . '.pdf"'); header('X-Content-Type-Options: nosniff');
  echo $pdf; exit;
}

/* ---------- Liste und Anlegen ---------- */
if (!$tcId) {
  $rows = pd_all('SELECT t.*, k.firma kf, k.name kn FROM tiefenchecks t LEFT JOIN kunden k ON k.id = t.kunde_id ORDER BY t.id DESC LIMIT 100');
  $kunden = pd_all("SELECT id, firma, name, email, website FROM kunden WHERE rolle = 'kunde' AND aktiv = 1 ORDER BY firma, name");
  $vorK = (int)($_GET['kunde'] ?? 0); $vorA = (int)($_GET['auftrag'] ?? 0); $vor = ['url' => '', 'firma' => '', 'ansprech' => '', 'email' => ''];
  if ($vorA) { $a = pd_row('SELECT * FROM auftraege WHERE id = ?', [$vorA]); if ($a) { $vor = ['url' => '', 'firma' => (string)$a['firma'], 'ansprech' => (string)$a['name'], 'email' => (string)$a['email']]; if (!$vorK) $vorK = (int)$a['kunde_id']; } }
  if ($vorK) { foreach ($kunden as $kk) if ((int)$kk['id'] === $vorK) { $vor['url'] = $vor['url'] ?: (string)$kk['website']; $vor['firma'] = $vor['firma'] ?: ($kk['firma'] !== '' ? $kk['firma'] : $kk['name']); $vor['ansprech'] = $vor['ansprech'] ?: (string)$kk['name']; $vor['email'] = $vor['email'] ?: (string)$kk['email']; } }
  $kOpts = ['0' => 'ohne Portal-Konto (Interessent)']; foreach ($kunden as $kk) $kOpts[(string)$kk['id']] = ($kk['firma'] !== '' ? $kk['firma'] . ' (' . $kk['name'] . ')' : $kk['name']) . ' · ' . $kk['email'];
  $o = '<h1 class="p-h1">Tiefen-Check</h1><p class="p-lead">Bezahlte Prüfung von Hand: ' . count(tc_alle_punkte()) . ' Prüfpunkte in 8 Bereichen, dazu der automatische 100-Punkte-Check als Anhang. Ergebnis ist ein PDF mit Ampel, Befunden, Maßnahmen und Umsetzungsangebot. Preis ' . number_format(LD_PREISE['tiefencheck'][0], 0, ',', '.') . ' € Pilot / ' . number_format(LD_PREISE['tiefencheck'][1], 0, ',', '.') . ' € normal, in Betreuung L einmal im Jahr enthalten.</p>'
     . '<section class="p-card"><h2>Neuen Tiefen-Check anlegen</h2><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="op" value="neu"><input type="hidden" name="auftrag_id" value="' . $vorA . '">'
     . '<div class="p-row"><label>Website-Adresse<input name="url" required maxlength="200" placeholder="firma.de" value="' . h($vor['url']) . '"></label><label>Kunde' . a_sel('kunde_id', $kOpts, (string)$vorK) . '</label></div>'
     . '<div class="p-row"><label>Firma<input name="firma" maxlength="120" value="' . h($vor['firma']) . '"></label><label>Ansprechpartner<input name="ansprech" maxlength="100" value="' . h($vor['ansprech']) . '"></label><label>E-Mail<input name="email" maxlength="150" value="' . h($vor['email']) . '"></label></div>'
     . '<div class="p-row"><label>Ziel der Website (aus dem Fragebogen)<input name="ziel" maxlength="300" placeholder="mehr Anfragen für Badsanierung"></label><label>Suchbegriffe (2–3)<input name="suche" maxlength="300" placeholder="Sanitär Karlsruhe, Badsanierung Karlsruhe"></label><label>Wettbewerber (2–3)<input name="wettbewerb" maxlength="300"></label></div>'
     . '<button class="btn btn--primary btn--small" type="submit">Anlegen</button></form></section>'
     . '<section class="p-card"><h2>Alle Tiefen-Checks</h2>' . ($rows ? '<table class="p-table"><tr><th>#</th><th>Website</th><th>Firma</th><th>Stand</th><th>Wert</th><th>Beurteilt</th><th>Geändert</th></tr>' . implode('', array_map(function ($r) use ($tcStatus) { $aw = tc_auswertung(json_decode((string)$r['punkte'], true) ?: []); return '<tr><td><a href="' . P_BASE . '/admin/tiefencheck?id=' . (int)$r['id'] . '">#' . (int)$r['id'] . '</a></td><td>' . h($r['host']) . '</td><td>' . h($r['firma'] !== '' ? $r['firma'] : ($r['kf'] ?: (string)$r['kn'])) . '</td><td>' . p_status_badge($r['status'], $tcStatus) . '</td><td>' . ($aw['beurteilt'] ? $aw['score'] . ' / 100' : '–') . '</td><td>' . $aw['beurteilt'] . ' / ' . $aw['gesamt'] . '</td><td>' . p_datum($r['aktualisiert'], true) . '</td></tr>'; }, $rows)) . '</table>' : p_empty('Noch kein Tiefen-Check.')) . '</section>';
  out('Tiefen-Check', $o);
}

/* ---------- Werkzeug: ein Check ---------- */
$c = pd_row('SELECT * FROM tiefenchecks WHERE id = ?', [$tcId]); if (!$c) p_go(P_BASE . '/admin/tiefencheck');
$punkte = json_decode((string)$c['punkte'], true) ?: []; $aw = tc_auswertung($punkte); $kat = tc_katalog(); $alle = tc_alle_punkte();
$auto = $c['auto'] !== '' ? (json_decode((string)$c['auto'], true) ?: null) : null;
$kunden = pd_all("SELECT id, firma, name, email FROM kunden WHERE rolle = 'kunde' AND aktiv = 1 ORDER BY firma, name");
$kOpts = ['0' => 'ohne Portal-Konto (Interessent)']; foreach ($kunden as $kk) $kOpts[(string)$kk['id']] = ($kk['firma'] !== '' ? $kk['firma'] . ' (' . $kk['name'] . ')' : $kk['name']) . ' · ' . $kk['email'];
$ang = tc_angebot($aw['aufwand']);
$titel = ($c['firma'] !== '' ? $c['firma'] : $c['host']);

/* Vorschlag für die Zusammenfassung aus der Auswertung */
$best = null; $worst = null; foreach ($aw['bereiche'] as $bid => $b) { if ($b['prozent'] === null) continue; if ($best === null || $b['prozent'] > $aw['bereiche'][$best]['prozent']) $best = $bid; if ($worst === null || $b['prozent'] < $aw['bereiche'][$worst]['prozent']) $worst = $bid; }
$vorschlag = 'Ihre Website ' . $c['host'] . ' erreicht im Tiefen-Check ' . $aw['score'] . ' von 100 Punkten (' . $aw['beurteilt'] . ' von ' . $aw['gesamt'] . ' Prüfpunkten beurteilt).'
  . ($best ? ' Am stärksten ist der Bereich „' . $aw['bereiche'][$best]['titel'] . '“ (' . $aw['bereiche'][$best]['prozent'] . ' %).' : '')
  . ($worst && $worst !== $best ? ' Den größten Nachholbedarf gibt es bei „' . $aw['bereiche'][$worst]['titel'] . '“ (' . $aw['bereiche'][$worst]['prozent'] . ' %).' : '')
  . ($aw['massnahmen'] ? ' Die erste Maßnahme: ' . $aw['massnahmen'][0]['titel'] . ' – ' . $aw['massnahmen'][0]['empf'] : '')
  . ' Alle ' . count($aw['massnahmen']) . ' Maßnahmen zusammen schätze ich auf ' . $aw['aufwand'] . ' Minuten (' . number_format($ang['preis'], 0, ',', '.') . ' € zum Festpreis).';
$empfVorschlag = $aw['score'] < 50 ? 'Neubau als „Website in 7 Tagen“ (' . number_format(ld_preise(true)['website'][0], 0, ',', '.') . ' €), der Tiefen-Check wird voll angerechnet; danach Betreuung M.' : ($aw['sofort'] > 0 ? 'Umsetzung der Sofort- und Bald-Maßnahmen zum Festpreis, anschließend Betreuung S oder M, damit es so bleibt.' : 'Die Website ist in guter Verfassung. Betreuung S sichert den Stand, die Später-Maßnahmen können nach und nach umgesetzt werden.');

/* Export der Befunde als Text (für den Prompt „Bericht formulieren“) */
$export = 'Tiefen-Check ' . $c['url'] . ' (' . date('d.m.Y') . "), Wert " . $aw['score'] . "/100, Ziel: " . $c['ziel'] . "\n";
foreach ($kat as $bid => [$bt, $bd, $pts]) { $b = $aw['bereiche'][$bid]; $export .= "\n## " . $bt . ' (' . ($b['prozent'] === null ? 'nicht beurteilt' : $b['prozent'] . ' %') . ")\n"; foreach ($pts as $pt) { $e = $punkte[$pt[0]] ?? []; $s = (string)($e['st'] ?? 'offen'); if ($s === 'offen') continue; $export .= '- [' . (TC_STATUS[$s] ?? $s) . '] ' . $pt[1] . (trim((string)($e['bf'] ?? '')) !== '' ? ': ' . trim((string)$e['bf']) : '') . (in_array($s, ['mangel', 'teils'], true) ? ' → ' . (trim((string)($e['em'] ?? '')) !== '' ? trim((string)$e['em']) : $pt[4]) . ' (' . (int)($e['aw'] ?? $pt[6]) . ' Min.)' : '') . "\n"; } }

$kopf = '<p class="p-small"><a href="' . P_BASE . '/admin/tiefencheck">← Alle Tiefen-Checks</a></p><div class="p-card-head"><h1 class="p-h1">Tiefen-Check: ' . h($titel) . '</h1>' . p_status_badge($c['status'], $tcStatus) . '</div>'
  . '<p class="p-lead"><a href="' . h($c['url']) . '" target="_blank" rel="noopener">' . h($c['url']) . '</a>' . ($c['ziel'] !== '' ? ' · Ziel: ' . h($c['ziel']) : '') . ($c['suche'] !== '' ? ' · Suchbegriffe: ' . h($c['suche']) : '') . ((int)$c['kunde_id'] ? ' · <a href="' . P_BASE . '/admin/kunde?id=' . (int)$c['kunde_id'] . '">Kunde #' . (int)$c['kunde_id'] . '</a>' : ' · ohne Portal-Konto') . '</p>'
  . '<div class="p-kpis tc-kpis" id="tc-kpis"><div class="p-kpi"><b id="tc-score">' . ($aw['beurteilt'] ? $aw['score'] : '–') . '</b><span>Wert von 100 (gewichtet)</span></div><div class="p-kpi"><b><span id="tc-beurteilt">' . $aw['beurteilt'] . '</span> / ' . $aw['gesamt'] . '</b><span>Punkte beurteilt</span><i class="p-bar"><i id="tc-bar" style="width:' . (int)round($aw['beurteilt'] / max(1, $aw['gesamt']) * 100) . '%"></i></i></div><div class="p-kpi"><b><span id="tc-sofort">' . $aw['sofort'] . '</span> · <span id="tc-bald">' . $aw['bald'] . '</span> · <span id="tc-spaeter">' . $aw['spaeter'] . '</span></b><span>Sofort · Bald · Später</span></div><div class="p-kpi"><b id="tc-preis">' . number_format($ang['preis'], 0, ',', '.') . ' €</b><span>Umsetzung zum Festpreis (<span id="tc-aufwand">' . $aw['aufwand'] . '</span> Min.)</span></div></div>'
  . '<section class="p-card tc-actions"><div class="p-actions"><button class="btn btn--primary btn--small" type="button" id="tc-auto" data-url="' . h($c['url']) . '" data-id="' . $tcId . '">' . ($auto ? 'Automatischen Check neu sammeln' : '1. Automatischen Check sammeln') . '</button>'
  . '<a class="btn btn--line btn--small" href="' . P_BASE . '/admin/tiefencheck?id=' . $tcId . '&amp;pdf=1" target="_blank" rel="noopener">PDF ansehen</a>'
  . '<form method="post">' . p_form($csrf) . '<input type="hidden" name="id" value="' . $tcId . '"><input type="hidden" name="op" value="ablegen"><input type="hidden" name="mail" value="1"><button class="btn btn--line btn--small" type="submit"' . ((int)$c['kunde_id'] ? ' data-confirm="PDF im Kundenportal ablegen und den Kunden per Mail informieren?"' : ' disabled title="Erst einen Kunden mit Konto zuordnen"') . '>Im Portal ablegen + Mail</button></form>'
  . '<form method="post" class="p-inline">' . p_form($csrf) . '<input type="hidden" name="id" value="' . $tcId . '"><input type="hidden" name="op" value="status">' . a_sel('status', $tcStatus, $c['status'], 'data-autosubmit') . '</form>'
  . '<span class="p-muted p-small" id="tc-save" aria-live="polite">' . ($auto ? 'Automatischer Check vom ' . p_datum($c['auto_zeit'], true) . ': ' . (int)$auto['punkte'] . ' / 100' : 'Noch kein automatischer Check') . '</span></div>'
  . '<p class="p-small p-muted">Jede Änderung an einem Punkt wird sofort gespeichert. Reihenfolge: automatischen Check sammeln (belegt die Technik-Punkte vor), dann Bereich für Bereich von Hand prüfen (Startseite und bis zu 5 Unterseiten, am Rechner und auf dem Handy), Zusammenfassung schreiben, PDF prüfen, ablegen.</p></section>';

/* Stammdaten */
$stamm = '<details class="p-details"><summary>Stammdaten, Ziel und Zuordnung</summary><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="id" value="' . $tcId . '"><input type="hidden" name="op" value="stamm">'
  . '<div class="p-row"><label>Website-Adresse<input name="url" maxlength="200" value="' . h($c['url']) . '"></label><label>Kunde' . a_sel('kunde_id', $kOpts, (string)(int)$c['kunde_id']) . '</label><label>Auftrag #<input name="auftrag_id" type="number" min="0" value="' . (int)$c['auftrag_id'] . '"></label></div>'
  . '<div class="p-row"><label>Firma<input name="firma" maxlength="120" value="' . h($c['firma']) . '"></label><label>Ansprechpartner<input name="ansprech" maxlength="100" value="' . h($c['ansprech']) . '"></label><label>E-Mail<input name="email" maxlength="150" value="' . h($c['email']) . '"></label></div>'
  . '<div class="p-row"><label>Ziel der Website<input name="ziel" maxlength="300" value="' . h($c['ziel']) . '"></label><label>Suchbegriffe<input name="suche" maxlength="300" value="' . h($c['suche']) . '"></label><label>Wettbewerber<input name="wettbewerb" maxlength="300" value="' . h($c['wettbewerb']) . '"></label></div>'
  . '<label>Stand' . a_sel('status', $tcStatus, $c['status']) . '</label><div class="p-actions"><button class="btn btn--line btn--small" type="submit">Speichern</button><button class="p-link p-danger" type="submit" name="op" value="weg" data-confirm="Diesen Tiefen-Check wirklich löschen?">Löschen</button></div></form></details>';

/* Prüfliste */
$liste = '<form method="post" id="tc-form" class="p-form p-form--wide tc-form" data-id="' . $tcId . '">' . p_form($csrf) . '<input type="hidden" name="id" value="' . $tcId . '"><input type="hidden" name="op" value="alle">';
foreach ($kat as $bid => [$bt, $bd, $pts]) {
  $b = $aw['bereiche'][$bid];
  $liste .= '<section class="p-card tc-bereich" id="tc-' . $bid . '"><details open><summary><h2>' . h($bt) . ' <span class="p-badge" data-bereich-stand="' . $bid . '">' . ($b['n'] - $b['offen']) . ' / ' . $b['n'] . ' beurteilt' . ($b['prozent'] !== null ? ' · ' . $b['prozent'] . ' %' : '') . '</span></h2></summary><p class="p-small p-muted">' . h($bd) . '</p>';
  foreach ($pts as $pt) {
    [$pid, $pt_t, $wie, $warum, $empf, $gew, $min, $autoId] = $pt; $e = $punkte[$pid] ?? []; $st = (string)($e['st'] ?? 'offen'); if (!isset(TC_STATUS[$st])) $st = 'offen';
    $autoHint = ''; if ($autoId !== '' && $auto) { foreach ($auto['items'] as $it) if ($it['id'] === $autoId) { $autoHint = '<span class="p-badge' . ($it['status'] === 'ok' ? ' p-badge--ok' : ($it['status'] === 'fehlt' ? ' p-badge--fehler' : '')) . '" title="' . h((string)$it['detail']) . '">automatisch: ' . h($it['status'] === 'ok' ? 'bestanden' : ($it['status'] === 'fehlt' ? 'fehlt' : ($it['status'] === 'na' ? 'trifft nicht zu' : 'nicht prüfbar'))) . '</span>'; break; } }
    $liste .= '<div class="tc-punkt" data-pid="' . $pid . '" data-gewicht="' . $gew . '"><div class="tc-head"><h3>' . h($pt_t) . '</h3><span class="p-badge">Gewicht ' . $gew . '</span>' . $autoHint . '<button type="button" class="p-link tc-info-btn" aria-expanded="false">So prüfen</button></div>'
      . '<div class="tc-info" hidden><p><b>So prüfen:</b> ' . h($wie) . '</p><p><b>Warum:</b> ' . h($warum) . '</p></div>'
      . '<div class="tc-status" role="radiogroup" aria-label="Status ' . h($pt_t) . '">';
    foreach (TC_STATUS as $sk => $sl) $liste .= '<label class="tc-st tc-st--' . $sk . '"><input type="radio" name="st[' . $pid . ']" value="' . $sk . '"' . ($st === $sk ? ' checked' : '') . '><span>' . $sl . '</span></label>';
    $liste .= '</div><div class="p-row tc-felder"><label>Befund<textarea name="bf[' . $pid . ']" rows="2" maxlength="' . $TC_LIMITS['bf'] . '" placeholder="Was ich gesehen habe">' . h((string)($e['bf'] ?? '')) . '</textarea></label><label>Empfehlung<textarea name="em[' . $pid . ']" rows="2" maxlength="' . $TC_LIMITS['em'] . '" placeholder="' . h($empf) . '">' . h((string)($e['em'] ?? '')) . '</textarea></label><label class="tc-aw">Aufwand (Min.)<input name="aw[' . $pid . ']" type="number" min="0" max="2000" step="5" value="' . (int)($e['aw'] ?? $min) . '"></label></div></div>';
  }
  $liste .= '</details></section>';
}
$liste .= '<section class="p-card" id="tc-zusammenfassung"><h2>Zusammenfassung und Empfehlung</h2>'
  . '<label>Zusammenfassung für den Bericht (5 Sätze: Gesamteindruck, Stärke, Risiko, erste Maßnahme, Nutzen) <button type="button" class="p-link" id="tc-vorschlag" data-text="' . h($vorschlag) . '">Vorschlag einfügen</button><textarea name="zusammenfassung" rows="5" maxlength="4000">' . h($c['zusammenfassung']) . '</textarea></label>'
  . '<label>Empfehlung (welches Paket passt) <button type="button" class="p-link" id="tc-empf-vorschlag" data-text="' . h($empfVorschlag) . '">Vorschlag einfügen</button><textarea name="empfehlung" rows="2" maxlength="1000">' . h($c['empfehlung']) . '</textarea></label>'
  . '<label>Interne Notiz (nicht im Bericht)<textarea name="notiz" rows="2" maxlength="4000">' . h($c['notiz']) . '</textarea></label>'
  . '<div class="p-actions"><button class="btn btn--primary btn--small" type="submit">Alles speichern</button><span class="p-small p-muted">Die Maßnahmenliste im PDF entsteht automatisch aus allen Punkten mit Mangel oder Verbesserung, sortiert nach Priorität.</span></div></section></form>';

/* Maßnahmen und Export */
$mass = '<section class="p-card"><h2>Maßnahmen (aus den Befunden)</h2>' . ($aw['massnahmen'] ? '<table class="p-table"><tr><th>#</th><th>Maßnahme</th><th>Bereich</th><th>Priorität</th><th>Min.</th></tr>' . implode('', array_map(function ($m, $i) { return '<tr><td>' . ($i + 1) . '</td><td><b>' . h($m['titel']) . '</b><br><span class="p-muted">' . h($m['empf']) . '</span></td><td>' . h($m['bereich_titel']) . '</td><td>' . p_status_badge((string)$m['prio'], [3 => 'Sofort', 2 => 'Bald', 1 => 'Später']) . '</td><td>' . $m['minuten'] . '</td></tr>'; }, $aw['massnahmen'], array_keys($aw['massnahmen']))) . '</table><p class="p-small">Umsetzung zum Festpreis: <b>' . number_format($ang['preis'], 0, ',', '.') . ' €</b> (' . $aw['aufwand'] . ' Minuten, 1 € je Minute, auf 10 € gerundet)' . ($ang['anrechnung'] ? ' – Tiefen-Check wird angerechnet.' : '.') . '</p>' : p_empty('Noch keine Maßnahmen – erst Punkte beurteilen.')) . '</section>'
  . '<section class="p-card"><h2>Befunde als Text (für den Prompt „Bericht formulieren“)</h2><textarea class="p-copy" data-copy readonly rows="8">' . h($export) . '</textarea><button class="btn btn--line btn--small" type="button" data-copy-btn>Kopieren</button></section>';

out('Tiefen-Check ' . $titel, $kopf . $stamm . $mass . $liste);
