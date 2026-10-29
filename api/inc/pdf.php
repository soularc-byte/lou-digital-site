<?php
/* Lou Digital – kleiner PDF-Schreiber ohne Fremdbibliothek (Version 30, 29.10.2026)
   Reicht für Monatsberichte: Text in Helvetica/Helvetica-Bold, Linien, einfache Tabellen, mehrere Seiten, A4.
   Umlaute über Windows-1252. Koordinaten in Punkt (1/72 Zoll), Ursprung oben links wie auf Papier. */
declare(strict_types=1);
if (!defined('LD')) { http_response_code(404); exit; }

final class LdPdf {
  private array $pages = []; private string $cur = ''; private float $y = 0;
  public float $w = 595.28; public float $h = 841.89; public float $m = 56;
  private array $fonts = ['F1' => 'Helvetica', 'F2' => 'Helvetica-Bold', 'F3' => 'Courier'];
  private string $title; private string $footer;
  public function __construct(string $title, string $footer = '') { $this->title = $title; $this->footer = $footer; $this->seite(); }
  public function seite(): void { if ($this->cur !== '') $this->pages[] = $this->cur; $this->cur = ''; $this->y = $this->m; }
  private static function enc(string $s): string { $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s); return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $t); }
  private function raum(float $n): void { if ($this->y + $n > $this->h - $this->m - 20) $this->seite(); }
  public function text(string $s, float $size = 10, string $font = 'F1', float $x = 0, float $dy = 0, int $farbe = 0): void {
    $x = $x ?: $this->m; $this->cur .= sprintf("BT /%s %.1f Tf %s %.2f %.2f Td (%s) Tj ET\n", $font, $size, $farbe ? '0.043 0.431 0.412 rg' : '0.067 0.098 0.106 rg', $x, $this->h - $this->y - $dy, self::enc($s));
  }
  public function zeile(string $s, float $size = 10, string $font = 'F1', float $abstand = 1.45): void {
    foreach ($this->umbruch($s, $size, $this->w - 2 * $this->m) as $z) { $this->raum($size * $abstand); $this->y += $size; $this->text($z, $size, $font); $this->y += $size * ($abstand - 1); }
  }
  public function titel(string $s, float $size = 20): void { $this->y += 6; $this->zeile($s, $size, 'F2', 1.3); $this->y += 4; }
  public function abschnitt(string $s): void { $this->y += 10; $this->zeile($s, 13, 'F2', 1.3); $this->linie(); $this->y += 4; }
  public function leer(float $n = 8): void { $this->y += $n; }
  public function linie(): void { $this->raum(4); $this->cur .= sprintf("0.867 0.886 0.875 RG 0.6 w %.2f %.2f m %.2f %.2f l S\n", $this->m, $this->h - $this->y, $this->w - $this->m, $this->h - $this->y); $this->y += 4; }
  /* Kennzahl-Kacheln: [['Wert', 'Beschriftung'], …] */
  public function kacheln(array $k): void {
    $n = max(1, count($k)); $bw = ($this->w - 2 * $this->m - 8 * ($n - 1)) / $n; $this->raum(56);
    foreach ($k as $i => [$wert, $label]) {
      $x = $this->m + $i * ($bw + 8); $yt = $this->h - $this->y;
      $this->cur .= sprintf("0.961 0.965 0.953 rg %.2f %.2f %.2f %.2f re f\n", $x, $yt - 52, $bw, 52);
      $this->cur .= sprintf("BT /F2 15 Tf 0.067 0.098 0.106 rg %.2f %.2f Td (%s) Tj ET\n", $x + 10, $yt - 24, self::enc($wert));
      $this->cur .= sprintf("BT /F1 8.5 Tf 0.294 0.345 0.353 rg %.2f %.2f Td (%s) Tj ET\n", $x + 10, $yt - 41, self::enc(mb_substr($label, 0, (int)($bw / 4.3))));
    }
    $this->y += 62;
  }
  /* Tabelle: Spaltenbreiten in Punkt, erste Zeile = Kopf */
  public function tabelle(array $rows, array $breiten, float $size = 9): void {
    foreach ($rows as $ri => $row) {
      $lines = 1; $cells = [];
      foreach ($row as $ci => $c) { $cells[$ci] = $this->umbruch((string)$c, $size, $breiten[$ci] - 6); $lines = max($lines, count($cells[$ci])); }
      $hh = $lines * $size * 1.4 + 6; $this->raum($hh);
      if ($ri === 0) $this->cur .= sprintf("0.863 0.937 0.922 rg %.2f %.2f %.2f %.2f re f\n", $this->m, $this->h - $this->y - $hh, array_sum($breiten), $hh);
      $x = $this->m;
      foreach ($cells as $ci => $ls) { foreach ($ls as $li => $l) $this->cur .= sprintf("BT /%s %.1f Tf 0.067 0.098 0.106 rg %.2f %.2f Td (%s) Tj ET\n", $ri === 0 ? 'F2' : 'F1', $size, $x + 3, $this->h - $this->y - 4 - $size - $li * $size * 1.4, self::enc($l)); $x += $breiten[$ci]; }
      $this->y += $hh;
      $this->cur .= sprintf("0.867 0.886 0.875 RG 0.4 w %.2f %.2f m %.2f %.2f l S\n", $this->m, $this->h - $this->y, $this->m + array_sum($breiten), $this->h - $this->y);
    }
    $this->y += 6;
  }
  private function umbruch(string $s, float $size, float $maxw): array {
    $out = []; foreach (explode("\n", $s) as $abs) { $z = ''; foreach (preg_split('/\s+/', $abs) ?: [] as $w) { $t = $z === '' ? $w : $z . ' ' . $w; if (mb_strlen($t) * $size * 0.5 > $maxw && $z !== '') { $out[] = $z; $z = $w; } else $z = $t; } $out[] = $z; }
    return $out;
  }
  public function ausgabe(): string {
    if ($this->cur !== '') { $this->pages[] = $this->cur; $this->cur = ''; }
    $objs = []; $n = count($this->pages);
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $kids = []; for ($i = 0; $i < $n; $i++) $kids[] = (6 + $i * 2) . ' 0 R';
    $objs[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $n >>";
    $objs[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>";
    foreach ($this->pages as $i => $c) {
      $foot = sprintf("BT /F1 8 Tf 0.294 0.345 0.353 rg %.2f %.2f Td (%s) Tj ET\nBT /F1 8 Tf 0.294 0.345 0.353 rg %.2f %.2f Td (Seite %d von %d) Tj ET\n", $this->m, 30, self::enc($this->footer), $this->w - $this->m - 60, 30, $i + 1, $n);
      $c .= $foot;
      $objs[6 + $i * 2] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents %d 0 R >>", $this->w, $this->h, 7 + $i * 2);
      $objs[7 + $i * 2] = "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream";
    }
    $objs[8 + ($n - 1) * 2] = "<< /Title (" . self::enc($this->title) . ") /Producer (Lou Digital) /CreationDate (D:" . date('YmdHis') . ") >>";
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    ksort($objs);
    foreach ($objs as $id => $o) { $off[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$o\nendobj\n"; }
    $x = strlen($pdf); $max = max(array_keys($objs));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) $pdf .= sprintf("%010d 00000 n \n", $off[$i] ?? 0);
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info " . (8 + ($n - 1) * 2) . " 0 R >>\nstartxref\n$x\n%%EOF\n";
    return $pdf;
  }
}
