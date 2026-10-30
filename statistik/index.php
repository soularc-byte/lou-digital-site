<?php
/* Lou Digital – Statistik (Version 32, 30.10.2026)
   Die Auswertung liegt jetzt im Kundenportal unter Büro → Statistik und ist durch den Portal-Login geschützt.
   Vorher wurde hier ein beliebiger „Authorization“-Header als Anmeldung gewertet; der Passwortschutz in Plesk kann zusätzlich bleiben. */
declare(strict_types=1);
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$d = (int)($_GET['d'] ?? 30); $seite = is_string($_GET['seite'] ?? null) ? (string)$_GET['seite'] : '/';
$q = '?d=' . (in_array($d, [1, 7, 30, 90, 365], true) ? $d : 30) . (preg_match('~^/[a-z0-9/_.-]{0,80}$~i', $seite) ? '&seite=' . rawurlencode($seite) : '');
header('Location: /portal/admin/statistik' . $q, true, 302);
exit;
