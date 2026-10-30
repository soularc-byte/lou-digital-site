<?php
/* Lou Digital – Reichweitenmessung ohne Cookies (Version 12, 24.10.2026)
   Speichert Seite, Klickziel, Herkunft (nur Domain), Gerätetyp und einen täglich wechselnden,
   nicht rückrechenbaren Besucher-Hash. Keine IP-Adresse, keine Cookies, nichts im Browser. */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|headless|lighthouse|preview|monitor|curl|wget|python|playwright/i', $ua)) { http_response_code(204); exit; }
$raw = file_get_contents('php://input', false, null, 0, 2048) ?: '';
$d = json_decode($raw, true);
if (!is_array($d) || !ld_rate('t', 300)) { http_response_code(204); exit; }   // Version 33: Begrenzung je Besucher
$t = (string)($d['t'] ?? '');
if (!in_array($t, ['pv', 'c', 's', 'f', 'k'], true)) { http_response_code(204); exit; }
$clean = function ($v, int $max) { $v = is_scalar($v) ? (string)$v : ''; $v = preg_replace('/[^\P{C}]/u', '', $v) ?? ''; return mb_substr(trim($v), 0, $max); };
$p = (string)($d['p'] ?? '/');
if (!preg_match('~^/[a-z0-9/_.-]{0,80}$~i', $p)) $p = '/?';
$w = (int)($d['w'] ?? 0);
$dev = $w <= 0 ? '?' : ($w < 700 ? 'mobil' : ($w < 1100 ? 'tablet' : 'desktop'));
$os = preg_match('/iPhone|iPad|iPod/i', $ua) ? 'iOS' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS X|Macintosh/i', $ua) ? 'macOS' : (preg_match('/Linux|CrOS/i', $ua) ? 'Linux' : 'andere'))));
$br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/SamsungBrowser/', $ua) ? 'Samsung' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'andere')))));
$dir = ld_dir('ld-data');
$day = date('Y-m-d');
$saltFile = $dir . '/salt-' . $day;
if (!is_file($saltFile)) { @file_put_contents($saltFile, bin2hex(random_bytes(16))); foreach (glob($dir . '/salt-*') ?: [] as $f) if ($f !== $saltFile) @unlink($f); }
$salt = (string)@file_get_contents($saltFile);
$row = ['ts' => time(), 't' => $t, 'p' => $p, 'v' => substr(hash('sha256', $salt . '|' . ld_ip() . '|' . $ua), 0, 12), 'dev' => $dev, 'os' => $os, 'br' => $br,
        'l' => ($d['l'] ?? '') === 'en' ? 'en' : 'de'];
if ($t === 'pv') { $r = strtolower($clean($d['r'] ?? '', 80)); if ($r !== '' && preg_match('/^[a-z0-9.-]+$/', $r) && strpos($r, 'lou-digital.de') === false) $row['r'] = $r;
  $q = $clean($d['q'] ?? '', 40); if ($q !== '' && preg_match('/^[a-z0-9_.-]+$/i', $q)) $row['q'] = $q; }
if ($t === 'c') { $row['k'] = $clean($d['k'] ?? '', 60); $h = $clean($d['h'] ?? '', 100); if ($h !== '' && preg_match('~^(/|#|https?://|tel:|mailto:)~', $h)) $row['h'] = $h;
  $row['x'] = max(0, min(100, (int)($d['x'] ?? 0))); $row['y'] = max(0, min(100, (int)($d['y'] ?? 0))); }
if ($t === 's') $row['d'] = max(0, min(100, (int)($d['d'] ?? 0)));
if ($t === 'f' || $t === 'k') $row['k'] = $clean($d['k'] ?? '', 30);
$file = $dir . '/events-' . date('Y-m') . '.jsonl';
if (!is_file($file) || filesize($file) < 60 * 1024 * 1024) @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
/* Aufbewahrung: Rohdaten älter als 14 Monate löschen */
if (mt_rand(1, 200) === 1) foreach (glob($dir . '/events-*.jsonl') ?: [] as $f) if (preg_match('/events-(\d{4}-\d{2})\.jsonl$/', $f, $m) && $m[1] < date('Y-m', strtotime('-14 months'))) @unlink($f);
http_response_code(204);
