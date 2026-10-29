<?php
/* Lou Digital – Eingang für Anfrage, Terminwunsch, Frage und Feedback (Version 12, 24.10.2026)
   Schickt die Nachricht an info@ und eine Bestätigung in der Sprache der Seite an den Absender. */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ld_json(405, ['ok' => false, 'error' => 'method']);
if (ld_post('website', 200) !== '') ld_json(200, ['ok' => true]);           // Falle für Bots
if (!ld_rate('form', 6)) ld_json(429, ['ok' => false, 'error' => 'rate']);

$lang = ld_post('sprache', 2) === 'en' ? 'en' : 'de';
$de = $lang === 'de';
$kind = ld_post('anliegen', 30);
$kind = in_array($kind, ['frage', 'feedback'], true) ? $kind : 'anfrage';

$L = [
  'check'       => [$de ? 'Website-Check (kostenlos)' : 'Website check (free)'],
  'website'     => [$de ? 'Website in 7 Tagen (390 €)' : 'Website in 7 days (€390)'],
  'automatik'   => [$de ? 'Anfrage-Automatik (290 €)' : 'Inquiry automation (€290)'],
  'umfrage'     => [$de ? 'Kundenumfrage (190 €)' : 'Customer survey (€190)'],
  'betreuung'   => [$de ? 'Betreuung' : 'Maintenance'],
  'individuell' => [$de ? 'Individuelles Paket' : 'Custom package'],
  'termin'      => [$de ? 'Gespräch vereinbaren' : 'Book a call'],
  'sonstiges'   => [$de ? 'Sonstiges' : 'Other'],
];
$TIERS = ['S' => 29, 'M' => 49, 'L' => 79];
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
if ($msg !== '') $rows[$kind === 'anfrage' ? 'Nachricht' : ($kind === 'frage' ? 'Frage' : 'Vorschlag')] = $msg;
$rows['Sprache'] = strtoupper($lang);
if ($page !== '') $rows['Gesendet von'] = $page;
$rows['Eingang'] = $when . ' · Einwilligung erteilt';

$text = "Neue $kindDe über lou-digital.de\n\n";
foreach ($rows as $k => $v) $text .= str_pad($k . ':', 18) . str_replace("\n", "\n" . str_repeat(' ', 18), $v) . "\n";
if ($check) $text .= "\n" . ld_check_text($check, 'de');
$extra = $check ? ld_check_html($check, 'de') : '';
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
    if ($check) $ci .= '<p style="margin:14px 0 0">' . ($de ? 'Ihr Website-Check ist schon fertig, das Ergebnis finden Sie unten. Wenn Sie über die offenen Punkte sprechen möchten, antworten Sie einfach auf diese E-Mail.' : 'Your website check is already done, see the result below. If you’d like to talk about the open points, simply reply to this email.') . '</p>';
  }
  $ct = strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], $ci));
  foreach ($crows as $k => $v) $ct .= "$k: $v\n";
  if ($check) $ct .= "\n" . ld_check_text($check, $lang);
  $ct .= "\n" . ($de ? "Viele Grüße\nAdrian Semlinger\nLou Digital, Karlsruhe\n" : "Best regards\nAdrian Semlinger\nLou Digital, Karlsruhe\n") . LD_PHONE . "\n" . LD_SITE . "\n\n"
       . ($de ? 'PS: Diese E-Mail wurde automatisch versendet. Wenn Sie auf „Antworten“ klicken, geht Ihre Nachricht direkt an ' . LD_TO . '.' : 'PS: This email was sent automatically. If you click “Reply”, your message goes straight to ' . LD_TO . '.');
  ld_send($email, $cs, html_entity_decode($ct, ENT_QUOTES, 'UTF-8'), ld_mail_html($lang, $cs, $ci, $crows, $check ? ld_check_html($check, $lang) : ''), LD_TO);
}

/* An n8n weiterreichen (Telegram-Meldung + Leads-Tabelle). Adresse steht in ld-private/n8n.php, nicht im Repo. */
$hook = ld_n8n_hook();
if ($hook !== '' && function_exists('curl_init')) {
  $ch = curl_init($hook);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['art' => $kind, 'auswahl' => $topic, 'termin' => $terminText, 'name' => $name, 'firma' => $company, 'email' => $email, 'telefon' => $phone,
      'website_url' => $url, 'check' => $check && !empty($check['ok']) ? $check['score'] . '/5' : '', 'zusammenstellung' => $pkg, 'nachricht' => $msg, 'sprache' => $lang,
      'seite' => $page, 'zeit' => date('Y-m-d H:i'), 'quelle' => 'lou-digital.de'], JSON_UNESCAPED_UNICODE)]);
  @curl_exec($ch); curl_close($ch);
}

ld_json(200, ['ok' => true, 'check' => $check && !empty($check['ok']) ? ['score' => $check['score']] : null]);
