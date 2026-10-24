<?php
/* Lou Digital – Sofort-Check einer Website an fünf Punkten (Version 12, 24.10.2026) */
declare(strict_types=1);
define('LD', 1);
require __DIR__ . '/inc/lib.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ld_json(405, ['ok' => false, 'error' => 'method']);
if (!ld_rate('check', 12)) ld_json(429, ['ok' => false, 'error' => 'rate']);
$lang = ld_post('sprache', 2) === 'en' ? 'en' : 'de';
$r = ld_check(ld_post('url', 200), $lang);
ld_json($r['ok'] ? 200 : 422, $r);
