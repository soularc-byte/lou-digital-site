<?php
/* Lou Digital – Büro: Prompt-Werkstatt (Version 34, 31.10.2026). Wird von admin.php eingebunden (case 'prompts'), nur Rolle admin.
   Je Leistung: Umfang (drin / nicht drin / Regeln), Fragen an den Kunden (Antworten speichern, im Portal freischalten, als Text kopieren)
   und fertige Arbeits-Prompts im Standard-Aufbau, in die die Antworten eingesetzt sind. Quelle: api/inc/katalog.php. */
declare(strict_types=1);
if (!defined('LD') || !isset($me) || $me['rolle'] !== 'admin') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/inc/katalog.php';
$KAT = ld_katalog();

/* Reiter: [Schlüssel, Name, Fragebögen, Prompt-Gruppen, Leistungen für den Umfang] */
$TABS = [
  'website' => ['Website in 7 Tagen', ['website'], ['website'], ['website']],
  'automatik' => ['Anfrage-Automatik', ['automatik'], ['automatik'], ['automatik']],
  'umfrage' => ['Kundenumfrage', ['umfrage'], ['umfrage'], ['umfrage']],
  'tiefencheck' => ['Tiefen-Check', ['tiefencheck'], ['tiefencheck'], ['tiefencheck', 'check']],
  'betreuung' => ['Betreuung S/M/L', ['betreuung', 'aenderung'], ['betreuung'], ['betreuung']],
  'exklusiv' => ['Betreuung E', ['exklusiv'], ['exklusiv'], []],
  'buero' => ['Büro', [], ['buero'], ['individuell']],
];
$FB_NAMEN = ['website' => 'Fragebogen Website', 'automatik' => 'Fragebogen Anfrage-Automatik', 'umfrage' => 'Fragebogen Kundenumfrage', 'tiefencheck' => 'Fragebogen Tiefen-Check', 'betreuung' => 'Fragebogen Betreuung', 'aenderung' => 'Änderungswunsch', 'exklusiv' => 'Bedarf Betreuung E'];
$FB_STATUS = ['entwurf' => 'Entwurf', 'offen' => 'Beim Kunden', 'beantwortet' => 'Beantwortet'];
$tab = preg_replace('/[^a-z]/', '', ld_get('l') ?: p_str('l')) ?? ''; if (!isset($TABS[$tab])) $tab = 'website';
$kundeId = (int)($_GET['k'] ?? $_POST['k'] ?? 0);
$kunden = pd_all("SELECT id, firma, name, email, website, stufe, zahlung, minuten_genutzt FROM kunden WHERE rolle = 'kunde' AND aktiv = 1 ORDER BY firma, name");
$kunde = null; foreach ($kunden as $kk) if ((int)$kk['id'] === $kundeId) $kunde = $kk; if (!$kunde) $kundeId = 0;
$zurueck = P_BASE . '/admin/prompts?l=' . $tab . ($kundeId ? '&k=' . $kundeId : '');

/* ---------- Antworten speichern / freischalten ---------- */
if ($post) {
  $op = preg_replace('/[^a-z]/', '', p_str('op')) ?? '';
  $fb = preg_replace('/[^a-z]/', '', p_str('fb')) ?? '';
  if (in_array($op, ['antworten', 'freigabe', 'zurueck'], true) && isset($KAT['fragebogen'][$fb])) {
    $row = pd_row('SELECT * FROM fragebogen WHERE kunde_id = ? AND leistung = ?', [$kundeId, $fb]);
    $ant = $row ? (json_decode((string)$row['antworten'], true) ?: []) : [];
    if ($op === 'antworten') {
      foreach ($KAT['fragebogen'][$fb] as [$fid, $frage, $hinweis]) if (isset($_POST['a'][$fid]) && is_string($_POST['a'][$fid])) { $v = str_replace(["\r\n", "\r"], "\n", $_POST['a'][$fid]); $ant[$fid] = trim(mb_substr(preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '', 0, 2000)); }
      $status = $row ? $row['status'] : 'entwurf';
      if ($row) pd_q('UPDATE fragebogen SET antworten = ?, notiz = ?, aktualisiert = ? WHERE id = ?', [json_encode($ant, JSON_UNESCAPED_UNICODE), ld_post('notiz', 2000), pd_now(), (int)$row['id']]);
      else pd_q('INSERT INTO fragebogen (kunde_id, leistung, antworten, status, freigabe, notiz, angelegt, aktualisiert) VALUES (?, ?, ?, ?, 0, ?, ?, ?)', [$kundeId, $fb, json_encode($ant, JSON_UNESCAPED_UNICODE), 'entwurf', ld_post('notiz', 2000), pd_now(), pd_now()]);
      p_flash('Antworten gespeichert.');
    }
    if ($op === 'freigabe') {
      if (!$kunde) p_flash('Zum Freischalten erst einen Kunden mit Portal-Konto wählen.', 'warn');
      else {
        if ($row) pd_q("UPDATE fragebogen SET freigabe = 1, status = CASE WHEN status = 'beantwortet' THEN status ELSE 'offen' END, aktualisiert = ? WHERE id = ?", [pd_now(), (int)$row['id']]);
        else pd_q('INSERT INTO fragebogen (kunde_id, leistung, antworten, status, freigabe, angelegt, aktualisiert) VALUES (?, ?, ?, ?, 1, ?, ?)', [$kundeId, $fb, '{}', 'offen', pd_now(), pd_now()]);
        $k = pd_row('SELECT * FROM kunden WHERE id = ?', [$kundeId]);
        $ok = p_mail($k, 'Ein paar Fragen zu „' . ($FB_NAMEN[$fb] ?? $fb) . '“ – Lou Digital', '<p style="margin:0">Guten Tag ' . h($k['name']) . ',</p><p style="margin:10px 0 0">damit ich direkt loslegen kann, habe ich Ihnen im Kundenportal einen kurzen Fragebogen freigeschaltet (' . h($FB_NAMEN[$fb] ?? $fb) . '). Stichpunkte reichen, Sie können zwischenspeichern und später weitermachen.</p>', [], p_btn_link(LD_SITE . P_BASE . '/fragebogen?l=' . $fb, 'Fragebogen öffnen'));
        pd_log($kundeId, 'fragebogen_frei', $fb);
        p_flash($ok ? 'Im Portal freigeschaltet und Kunde per Mail informiert.' : 'Freigeschaltet, aber die Mail ging nicht raus.', $ok ? 'ok' : 'warn');
      }
    }
    if ($op === 'zurueck' && $row) { pd_q('UPDATE fragebogen SET freigabe = 0, aktualisiert = ? WHERE id = ?', [pd_now(), (int)$row['id']]); p_flash('Fragebogen im Portal ausgeblendet.'); }
  }
  p_go($zurueck);
}

/* ---------- Seite ---------- */
$kOpts = ['0' => 'ohne Kunde (allgemein)']; foreach ($kunden as $kk) $kOpts[(string)$kk['id']] = ($kk['firma'] !== '' ? $kk['firma'] . ' (' . $kk['name'] . ')' : $kk['name']);
$tabs = '<p class="p-tabs">'; foreach ($TABS as $k => $t) $tabs .= '<a href="' . P_BASE . '/admin/prompts?l=' . $k . ($kundeId ? '&amp;k=' . $kundeId : '') . '"' . ($k === $tab ? ' class="on"' : '') . '>' . h($t[0]) . '</a>'; $tabs .= '</p>';
$wahl = '<form method="get" action="' . P_BASE . '/admin/prompts" class="p-inline p-actions"><input type="hidden" name="l" value="' . h($tab) . '"><label>Kunde ' . a_sel('k', $kOpts, (string)$kundeId, 'data-autosubmit') . '</label>' . ($kunde ? '<span class="p-muted p-small">' . h($kunde['email']) . ($kunde['website'] !== '' ? ' · ' . h($kunde['website']) : '') . ($kunde['stufe'] !== '' ? ' · Betreuung ' . h($kunde['stufe']) : '') . ' · <a href="' . P_BASE . '/admin/kunde?id=' . $kundeId . '">Kundenseite</a></span>' : '<span class="p-muted p-small">Mit Kunde werden Name, Firma, Website und Antworten in die Prompts eingesetzt.</span>') . '</form>';

[$tabName, $fbKeys, $prKeys, $lKeys] = $TABS[$tab];
$o = '<h1 class="p-h1">Prompt-Werkstatt</h1><p class="p-lead">Je Leistung: was drin ist, die Fragen an den Kunden und fertige Arbeits-Prompts im Standard-Aufbau. Antworten des Kunden fließen automatisch in die Prompts – Kopieren, in Claude einfügen, fertig.</p>' . $tabs . '<section class="p-card">' . $wahl . '</section>';

/* Umfang */
foreach ($lKeys as $lk) {
  $l = $KAT['leistungen'][$lk];
  $o .= '<details class="p-details"><summary>Umfang: ' . h($l['name']) . ' · ' . h($l['preis']) . ' · ' . h($l['dauer']) . '</summary><div class="p-grid3"><div><h3>Drin</h3><ul class="haken p-haken">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $l['drin'])) . '</ul></div><div><h3>Nicht drin</h3><ul class="p-minus">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $l['nicht'])) . '</ul></div><div><h3>Regeln</h3><ul class="p-minus p-minus--neutral">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $l['regeln'])) . '</ul></div></div><p class="p-small p-muted">Eigener Aufwand: ' . h($l['aufwand']) . '</p></details>';
}
if ($tab === 'betreuung') {
  $bt = $KAT['betreuung'];
  $o .= '<details class="p-details"><summary>Betreuung im Vergleich (S / M / L / E) und Änderungszeit</summary><table class="p-table"><tr><th>Leistung</th><th>S</th><th>M</th><th>L</th><th>E</th></tr>' . implode('', array_map(function ($z) { return '<tr><th scope="row">' . h($z[0]) . '</th><td>' . h($z[1]) . '</td><td>' . h($z[2]) . '</td><td>' . h($z[3]) . '</td><td>' . h($z[4]) . '</td></tr>'; }, $bt['zeilen'])) . '</table>'
     . '<div class="p-grid3"><div><h3>Zählt als Änderung</h3><ul class="haken p-haken">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $bt['aenderung']['zaehlt'])) . '</ul></div><div><h3>Zählt nicht (Festpreis vorab)</h3><ul class="p-minus">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $bt['aenderung']['zaehlt_nicht'])) . '</ul></div><div><h3>Regeln</h3><ul class="p-minus p-minus--neutral">' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $bt['aenderung']['regeln'])) . '</ul></div></div>'
     . '<p class="p-small"><b>Beispiele:</b> ' . h(implode(' · ', array_map(function ($b) { return $b[0] . ' ≈ ' . $b[1] . ' Min.'; }, $bt['aenderung']['beispiele']))) . '</p></details>';
}

/* Fragebögen */
$vars = ['kunde' => $kunde ? $kunde['name'] : '(Kunde)', 'firma' => $kunde ? ($kunde['firma'] !== '' ? $kunde['firma'] : $kunde['name']) : '(Firma)', 'email' => $kunde ? $kunde['email'] : '', 'telefon' => '', 'website' => $kunde && $kunde['website'] !== '' ? $kunde['website'] : '(Website)',
         'stufe' => $kunde && $kunde['stufe'] !== '' ? $kunde['stufe'] : '(Stufe)', 'zahlung' => $kunde ? ($kunde['zahlung'] === 'jahr' ? 'jährlich' : 'monatlich') : 'monatlich', 'minuten' => (string)(LD_TIER_MIN[$kunde['stufe'] ?? ''] ?? 0), 'minuten_genutzt' => (string)(int)($kunde['minuten_genutzt'] ?? 0)];
$alleAntworten = []; $alleFragen = [];
foreach ($fbKeys as $fb) {
  $fragen = $KAT['fragebogen'][$fb]; $row = pd_row('SELECT * FROM fragebogen WHERE kunde_id = ? AND leistung = ?', [$kundeId, $fb]); $ant = $row ? (json_decode((string)$row['antworten'], true) ?: []) : [];
  foreach ($fragen as $f) { $alleFragen[] = [$f[0], $f[1]]; if (isset($ant[$f[0]])) $alleAntworten[$f[0]] = $ant[$f[0]]; }
  $beantwortet = count(array_filter($fragen, function ($f) use ($ant) { return trim((string)($ant[$f[0]] ?? '')) !== ''; }));
  $o .= '<section class="p-card"><div class="p-card-head"><h2>' . h($FB_NAMEN[$fb] ?? $fb) . '</h2><span>' . ($row ? p_status_badge($row['status'], $FB_STATUS) . ((int)$row['freigabe'] ? ' <span class="p-badge p-badge--ok">im Portal sichtbar</span>' : '') . ' <span class="p-muted p-small">' . p_datum($row['aktualisiert'], true) . '</span>' : '<span class="p-badge">noch nicht gespeichert</span>') . ' <span class="p-badge">' . $beantwortet . ' / ' . count($fragen) . ' beantwortet</span></span></div>'
     . '<form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="l" value="' . h($tab) . '"><input type="hidden" name="k" value="' . $kundeId . '"><input type="hidden" name="fb" value="' . h($fb) . '"><input type="hidden" name="op" value="antworten"><ol class="p-fragen">';
  foreach ($fragen as [$fid, $frage, $hinweis]) $o .= '<li><label>' . h($frage) . ($hinweis !== '' ? '<small class="p-muted">' . h($hinweis) . '</small>' : '') . '<textarea name="a[' . $fid . ']" rows="2" maxlength="2000">' . h((string)($ant[$fid] ?? '')) . '</textarea></label></li>';
  $o .= '</ol><label>Interne Notiz<textarea name="notiz" rows="1" maxlength="2000">' . h((string)($row['notiz'] ?? '')) . '</textarea></label><div class="p-actions"><button class="btn btn--primary btn--small" type="submit">Antworten speichern</button>'
     . ($kunde ? '<button class="btn btn--line btn--small" type="submit" name="op" value="freigabe"' . ($row && (int)$row['freigabe'] ? ' data-confirm="Hinweis-Mail noch einmal an den Kunden senden?"' : '') . '>' . ($row && (int)$row['freigabe'] ? 'Mail erneut senden' : 'Im Portal freischalten + Mail') . '</button>' . ($row && (int)$row['freigabe'] ? '<button class="p-link" type="submit" name="op" value="zurueck">im Portal ausblenden</button>' : '') : '<span class="p-small p-muted">Kunde wählen, um den Fragebogen im Portal freizuschalten.</span>') . '</div></form>'
     . '<details class="p-details"><summary>Fragen als Text (für E-Mail oder WhatsApp)</summary><textarea class="p-copy" data-copy readonly rows="6">' . h(ld_fragen_text($fb, $kunde ? ($kunde['firma'] !== '' ? $kunde['firma'] : $kunde['name']) : '')) . '</textarea><button class="btn btn--line btn--small" type="button" data-copy-btn>Kopieren</button></details></section>';
}

/* Prompts */
foreach ($prKeys as $pk) {
  $o .= '<section class="p-card"><h2>Prompts: ' . h($tabName) . '</h2><p class="p-small p-muted">Standard-Aufbau aus lou-digital-prompts.md. Platzhalter sind mit Kundendaten und Antworten gefüllt; „(noch offen)“ heißt: Antwort fehlt noch.</p>';
  foreach ($KAT['prompts'][$pk] as [$pid, $ptitel, $ptext]) {
    $txt = ld_prompt_fuellen($ptext, $vars, $alleFragen, $alleAntworten);
    $o .= '<div class="p-prompt"><div class="p-card-head"><h3>' . h($ptitel) . '</h3><button class="btn btn--line btn--small" type="button" data-copy-btn>Kopieren</button></div><textarea class="p-copy" data-copy readonly rows="' . min(22, max(6, substr_count($txt, "\n") + 2)) . '">' . h($txt) . '</textarea></div>';
  }
  $o .= '</section>';
}

/* Automatisierung (nur auf Büro-Reiter) */
if ($tab === 'buero') {
  $o .= '<section class="p-card"><h2>Was automatisch läuft</h2><table class="p-table"><tr><th>Ablauf</th><th>Stand</th><th>Womit</th><th>Nächster Schritt</th></tr>' . implode('', array_map(function ($z) { return '<tr><td>' . h($z[0]) . '</td><td>' . p_status_badge($z[1], ['automatisch' => 'automatisch', 'halbautomatisch' => 'halbautomatisch', 'manuell' => 'manuell']) . '</td><td>' . h($z[2]) . '</td><td>' . h($z[3]) . '</td></tr>'; }, $KAT['automatisierung'])) . '</table></section>'
     . '<section class="p-card"><h2>Rabatte (genau diese fünf)</h2><ul class="p-list p-list--tight">' . implode('', array_map(function ($r) { return '<li><b>' . h($r[0]) . '</b>: ' . h($r[1]) . '</li>'; }, $KAT['rabatte'])) . '</ul><p class="p-small p-muted">' . h(implode(' · ', $KAT['rabatt_regeln'])) . '</p></section>';
}
out('Prompt-Werkstatt', $o);
