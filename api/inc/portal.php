<?php
/* Lou Digital – Datenschicht für Codes, Aufträge und Kundenportal (Version 30, 29.10.2026)
   SQLite-Datei außerhalb des Webroots: ../ld-data/portal.sqlite. Wird beim ersten Aufruf angelegt.
   Enthält: Preise, Rabattcodes (Pilot, Empfehlung, Aktion), Aufträge, Kunden, Sitzungen, 2FA, Tickets, Dokumente. */
declare(strict_types=1);
if (!defined('LD')) { http_response_code(404); exit; }

/* ---------- Preise und Regeln (eine Stelle für alles) ---------- */
const LD_PREISE = ['website' => [390, 790], 'automatik' => [290, 590], 'umfrage' => [190, 290]]; // [Pilotpreis, Normalpreis]
const LD_TIERS  = ['S' => 29, 'M' => 49, 'L' => 79];
const LD_TIER_MIN = ['S' => 0, 'M' => 30, 'L' => 60];   // Änderungsminuten je Monat
const LD_PILOT_MAX = 5;          // Pilotpreis für die ersten fünf bezahlten Kunden
const LD_PILOT_CODE = 'PILOT';
const LD_EMPF_PROZENT = 10;      // Empfehlungscode: 10 % für den Neukunden
const LD_EMPF_GUTSCHRIFT = 50;   // und 50 € Gutschrift für den Werber
const LD_KOMBI_PROZENT = 10;     // Website + Automatik zusammen

const LD_PAKET_NAMEN = ['website' => 'Website in 7 Tagen', 'automatik' => 'Anfrage-Automatik', 'umfrage' => 'Kundenumfrage', 'betreuung' => 'Betreuung', 'check' => 'Website-Check', 'individuell' => 'Individuelles Paket'];
const LD_STATUS = ['angebot' => 'Angebot gesendet', 'gespraech' => 'Gespräch geführt', 'vertrag' => 'Vertrag geschlossen', 'bezahlt' => 'Bezahlt', 'laufend' => 'In Arbeit', 'fertig' => 'Abgeschlossen', 'abgelehnt' => 'Nicht zustande gekommen'];

function pd(): PDO {
  static $db = null;
  if ($db) return $db;
  $f = ld_dir('ld-data') . '/portal.sqlite';
  $neu = !is_file($f);
  $db = new PDO('sqlite:' . $f, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=4000');
  if ($neu) @chmod($f, 0640);
  pd_schema($db);
  return $db;
}
function pd_schema(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS kunden (id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, name TEXT NOT NULL DEFAULT '', firma TEXT NOT NULL DEFAULT '', telefon TEXT NOT NULL DEFAULT '', website TEXT NOT NULL DEFAULT '',
    rolle TEXT NOT NULL DEFAULT 'kunde', pass_hash TEXT NOT NULL DEFAULT '', pass_wechsel INTEGER NOT NULL DEFAULT 1, totp_secret TEXT NOT NULL DEFAULT '', totp_aktiv INTEGER NOT NULL DEFAULT 0, empfehlungscode TEXT NOT NULL DEFAULT '',
    stufe TEXT NOT NULL DEFAULT '', zahlung TEXT NOT NULL DEFAULT 'monat', betreuung_seit TEXT NOT NULL DEFAULT '', minuten_genutzt INTEGER NOT NULL DEFAULT 0, minuten_monat TEXT NOT NULL DEFAULT '',
    notiz TEXT NOT NULL DEFAULT '', aktiv INTEGER NOT NULL DEFAULT 1, angelegt TEXT NOT NULL, letzter_login TEXT NOT NULL DEFAULT '');
  CREATE TABLE IF NOT EXISTS codes (code TEXT PRIMARY KEY, art TEXT NOT NULL, prozent INTEGER NOT NULL DEFAULT 0, gutschrift INTEGER NOT NULL DEFAULT 0, max_nutzung INTEGER NOT NULL DEFAULT 0, genutzt INTEGER NOT NULL DEFAULT 0,
    bis TEXT NOT NULL DEFAULT '', text TEXT NOT NULL DEFAULT '', kunde_id INTEGER NOT NULL DEFAULT 0, aktiv INTEGER NOT NULL DEFAULT 1, angelegt TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS auftraege (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL DEFAULT 0, email TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', firma TEXT NOT NULL DEFAULT '', telefon TEXT NOT NULL DEFAULT '',
    pakete TEXT NOT NULL DEFAULT '', stufe TEXT NOT NULL DEFAULT '', zahlung TEXT NOT NULL DEFAULT 'monat', pilot INTEGER NOT NULL DEFAULT 0, einmalig INTEGER NOT NULL DEFAULT 0, monatlich INTEGER NOT NULL DEFAULT 0, rabatt INTEGER NOT NULL DEFAULT 0,
    code TEXT NOT NULL DEFAULT '', code_art TEXT NOT NULL DEFAULT '', code_verbraucht INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'angebot', notiz TEXT NOT NULL DEFAULT '', nachricht TEXT NOT NULL DEFAULT '', sprache TEXT NOT NULL DEFAULT 'de',
    angelegt TEXT NOT NULL, aktualisiert TEXT NOT NULL, bezahlt_am TEXT NOT NULL DEFAULT '');
  CREATE TABLE IF NOT EXISTS projekte (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, titel TEXT NOT NULL, art TEXT NOT NULL DEFAULT 'website', status TEXT NOT NULL DEFAULT 'laufend', schritte TEXT NOT NULL DEFAULT '[]', domain TEXT NOT NULL DEFAULT '',
    ziel TEXT NOT NULL DEFAULT '', notiz TEXT NOT NULL DEFAULT '', angelegt TEXT NOT NULL, aktualisiert TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS workflows (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, name TEXT NOT NULL, beschreibung TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'aktiv', ausloeser TEXT NOT NULL DEFAULT '', letzter_lauf TEXT NOT NULL DEFAULT '',
    laeufe INTEGER NOT NULL DEFAULT 0, fehler INTEGER NOT NULL DEFAULT 0, aktualisiert TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS dokumente (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, titel TEXT NOT NULL, datei TEXT NOT NULL, art TEXT NOT NULL DEFAULT 'sonstiges', groesse INTEGER NOT NULL DEFAULT 0, angelegt TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS tickets (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, art TEXT NOT NULL DEFAULT 'support', betreff TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'offen', minuten INTEGER NOT NULL DEFAULT 0, angelegt TEXT NOT NULL, aktualisiert TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS nachrichten (id INTEGER PRIMARY KEY, ticket_id INTEGER NOT NULL, von TEXT NOT NULL, text TEXT NOT NULL, angelegt TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS sitzungen (id TEXT PRIMARY KEY, kunde_id INTEGER NOT NULL, zfa INTEGER NOT NULL DEFAULT 0, csrf TEXT NOT NULL, geraet TEXT NOT NULL DEFAULT '', angelegt TEXT NOT NULL, zuletzt TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS tokens (id TEXT PRIMARY KEY, kunde_id INTEGER NOT NULL, zweck TEXT NOT NULL, daten TEXT NOT NULL DEFAULT '', bis TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS pruefungen (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, url TEXT NOT NULL, ok INTEGER NOT NULL, ms INTEGER NOT NULL DEFAULT 0, code INTEGER NOT NULL DEFAULT 0, ssl_bis TEXT NOT NULL DEFAULT '', zeit TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS gutschriften (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL, betrag INTEGER NOT NULL, grund TEXT NOT NULL, angelegt TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS protokoll (id INTEGER PRIMARY KEY, kunde_id INTEGER NOT NULL DEFAULT 0, aktion TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', zeit TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT NOT NULL DEFAULT '');
  CREATE INDEX IF NOT EXISTS i_auf_k ON auftraege(kunde_id); CREATE INDEX IF NOT EXISTS i_auf_e ON auftraege(email); CREATE INDEX IF NOT EXISTS i_pr_k ON pruefungen(kunde_id, zeit);
  CREATE INDEX IF NOT EXISTS i_na_t ON nachrichten(ticket_id); CREATE INDEX IF NOT EXISTS i_tk_k ON tickets(kunde_id);");
  $db->exec("INSERT OR IGNORE INTO codes (code, art, prozent, max_nutzung, text, angelegt) VALUES ('" . LD_PILOT_CODE . "', 'pilot', 0, " . LD_PILOT_MAX . ", 'Pilotpreis für die ersten fünf Kunden mit Referenz', '" . date('c') . "')");
}
function pd_now(): string { return date('Y-m-d H:i:s'); }
function pd_q(string $sql, array $p = []): PDOStatement { $s = pd()->prepare($sql); $s->execute($p); return $s; }
function pd_row(string $sql, array $p = []): ?array { $r = pd_q($sql, $p)->fetch(); return $r === false ? null : $r; }
function pd_all(string $sql, array $p = []): array { return pd_q($sql, $p)->fetchAll(); }
function pd_set(string $k, string $v): void { pd_q('INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON CONFLICT(schluessel) DO UPDATE SET wert = excluded.wert', [$k, $v]); }
function pd_get(string $k, string $d = ''): string { $r = pd_row('SELECT wert FROM einstellungen WHERE schluessel = ?', [$k]); return $r ? (string)$r['wert'] : $d; }
function pd_log(int $kunde, string $aktion, string $details = ''): void { try { pd_q('INSERT INTO protokoll (kunde_id, aktion, details, zeit) VALUES (?, ?, ?, ?)', [$kunde, $aktion, mb_substr($details, 0, 500), pd_now()]); } catch (Throwable $e) {} }

/* ---------- Pilotpreis: frei, solange weniger als fünf Aufträge mit Pilotpreis bezahlt sind ---------- */
function ld_pilot_frei(): int {
  try { $c = pd_row('SELECT max_nutzung, genutzt, aktiv FROM codes WHERE code = ?', [LD_PILOT_CODE]); } catch (Throwable $e) { return LD_PILOT_MAX; }
  if (!$c || !(int)$c['aktiv']) return 0;
  return max(0, (int)$c['max_nutzung'] - (int)$c['genutzt']);
}
/* Grundpreise je nach Pilotphase: ['website' => [preis, normalpreis], …] */
function ld_preise(bool $pilot): array {
  $o = [];
  foreach (LD_PREISE as $k => [$p, $n]) $o[$k] = [$pilot ? $p : $n, $n];
  return $o;
}
/* Code prüfen (ohne ihn zu verbrauchen). $pakete = gewählte Einmal-Pakete, $tier = Betreuungsstufe oder '' */
function ld_code_pruefen(string $codeIn, array $pakete = [], string $tier = '', bool $jahr = false): array {
  $codeIn = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $codeIn) ?? '');
  $pilotFrei = ld_pilot_frei();
  $preise = ld_preise($pilotFrei > 0);
  $r = ['code' => $codeIn, 'gueltig' => false, 'art' => '', 'prozent' => 0, 'text' => '', 'grund' => '', 'ersparnis' => 0, 'pilot_frei' => $pilotFrei];
  if ($codeIn === '') { $r['grund'] = 'leer'; return $r; }
  try { $c = pd_row('SELECT * FROM codes WHERE code = ?', [$codeIn]); } catch (Throwable $e) { $c = null; }
  if (!$c) { $r['grund'] = preg_match('/^LD-[A-Z0-9]{4,8}$/', $codeIn) ? 'unbekannt_empfehlung' : 'unbekannt'; return $r; }
  $r['art'] = (string)$c['art']; $r['prozent'] = (int)$c['prozent']; $r['text'] = (string)$c['text'];
  if (!(int)$c['aktiv']) { $r['grund'] = 'inaktiv'; return $r; }
  if ($c['bis'] !== '' && $c['bis'] < date('Y-m-d')) { $r['grund'] = 'abgelaufen'; return $r; }
  if ((int)$c['max_nutzung'] > 0 && (int)$c['genutzt'] >= (int)$c['max_nutzung']) { $r['grund'] = 'aufgebraucht'; return $r; }
  if ($c['art'] === 'pilot') {
    // Pilot gilt automatisch; der Code bestätigt nur den Pilotpreis
    foreach ($pakete as $k) if (isset(LD_PREISE[$k])) $r['ersparnis'] += LD_PREISE[$k][1] - LD_PREISE[$k][0];
    $r['gueltig'] = true; $r['grund'] = 'pilot'; return $r;
  }
  $r['gueltig'] = true;
  $r['ersparnis'] = ld_code_rabatt($r['prozent'], $pakete, $preise);
  return $r;
}
/* Rabattbetrag eines Prozent-Codes auf die Einmalsumme (nach Kombi-Rabatt) */
function ld_code_rabatt(int $prozent, array $pakete, array $preise): int {
  if ($prozent <= 0) return 0;
  $einmalig = 0; foreach ($pakete as $k) if (isset($preise[$k])) $einmalig += $preise[$k][0];
  $kombi = in_array('website', $pakete, true) && in_array('automatik', $pakete, true) ? (int)round(($preise['website'][0] + $preise['automatik'][0]) * LD_KOMBI_PROZENT / 100) : 0;
  return (int)round(($einmalig - $kombi) * $prozent / 100);
}
/* Code verbrauchen: erst wenn der Auftrag bezahlt ist. Empfehlungscode -> Gutschrift für den Werber. */
function ld_code_verbrauchen(array $auftrag): void {
  if ((int)$auftrag['code_verbraucht']) return;
  $db = pd();
  $db->beginTransaction();
  try {
    if ((int)$auftrag['pilot']) $db->exec("UPDATE codes SET genutzt = genutzt + 1 WHERE code = '" . LD_PILOT_CODE . "'");
    if ($auftrag['code'] !== '' && $auftrag['code'] !== LD_PILOT_CODE) {
      $c = pd_row('SELECT * FROM codes WHERE code = ?', [$auftrag['code']]);
      if ($c) {
        pd_q('UPDATE codes SET genutzt = genutzt + 1 WHERE code = ?', [$c['code']]);
        if ($c['art'] === 'empfehlung' && (int)$c['kunde_id'] > 0 && (int)$c['gutschrift'] > 0) {
          pd_q('INSERT INTO gutschriften (kunde_id, betrag, grund, angelegt) VALUES (?, ?, ?, ?)', [(int)$c['kunde_id'], (int)$c['gutschrift'], 'Empfehlung ' . ($auftrag['firma'] !== '' ? $auftrag['firma'] : $auftrag['name']) . ' (Auftrag #' . $auftrag['id'] . ')', pd_now()]);
          pd_log((int)$c['kunde_id'], 'gutschrift', $c['gutschrift'] . ' € für Empfehlung, Auftrag #' . $auftrag['id']);
        }
      }
    }
    pd_q('UPDATE auftraege SET code_verbraucht = 1, bezahlt_am = ?, aktualisiert = ? WHERE id = ?', [date('Y-m-d'), pd_now(), (int)$auftrag['id']]);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
/* Empfehlungscode für einen Kunden anlegen (LD-XXXX, eindeutig) */
function ld_empfehlungscode(int $kunde): string {
  $k = pd_row('SELECT empfehlungscode FROM kunden WHERE id = ?', [$kunde]);
  if ($k && $k['empfehlungscode'] !== '') return $k['empfehlungscode'];
  $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  for ($i = 0; $i < 20; $i++) {
    $c = 'LD-'; for ($j = 0; $j < 4; $j++) $c .= $abc[random_int(0, strlen($abc) - 1)];
    if (!pd_row('SELECT 1 FROM codes WHERE code = ?', [$c])) {
      pd_q('INSERT INTO codes (code, art, prozent, gutschrift, max_nutzung, text, kunde_id, angelegt) VALUES (?, ?, ?, ?, 0, ?, ?, ?)', [$c, 'empfehlung', LD_EMPF_PROZENT, LD_EMPF_GUTSCHRIFT, 'Empfehlung', $kunde, pd_now()]);
      pd_q('UPDATE kunden SET empfehlungscode = ? WHERE id = ?', [$c, $kunde]);
      return $c;
    }
  }
  return '';
}
function ld_guthaben(int $kunde): int { $r = pd_row('SELECT COALESCE(SUM(betrag),0) s FROM gutschriften WHERE kunde_id = ?', [$kunde]); return (int)($r['s'] ?? 0); }

/* ---------- Auftrag aus einer Website-Anfrage speichern ---------- */
function ld_auftrag_anlegen(array $d): int {
  $k = pd_row('SELECT id FROM kunden WHERE email = ?', [strtolower($d['email'])]);
  pd_q('INSERT INTO auftraege (kunde_id, email, name, firma, telefon, pakete, stufe, zahlung, pilot, einmalig, monatlich, rabatt, code, code_art, status, nachricht, sprache, angelegt, aktualisiert)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$k ? (int)$k['id'] : 0, strtolower($d['email']), $d['name'], $d['firma'], $d['telefon'], implode(',', $d['pakete']), $d['stufe'], $d['zahlung'], (int)$d['pilot'], (int)$d['einmalig'], (int)$d['monatlich'], (int)$d['rabatt'],
     $d['code'], $d['code_art'], 'angebot', mb_substr($d['nachricht'], 0, 1500), $d['sprache'], pd_now(), pd_now()]);
  return (int)pd()->lastInsertId();
}

/* ---------- Passwörter, Token, TOTP ---------- */
function ld_passwort_neu(int $len = 14): string {
  $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $p = ''; for ($i = 0; $i < $len; $i++) $p .= $abc[random_int(0, strlen($abc) - 1)];
  return substr($p, 0, 4) . '-' . substr($p, 4, 5) . '-' . substr($p, 9);
}
function ld_token_neu(int $kunde, string $zweck, string $daten = '', int $stunden = 24): string {
  $t = bin2hex(random_bytes(24));
  pd_q('DELETE FROM tokens WHERE bis < ? OR (kunde_id = ? AND zweck = ?)', [pd_now(), $kunde, $zweck]);
  pd_q('INSERT INTO tokens (id, kunde_id, zweck, daten, bis) VALUES (?, ?, ?, ?, ?)', [hash('sha256', $t), $kunde, $zweck, $daten, date('Y-m-d H:i:s', time() + $stunden * 3600)]);
  return $t;
}
function ld_token_lesen(string $t, string $zweck, bool $loeschen = true): ?array {
  if (!preg_match('/^[a-f0-9]{48}$/', $t)) return null;
  $r = pd_row('SELECT * FROM tokens WHERE id = ? AND zweck = ? AND bis >= ?', [hash('sha256', $t), $zweck, pd_now()]);
  if ($r && $loeschen) pd_q('DELETE FROM tokens WHERE id = ?', [$r['id']]);
  return $r;
}
function ld_b32(string $bin): string {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $o = '';
  foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
  foreach (str_split($bits, 5) as $ch) $o .= $a[bindec(str_pad($ch, 5, '0'))];
  return $o;
}
function ld_b32dec(string $s): string {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $o = '';
  foreach (str_split(strtoupper(rtrim($s, '='))) as $c) { $i = strpos($a, $c); if ($i === false) continue; $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT); }
  foreach (str_split($bits, 8) as $ch) if (strlen($ch) === 8) $o .= chr(bindec($ch));
  return $o;
}
function ld_totp(string $secret, int $t = 0): string {
  $t = $t ?: (int)floor(time() / 30);
  $h = hash_hmac('sha1', pack('N*', 0, $t), ld_b32dec($secret), true);
  $off = ord($h[19]) & 0xf;
  $code = ((ord($h[$off]) & 0x7f) << 24 | ord($h[$off + 1]) << 16 | ord($h[$off + 2]) << 8 | ord($h[$off + 3])) % 1000000;
  return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}
function ld_totp_ok(string $secret, string $eingabe): bool {
  $e = preg_replace('/\D/', '', $eingabe) ?? '';
  if (strlen($e) !== 6 || $secret === '') return false;
  $t = (int)floor(time() / 30);
  foreach ([0, -1, 1] as $d) if (hash_equals(ld_totp($secret, $t + $d), $e)) return true;
  return false;
}

/* ---------- Website-Prüfung (Erreichbarkeit, Antwortzeit, Zertifikat) ---------- */
function ld_ssl_bis(string $host): string {
  $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
  $s = @stream_socket_client('ssl://' . $host . ':443', $en, $es, 6, STREAM_CLIENT_CONNECT, $ctx);
  if (!$s) return '';
  $p = stream_context_get_params($s); fclose($s);
  $cert = $p['options']['ssl']['peer_certificate'] ?? null;
  if (!$cert) return '';
  $i = openssl_x509_parse($cert);
  return !empty($i['validTo_time_t']) ? date('Y-m-d', (int)$i['validTo_time_t']) : '';
}
function ld_website_pruefen(int $kunde, string $url): ?array {
  $n = ld_norm_url($url);
  if (!$n || !function_exists('curl_init')) return null;
  $r = ld_fetch('https://' . $n['host'] . $n['path']);
  $row = ['kunde_id' => $kunde, 'url' => $n['host'], 'ok' => $r['ok'] ? 1 : 0, 'ms' => (int)$r['ms'], 'code' => (int)($r['code'] ?? 0), 'ssl_bis' => $r['ok'] ? ld_ssl_bis($n['host']) : '', 'zeit' => pd_now()];
  pd_q('INSERT INTO pruefungen (kunde_id, url, ok, ms, code, ssl_bis, zeit) VALUES (?, ?, ?, ?, ?, ?, ?)', array_values($row));
  pd_q('DELETE FROM pruefungen WHERE kunde_id = ? AND zeit < ?', [$kunde, date('Y-m-d H:i:s', strtotime('-400 days'))]);
  return $row;
}
