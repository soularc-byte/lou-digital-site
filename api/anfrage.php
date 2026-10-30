<?php
/* Lou Digital – Eingang für Anfrage, Terminwunsch, Frage und Feedback (Version 32, 30.10.2026)
   Version 32: Link zum Auftrag im Portal korrigiert (Büro → Auftrag), Sicherheitsrunde (siehe lib.php, portal.php).
   Version 30: Codes und Pilotpreis aus der Portal-Datenbank, Auftrag wird im Portal angelegt.
   Version 29: unverbindliche Preisübersicht (Angebot) in der Bestätigung, an info@ und an n8n (Leads), Kombi-Rabatt.
   Schickt die Nachricht an info@ und eine Bestätigung in der Sprache der Seite an den Absender. */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/portal.php';


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ld_json(405, ['ok' => false, 'error' => 'method']);
if (ld_post('website', 200) !== '') ld_json(200, ['ok' => true]);           // Falle für Bots
if (!ld_rate('form', 6)) ld_json(429, ['ok' => false, 'error' => 'rate']);

$lang = ld_post('sprache', 2) === 'en' ? 'en' : 'de';
$de = $lang === 'de';
$kind = ld_post('anliegen', 30);
$kind = in_array($kind, ['frage', 'feedback'], true) ? $kind : 'anfrage';

$L = [
  'check'       => [$de ? 'Website-Check (kostenlos)' : 'Website check (free)'],
  'website'     => [$de ? 'Website in 7 Tagen' : 'Website in 7 days'],
  'automatik'   => [$de ? 'Anfrage-Automatik' : 'Inquiry automation'],
  'umfrage'     => [$de ? 'Kundenumfrage' : 'Customer survey'],
  'betreuung'   => [$de ? 'Betreuung' : 'Maintenance'],
  'individuell' => [$de ? 'Individuelles Paket' : 'Custom package'],
  'termin'      => [$de ? 'Gespräch vereinbaren' : 'Book a call'],
  'sonstiges'   => [$de ? 'Sonstiges' : 'Other'],
];
$TIERS = LD_TIERS;
$SLOTS = ['vormittag' => $de ? 'Vormittag (9–12 Uhr)' : 'Morning (9am–12pm)', 'mittag' => $de ? 'Mittag (12–15 Uhr)' : 'Midday (12–3pm)',
          'nachmittag' => $de ? 'Nachmittag (15–18 Uhr)' : 'Afternoon (3–6pm)', 'abend' => $de ? 'Abend (18–20 Uhr)' : 'Evening (6–8pm)'];
$SLOT_H = ['vormittag' => [9, 12], 'mittag' => [12, 15], 'nachmittag' => [15, 18], 'abend' => [18, 20]];
$ARTS = ['telefon' => $de ? 'Telefon' : 'Phone', 'video' => $de ? 'Videocall' : 'Video call', 'vorort' => $de ? 'Vor Ort' : 'In person'];

/* gewählte Pakete: neues Feld pakete[], ältere Links mit ?anliegen=… */
$sel = [];
$raw = $_POST['pakete'] ?? [];
if (is_string($raw)) $raw = explode(',', $raw);
if (is_array($raw)) foreach ($raw as $v) if (is_string($v) && isset($L[$v]) && !in_array($v, $sel, true)) $sel[] = $v;
$legacy = preg_replace('/[^a-z-]/', '', ld_post('anliegen_alt', 30)) ?? '';
if (!$sel && isset($L[$legacy])) $sel[] = $legacy;
$tier = ld_post('betreuung_stufe', 1); if (!isset($TIERS[$tier])) $tier = 'S';
$kombi = ld_post('kombi', 1) === '1';
$jahr = ld_post('zahlung', 10) === 'jahr';
/* Version 30: Codes aus der Datenbank (api/inc/portal.php). Pilotpreis automatisch, solange weniger als fünf Aufträge bezahlt sind. */
$codeIn = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', ld_post('code', 20)) ?? '');
$code = null;
try { $pilotFrei = ld_pilot_frei(); $aktion = ld_aktion_aktiv(); } catch (Throwable $e) { $pilotFrei = 0; $aktion = null; }
$pilot = $aktion && $aktion['art'] === 'pilot';
$aktionTitel = $aktion ? ($aktion['titel'] !== '' ? $aktion['titel'] : $aktion['code']) : '';
if ($codeIn !== '' && $codeIn !== LD_PILOT_CODE) {
  try { $c = ld_code_pruefen($codeIn); } catch (Throwable $e) { $c = ['code' => $codeIn, 'gueltig' => false, 'art' => '', 'prozent' => 0, 'text' => '', 'grund' => 'pruefen']; }
  if ($c['gueltig']) $code = ['code' => $c['code'], 'art' => $c['art'], 'prozent' => $c['prozent'], 'text' => $c['text'], 'gueltig' => true];
  elseif ($c['grund'] === 'unbekannt_empfehlung' || $c['grund'] === 'pruefen') $code = ['code' => $codeIn, 'art' => 'empfehlung', 'prozent' => LD_EMPF_PROZENT, 'text' => '', 'gueltig' => false]; // Adrian prüft vor dem Angebot
  else $code = ['code' => $codeIn, 'art' => $c['art'] !== '' ? $c['art'] : 'unbekannt', 'prozent' => 0, 'text' => '', 'gueltig' => false, 'grund' => $c['grund']];
}
/* Preise der Grundpakete ([aktueller Preis, Normalpreis]) */
$PREIS = ld_preise($aktion);

$name    = ld_line(ld_post('name', 100));
$company = ld_line(ld_post('firma', 120));
$email   = ld_line(ld_post('email', 150));
$phone   = ld_line(ld_post('telefon', 30));
$url     = ld_line(ld_post('website_url', 200));
$msg     = ld_post('nachricht', $kind === 'feedback' ? 1000 : 1500);
$pkg     = ld_post('zusammenstellung', 1500);
$consent = ld_post('einwilligung', 5) === '1';
$page    = preg_replace('~[^a-z0-9/_-]~i', '', ld_post('seite', 80)) ?? '';

$errors = [];
if ($kind !== 'feedback' && mb_strlen($name) < 2) $errors[] = 'name';
if ($kind !== 'feedback' || $email !== '') { if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email'; }
if (in_array($kind, ['frage', 'feedback'], true) && mb_strlen($msg) < 5) $errors[] = 'nachricht';
if ($kind === 'anfrage' && mb_strlen($msg) < 10 && !(mb_strlen($msg) === 0 && mb_strlen($pkg) >= 10)) $errors[] = 'nachricht';   // mit Zusammenstellung aus dem Baukasten ist die Nachricht freiwillig
if ($phone !== '' && !preg_match('/^[+\d][\d\s\/()-]{5,24}$/', $phone)) $errors[] = 'telefon';
if (in_array('check', $sel, true) && $url === '') $errors[] = 'website_url';
if ($url !== '' && !ld_norm_url($url)) $errors[] = 'website_url';
$termin = null;
if (in_array('termin', $sel, true)) {
  $d = ld_post('termin_datum', 10); $slot = ld_post('termin_zeit', 12); $art = ld_post('termin_art', 10);
  $dt = DateTime::createFromFormat('!Y-m-d', $d, new DateTimeZone('Europe/Berlin'));
  $today = new DateTime('today', new DateTimeZone('Europe/Berlin'));
  $days = $dt ? (int)$today->diff($dt)->format('%r%a') : -1;
  if (!$dt || $days < 1 || $days > 60 || $dt->format('N') === '7' || !isset($SLOTS[$slot]) || !isset($ARTS[$art])) $errors[] = 'termin';
  else $termin = ['dt' => $dt, 'slot' => $slot, 'art' => $art];
}
if (!$consent) $errors[] = 'einwilligung';
if ($errors) ld_json(422, ['ok' => false, 'error' => 'invalid', 'fields' => $errors]);

/* Texte der Auswahl */
$labels = [];
foreach ($sel as $k) $labels[] = $k === 'betreuung' ? $L[$k][0] . ' ' . $tier . ' (' . ($de ? $TIERS[$tier] . ' €/Monat' : '€' . $TIERS[$tier] . '/month') . ')' : $L[$k][0];
$topic = $labels ? implode(', ', $labels) : ($de ? 'Allgemeine Anfrage' : 'General inquiry');
$fmtDate = function (DateTime $d) use ($de) {
  $wd = $de ? ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] : ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  return $wd[(int)$d->format('N')] . ', ' . ($de ? $d->format('d.m.Y') : $d->format('j M Y'));
};
$terminText = $termin ? $fmtDate($termin['dt']) . ', ' . $SLOTS[$termin['slot']] . ', ' . $ARTS[$termin['art']] : '';

/* ---------- Unverbindliche Preisübersicht (Angebot) aus der Auswahl ---------- */
$angebot = null;
$posten = [];
$einmalig = 0; $monatlich = 0;
foreach ($sel as $k) {
  if (isset($PREIS[$k])) { $posten[] = [preg_replace('/ \(.*\)$/', '', $L[$k][0]), $PREIS[$k][0], $PREIS[$k][1], 'einmalig']; $einmalig += $PREIS[$k][0]; }
  if ($k === 'betreuung') { $posten[] = [$L[$k][0] . ' ' . $tier, $TIERS[$tier], $TIERS[$tier], 'monat']; $monatlich += $TIERS[$tier]; }
}
$kombiOk = $kombi && in_array('website', $sel, true) && in_array('automatik', $sel, true);
if (!$kombiOk && in_array('website', $sel, true) && in_array('automatik', $sel, true)) $kombiOk = true; // Kombi gilt automatisch
$rabatt = $kombiOk ? (int)round(($PREIS['website'][0] + $PREIS['automatik'][0]) * 0.1) : 0;
$codeRabatt = ($code && $code['prozent'] > 0 && $einmalig) ? (int)round(($einmalig - $rabatt) * $code['prozent'] / 100) : 0;
$codeZeile = $code ? ($de ? 'Code ' . $code['code'] : 'Code ' . $code['code']) . ($code['art'] === 'empfehlung' ? ($de ? ' (Empfehlung, 10 %' . ($code['gueltig'] ? '' : ', wird geprüft') . ')' : ' (referral, 10%' . ($code['gueltig'] ? '' : ', to be verified') . ')') : ($code['art'] === 'aktion' ? ' (' . $code['prozent'] . ' %' . ($code['text'] !== '' ? ', ' . $code['text'] : '') . ')' : ($de ? ' (unbekannt, wird geprüft)' : ' (unknown, to be checked)'))) : '';
if ($posten && $kind === 'anfrage') {
  $eur = function (int $n) use ($de) { return $de ? number_format($n, 0, ',', '.') . ' €' : '€' . number_format($n, 0, '.', ','); };
  $lines = [];
  foreach ($posten as [$n, $pp, $np, $art]) {
    $lines[] = str_pad(($art === 'monat' ? $n . ($de ? ' (monatlich)' : ' (per month)') : $n), 46) . str_pad($eur($pp), 12, ' ', STR_PAD_LEFT);
  }
  $pilotErsparnis = 0; if ($aktion) foreach ($posten as [$n, $pp, $np, $art]) if ($art === 'einmalig') $pilotErsparnis += $np - $pp;
  if ($pilotErsparnis) $lines[] = str_pad(mb_substr(($pilot ? ($de ? 'Pilotpreis' : 'Pilot price') : $aktionTitel) . ' (Code ' . $aktion['code'] . '): ' . ($de ? 'schon abgezogen' : 'already deducted'), 0, 46), 46) . str_pad(($de ? 'spart ' : 'saves ') . $eur($pilotErsparnis), 12, ' ', STR_PAD_LEFT);
  if ($rabatt) $lines[] = str_pad($de ? 'Kombi-Rabatt Website + Automatik (10 %)' : 'Bundle discount website + automation (10%)', 46) . str_pad('-' . $eur($rabatt), 12, ' ', STR_PAD_LEFT);
  if ($codeRabatt) $lines[] = str_pad(mb_substr($codeZeile, 0, 43), 46) . str_pad('-' . $eur($codeRabatt), 12, ' ', STR_PAD_LEFT);
  $sumOnce = $einmalig - $rabatt - $codeRabatt;
  $monatlichText = $jahr && $monatlich ? ($de ? 'Jahreszahlung Betreuung (zwei Monate geschenkt)' : 'Annual payment maintenance (two months free)') : ($de ? 'Monatlich, monatlich kündbar' : 'Monthly, cancel any month');
  $monatlichWert = $jahr && $monatlich ? $eur($monatlich * 10) . ($de ? '/Jahr' : '/year') : $eur($monatlich);
  $angebotText = implode("\n", $lines) . "\n" . str_repeat('-', 58) . "\n";
  if ($einmalig) $angebotText .= str_pad($de ? 'Einmalig' : 'One-off', 46) . str_pad($eur($sumOnce), 12, ' ', STR_PAD_LEFT) . "\n";
  $startpaket = $kombiOk && $monatlich && !$jahr;
  if ($startpaket) $angebotText .= str_pad($de ? 'Startpaket: erster Betreuungsmonat geschenkt' : 'Starter: first month of maintenance free', 46) . str_pad('-' . $eur($monatlich), 12, ' ', STR_PAD_LEFT) . "\n";
  if ($monatlich) $angebotText .= str_pad($monatlichText, 46) . str_pad($monatlichWert, 12, ' ', STR_PAD_LEFT) . "\n";
  $angebotText .= ($pilot ? ($de ? 'Pilotpreise für die ersten fünf Kunden mit Referenz (noch ' . $pilotFrei . ' frei). ' : 'Pilot prices for the first five clients who allow a reference (' . $pilotFrei . ' left). ') : ($aktion ? ($de ? 'Aktionspreise „' . $aktionTitel . '“' . ($aktion['bis'] !== '' ? ' bis ' . date('d.m.Y', strtotime($aktion['bis'])) : '') . '. ' : 'Promotion “' . $aktionTitel . '”' . ($aktion['bis'] !== '' ? ' until ' . date('j M Y', strtotime($aktion['bis'])) : '') . '. ') : ''))
                . ($de ? 'Alle Preise ohne Umsatzsteuer (§ 19 UStG). Unverbindlich, gültig 14 Tage; das verbindliche Angebot bekommen Sie nach unserem Gespräch als PDF.'
                       : 'No VAT charged (§ 19 UStG). Non-binding, valid for 14 days; you receive the binding quote as a PDF after our call.');
  $rowsHtml = '';
  foreach ($posten as [$n, $pp, $np, $art]) {
    $rowsHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #dde2df">' . ld_h($n) . ($art === 'monat' ? '<br><span style="color:#4b585a;font-size:13px">' . ($de ? 'monatlich, jederzeit kündbar' : 'per month, cancel any time') . '</span>' : ($pp < $np ? '<br><span style="color:#4b585a;font-size:13px">' . ($de ? 'Pilotpreis, Normalpreis ' : 'pilot price, regular ') . $eur($np) . '</span>' : '')) . '</td>'
              . '<td style="padding:8px 0;border-bottom:1px solid #dde2df;text-align:right;white-space:nowrap">' . $eur($pp) . ($art === 'monat' ? ($de ? '/Monat' : '/month') : '') . '</td></tr>';
  }
  if ($pilotErsparnis) $rowsHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #dde2df">' . ($pilot ? ($de ? 'Ihre Ersparnis durch den Pilotpreis (Code PILOT)' : 'Your saving from the pilot price (code PILOT)') : ($de ? 'Ihre Ersparnis durch die Aktion ' : 'Your saving from the promotion ') . ld_h($aktionTitel) . ' (Code ' . ld_h($aktion['code']) . ')') . '</td><td style="padding:8px 0;border-bottom:1px solid #dde2df;text-align:right;color:#0b6e69;white-space:nowrap">' . ($de ? 'bereits abgezogen, ' : 'already deducted, ') . $eur($pilotErsparnis) . '</td></tr>';
  if ($rabatt) $rowsHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #dde2df">' . ($de ? 'Kombi-Rabatt Website + Anfrage-Automatik (10 %)' : 'Bundle discount website + inquiry automation (10%)') . '</td><td style="padding:8px 0;border-bottom:1px solid #dde2df;text-align:right;color:#0b6e69;white-space:nowrap">&minus;' . $eur($rabatt) . '</td></tr>';
  if ($codeRabatt) $rowsHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #dde2df">' . ld_h($codeZeile) . '</td><td style="padding:8px 0;border-bottom:1px solid #dde2df;text-align:right;color:#0b6e69;white-space:nowrap">&minus;' . $eur($codeRabatt) . '</td></tr>';
  elseif ($code) $rowsHtml .= '<tr><td colspan="2" style="padding:8px 0;border-bottom:1px solid #dde2df;color:#4b585a;font-size:13px">' . ld_h($codeZeile) . '</td></tr>';
  if ($einmalig) $rowsHtml .= '<tr><td style="padding:10px 0 4px;font-weight:bold">' . ($de ? 'Einmalig' : 'One-off') . '</td><td style="padding:10px 0 4px;text-align:right;font-weight:bold;white-space:nowrap">' . $eur($sumOnce) . '</td></tr>';
  if ($startpaket) $rowsHtml .= '<tr><td style="padding:4px 0;color:#0b6e69">' . ($de ? 'Startpaket: erster Betreuungsmonat geschenkt' : 'Starter: first month of maintenance free') . '</td><td style="padding:4px 0;text-align:right;color:#0b6e69;white-space:nowrap">&minus;' . $eur($monatlich) . '</td></tr>';
  if ($monatlich) $rowsHtml .= '<tr><td style="padding:4px 0;font-weight:bold">' . ld_h($jahr ? ($de ? 'Jährlich (zwei Monate geschenkt)' : 'Yearly (two months free)') : ($de ? 'Monatlich' : 'Monthly')) . '</td><td style="padding:4px 0;text-align:right;font-weight:bold;white-space:nowrap">' . ($jahr ? $eur($monatlich * 10) . ($de ? '/Jahr' : '/year') : $eur($monatlich) . ($de ? '/Monat' : '/month')) . '</td></tr>';
  $angebotHtml = '<h2 style="font-size:17px;margin:22px 0 6px">' . ($de ? 'Ihre unverbindliche Preisübersicht' : 'Your non-binding price overview') . '</h2>'
               . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:15px">' . $rowsHtml . '</table>'
               . '<p style="margin:10px 0 0;color:#4b585a;font-size:13px">' . ($pilot ? ($de ? 'Pilotpreise für die ersten fünf Kunden, die eine Referenz erlauben (noch ' . $pilotFrei . ' frei). ' : 'Pilot prices for the first five clients who allow a reference (' . $pilotFrei . ' left). ') : ($aktion ? ld_h($de ? 'Aktionspreise „' . $aktionTitel . '“' . ($aktion['bis'] !== '' ? ' bis ' . date('d.m.Y', strtotime($aktion['bis'])) : '') . '. ' : 'Promotion “' . $aktionTitel . '”' . ($aktion['bis'] !== '' ? ' until ' . date('j M Y', strtotime($aktion['bis'])) : '') . '. ') : '')) . ($de ? 'Alle Preise ohne Umsatzsteuer (§ 19 UStG). Unverbindlich, 14 Tage gültig. Das verbindliche Angebot erhalten Sie nach unserem Gespräch als PDF.' : 'No VAT charged (§ 19 UStG). Non-binding, valid for 14 days. You receive the binding quote as a PDF after our call.') . '</p>';
  $angebot = ['text' => $angebotText, 'html' => $angebotHtml, 'einmalig' => $sumOnce, 'monatlich' => $monatlich, 'rabatt' => $rabatt + $codeRabatt, 'posten' => count($posten), 'jahr' => $jahr && $monatlich];
}

/* Version 30: Auftrag in der Portal-Datenbank anlegen (Status „Angebot“); Adrian führt ihn im Portal weiter */
$auftragId = 0;
if ($kind === 'anfrage' && $posten) {
  try { $auftragId = ld_auftrag_anlegen(['email' => $email, 'name' => $name, 'firma' => $company, 'telefon' => $phone, 'pakete' => $sel, 'stufe' => in_array('betreuung', $sel, true) ? $tier : '', 'zahlung' => $jahr ? 'jahr' : 'monat', 'pilot' => $pilot ? 1 : 0, 'aktion' => $aktion ? $aktion['code'] : '',
    'einmalig' => $angebot['einmalig'], 'monatlich' => $angebot['monatlich'], 'rabatt' => $angebot['rabatt'], 'code' => $code ? $code['code'] : '', 'code_art' => $code ? $code['art'] : '', 'nachricht' => $msg, 'sprache' => $lang]); } catch (Throwable $e) { $auftragId = 0; }
}

/* Website-Check automatisch mitprüfen */
$check = null;
$checkNote = '';
if (in_array('check', $sel, true) && $url !== '') { $check = ld_check($url, $lang); if (empty($check['ok'])) { $checkNote = 'nicht automatisch möglich (' . ($check['error'] ?? '?') . '), bitte von Hand prüfen'; $check = null; } }

/* ---------- Mail an info@ (immer Deutsch) ---------- */
$when = date('d.m.Y H:i');
$kindDe = ['anfrage' => 'Anfrage', 'frage' => 'Frage', 'feedback' => 'Feedback'][$kind];
if ($kind === 'anfrage') $subject = ($termin ? 'Terminwunsch' : 'Anfrage') . ': ' . mb_substr($topic, 0, 160) . ' – ' . $name;
elseif ($kind === 'frage') $subject = 'Frage: ' . mb_substr(ld_line($msg), 0, 60) . ' – ' . $name;
else $subject = 'Feedback: ' . mb_substr(ld_line($msg), 0, 70);

$rows = [];
if ($kind === 'anfrage') $rows['Auswahl'] = $topic;
if ($termin) $rows['Terminwunsch'] = $terminText;
if ($name !== '') $rows['Name'] = $name;
if ($company !== '') $rows['Firma'] = $company;
if ($email !== '') $rows['E-Mail'] = $email;
if ($phone !== '') $rows['Telefon'] = $phone;
if ($url !== '') $rows['Website'] = $url;
if ($checkNote !== '') $rows['Sofort-Check'] = $checkNote;
if ($pkg !== '') $rows['Zusammenstellung'] = $pkg;
if ($code) $rows['Code'] = $codeZeile . ($code['gueltig'] ? '' : ' – BITTE PRÜFEN');
if ($auftragId) $rows['Portal'] = 'Auftrag #' . $auftragId . ' angelegt (Status Angebot) – ' . LD_SITE . '/portal/admin/auftrag?id=' . $auftragId;
if ($aktion && $posten) $rows[$pilot ? 'Pilot' : 'Aktion'] = $pilot ? 'Pilotpreis aktiv, noch ' . $pilotFrei . ' von ' . LD_PILOT_MAX . ' frei' : $aktionTitel . ' (' . $aktion['code'] . ') aktiv';
if ($angebot) $rows['Preisübersicht'] = ($angebot['einmalig'] ? number_format($angebot['einmalig'], 0, ',', '.') . ' € einmalig' : '') . ($angebot['monatlich'] ? ($angebot['einmalig'] ? ' + ' : '') . $angebot['monatlich'] . ' €/Monat' : '') . ($angebot['rabatt'] ? ' (Rabatte ' . $angebot['rabatt'] . ' € abgezogen)' : '') . ' – unverbindlich an den Kunden gesendet';
if ($msg !== '') $rows[$kind === 'anfrage' ? 'Nachricht' : ($kind === 'frage' ? 'Frage' : 'Vorschlag')] = $msg;
$rows['Sprache'] = strtoupper($lang);
if ($page !== '') $rows['Gesendet von'] = $page;
$rows['Eingang'] = $when . ' · Einwilligung erteilt';

$text = "Neue $kindDe über lou-digital.de\n\n";
foreach ($rows as $k => $v) $text .= str_pad($k . ':', 18) . str_replace("\n", "\n" . str_repeat(' ', 18), $v) . "\n";
if ($check) $text .= "\n" . ld_check_text($check, 'de');
if ($angebot) $text .= "\nPreisübersicht an den Kunden:\n" . $angebot['text'] . "\n";
$extra = ($angebot ? $angebot['html'] : '') . ($check ? ld_check_html($check, 'de') : '');
if ($termin) {
  [$h0, $h1] = $SLOT_H[$termin['slot']];
  $start = (clone $termin['dt'])->setTime($h0, 0); $end = (clone $termin['dt'])->setTime($h1, 0);
  $utc = new DateTimeZone('UTC');
  $cal = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('Lou Digital: ' . $name . ' (' . $ARTS[$termin['art']] . ')')
       . '&dates=' . (clone $start)->setTimezone($utc)->format('Ymd\THis\Z') . '/' . (clone $end)->setTimezone($utc)->format('Ymd\THis\Z')
       . '&details=' . rawurlencode($topic . "\n" . $email . ($phone ? "\n" . $phone : ''));
  $extra .= '<p style="margin:16px 0"><a href="' . ld_h($cal) . '" style="display:inline-block;background:#0b6e69;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:999px;font-weight:bold;font-size:14px">In Google Kalender eintragen</a></p>';
  $text .= "\nKalender: $cal\n";
}
$intro = '<p style="margin:0">' . ($kind === 'feedback' ? 'Jemand hat über die Feedback-Lasche geschrieben.' : 'Antworten Sie direkt auf diese E-Mail, die Antwort geht an ' . ld_h($email) . '.') . '</p>';
$htmlIn = ld_mail_html('de', "Neue $kindDe", $intro, $rows, $extra);
if (!ld_send(LD_TO, $subject, $text, $htmlIn, $email)) ld_json(500, ['ok' => false, 'error' => 'mail']);

/* ---------- Bestätigung an den Absender, in der Sprache der Seite ---------- */
if ($email !== '') {
  $first = $name !== '' ? $name : ($de ? 'und danke' : 'and thank you');
  if ($kind === 'feedback') {
    $cs = $de ? 'Danke für Ihr Feedback' : 'Thank you for your feedback';
    $ci = $de ? '<p style="margin:0">vielen Dank für Ihren Vorschlag. Ich lese jede Rückmeldung selbst und melde mich, falls ich Rückfragen habe.</p>' : '<p style="margin:0">thank you for your suggestion. I read every message myself and will get back to you if I have questions.</p>';
    $crows = [];
  } else {
    $cs = $kind === 'frage' ? ($de ? 'Ihre Frage an Lou Digital' : 'Your question to Lou Digital') : ($termin ? ($de ? 'Ihr Terminwunsch bei Lou Digital' : 'Your appointment request at Lou Digital') : ($de ? 'Ihre Anfrage bei Lou Digital' : 'Your inquiry at Lou Digital'));
    $ci = '<p style="margin:0 0 10px">' . ($de ? 'Guten Tag ' : 'Hello ') . ld_h($name) . ',</p><p style="margin:0">'
        . ($de ? 'vielen Dank, Ihre ' . ($kind === 'frage' ? 'Frage' : 'Anfrage') . ' ist angekommen. Ich melde mich persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.'
               : 'thank you, your ' . ($kind === 'frage' ? 'question' : 'inquiry') . ' has arrived. I will reply personally, usually within 24 hours on working days.')
        . ($termin ? ($de ? ' Ihren Terminwunsch bestätige ich Ihnen per E-Mail.' : ' I will confirm your appointment by email.') : '')
        . '</p>';
    $crows = [];
    if ($kind === 'anfrage' && $labels) $crows[$de ? 'Ihre Auswahl' : 'Your selection'] = $topic;
    if ($termin) $crows[$de ? 'Terminwunsch' : 'Requested slot'] = $terminText;
    if ($angebot) $ci .= '<p style="margin:14px 0 0">' . ($de ? 'Unten finden Sie schon eine unverbindliche Preisübersicht zu Ihrer Auswahl. Im Gespräch klären wir Umfang und Extras, danach bekommen Sie das verbindliche Angebot als PDF.' : 'Below you already find a non-binding price overview for your selection. In our call we settle scope and extras, then you receive the binding quote as a PDF.') . '</p>';
    if ($check) $ci .= '<p style="margin:14px 0 0">' . ($de ? 'Ihr Website-Check ist schon fertig, das Ergebnis finden Sie unten. Wenn Sie über die offenen Punkte sprechen möchten, antworten Sie einfach auf diese E-Mail.' : 'Your website check is already done, see the result below. If you’d like to talk about the open points, simply reply to this email.') . '</p>';
  }
  $ct = strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], $ci));
  foreach ($crows as $k => $v) $ct .= "$k: $v\n";
  if ($angebot) $ct .= "\n" . ($de ? 'Ihre unverbindliche Preisübersicht' : 'Your non-binding price overview') . "\n" . $angebot['text'] . "\n";
  if ($check) $ct .= "\n" . ld_check_text($check, $lang);
  $ct .= "\n" . ($de ? "Viele Grüße\nAdrian Semlinger\nLou Digital, Karlsruhe\n" : "Best regards\nAdrian Semlinger\nLou Digital, Karlsruhe\n") . LD_PHONE . "\n" . LD_SITE . "\n\n"
       . ($de ? 'PS: Diese E-Mail wurde automatisch versendet. Wenn Sie auf „Antworten“ klicken, geht Ihre Nachricht direkt an ' . LD_TO . '.' : 'PS: This email was sent automatically. If you click “Reply”, your message goes straight to ' . LD_TO . '.');
  ld_send($email, $cs, html_entity_decode($ct, ENT_QUOTES, 'UTF-8'), ld_mail_html($lang, $cs, $ci, $crows, ($angebot ? $angebot['html'] : '') . ($check ? ld_check_html($check, $lang) : '')), LD_TO);
}

/* An n8n weiterreichen (Telegram-Meldung + Leads-Tabelle). Adresse steht in ld-private/n8n.php, nicht im Repo. */
$hook = ld_n8n_hook();
if ($hook !== '' && function_exists('curl_init')) {
  $ch = curl_init($hook);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['art' => $kind, 'auswahl' => $topic, 'termin' => $terminText, 'name' => $name, 'firma' => $company, 'email' => $email, 'telefon' => $phone,
      'website_url' => $url, 'check' => $check && !empty($check['ok']) ? $check['score'] . '/5' : '', 'zusammenstellung' => $pkg, 'nachricht' => $msg, 'sprache' => $lang,
      'seite' => $page, 'zeit' => date('Y-m-d H:i'), 'quelle' => 'lou-digital.de',
      'angebot_einmalig' => $angebot ? $angebot['einmalig'] : '', 'angebot_monatlich' => $angebot ? $angebot['monatlich'] : '', 'angebot_rabatt' => $angebot ? $angebot['rabatt'] : '', 'angebot' => $angebot ? $angebot['text'] : '', 'status_vorschlag' => $angebot ? 'Angebot' : 'neu',
      'code' => $code ? $code['code'] : '', 'code_art' => $code ? $code['art'] : '', 'code_gueltig' => $code ? ($code['gueltig'] ? 'ja' : 'prüfen') : '', 'zahlung' => $jahr ? 'jahr' : 'monat', 'pilot' => $pilot ? 'ja' : 'nein', 'aktion' => $aktion ? $aktion['code'] : '', 'auftrag_id' => $auftragId], JSON_UNESCAPED_UNICODE)]);
  @curl_exec($ch); curl_close($ch);
}

ld_json(200, ['ok' => true, 'check' => $check && !empty($check['ok']) ? ['score' => $check['score']] : null]);
