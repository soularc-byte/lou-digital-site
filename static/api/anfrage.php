<?php
/* Lou Digital – Anfrage-Eingang (Version 10, 19.10.2026)
   Nimmt das Formular von /kontakt an, prüft die Eingaben, schickt die Anfrage an info@
   und eine kurze Bestätigung an den Absender. Optional zusätzlich an einen n8n-Webhook. */

declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const TO        = 'info@lou-digital.de';
const FROM      = 'noreply@lou-digital.de';
const FROM_NAME = 'Lou Digital';
const N8N_WEBHOOK = '';          // später z. B. https://n8n.oblivion-hq.duckdns.org/webhook/anfrage
const MAX_PER_HOUR = 5;          // pro IP

function out(int $code, array $data): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function clean(string $k, int $max): string {
  $v = isset($_POST[$k]) ? (string)$_POST[$k] : '';
  $v = str_replace(["\r\n", "\r"], "\n", $v);
  $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '';
  return trim(mb_substr($v, 0, $max));
}
function oneLine(string $v): string { return trim(preg_replace('/\s+/u', ' ', $v) ?? ''); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, ['ok' => false, 'error' => 'method']);

// Honeypot: Bots füllen das versteckte Feld aus – so tun, als wäre alles gut
if (clean('website', 200) !== '') out(200, ['ok' => true]);

// Einfache Begrenzung pro IP und Stunde
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rlFile = sys_get_temp_dir() . '/ld_rl_' . hash('sha256', $ip . date('YmdH'));
$count = is_file($rlFile) ? (int)file_get_contents($rlFile) : 0;
if ($count >= MAX_PER_HOUR) out(429, ['ok' => false, 'error' => 'rate']);
@file_put_contents($rlFile, (string)($count + 1));

$lang    = clean('sprache', 2) === 'en' ? 'en' : 'de';
$topicId = preg_replace('/[^a-z-]/', '', clean('anliegen', 30));
$topic   = oneLine(clean('anliegen_text', 80)) ?: $topicId;
$name    = oneLine(clean('name', 100));
$company = oneLine(clean('firma', 120));
$email   = oneLine(clean('email', 150));
$phone   = oneLine(clean('telefon', 30));
$url     = oneLine(clean('website_url', 200));
$msg     = clean('nachricht', 1500);
$pkg     = clean('zusammenstellung', 1500);
$consent = clean('einwilligung', 5);

$errors = [];
if (mb_strlen($name) < 2) $errors[] = 'name';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if ($phone !== '' && !preg_match('/^[+\d][\d\s\/()-]{5,24}$/', $phone)) $errors[] = 'telefon';
if ($topicId === 'check' && $url === '') $errors[] = 'website_url';
if ($topicId === 'frage' && mb_strlen($msg) < 5) $errors[] = 'nachricht';
if ($url !== '' && !preg_match('~^(https?://)?[\w-]+(\.[\w-]+)+(/\S*)?$~i', $url)) $errors[] = 'website_url';
if ($consent !== '1') $errors[] = 'einwilligung';
if ($errors) out(422, ['ok' => false, 'error' => 'invalid', 'fields' => $errors]);

$when = date('d.m.Y H:i');
$lines = [
  "Neue " . ($topicId === 'frage' ? 'Frage' : 'Anfrage') . " über lou-digital.de ($when, Sprache: " . strtoupper($lang) . ")",
  '',
  "Anliegen:  $topic",
  "Name:      $name",
];
if ($company !== '') $lines[] = "Firma:     $company";
$lines[] = "E-Mail:    $email";
if ($phone !== '') $lines[] = "Telefon:   $phone";
if ($url !== '')   $lines[] = "Website:   $url";
if ($pkg !== '')   { $lines[] = ''; $lines[] = 'Zusammenstellung:'; $lines[] = $pkg; }
if ($msg !== '')   { $lines[] = ''; $lines[] = 'Nachricht:'; $lines[] = $msg; }
$lines[] = '';
$lines[] = 'Einwilligung zur Speicherung: ja';
$body = implode("\n", $lines);

function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
function send(string $to, string $subject, string $body, string $replyTo = ''): bool {
  $h = [
    'From: ' . enc(FROM_NAME) . ' <' . FROM . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: lou-digital.de',
  ];
  if ($replyTo !== '') $h[] = 'Reply-To: ' . $replyTo;
  return @mail($to, enc($subject), $body, implode("\r\n", $h), '-f' . FROM);
}

$isQ = ($topicId === 'frage');
$subject = ($isQ ? 'Frage: ' . mb_substr(oneLine($msg), 0, 60) : 'Anfrage: ' . $topic) . ' – ' . $name;
$sent = send(TO, $subject, $body, $email);
if (!$sent) out(500, ['ok' => false, 'error' => 'mail']);

// Bestätigung an den Absender (ohne Inhalte der Anfrage, damit das Formular nicht zum Spam-Versand taugt)
if ($lang === 'en') {
  $cs = 'Your enquiry to Lou Digital';
  $cb = "Hello $name,\n\nthank you for your enquiry. It has arrived and I will reply personally, usually within 24 hours on working days.\n\nBest regards\nAdrian Semlinger\nLou Digital, Karlsruhe\nhttps://lou-digital.de\n\nThis is an automatic message. Please reply to info@lou-digital.de.";
} else {
  $cs = 'Ihre Anfrage bei Lou Digital';
  $cb = "Guten Tag $name,\n\nvielen Dank für Ihre Anfrage. Sie ist bei mir angekommen, ich melde mich persönlich, in der Regel innerhalb von 24 Stunden an Werktagen.\n\nViele Grüße\nAdrian Semlinger\nLou Digital, Karlsruhe\nhttps://lou-digital.de\n\nDies ist eine automatische Nachricht. Antworten Sie bitte an info@lou-digital.de.";
}
send($email, $cs, $cb, TO);

// Optional: an n8n weiterreichen (Tabelle, Telegram)
if (N8N_WEBHOOK !== '' && function_exists('curl_init')) {
  $ch = curl_init(N8N_WEBHOOK);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['anliegen' => $topic, 'name' => $name, 'firma' => $company, 'email' => $email, 'telefon' => $phone, 'website_url' => $url, 'zusammenstellung' => $pkg, 'nachricht' => $msg, 'sprache' => $lang, 'quelle' => 'lou-digital.de'], JSON_UNESCAPED_UNICODE)]);
  @curl_exec($ch); curl_close($ch);
}

out(200, ['ok' => true]);
