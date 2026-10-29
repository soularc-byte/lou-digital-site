<?php
/* Lou Digital – Kundenportal: Seiten für Kunden (Version 30, 29.10.2026). Wird von index.php eingebunden. */
declare(strict_types=1);
if (!defined('LD') || !isset($me)) { http_response_code(404); exit; }
$kid = (int)$me['id'];
$BETREUUNG = [
  'S' => ['name' => 'Betreuung S', 'preis' => 29, 'min' => 0, 'punkte' => ['Erreichbarkeit alle 5 Minuten geprüft, bei Ausfall kümmere ich mich', 'Tägliche Sicherung, 30 Tage aufbewahrt', 'Updates und Sicherheitsprüfung monatlich', 'SSL-Zertifikat und Domain-Laufzeit im Blick', 'Monatsbericht mit Erreichbarkeit und Ladezeit', 'Änderungen 15 € je angefangene 15 Minuten', 'Antwort in 2 Werktagen']],
  'M' => ['name' => 'Betreuung M', 'preis' => 49, 'min' => 30, 'punkte' => ['Alles aus S, Sicherung 90 Tage', 'Updates und Sicherheitsprüfung wöchentlich', 'Formular-Test monatlich', '30 Minuten Änderungen im Monat', 'Antwort in 1 Werktag', 'Website-Check halbjährlich']],
  'L' => ['name' => 'Betreuung L', 'preis' => 79, 'min' => 60, 'punkte' => ['Alles aus M, Sicherung 12 Monate', 'Formular-Test wöchentlich', '60 Minuten Änderungen im Monat, danach 12 € je 15 Min.', 'Antwort am selben Werktag', 'Website-Check vierteljährlich', 'Betreuung der Anfrage-Automatik (Workflows, Tabellen, Nachrichten)']],
];
$TICKET_ART = ['support' => 'Support', 'aenderung' => 'Änderungswunsch', 'frage' => 'Frage', 'upgrade' => 'Betreuung ändern'];
$TICKET_STATUS = ['offen' => 'Offen', 'in_arbeit' => 'In Arbeit', 'wartet' => 'Wartet auf Sie', 'erledigt' => 'Erledigt'];
$DOK_ART = ['angebot' => 'Angebot', 'vertrag' => 'Vertrag', 'rechnung' => 'Rechnung', 'bericht' => 'Bericht', 'zugang' => 'Zugänge', 'sonstiges' => 'Sonstiges'];

$projekte = pd_all('SELECT * FROM projekte WHERE kunde_id = ? ORDER BY id DESC', [$kid]);
$website = $me['website'] !== '' ? $me['website'] : '';
$offen = p_tickets_zaehlen($kid);
$guthaben = ld_guthaben($kid);
$auftraege = pd_all('SELECT * FROM auftraege WHERE kunde_id = ? OR email = ? ORDER BY id DESC', [$kid, $me['email']]);

/* Minuten-Monat zurücksetzen */
if ($me['stufe'] !== '' && $me['minuten_monat'] !== date('Y-m')) { pd_q('UPDATE kunden SET minuten_genutzt = 0, minuten_monat = ? WHERE id = ?', [date('Y-m'), $kid]); $me['minuten_genutzt'] = 0; }

function k_schritte(array $p): array { $s = json_decode((string)$p['schritte'], true); return is_array($s) ? $s : []; }
function k_fortschritt(array $p): int { $s = k_schritte($p); if (!$s) return 0; $ok = 0; foreach ($s as $x) if (!empty($x['ok'])) $ok++; return (int)round($ok / count($s) * 100); }
function k_letzte_pruefung(int $kid): ?array { return pd_row('SELECT * FROM pruefungen WHERE kunde_id = ? ORDER BY id DESC LIMIT 1', [$kid]); }
function k_verfuegbarkeit(int $kid, int $tage): array { $r = pd_row('SELECT COUNT(*) n, SUM(ok) o, AVG(ms) ms FROM pruefungen WHERE kunde_id = ? AND zeit >= ?', [$kid, date('Y-m-d H:i:s', strtotime("-$tage days"))]); return ['n' => (int)($r['n'] ?? 0), 'ok' => (int)($r['o'] ?? 0), 'ms' => (int)($r['ms'] ?? 0), 'prozent' => ($r && (int)$r['n'] > 0) ? round((int)$r['o'] / (int)$r['n'] * 100, 2) : null]; }
function k_bot_antwort(array $me, string $frage, array $verlauf): array {
  /* Support-Bot: n8n-Webhook (Büro-KI, Claude Haiku) wenn eingetragen, sonst Antworten aus festen Regeln */
  $url = pd_get('bot_url');
  if ($url !== '' && preg_match('~^https://n8n\.oblivion-hq\.duckdns\.org/webhook/[A-Za-z0-9_/-]+$~', $url) && function_exists('curl_init')) {
    $ctx = ['kunde' => $me['name'], 'firma' => $me['firma'], 'website' => $me['website'], 'betreuung' => $me['stufe'], 'projekte' => array_map(function ($p) { return $p['titel'] . ' (' . k_fortschritt($p) . ' %)'; }, pd_all('SELECT * FROM projekte WHERE kunde_id = ?', [(int)$me['id']])),
            'workflows' => array_map(function ($w) { return $w['name'] . ' (' . $w['status'] . ')'; }, pd_all('SELECT * FROM workflows WHERE kunde_id = ?', [(int)$me['id']]))];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 40, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Portal: lou-digital'],
      CURLOPT_POSTFIELDS => json_encode(['quelle' => 'portal', 'frage' => $frage, 'verlauf' => array_slice($verlauf, -8), 'kontext' => $ctx], JSON_UNESCAPED_UNICODE)]);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $j = is_string($r) ? json_decode($r, true) : null;
    $a = is_array($j) ? (string)($j['antwort'] ?? $j['output'] ?? $j['text'] ?? '') : (is_string($r) ? trim($r) : '');
    if ($code === 200 && $a !== '') return ['text' => mb_substr(str_replace(['**', '__', '##'], '', $a), 0, 4000), 'quelle' => 'ki'];
  }
  $f = mb_strtolower($frage);
  $regeln = [
    ['/passwort|kennwort|anmeld|login/', 'Ihr Passwort ändern Sie unter „Einstellungen“. Nach dem Startpasswort bestätigen Sie jede Änderung per E-Mail. Passwort vergessen: auf der Anmeldeseite „Passwort vergessen“.'],
    ['/2fa|zwei.?faktor|authenticator|totp/', 'Den zweiten Faktor schalten Sie unter „Einstellungen“ ein: QR-Code mit einer Authenticator-App (z. B. Google Authenticator, Microsoft Authenticator, Aegis) scannen und den Code eingeben. Er ist freiwillig.'],
    ['/rechnung|zahlung|bezahl|überweis|konto/', 'Rechnungen finden Sie unter „Dokumente“. Zahlung per Überweisung innerhalb von 14 Tagen; die Betreuung wird monatlich oder jährlich (zwei Monate geschenkt) abgerechnet. Als Kleinunternehmer berechne ich keine Umsatzsteuer (§ 19 UStG).'],
    ['/kündig|beenden|vertrag/', 'Die Betreuung ist monatlich kündbar, bei Jahreszahlung zum Ende des Jahres. Schreiben Sie mir einfach eine Nachricht unter „Nachrichten“, ich bestätige die Kündigung schriftlich. Ihre Website und alle Zugänge bleiben in jedem Fall bei Ihnen.'],
    ['/änder|text|bild|preis|neue seite|anpass/', 'Änderungswünsche schicken Sie unter „Nachrichten“ → „Änderungswunsch“. In Betreuung M sind 30, in L 60 Minuten im Monat enthalten; den Stand sehen Sie unter „Betreuung“. Ohne Betreuung rechne ich 15 € je angefangene 15 Minuten ab.'],
    ['/nicht erreichbar|offline|down|fehler|kaputt|geht nicht|langsam/', 'Unter „Website“ sehen Sie die letzte Prüfung und können sofort neu prüfen lassen. Wenn die Website nicht erreichbar ist, schreiben Sie bitte unter „Nachrichten“ mit Betreff „Notfall“; ich melde mich am selben Werktag.'],
    ['/workflow|automatik|n8n|automatis/', 'Unter „Automatik“ sehen Sie Ihre Workflows mit Status und letztem Lauf. Wenn ein Workflow Fehler zeigt oder etwas Neues automatisiert werden soll, schreiben Sie mir unter „Nachrichten“.'],
    ['/empfehl|gutschrift|code|rabatt/', 'Unter „Empfehlen“ finden Sie Ihren persönlichen Code. Wer damit bucht, bekommt 10 % auf das erste Paket, Sie bekommen 50 € Gutschrift, sobald der Auftrag bezahlt ist. Die Gutschrift wird mit Ihrer nächsten Rechnung verrechnet.'],
    ['/domain|hosting|ssl|zertifikat|e-?mail.*(postfach|einricht)/', 'Domain, Hosting und Zertifikat laufen auf Ihren Namen. Das Ablaufdatum des Zertifikats sehen Sie unter „Website“; es verlängert sich automatisch. Bei Fragen zu Postfächern schreiben Sie mir kurz.'],
    ['/bericht|statistik|besucher|zahlen/', 'Unter „Berichte“ finden Sie je Monat Erreichbarkeit, Antwortzeit, Änderungsminuten und Nachrichten. Besucherzahlen ohne Cookies ergänze ich auf Wunsch.'],
    ['/termin|gespräch|anruf|telefon/', 'Am schnellsten erreichen Sie mich unter ' . LD_PHONE . ' oder per WhatsApp. Für ein Gespräch schlagen Sie unter „Nachrichten“ einfach zwei Zeiten vor.'],
  ];
  foreach ($regeln as [$re, $txt]) if (preg_match($re, $f)) return ['text' => $txt, 'quelle' => 'regel'];
  return ['text' => 'Das kann ich hier nicht sicher beantworten. Mit „An Adrian weitergeben“ wird aus dem Gespräch eine Nachricht, auf die ich persönlich antworte, in der Regel am nächsten Werktag.', 'quelle' => 'regel'];
}

switch ($route) {
/* ===================== Übersicht ===================== */
case '':
  if ($admin) p_go(P_BASE . '/admin');
  $pr = k_letzte_pruefung($kid); $v = k_verfuegbarkeit($kid, 30);
  $b = $me['stufe'] !== '' && isset($BETREUUNG[$me['stufe']]) ? $BETREUUNG[$me['stufe']] : null;
  $k = '<div class="p-kpis">';
  foreach ($projekte as $p) $k .= '<a class="p-kpi" href="' . P_BASE . '/projekt"><b>' . k_fortschritt($p) . ' %</b><span>' . h($p['titel']) . '</span><i class="p-bar"><i style="width:' . k_fortschritt($p) . '%"></i></i></a>';
  if (!$projekte) $k .= '<a class="p-kpi" href="' . P_BASE . '/projekt"><b>–</b><span>Noch kein Projekt angelegt</span></a>';
  $k .= '<a class="p-kpi" href="' . P_BASE . '/website"><b>' . ($pr ? ((int)$pr['ok'] ? 'online' : 'Störung') : '–') . '</b><span>' . ($website !== '' ? h($website) : 'Website') . ($pr ? ' · ' . (int)$pr['ms'] . ' ms' : '') . '</span></a>';
  $k .= '<a class="p-kpi" href="' . P_BASE . '/betreuung"><b>' . ($b ? h($me['stufe']) : 'keine') . '</b><span>' . ($b ? 'Betreuung · ' . ($b['min'] ? max(0, $b['min'] - (int)$me['minuten_genutzt']) . ' von ' . $b['min'] . ' Min. frei' : 'Änderungen nach Aufwand') : 'Betreuung – jederzeit buchbar') . '</span></a>';
  $k .= '<a class="p-kpi" href="' . P_BASE . '/nachrichten"><b>' . $offen . '</b><span>' . ($offen === 1 ? 'offene Nachricht' : 'offene Nachrichten') . '</span></a>';
  $k .= '<a class="p-kpi" href="' . P_BASE . '/empfehlen"><b>' . p_eur($guthaben) . '</b><span>Guthaben aus Empfehlungen</span></a>';
  $k .= '</div>';
  $letzte = pd_all("SELECT t.id, t.betreff, t.status, t.art, n.von, n.text, n.angelegt FROM tickets t JOIN nachrichten n ON n.ticket_id = t.id WHERE t.kunde_id = ? AND t.art <> 'bot' ORDER BY n.id DESC LIMIT 4", [$kid]);
  $l = '';
  foreach ($letzte as $n) $l .= '<li><a href="' . P_BASE . '/ticket?id=' . (int)$n['id'] . '"><b>' . h($n['betreff']) . '</b> <span class="p-muted">' . ($n['von'] === 'kunde' ? 'Sie' : 'Adrian') . ', ' . p_datum($n['angelegt'], true) . '</span><br>' . h(mb_substr($n['text'], 0, 140)) . (mb_strlen($n['text']) > 140 ? ' …' : '') . '</a></li>';
  $aufl = '';
  foreach (array_slice($auftraege, 0, 3) as $a) $aufl .= '<li><b>' . h(implode(', ', array_map(function ($x) { return LD_PAKET_NAMEN[$x] ?? $x; }, array_filter(explode(',', $a['pakete']))))) . '</b> ' . p_status_badge($a['status'], LD_STATUS) . ' <span class="p-muted">' . p_datum($a['angelegt']) . ($a['einmalig'] ? ' · ' . p_eur((int)$a['einmalig']) : '') . ($a['monatlich'] ? ' + ' . p_eur((int)$a['monatlich']) . '/Monat' : '') . '</span></li>';
  out('Übersicht', '<h1 class="p-h1">Guten Tag' . ($me['name'] !== '' ? ', ' . h(explode(' ', $me['name'])[0]) : '') . '</h1><p class="p-lead">Ihr Stand bei Lou Digital auf einen Blick.</p>' . $k
    . '<div class="p-grid2"><section class="p-card"><h2>Letzte Nachrichten</h2>' . ($l ? '<ul class="p-list">' . $l . '</ul>' : p_empty('Noch keine Nachrichten. <a href="' . P_BASE . '/nachrichten">Schreiben Sie mir</a>, wenn etwas ansteht.')) . '</section>'
    . '<section class="p-card"><h2>Aufträge</h2>' . ($aufl ? '<ul class="p-list">' . $aufl . '</ul>' : p_empty('Noch kein Auftrag hinterlegt.')) . '<p class="p-small"><a class="btn btn--line btn--small" href="' . P_BASE . '/nachrichten?neu=aenderung">Änderung wünschen</a> <a class="btn btn--line btn--small" href="' . P_BASE . '/hilfe">Frage stellen</a></p></section></div>');

/* ===================== Projekt ===================== */
case 'projekt':
  $o = '<h1 class="p-h1">Projekt</h1><p class="p-lead">Jeder Schritt mit Datum. Grün ist erledigt, der nächste offene Schritt ist markiert.</p>';
  if (!$projekte) $o .= '<section class="p-card">' . p_empty('Noch kein Projekt angelegt. Sobald wir starten, sehen Sie hier Kickoff, Entwurf, Freigabe, Livegang und Nachsorge mit Terminen.') . '</section>';
  foreach ($projekte as $p) {
    $s = k_schritte($p); $next = true; $li = '';
    foreach ($s as $x) { $ok = !empty($x['ok']); $cls = $ok ? 'ok' : ($next ? 'next' : ''); if (!$ok) $next = false; $li .= '<li class="' . $cls . '"><span class="p-step-t">' . h((string)($x['t'] ?? '')) . '</span><span class="p-step-d">' . h((string)($x['d'] ?? '')) . '</span>' . (!empty($x['n']) ? '<span class="p-step-n">' . h((string)$x['n']) . '</span>' : '') . '</li>'; }
    $o .= '<section class="p-card"><div class="p-card-head"><h2>' . h($p['titel']) . '</h2>' . p_status_badge($p['status'], ['laufend' => 'In Arbeit', 'wartet' => 'Wartet auf Ihre Freigabe', 'fertig' => 'Abgeschlossen', 'pause' => 'Pausiert']) . '</div>'
        . '<div class="p-progress"><i style="width:' . k_fortschritt($p) . '%"></i></div><p class="p-small p-muted">' . k_fortschritt($p) . ' % · ' . ($p['domain'] !== '' ? h($p['domain']) . ' · ' : '') . ($p['ziel'] !== '' ? 'Ziel: ' . h($p['ziel']) . ' · ' : '') . 'Stand ' . p_datum($p['aktualisiert'], true) . '</p>'
        . ($li ? '<ol class="p-steps">' . $li . '</ol>' : p_empty('Schritte folgen.')) . ($p['notiz'] !== '' ? '<p class="p-note">' . nl2br(h($p['notiz'])) . '</p>' : '')
        . ($p['status'] === 'wartet' ? '<p><a class="btn btn--primary btn--small" href="' . P_BASE . '/nachrichten?neu=frage&betreff=' . rawurlencode('Freigabe: ' . $p['titel']) . '">Freigabe oder Änderungen senden</a></p>' : '') . '</section>';
  }
  out('Projekt', $o);

/* ===================== Website ===================== */
case 'website':
  if ($post && isset($_POST['pruefen'])) {
    if ($website === '') p_flash('Es ist noch keine Website hinterlegt. Schreiben Sie mir kurz die Adresse.', 'warn');
    elseif (!ld_rate('pweb' . $kid, 6)) p_flash('Bitte in einer Stunde noch einmal prüfen, das reicht völlig.', 'warn');
    else { $r = ld_website_pruefen($kid, $website); p_flash($r ? ($r['ok'] ? 'Geprüft: online, ' . $r['ms'] . ' ms.' : 'Geprüft: nicht erreichbar (Antwort ' . $r['code'] . '). Ich bekomme eine Nachricht.') : 'Adresse konnte nicht geprüft werden.', $r && $r['ok'] ? 'ok' : 'warn'); if ($r && !$r['ok']) p_adrian('Portal: Website-Störung ' . $website, $me['name'] . ' (' . $me['firma'] . ') hat ' . $website . ' geprüft: nicht erreichbar, Antwort ' . $r['code'] . '.'); }
    p_go(P_BASE . '/website');
  }
  $pr = k_letzte_pruefung($kid); $v30 = k_verfuegbarkeit($kid, 30); $v7 = k_verfuegbarkeit($kid, 7);
  $hist = pd_all('SELECT ok, ms, zeit FROM pruefungen WHERE kunde_id = ? ORDER BY id DESC LIMIT 96', [$kid]);
  $bars = ''; foreach (array_reverse($hist) as $x) { $hgt = max(6, min(100, (int)round((int)$x['ms'] / 20))); $bars .= '<i class="' . ((int)$x['ok'] ? 'ok' : 'bad') . '" style="height:' . $hgt . '%" title="' . p_datum($x['zeit'], true) . ': ' . ((int)$x['ok'] ? (int)$x['ms'] . ' ms' : 'Störung') . '"></i>'; }
  $ssl = $pr && $pr['ssl_bis'] !== '' ? $pr['ssl_bis'] : ''; $sslTage = $ssl !== '' ? (int)floor((strtotime($ssl) - time()) / 86400) : null;
  $k = '<div class="p-kpis"><div class="p-kpi"><b>' . ($pr ? ((int)$pr['ok'] ? 'online' : 'Störung') : '–') . '</b><span>Letzte Prüfung ' . ($pr ? p_datum($pr['zeit'], true) : '–') . '</span></div>'
     . '<div class="p-kpi"><b>' . ($pr ? (int)$pr['ms'] . ' ms' : '–') . '</b><span>Antwortzeit' . ($v30['ms'] ? ' · Ø 30 Tage ' . $v30['ms'] . ' ms' : '') . '</span></div>'
     . '<div class="p-kpi"><b>' . ($v30['prozent'] !== null ? number_format($v30['prozent'], 2, ',', '.') . ' %' : '–') . '</b><span>Erreichbarkeit 30 Tage (' . $v30['n'] . ' Prüfungen)</span></div>'
     . '<div class="p-kpi"><b>' . ($sslTage !== null ? $sslTage . ' Tage' : '–') . '</b><span>Zertifikat gültig bis ' . ($ssl !== '' ? p_datum($ssl) : '–') . '</span></div></div>';
  out('Website', '<h1 class="p-h1">Website</h1><p class="p-lead">' . ($website !== '' ? '<a href="https://' . h(preg_replace('~^https?://~', '', $website)) . '" rel="noopener" target="_blank">' . h($website) . '</a>' : 'Noch keine Adresse hinterlegt.') . '</p>' . $k
    . '<section class="p-card"><div class="p-card-head"><h2>Verlauf der letzten Prüfungen</h2><form method="post">' . p_form($csrf) . '<button class="btn btn--line btn--small" name="pruefen" value="1" type="submit">Jetzt prüfen</button></form></div>'
    . ($bars ? '<div class="p-chart" aria-label="Antwortzeiten">' . $bars . '</div><p class="p-small p-muted">Balkenhöhe = Antwortzeit, rot = nicht erreichbar. Geprüft wird alle 5 Minuten automatisch und auf Knopfdruck.</p>' : p_empty('Noch keine Prüfungen. Klicken Sie auf „Jetzt prüfen“.')) . '</section>'
    . '<section class="p-card"><h2>Was überwacht wird</h2><ul class="haken p-haken"><li>Erreichbarkeit und Antwortzeit (alle 5 Minuten)</li><li>Zertifikat (HTTPS) und Ablaufdatum</li><li>Bei Störung: Nachricht an mich, Beginn der Hilfe am selben Werktag (mit Betreuung)</li><li>Die 100 Prüfpunkte des Website-Checks einmal im Quartal (Bericht unter „Dokumente“)</li></ul></section>');

/* ===================== Betreuung ===================== */
case 'betreuung':
  $b = $me['stufe'] !== '' && isset($BETREUUNG[$me['stufe']]) ? $BETREUUNG[$me['stufe']] : null;
  $o = '<h1 class="p-h1">Betreuung</h1>';
  if ($b) {
    $frei = max(0, $b['min'] - (int)$me['minuten_genutzt']);
    $o .= '<p class="p-lead">' . h($b['name']) . ' · ' . p_eur($b['preis']) . ($me['zahlung'] === 'jahr' ? '/Monat, jährlich bezahlt (' . p_eur($b['preis'] * 10) . '/Jahr)' : '/Monat, monatlich kündbar') . ($me['betreuung_seit'] !== '' ? ' · seit ' . p_datum($me['betreuung_seit']) : '') . '</p>'
        . '<div class="p-kpis">' . ($b['min'] ? '<div class="p-kpi"><b>' . $frei . ' Min.</b><span>frei in ' . date('m/Y') . ' (von ' . $b['min'] . ')</span><i class="p-bar"><i style="width:' . min(100, (int)round((int)$me['minuten_genutzt'] / $b['min'] * 100)) . '%"></i></i></div>' : '') . '<div class="p-kpi"><b>' . (int)$me['minuten_genutzt'] . ' Min.</b><span>' . ($b['min'] ? 'genutzt in diesem Monat' : 'Änderungen in diesem Monat (15 € je 15 Min.)') . '</span></div></div>'
        . '<section class="p-card"><h2>Enthalten</h2><ul class="haken p-haken">'; foreach ($b['punkte'] as $x) $o .= '<li>' . h($x) . '</li>'; $o .= '</ul></section>';
    $ab = pd_all("SELECT betreff, minuten, aktualisiert FROM tickets WHERE kunde_id = ? AND minuten > 0 ORDER BY id DESC LIMIT 12", [$kid]);
    $o .= '<section class="p-card"><h2>Abgerechnete Änderungen</h2>' . ($ab ? '<table class="p-table"><tr><th>Wunsch</th><th>Minuten</th><th>Datum</th></tr>' . implode('', array_map(function ($x) { return '<tr><td>' . h($x['betreff']) . '</td><td>' . (int)$x['minuten'] . '</td><td>' . p_datum($x['aktualisiert']) . '</td></tr>'; }, $ab)) . '</table>' : p_empty('Noch keine Änderungen abgerechnet.')) . '</section>';
  } else {
    $o .= '<p class="p-lead">Sie haben derzeit keine Betreuung. Ihre Website gehört Ihnen genauso; Änderungen rechne ich nach Aufwand ab (15 € je angefangene 15 Minuten).</p>';
  }
  $o .= '<section class="p-card"><h2>Stufen im Vergleich</h2><div class="p-tiers">';
  foreach ($BETREUUNG as $st => $t) $o .= '<div class="p-tier' . ($me['stufe'] === $st ? ' on' : '') . '"><h3>' . h($t['name']) . '</h3><b>' . p_eur($t['preis']) . '<small>/Monat</small></b><ul>' . implode('', array_map(function ($x) { return '<li>' . h($x) . '</li>'; }, $t['punkte'])) . '</ul>' . ($me['stufe'] === $st ? '<span class="p-badge p-badge--ok">Ihre Stufe</span>' : '<a class="btn btn--line btn--small" href="' . P_BASE . '/nachrichten?neu=upgrade&betreff=' . rawurlencode('Betreuung ' . $st . ' ' . ($me['stufe'] === '' ? 'buchen' : 'statt ' . $me['stufe'])) . '">' . ($me['stufe'] === '' ? 'Buchen' : 'Wechseln') . '</a>') . '</div>';
  $o .= '</div><p class="p-small p-muted">Jahreszahlung: zehn Monatsbeiträge für zwölf Monate. Wechsel zum Monatsende, Kündigung jederzeit zum Monatsende (bei Jahreszahlung zum Jahresende). Ohne Umsatzsteuer (§ 19 UStG).</p></section>';
  out('Betreuung', $o);

/* ===================== Workflows ===================== */
case 'workflows':
  $ws = pd_all('SELECT * FROM workflows WHERE kunde_id = ? ORDER BY name', [$kid]);
  $o = '<h1 class="p-h1">Automatik</h1><p class="p-lead">Ihre Workflows, was sie auslöst und wann sie zuletzt gelaufen sind.</p>';
  if (!$ws) $o .= '<section class="p-card">' . p_empty('Noch keine Automatik eingerichtet. Typisch: Anfrage von der Website → sofort auf Ihr Handy, Antwort an den Kunden, Eintrag in Ihre Liste. <a href="' . P_BASE . '/nachrichten?neu=frage&betreff=Anfrage-Automatik">Fragen Sie mich dazu</a>.') . '</section>';
  foreach ($ws as $w) $o .= '<section class="p-card p-wf"><div class="p-card-head"><h2>' . h($w['name']) . '</h2>' . p_status_badge($w['status'], ['aktiv' => 'Aktiv', 'pause' => 'Pausiert', 'fehler' => 'Fehler', 'aufbau' => 'Im Aufbau']) . '</div>' . ($w['beschreibung'] !== '' ? '<p>' . nl2br(h($w['beschreibung'])) . '</p>' : '')
      . '<dl class="p-dl"><dt>Auslöser</dt><dd>' . h($w['ausloeser'] !== '' ? $w['ausloeser'] : '–') . '</dd><dt>Letzter Lauf</dt><dd>' . p_datum($w['letzter_lauf'], true) . '</dd><dt>Läufe gesamt</dt><dd>' . (int)$w['laeufe'] . '</dd><dt>Fehler</dt><dd>' . (int)$w['fehler'] . '</dd></dl>'
      . '<p class="p-small"><a href="' . P_BASE . '/nachrichten?neu=aenderung&betreff=' . rawurlencode('Workflow: ' . $w['name']) . '">Änderung an diesem Workflow wünschen</a></p></section>';
  out('Automatik', $o);

/* ===================== Dokumente ===================== */
case 'dokumente':
  $ds = pd_all('SELECT * FROM dokumente WHERE kunde_id = ? ORDER BY id DESC', [$kid]);
  $o = '<h1 class="p-h1">Dokumente</h1><p class="p-lead">Angebote, Vertrag, Rechnungen, Berichte und Zugangsdaten – alles an einem Ort.</p><section class="p-card">';
  if (!$ds) $o .= p_empty('Noch keine Dokumente. Angebot und Vertrag erscheinen hier, sobald sie erstellt sind.');
  else { $o .= '<table class="p-table"><tr><th>Dokument</th><th>Art</th><th>Datum</th><th>Größe</th><th></th></tr>'; foreach ($ds as $d) $o .= '<tr><td>' . h($d['titel']) . '</td><td>' . h($DOK_ART[$d['art']] ?? $d['art']) . '</td><td>' . p_datum($d['angelegt']) . '</td><td>' . number_format((int)$d['groesse'] / 1024, 0, ',', '.') . ' KB</td><td><a class="btn btn--line btn--small" href="' . P_BASE . '/dokument?id=' . (int)$d['id'] . '">Öffnen</a></td></tr>'; $o .= '</table>'; }
  out('Dokumente', $o . '</section>');

case 'dokument':
  $d = pd_row('SELECT * FROM dokumente WHERE id = ? AND kunde_id = ?', [(int)($_GET['id'] ?? 0), $kid]);
  $f = $d ? ld_dir('ld-data') . '/portal-dokumente/' . $kid . '/' . basename($d['datei']) : '';
  if (!$d || !is_file($f)) { http_response_code(404); out('Nicht gefunden', '<section class="p-card"><h1>Dokument nicht gefunden</h1></section>'); }
  $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
  $mime = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8', 'zip' => 'application/zip', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$ext] ?? 'application/octet-stream';
  header('Content-Type: ' . $mime); header('Content-Length: ' . (string)filesize($f)); header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $d['titel']) . '.' . $ext . '"'); header('X-Content-Type-Options: nosniff');
  pd_log($kid, 'dokument', $d['titel']);
  readfile($f); exit;

/* ===================== Nachrichten / Tickets ===================== */
case 'nachrichten':
  if ($post) {
    $art = (string)($_POST['art'] ?? 'support'); if (!isset($TICKET_ART[$art])) $art = 'support';
    $betreff = ld_line(ld_post('betreff', 120)); $text = ld_post('text', 4000);
    if (mb_strlen($betreff) < 3 || mb_strlen($text) < 5) p_flash('Bitte Betreff und Nachricht ausfüllen.', 'warn');
    elseif (!ld_rate('ptick' . $kid, 20)) p_flash('Zu viele Nachrichten in kurzer Zeit.', 'warn');
    else {
      pd_q('INSERT INTO tickets (kunde_id, art, betreff, status, angelegt, aktualisiert) VALUES (?, ?, ?, ?, ?, ?)', [$kid, $art, $betreff, 'offen', pd_now(), pd_now()]);
      $tid = (int)pd()->lastInsertId();
      pd_q('INSERT INTO nachrichten (ticket_id, von, text, angelegt) VALUES (?, ?, ?, ?)', [$tid, 'kunde', $text, pd_now()]);
      p_adrian('Portal: ' . $TICKET_ART[$art] . ' von ' . ($me['firma'] !== '' ? $me['firma'] : $me['name']) . ' – ' . $betreff, "Von: " . $me['name'] . ' <' . $me['email'] . ">\nArt: " . $TICKET_ART[$art] . "\nBetreff: $betreff\n\n$text\n\nAntworten im Portal: " . LD_SITE . P_BASE . '/admin/ticket?id=' . $tid);
      pd_log($kid, 'ticket_neu', $betreff);
      p_flash('Nachricht gesendet. Ich antworte in der Regel am nächsten Werktag, bei Betreuung L am selben Tag.');
      p_go(P_BASE . '/ticket?id=' . $tid);
    }
    p_go(P_BASE . '/nachrichten');
  }
  $ts = pd_all("SELECT t.*, (SELECT COUNT(*) FROM nachrichten n WHERE n.ticket_id = t.id) anz FROM tickets t WHERE kunde_id = ? AND art <> 'bot' ORDER BY CASE status WHEN 'erledigt' THEN 1 ELSE 0 END, aktualisiert DESC", [$kid]);
  $neu = (string)($_GET['neu'] ?? ''); $vb = ld_line((string)($_GET['betreff'] ?? ''));
  $o = '<h1 class="p-h1">Nachrichten</h1><p class="p-lead">Änderungswünsche, Fragen und Support. Jede Nachricht landet sofort bei mir.</p>'
     . '<section class="p-card" id="neu"><h2>Neue Nachricht</h2><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<div class="p-row"><label>Art<select name="art">';
  foreach ($TICKET_ART as $k => $v) $o .= '<option value="' . $k . '"' . ($neu === $k ? ' selected' : '') . '>' . $v . '</option>';
  $o .= '</select></label><label>Betreff<input name="betreff" maxlength="120" required value="' . h(mb_substr($vb, 0, 120)) . '"></label></div><label>Nachricht<textarea name="text" rows="5" maxlength="4000" required placeholder="Was soll geändert werden oder worum geht es? Bei Änderungen am besten Seite und Stelle nennen."></textarea></label><button class="btn btn--primary" type="submit">Senden</button></form></section>';
  $o .= '<section class="p-card"><h2>Bisherige Nachrichten</h2>' . ($ts ? '<ul class="p-list">' . implode('', array_map(function ($t) use ($TICKET_ART, $TICKET_STATUS) { return '<li><a href="' . P_BASE . '/ticket?id=' . (int)$t['id'] . '"><b>' . h($t['betreff']) . '</b> ' . p_status_badge($t['status'], $TICKET_STATUS) . '<br><span class="p-muted">' . h($TICKET_ART[$t['art']] ?? $t['art']) . ' · ' . (int)$t['anz'] . ' Nachrichten · ' . p_datum($t['aktualisiert'], true) . '</span></a></li>'; }, $ts)) . '</ul>' : p_empty('Noch keine Nachrichten.')) . '</section>';
  out('Nachrichten', $o);

case 'ticket':
  $t = pd_row("SELECT * FROM tickets WHERE id = ? AND kunde_id = ? AND art <> 'bot'", [(int)($_GET['id'] ?? $_POST['id'] ?? 0), $kid]);
  if (!$t) p_go(P_BASE . '/nachrichten');
  if ($post) {
    $text = ld_post('text', 4000);
    if (mb_strlen($text) >= 2 && ld_rate('ptick' . $kid, 20)) {
      pd_q('INSERT INTO nachrichten (ticket_id, von, text, angelegt) VALUES (?, ?, ?, ?)', [(int)$t['id'], 'kunde', $text, pd_now()]);
      pd_q("UPDATE tickets SET status = CASE status WHEN 'erledigt' THEN 'offen' WHEN 'wartet' THEN 'offen' ELSE status END, aktualisiert = ? WHERE id = ?", [pd_now(), (int)$t['id']]);
      p_adrian('Portal: Antwort von ' . ($me['firma'] !== '' ? $me['firma'] : $me['name']) . ' – ' . $t['betreff'], $text . "\n\n" . LD_SITE . P_BASE . '/admin/ticket?id=' . (int)$t['id']);
    }
    p_go(P_BASE . '/ticket?id=' . (int)$t['id']);
  }
  $ns = pd_all('SELECT * FROM nachrichten WHERE ticket_id = ? ORDER BY id', [(int)$t['id']]);
  $o = '<p class="p-small"><a href="' . P_BASE . '/nachrichten">← Alle Nachrichten</a></p><h1 class="p-h1">' . h($t['betreff']) . '</h1><p class="p-lead">' . h($TICKET_ART[$t['art']] ?? $t['art']) . ' · ' . p_status_badge($t['status'], $TICKET_STATUS) . ((int)$t['minuten'] ? ' · ' . (int)$t['minuten'] . ' Minuten abgerechnet' : '') . '</p><div class="p-thread">';
  foreach ($ns as $n) $o .= '<div class="p-msg p-msg--' . h($n['von']) . '"><div class="p-msg-head">' . ($n['von'] === 'kunde' ? 'Sie' : 'Adrian') . ' · ' . p_datum($n['angelegt'], true) . '</div>' . nl2br(h($n['text'])) . '</div>';
  $o .= '</div><section class="p-card"><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="id" value="' . (int)$t['id'] . '"><label>Antwort<textarea name="text" rows="4" maxlength="4000" required></textarea></label><button class="btn btn--primary" type="submit">Antworten</button></form></section>';
  out($t['betreff'], $o);

/* ===================== Hilfe (Support-Bot) ===================== */
case 'hilfe':
  $bt = pd_row("SELECT * FROM tickets WHERE kunde_id = ? AND art = 'bot' ORDER BY id DESC LIMIT 1", [$kid]);
  if (!$bt) { pd_q('INSERT INTO tickets (kunde_id, art, betreff, status, angelegt, aktualisiert) VALUES (?, ?, ?, ?, ?, ?)', [$kid, 'bot', 'Hilfe-Gespräch', 'offen', pd_now(), pd_now()]); $bt = pd_row('SELECT * FROM tickets WHERE id = ?', [(int)pd()->lastInsertId()]); }
  if ($post) {
    if (isset($_POST['weitergeben'])) {
      $ns = pd_all('SELECT * FROM nachrichten WHERE ticket_id = ? ORDER BY id', [(int)$bt['id']]);
      $txt = ''; foreach ($ns as $n) $txt .= ($n['von'] === 'kunde' ? 'Kunde' : 'Bot') . ': ' . $n['text'] . "\n\n";
      pd_q('INSERT INTO tickets (kunde_id, art, betreff, status, angelegt, aktualisiert) VALUES (?, ?, ?, ?, ?, ?)', [$kid, 'support', 'Aus dem Hilfe-Gespräch', 'offen', pd_now(), pd_now()]);
      $tid = (int)pd()->lastInsertId();
      pd_q('INSERT INTO nachrichten (ticket_id, von, text, angelegt) VALUES (?, ?, ?, ?)', [$tid, 'kunde', trim($txt) !== '' ? trim($txt) : 'Bitte um Rückmeldung.', pd_now()]);
      pd_q("UPDATE tickets SET status = 'erledigt' WHERE id = ?", [(int)$bt['id']]);
      p_adrian('Portal: Hilfe-Gespräch weitergegeben – ' . ($me['firma'] !== '' ? $me['firma'] : $me['name']), $txt . "\n" . LD_SITE . P_BASE . '/admin/ticket?id=' . $tid);
      p_flash('Weitergegeben. Ich antworte persönlich unter „Nachrichten“.'); p_go(P_BASE . '/ticket?id=' . $tid);
    }
    if (isset($_POST['neu'])) { pd_q("UPDATE tickets SET status = 'erledigt' WHERE id = ?", [(int)$bt['id']]); p_go(P_BASE . '/hilfe'); }
    $frage = ld_post('frage', 1000);
    if (mb_strlen($frage) >= 2 && ld_rate('pbot' . $kid, 40)) {
      $verlauf = array_map(function ($n) { return ['rolle' => $n['von'] === 'kunde' ? 'user' : 'assistant', 'text' => $n['text']]; }, pd_all('SELECT von, text FROM nachrichten WHERE ticket_id = ? ORDER BY id', [(int)$bt['id']]));
      pd_q('INSERT INTO nachrichten (ticket_id, von, text, angelegt) VALUES (?, ?, ?, ?)', [(int)$bt['id'], 'kunde', $frage, pd_now()]);
      $a = k_bot_antwort($me, $frage, $verlauf);
      pd_q('INSERT INTO nachrichten (ticket_id, von, text, angelegt) VALUES (?, ?, ?, ?)', [(int)$bt['id'], 'bot', $a['text'], pd_now()]);
      pd_q('UPDATE tickets SET aktualisiert = ? WHERE id = ?', [pd_now(), (int)$bt['id']]);
    }
    p_go(P_BASE . '/hilfe#ende');
  }
  $ns = pd_all('SELECT * FROM nachrichten WHERE ticket_id = ? ORDER BY id', [(int)$bt['id']]);
  $o = '<h1 class="p-h1">Hilfe</h1><p class="p-lead">Stellen Sie Ihre Frage. Der Assistent kennt Ihr Projekt, Ihre Betreuung und Ihre Workflows und antwortet sofort. Alles, was er nicht sicher weiß, geben Sie mit einem Klick an mich weiter.</p><div class="p-thread p-thread--bot">';
  if (!$ns) $o .= '<div class="p-msg p-msg--bot"><div class="p-msg-head">Assistent</div>Guten Tag' . ($me['name'] !== '' ? ' ' . h(explode(' ', $me['name'])[0]) : '') . '! Womit kann ich helfen? Zum Beispiel: „Wie ändere ich mein Passwort?“, „Wie viele Minuten habe ich noch?“ oder „Meine Website lädt langsam.“</div>';
  foreach ($ns as $n) $o .= '<div class="p-msg p-msg--' . ($n['von'] === 'kunde' ? 'kunde' : 'bot') . '"><div class="p-msg-head">' . ($n['von'] === 'kunde' ? 'Sie' : 'Assistent') . ' · ' . p_datum($n['angelegt'], true) . '</div>' . nl2br(h($n['text'])) . '</div>';
  $o .= '<span id="ende"></span></div><section class="p-card"><form method="post" class="p-form p-form--wide p-chat">' . p_form($csrf) . '<label>Ihre Frage<textarea name="frage" rows="2" maxlength="1000" required autofocus></textarea></label><div class="p-actions"><button class="btn btn--primary" type="submit">Fragen</button>'
     . ($ns ? '<button class="btn btn--line" type="submit" name="weitergeben" value="1">An Adrian weitergeben</button><button class="p-link" type="submit" name="neu" value="1">Neues Gespräch</button>' : '') . '</div></form><p class="p-small p-muted">Der Assistent antwortet automatisch (Claude Haiku über meinen Server, keine Weitergabe Ihrer Daten an Dritte außer dem KI-Anbieter für die Antwort). Keine Zusagen zu Preisen oder Terminen ohne meine Bestätigung.</p></section>';
  out('Hilfe', $o);

/* ===================== Empfehlen ===================== */
case 'empfehlen':
  $code = ld_empfehlungscode($kid);
  $c = pd_row('SELECT * FROM codes WHERE code = ?', [$code]);
  $gs = pd_all('SELECT * FROM gutschriften WHERE kunde_id = ? ORDER BY id DESC', [$kid]);
  $link = LD_SITE . '/pakete?code=' . $code;
  $txt = 'Ich lasse meine Website von Lou Digital in Karlsruhe betreuen und kann Adrian empfehlen. Mit meinem Code ' . $code . ' bekommst du 10 % auf dein erstes Paket: ' . $link;
  $o = '<h1 class="p-h1">Empfehlen</h1><p class="p-lead">Wer mit Ihrem Code bucht, bekommt ' . LD_EMPF_PROZENT . ' % auf das erste Paket. Sie bekommen ' . p_eur(LD_EMPF_GUTSCHRIFT) . ' Gutschrift, sobald der Auftrag bezahlt ist.</p>'
     . '<div class="p-kpis"><div class="p-kpi"><b class="p-code">' . h($code) . '</b><span>Ihr persönlicher Code</span></div><div class="p-kpi"><b>' . ($c ? (int)$c['genutzt'] : 0) . '</b><span>erfolgreiche Empfehlungen</span></div><div class="p-kpi"><b>' . p_eur($guthaben) . '</b><span>Guthaben, wird mit der nächsten Rechnung verrechnet</span></div></div>'
     . '<section class="p-card"><h2>Teilen</h2><p class="p-small p-muted">Link mit Code (der Code wird auf der Paketseite automatisch eingetragen):</p><div class="p-copy"><input readonly value="' . h($link) . '" data-copy><button class="btn btn--line btn--small" type="button" data-copy-btn>Kopieren</button></div>'
     . '<p class="p-small p-muted">Vorschlag für eine Nachricht:</p><div class="p-copy"><textarea readonly rows="3" data-copy>' . h($txt) . '</textarea><button class="btn btn--line btn--small" type="button" data-copy-btn>Kopieren</button></div>'
     . '<p class="p-small"><a class="btn btn--primary btn--small" href="https://wa.me/?text=' . rawurlencode($txt) . '" rel="noopener" target="_blank">Per WhatsApp teilen</a> <a class="btn btn--line btn--small" href="mailto:?subject=' . rawurlencode('Empfehlung: Lou Digital') . '&body=' . rawurlencode($txt) . '">Per E-Mail teilen</a></p></section>'
     . '<section class="p-card"><h2>Gutschriften</h2>' . ($gs ? '<table class="p-table"><tr><th>Grund</th><th>Betrag</th><th>Datum</th></tr>' . implode('', array_map(function ($g) { return '<tr><td>' . h($g['grund']) . '</td><td>' . p_eur((int)$g['betrag']) . '</td><td>' . p_datum($g['angelegt']) . '</td></tr>'; }, $gs)) . '</table>' : p_empty('Noch keine Gutschrift. Sie erscheint hier, sobald eine Empfehlung bezahlt ist.')) . '</section>'
     . '<section class="p-card"><h2>Regeln</h2><ul class="haken p-haken"><li>Gilt für Neukunden, die noch keine Anfrage gestellt hatten.</li><li>Der Rabatt gilt auf das erste Einmal-Paket (Website, Automatik oder Umfrage), nicht auf die Betreuung.</li><li>Die Gutschrift wird fällig, wenn der empfohlene Auftrag bezahlt ist, und mit Ihrer nächsten Rechnung verrechnet. Auszahlung auf Wunsch ab 100 €.</li><li>Keine Begrenzung: jede bezahlte Empfehlung zählt.</li><li>Bitte den Code nur persönlich weitergeben, keine Werbe-Mails oder Massen-Nachrichten (UWG § 7).</li></ul></section>';
  out('Empfehlen', $o);

/* ===================== Berichte ===================== */
case 'berichte':
  $monat = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : date('Y-m');
  $von = $monat . '-01 00:00:00'; $bis = date('Y-m-t 23:59:59', strtotime($von));
  $pr = pd_row('SELECT COUNT(*) n, SUM(ok) o, AVG(ms) ms, MAX(ms) mx FROM pruefungen WHERE kunde_id = ? AND zeit BETWEEN ? AND ?', [$kid, $von, $bis]);
  $tk = pd_row("SELECT COUNT(*) n, SUM(CASE WHEN status = 'erledigt' THEN 1 ELSE 0 END) e, SUM(minuten) m FROM tickets WHERE kunde_id = ? AND art <> 'bot' AND angelegt BETWEEN ? AND ?", [$kid, $von, $bis]);
  $ws = pd_all('SELECT name, status, laeufe, fehler, letzter_lauf FROM workflows WHERE kunde_id = ?', [$kid]);
  $st = pd_all("SELECT ok, zeit FROM pruefungen WHERE kunde_id = ? AND ok = 0 AND zeit BETWEEN ? AND ? ORDER BY zeit", [$kid, $von, $bis]);
  $sel = ''; for ($i = 0; $i < 12; $i++) { $m = date('Y-m', strtotime("-$i months")); $sel .= '<option value="' . $m . '"' . ($m === $monat ? ' selected' : '') . '>' . date('m/Y', strtotime($m . '-01')) . '</option>'; }
  $b = $me['stufe'] !== '' && isset($BETREUUNG[$me['stufe']]) ? $BETREUUNG[$me['stufe']] : null;
  $o = '<h1 class="p-h1">Berichte</h1><form method="get" class="p-inline"><label>Monat <select name="m" onchange="this.form.submit()">' . $sel . '</select></label></form>'
     . '<div class="p-kpis"><div class="p-kpi"><b>' . ($pr && (int)$pr['n'] ? number_format((int)$pr['o'] / (int)$pr['n'] * 100, 2, ',', '.') . ' %' : '–') . '</b><span>Erreichbarkeit (' . (int)($pr['n'] ?? 0) . ' Prüfungen)</span></div>'
     . '<div class="p-kpi"><b>' . ($pr && $pr['ms'] ? (int)$pr['ms'] . ' ms' : '–') . '</b><span>Ø Antwortzeit' . ($pr && $pr['mx'] ? ', max. ' . (int)$pr['mx'] . ' ms' : '') . '</span></div>'
     . '<div class="p-kpi"><b>' . (int)($tk['n'] ?? 0) . '</b><span>Nachrichten, davon ' . (int)($tk['e'] ?? 0) . ' erledigt</span></div>'
     . '<div class="p-kpi"><b>' . (int)($tk['m'] ?? 0) . ' Min.</b><span>Änderungen abgerechnet' . ($b && $monat === date('Y-m') ? ' (von ' . $b['min'] . ')' : '') . '</span></div></div>'
     . '<section class="p-card"><h2>Störungen</h2>' . ($st ? '<ul class="p-list">' . implode('', array_map(function ($x) { return '<li>' . p_datum($x['zeit'], true) . ' – nicht erreichbar</li>'; }, $st)) . '</ul>' : p_empty('Keine Störung in diesem Monat.')) . '</section>'
     . '<section class="p-card"><h2>Automatik</h2>' . ($ws ? '<table class="p-table"><tr><th>Workflow</th><th>Status</th><th>Läufe</th><th>Fehler</th><th>Letzter Lauf</th></tr>' . implode('', array_map(function ($w) { return '<tr><td>' . h($w['name']) . '</td><td>' . h($w['status']) . '</td><td>' . (int)$w['laeufe'] . '</td><td>' . (int)$w['fehler'] . '</td><td>' . p_datum($w['letzter_lauf'], true) . '</td></tr>'; }, $ws)) . '</table>' : p_empty('Keine Workflows.')) . '</section>'
     . '<p class="p-small p-muted">Der Monatsbericht als PDF liegt jeweils zum Monatsanfang unter „Dokumente“ (mit Betreuung).</p>';
  out('Berichte', $o);

/* ===================== Einstellungen ===================== */
case 'einstellungen':
  $tab = preg_replace('/[^a-z]/', '', (string)($_POST['tab'] ?? '')) ?? '';
  if ($post && $tab === 'profil') {
    $name = ld_line(ld_post('name', 100)); $firma = ld_line(ld_post('firma', 120)); $tel = ld_line(ld_post('telefon', 30)); $web = ld_line(ld_post('website', 200));
    if (mb_strlen($name) < 2) p_flash('Bitte einen Namen angeben.', 'warn');
    elseif ($web !== '' && !ld_norm_url($web)) p_flash('Bitte die Website als ihre-firma.de angeben.', 'warn');
    else { pd_q('UPDATE kunden SET name = ?, firma = ?, telefon = ?, website = ? WHERE id = ?', [$name, $firma, $tel, $web !== '' ? ld_norm_url($web)['host'] : '', $kid]); pd_log($kid, 'profil', ''); p_flash('Gespeichert.'); }
    p_go(P_BASE . '/einstellungen');
  }
  if ($post && $tab === 'passwort') {
    $alt = (string)($_POST['alt'] ?? ''); $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
    if (!password_verify($alt, $me['pass_hash'])) p_flash('Das aktuelle Passwort stimmt nicht.', 'warn');
    elseif ($p1 !== $p2) p_flash('Die neuen Passwörter sind nicht gleich.', 'warn');
    elseif (!p_pw_ok($p1)) p_flash('Mindestens 10 Zeichen, mit Buchstaben und einer Zahl oder einem Sonderzeichen.', 'warn');
    else {
      $t = ld_token_neu($kid, 'pw', password_hash($p1, PASSWORD_DEFAULT), 2);
      p_mail($me, 'Passwortänderung bestätigen – Lou Digital Portal', '<p style="margin:0">Guten Tag ' . h($me['name']) . ',</p><p style="margin:10px 0 0">Sie möchten das Passwort für Ihr Kundenportal ändern. Bitte bestätigen Sie das mit dem Knopf unten (gilt zwei Stunden). Erst dann gilt das neue Passwort; andere Geräte werden abgemeldet.</p>', [], p_btn_link(LD_SITE . P_BASE . '/bestaetigen?z=pw&t=' . $t, 'Neues Passwort bestätigen'));
      p_flash('Bitte bestätigen Sie die Änderung über den Link, den wir gerade an ' . $me['email'] . ' geschickt haben.');
    }
    p_go(P_BASE . '/einstellungen');
  }
  if ($post && $tab === 'email') {
    $neu = strtolower(ld_line(ld_post('email', 150))); $pw = (string)($_POST['pw'] ?? '');
    if (!password_verify($pw, $me['pass_hash'])) p_flash('Das Passwort stimmt nicht.', 'warn');
    elseif (!filter_var($neu, FILTER_VALIDATE_EMAIL) || $neu === $me['email']) p_flash('Bitte eine neue, gültige E-Mail-Adresse angeben.', 'warn');
    elseif (pd_row('SELECT 1 FROM kunden WHERE email = ?', [$neu])) p_flash('Diese Adresse wird schon verwendet.', 'warn');
    else { $t = ld_token_neu($kid, 'email', $neu, 2); ld_send($neu, 'Neue E-Mail-Adresse bestätigen – Lou Digital Portal', 'Bitte bestätigen Sie Ihre neue Adresse (gilt zwei Stunden), dafür müssen Sie im Portal angemeldet sein: ' . LD_SITE . P_BASE . '/bestaetigen?z=email&t=' . $t, ld_mail_html('de', 'Neue E-Mail-Adresse bestätigen', '<p style="margin:0">Bitte bestätigen Sie Ihre neue Adresse für das Lou Digital Portal. Der Link gilt zwei Stunden; Sie müssen dafür im Portal angemeldet sein.</p>', [], p_btn_link(LD_SITE . P_BASE . '/bestaetigen?z=email&t=' . $t, 'Adresse bestätigen'))); p_flash('Bestätigungs-Link an ' . $neu . ' geschickt.'); }
    p_go(P_BASE . '/einstellungen');
  }
  if ($post && $tab === 'zfa') {
    if (isset($_POST['start'])) { $sec = ld_b32(random_bytes(20)); pd_q('UPDATE kunden SET totp_secret = ?, totp_aktiv = 0 WHERE id = ?', [$sec, $kid]); p_go(P_BASE . '/einstellungen#zfa'); }
    if (isset($_POST['aktivieren'])) { if ($me['totp_secret'] !== '' && ld_totp_ok($me['totp_secret'], (string)($_POST['code'] ?? ''))) { pd_q('UPDATE kunden SET totp_aktiv = 1 WHERE id = ?', [$kid]); pd_q('UPDATE sitzungen SET zfa = 1 WHERE id = ?', [$sess['id']]); pd_log($kid, '2fa_an', ''); p_mail($me, 'Zweiter Faktor eingeschaltet – Lou Digital Portal', '<p style="margin:0">Für Ihr Kundenportal ist jetzt der zweite Faktor (Authenticator-App) aktiv. Falls Sie das nicht waren, antworten Sie bitte sofort auf diese E-Mail.</p>'); p_flash('Zweiter Faktor ist aktiv.'); } else p_flash('Der Code stimmt nicht. Bitte den aktuellen Code aus der App eingeben.', 'warn'); }
    if (isset($_POST['aus'])) { if (password_verify((string)($_POST['pw'] ?? ''), $me['pass_hash']) && ld_totp_ok($me['totp_secret'], (string)($_POST['code'] ?? ''))) { pd_q("UPDATE kunden SET totp_secret = '', totp_aktiv = 0 WHERE id = ?", [$kid]); pd_log($kid, '2fa_aus', ''); p_mail($me, 'Zweiter Faktor ausgeschaltet – Lou Digital Portal', '<p style="margin:0">Der zweite Faktor für Ihr Kundenportal wurde ausgeschaltet. Falls Sie das nicht waren, antworten Sie bitte sofort auf diese E-Mail.</p>'); p_flash('Zweiter Faktor ausgeschaltet.'); } else p_flash('Passwort oder Code stimmen nicht.', 'warn'); }
    if (isset($_POST['abbrechen'])) { pd_q("UPDATE kunden SET totp_secret = '' WHERE id = ? AND totp_aktiv = 0", [$kid]); }
    p_go(P_BASE . '/einstellungen#zfa');
  }
  if ($post && $tab === 'sitzungen') { pd_q('DELETE FROM sitzungen WHERE kunde_id = ? AND id <> ?', [$kid, $sess['id']]); p_flash('Alle anderen Geräte wurden abgemeldet.'); p_go(P_BASE . '/einstellungen'); }
  if ($post && $tab === 'export') {
    $data = ['konto' => array_diff_key($me, array_flip(['pass_hash', 'totp_secret'])), 'auftraege' => $auftraege, 'projekte' => $projekte, 'workflows' => pd_all('SELECT * FROM workflows WHERE kunde_id = ?', [$kid]), 'tickets' => pd_all('SELECT * FROM tickets WHERE kunde_id = ?', [$kid]), 'nachrichten' => pd_all('SELECT n.* FROM nachrichten n JOIN tickets t ON t.id = n.ticket_id WHERE t.kunde_id = ?', [$kid]), 'gutschriften' => pd_all('SELECT * FROM gutschriften WHERE kunde_id = ?', [$kid]), 'pruefungen' => pd_all('SELECT * FROM pruefungen WHERE kunde_id = ? ORDER BY id DESC LIMIT 2000', [$kid]), 'protokoll' => pd_all('SELECT * FROM protokoll WHERE kunde_id = ? ORDER BY id DESC LIMIT 500', [$kid])];
    header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="lou-digital-portal-export.json"'); echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
  }
  $sitz = pd_all('SELECT * FROM sitzungen WHERE kunde_id = ? ORDER BY zuletzt DESC', [$kid]);
  $prot = pd_all('SELECT * FROM protokoll WHERE kunde_id = ? ORDER BY id DESC LIMIT 15', [$kid]);
  $AKT = ['login' => 'Anmeldung', 'login_fehl' => 'Fehlgeschlagene Anmeldung', 'pw_reset_mail' => 'Link „Passwort vergessen“ angefordert', 'pw_reset' => 'Passwort zurückgesetzt', 'pw_erstwechsel' => 'Eigenes Passwort gesetzt', 'pw_wechsel' => 'Passwort geändert', 'email_wechsel' => 'E-Mail geändert', '2fa_an' => 'Zweiter Faktor an', '2fa_aus' => 'Zweiter Faktor aus', 'profil' => 'Profil gespeichert', 'ticket_neu' => 'Nachricht gesendet', 'dokument' => 'Dokument geöffnet', 'gutschrift' => 'Gutschrift', 'konto_neu' => 'Konto angelegt', 'pw_neu_admin' => 'Startpasswort neu erzeugt'];
  $o = '<h1 class="p-h1">Einstellungen</h1>'
     . '<section class="p-card"><h2>Profil</h2><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="tab" value="profil"><div class="p-row"><label>Name<input name="name" maxlength="100" required value="' . h($me['name']) . '"></label><label>Firma<input name="firma" maxlength="120" value="' . h($me['firma']) . '"></label></div><div class="p-row"><label>Telefon<input name="telefon" maxlength="30" value="' . h($me['telefon']) . '"></label><label>Website<input name="website" maxlength="200" value="' . h($me['website']) . '" placeholder="ihre-firma.de"></label></div><button class="btn btn--primary btn--small" type="submit">Speichern</button></form></section>'
     . '<section class="p-card"><h2>E-Mail-Adresse</h2><p class="p-small p-muted">Aktuell: ' . h($me['email']) . '. Eine neue Adresse bestätigen Sie über einen Link, der an die neue Adresse geht.</p><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="tab" value="email"><div class="p-row"><label>Neue E-Mail<input name="email" type="email" maxlength="150" required></label><label>Ihr Passwort<input name="pw" type="password" autocomplete="current-password" required></label></div><button class="btn btn--line btn--small" type="submit">Link senden</button></form></section>'
     . '<section class="p-card"><h2>Passwort</h2><p class="p-small p-muted">Die Änderung gilt erst, nachdem Sie den Link in der Bestätigungs-Mail geöffnet haben.</p><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="tab" value="passwort"><label>Aktuelles Passwort<input name="alt" type="password" autocomplete="current-password" required></label><div class="p-row"><label>Neues Passwort<input name="p1" type="password" minlength="10" autocomplete="new-password" required></label><label>Noch einmal<input name="p2" type="password" minlength="10" autocomplete="new-password" required></label></div><button class="btn btn--line btn--small" type="submit">Änderung anfordern</button></form></section>';
  $o .= '<section class="p-card" id="zfa"><h2>Zweiter Faktor (freiwillig)</h2>';
  if ((int)$me['totp_aktiv'] === 1) $o .= '<p><span class="p-badge p-badge--ok">Aktiv</span> Beim Anmelden wird zusätzlich ein Code aus Ihrer Authenticator-App abgefragt.</p><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="tab" value="zfa"><div class="p-row"><label>Passwort<input name="pw" type="password" autocomplete="current-password" required></label><label>Code aus der App<input name="code" inputmode="numeric" maxlength="7" required></label></div><button class="btn btn--line btn--small" type="submit" name="aus" value="1">Ausschalten</button></form>';
  elseif ($me['totp_secret'] !== '') { $uri = 'otpauth://totp/' . rawurlencode('Lou Digital Portal:' . $me['email']) . '?secret=' . $me['totp_secret'] . '&issuer=' . rawurlencode('Lou Digital') . '&digits=6&period=30'; $o .= '<ol class="p-ol"><li>Authenticator-App öffnen (z. B. Google Authenticator, Microsoft Authenticator, Aegis, 1Password).</li><li>Diesen QR-Code scannen oder den Schlüssel eintippen:</li></ol><div class="p-qr" data-qr="' . h($uri) . '"></div><p class="p-code p-code--small">' . h(chunk_split($me['totp_secret'], 4, ' ')) . '</p><form method="post" class="p-form p-form--wide">' . p_form($csrf) . '<input type="hidden" name="tab" value="zfa"><label>Code aus der App<input name="code" inputmode="numeric" maxlength="7" required autocomplete="one-time-code"></label><div class="p-actions"><button class="btn btn--primary btn--small" type="submit" name="aktivieren" value="1">Einschalten</button><button class="p-link" type="submit" name="abbrechen" value="1">Abbrechen</button></div></form>'; }
  else $o .= '<p class="p-small p-muted">Zusätzlich zum Passwort fragt das Portal dann beim Anmelden einen 6-stelligen Code aus einer Authenticator-App ab. Empfohlen, aber freiwillig.</p><form method="post">' . p_form($csrf) . '<input type="hidden" name="tab" value="zfa"><button class="btn btn--line btn--small" type="submit" name="start" value="1">Einrichten</button></form>';
  $o .= '</section><section class="p-card"><h2>Angemeldete Geräte</h2><ul class="p-list">';
  foreach ($sitz as $s) $o .= '<li>' . h($s['geraet']) . ($s['id'] === $sess['id'] ? ' <span class="p-badge p-badge--ok">dieses Gerät</span>' : '') . '<br><span class="p-muted">angemeldet ' . p_datum($s['angelegt'], true) . ', zuletzt aktiv ' . p_datum($s['zuletzt'], true) . '</span></li>';
  $o .= '</ul>' . (count($sitz) > 1 ? '<form method="post">' . p_form($csrf) . '<input type="hidden" name="tab" value="sitzungen"><button class="btn btn--line btn--small" type="submit">Alle anderen Geräte abmelden</button></form>' : '') . '</section>'
     . '<section class="p-card"><h2>Letzte Aktivitäten</h2>' . ($prot ? '<ul class="p-list p-list--tight">' . implode('', array_map(function ($p) use ($AKT) { return '<li>' . p_datum($p['zeit'], true) . ' – ' . h($AKT[$p['aktion']] ?? $p['aktion']) . ($p['details'] !== '' ? ' (' . h($p['details']) . ')' : '') . '</li>'; }, $prot)) . '</ul>' : p_empty('Nichts aufgezeichnet.')) . '</section>'
     . '<section class="p-card"><h2>Ihre Daten</h2><p class="p-small p-muted">Alle Daten aus dem Portal als Datei (JSON). Löschung des Kontos: Nachricht an ' . LD_TO . ', nach Ende der Aufbewahrungsfristen wird alles gelöscht.</p><form method="post">' . p_form($csrf) . '<input type="hidden" name="tab" value="export"><button class="btn btn--line btn--small" type="submit">Daten herunterladen</button></form></section>';
  out('Einstellungen', $o);

default:
  http_response_code(404);
  out('Nicht gefunden', '<section class="p-card"><h1>Seite nicht gefunden</h1><p><a href="' . P_BASE . '">Zur Übersicht</a></p></section>');
}
