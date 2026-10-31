<?php
/* Lou Digital – schreibt den Leistungskatalog als Markdown (für claude/lou-digital-leistungskatalog.md im Projekt).
   Aufruf: php tools/katalog_md.php > /pfad/leistungskatalog.md   (nur Kommandozeile) */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('LD', 1);
$root = dirname(__DIR__);
require $root . '/api/inc/lib.php';
require $root . '/api/inc/portal.php';
require $root . '/api/inc/katalog.php';
require $root . '/api/inc/tiefencheck.php';
$K = ld_katalog();
$eur = function (int $n): string { return number_format($n, 0, ',', '.') . ' €'; };
$o = [];
$o[] = '# Lou Digital – Leistungskatalog v2 (Stand ' . date('d.m.Y') . ')';
$o[] = '';
$o[] = '_Erzeugt aus `api/inc/katalog.php` (Texte, Regeln, Fragen, Prompts) und `api/inc/portal.php` (Preise). Änderungen dort machen, dann `php tools/katalog_md.php` und `php tools/katalog_html.php` laufen lassen. Im Büro: /portal/admin/prompts (Prompt-Werkstatt) und /portal/admin/tiefencheck (Tiefen-Check-Werkzeug)._';
$o[] = '';
$o[] = '## Grundsätze';
$o[] = '- Jede Leistung hat genau eine Aufgabe; nichts ist doppelt: Der kostenlose Website-Check prüft automatisch, der Tiefen-Check prüft von Hand, die Betreuung hält laufend in Ordnung, „Nach Absprache“ fängt alles Übrige zum Festpreis auf.';
$o[] = '- Preise in einer Stelle: `LD_PREISE` (Pilot/Normal je Paket), `LD_TIERS` (S/M/L, E = 0 = nach Absprache), `LD_JAHR_MONATE` (12 Monate zum Preis von 10), `LD_STUNDE` (' . LD_STUNDE . ' € je Stunde = 1 € je Minute), `LD_MIN_SCHRITT` (' . LD_MIN_SCHRITT . ' Minuten), `LD_NOTFALL` (' . LD_NOTFALL . ' €), `LD_EIL_PROZENT` (' . LD_EIL_PROZENT . ' %), `LD_E_AB` (' . LD_E_AB . ' €).';
$o[] = '- Genau fünf Rabatte (siehe unten). Der Tiefen-Check ist kein Rabatt, sondern wird angerechnet.';
$o[] = '';
$o[] = '## Preise auf einen Blick';
$o[] = '';
$o[] = '| Leistung | Pilotpreis (erste ' . LD_PILOT_MAX . ') | Normalpreis | Lieferzeit | Eigener Aufwand |';
$o[] = '|---|---|---|---|---|';
foreach (['check', 'tiefencheck', 'website', 'automatik', 'umfrage', 'betreuung', 'individuell'] as $k) { $l = $K['leistungen'][$k]; $p = LD_PREISE[$k] ?? null; $o[] = '| ' . $l['name'] . ' | ' . ($p ? $eur($p[0]) : ($k === 'check' ? 'kostenlos' : '–')) . ' | ' . ($p ? $eur($p[1]) : $l['preis']) . ' | ' . $l['dauer'] . ' | ' . $l['aufwand'] . ' |'; }
$o[] = '';
$o[] = 'Betreuung: S ' . $eur(LD_TIERS['S']) . ', M ' . $eur(LD_TIERS['M']) . ', L ' . $eur(LD_TIERS['L']) . ' im Monat; jährlich ' . $eur(LD_TIERS['S'] * LD_JAHR_MONATE) . ' / ' . $eur(LD_TIERS['M'] * LD_JAHR_MONATE) . ' / ' . $eur(LD_TIERS['L'] * LD_JAHR_MONATE) . '; E nach Absprache (meist ab ' . LD_E_AB . ' €).';
$o[] = '';
$o[] = '## Leistungen im Detail (drin / nicht drin / Regeln)';
foreach ($K['leistungen'] as $k => $l) {
  $o[] = ''; $o[] = '### ' . $l['name'];
  $o[] = $l['kurz']; $o[] = '';
  $o[] = '- **Preis:** ' . $l['preis'] . ' · **Lieferzeit:** ' . $l['dauer'] . ' · **Aufwand:** ' . $l['aufwand'];
  $o[] = '- **Drin:**'; foreach ($l['drin'] as $x) $o[] = '  - ' . $x;
  $o[] = '- **Nicht drin:**'; foreach ($l['nicht'] as $x) $o[] = '  - ' . $x;
  $o[] = '- **Regeln:**'; foreach ($l['regeln'] as $x) $o[] = '  - ' . $x;
}
$o[] = ''; $o[] = '## Betreuung S / M / L / E im Vergleich'; $o[] = '';
$o[] = '| Leistung | S | M | L | E |'; $o[] = '|---|---|---|---|---|';
foreach ($K['betreuung']['zeilen'] as $z) $o[] = '| ' . implode(' | ', $z) . ' |';
$o[] = '| Preis im Monat | ' . $eur(LD_TIERS['S']) . ' | ' . $eur(LD_TIERS['M']) . ' | ' . $eur(LD_TIERS['L']) . ' | nach Absprache |';
$o[] = '| Preis im Jahr (12 für 10) | ' . $eur(LD_TIERS['S'] * LD_JAHR_MONATE) . ' | ' . $eur(LD_TIERS['M'] * LD_JAHR_MONATE) . ' | ' . $eur(LD_TIERS['L'] * LD_JAHR_MONATE) . ' | nach Absprache |';
$o[] = '';
foreach ($K['betreuung']['stufen'] as $s => $st) { $o[] = '**' . $st['name'] . ' – ' . $st['motto'] . ':** ' . implode('; ', $st['punkte']) . '.'; $o[] = ''; }
$o[] = '### Änderungszeit'; $o[] = '';
$o[] = '**Zählt als Änderung:** ' . implode('; ', $K['betreuung']['aenderung']['zaehlt']) . '.'; $o[] = '';
$o[] = '**Zählt nicht (Festpreis vorab):** ' . implode('; ', $K['betreuung']['aenderung']['zaehlt_nicht']) . '.'; $o[] = '';
$o[] = '**Regeln:**'; foreach ($K['betreuung']['aenderung']['regeln'] as $x) $o[] = '- ' . $x;
$o[] = ''; $o[] = '**Beispiele:** ' . implode(' · ', array_map(function ($b) { return $b[0] . ' ≈ ' . $b[1] . ' Min.'; }, $K['betreuung']['aenderung']['beispiele']));
$o[] = ''; $o[] = '**Zahlung:** ' . implode(' ', $K['betreuung']['zahlung']);
$o[] = ''; $o[] = '## Rabatte (genau diese fünf)'; $o[] = '';
foreach ($K['rabatte'] as $r) $o[] = '- **' . $r[0] . ':** ' . $r[1];
foreach ($K['rabatt_regeln'] as $r) $o[] = '- ' . $r;
$o[] = '- Anrechnung: Tiefen-Check wird bei Beauftragung der Website in 7 Tagen oder einer Umsetzung ab 500 € innerhalb von 60 Tagen voll angerechnet (anfrage.php zieht ihn automatisch ab, wenn Website und Tiefen-Check zusammen angefragt werden).';
$o[] = ''; $o[] = '## Marktvergleich (Stand 31.10.2026, Spannen gerundet)'; $o[] = '';
$o[] = '| Leistung | Freelancer | Agentur | Lou Digital |'; $o[] = '|---|---|---|---|';
foreach ($K['markt'] as $m) $o[] = '| ' . implode(' | ', $m) . ' |';
$o[] = ''; $o[] = 'Quellen: ' . implode(' · ', array_map(function ($q) { return '[' . $q[0] . '](' . $q[1] . ')'; }, $K['markt_quellen']));
$o[] = ''; $o[] = 'Einordnung aus der Recherche: Basis-Wartung bei Freelancern 25–50 €, Mittel 69–99 € mit 30–60 Minuten, Premium 129–199 €; Jahreszahlung = zwei Monate gratis ist üblich. Stundensätze Webdesign Ø 70 € (freelancermap), Eilzuschlag +25–50 %, Wochenende/Nacht +100 %. Bezahlte manuelle Website-Audits 250–900 € (Freelancer) bis 690–890 € (pragma-code Tiefenanalyse), Agentur-SEO-Audits 1.500–2.800 €; kostenlose Kurz-Checks als Einstieg sind üblich, Anrechnung bei Folgeauftrag ebenfalls. Lou Digital liegt bewusst am unteren Rand (Vorlagen, Werkzeuge, kein Büro, § 19 UStG) und verdient über die Betreuung.';
$o[] = ''; $o[] = '## Tiefen-Check: Prüfbereiche (' . count(tc_alle_punkte()) . ' Punkte, von Hand)'; $o[] = '';
foreach (tc_katalog() as $bid => [$bt, $bd, $pts]) { $o[] = '**' . $bt . '** (' . count($pts) . ' Punkte) – ' . $bd; $o[] = ''; foreach ($pts as $pt) $o[] = '- ' . $pt[1] . ' (Gewicht ' . $pt[5] . ', Aufwand ca. ' . $pt[6] . ' Min.)'; $o[] = ''; }
$o[] = 'Bewertung: ok = 1, Verbesserung = 0,5, Mangel = 0, gewichtet (3/2/1) → Wert 0–100. Priorität: Mangel bei Gewicht 3 = Sofort, Gewicht 2 = Bald, sonst Später; Verbesserung bei Gewicht 3 = Bald, sonst Später. Umsetzungsangebot = Summe der Aufwände × 1 € je Minute, auf 10 € gerundet; ab 500 € wird der Tiefen-Check angerechnet.';
$o[] = ''; $o[] = '## Fragebögen (Fragen an den Kunden vor dem Start)';
foreach ($K['fragebogen'] as $k => $fr) { $o[] = ''; $o[] = '### ' . ($K['leistungen'][$k]['name'] ?? ($k === 'aenderung' ? 'Änderungswunsch' : ($k === 'exklusiv' ? 'Betreuung E (Bedarf)' : $k))); $i = 0; foreach ($fr as [$id, $q, $h]) { $i++; $o[] = $i . '. ' . $q . ($h !== '' ? ' _(' . $h . ')_' : '') . ' `' . $id . '`'; } }
$o[] = ''; $o[] = '## Prompts (Standard-Aufbau; Platzhalter {{…}} füllt die Prompt-Werkstatt)';
foreach ($K['prompts'] as $g => $ps) { $o[] = ''; $o[] = '### ' . ($K['leistungen'][$g]['name'] ?? ($g === 'exklusiv' ? 'Betreuung E' : 'Büro')); foreach ($ps as [$id, $t, $txt]) { $o[] = ''; $o[] = '**' . $t . '** (`' . $g . '/' . $id . '`)'; $o[] = ''; $o[] = '```'; $o[] = $txt; $o[] = '```'; } }
$o[] = ''; $o[] = '## Was automatisch läuft'; $o[] = '';
$o[] = '| Ablauf | Stand | Womit | Nächster Schritt |'; $o[] = '|---|---|---|---|';
foreach ($K['automatisierung'] as $a) $o[] = '| ' . implode(' | ', $a) . ' |';
$o[] = '';
echo implode("\n", $o);
