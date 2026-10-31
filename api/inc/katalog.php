<?php
/* Lou Digital – Leistungskatalog v2 (Version 34, 31.10.2026)
   Eine Quelle für: Was ist in jeder Leistung drin, was nicht, welche Regeln gelten, Betreuung S/M/L/E,
   Fragebögen an Kunden, Arbeits-Prompts für Claude und die Liste der automatisierten Abläufe.
   Preise kommen aus portal.php (LD_PREISE, LD_TIERS usw.), hier stehen nur Texte und Regeln.
   Genutzt von: Prompt-Werkstatt (Büro), Kundenportal (Fragebogen, Betreuung), Tiefen-Check, tools/katalog_md.php (Doc). */
declare(strict_types=1);
if (!defined('LD')) { http_response_code(404); exit; }

function ld_katalog(): array {
  static $k = null;
  if ($k !== null) return $k;
  $P = LD_PREISE; $T = LD_TIERS;
  $eur = function (int $n): string { return number_format($n, 0, ',', '.') . ' €'; };
  $k = [];

  /* ---------- Leistungen: was drin ist, was nicht, Regeln ---------- */
  $k['leistungen'] = [
    'check' => [
      'name' => 'Website-Check (kostenlos)', 'kurz' => 'Automatischer Check mit bis zu 100 Prüfpunkten, Ergebnis sofort.',
      'preis' => 'kostenlos', 'dauer' => 'sofort', 'aufwand' => '0 Min. (läuft automatisch)',
      'drin' => ['Sofort-Check mit 5 Punkten direkt auf der Website', 'Ausführlicher Check mit bis zu 100 Prüfpunkten in neun Bereichen (Technik, Recht, Google, Tempo, Kontakt, E-Mail)', 'Reihenfolge, was zuerst zu tun ist', 'Ergebnis per E-Mail, ohne Speicherung und ohne Gespräch'],
      'nicht' => ['Keine Prüfung von Texten, Aufbau, Fotos oder Nutzerführung (das macht der Tiefen-Check)', 'Keine Beratung, keine Umsetzung', 'Keine Prüfung von Unterseiten über Kontakt und Rechtstexte hinaus'],
      'regeln' => ['Prüft nur, was jeder Besucher sieht; keine Angriffe, keine Formulare werden ausgefüllt', 'Höchstens 6 ausführliche Checks je Stunde und Besucher'],
    ],
    'tiefencheck' => [
      'name' => 'Tiefen-Check', 'kurz' => 'Ich prüfe Ihre Website von Hand in acht Bereichen und schreibe auf, was zu tun ist, in welcher Reihenfolge und was es kostet.',
      'preis' => $eur($P['tiefencheck'][0]) . ' Pilot, ' . $eur($P['tiefencheck'][1]) . ' normal', 'dauer' => '5 Werktage', 'aufwand' => 'ca. 3 Std. mit dem Werkzeug im Büro',
      'drin' => ['Prüfung von Hand: Startseite und bis zu 5 Unterseiten, am Rechner und auf dem Handy', 'Acht Bereiche mit rund 70 Prüfpunkten: Erster Eindruck und Nutzerführung, Inhalte und Texte, Handy und Bedienung, Kontakt und Anfragewege, Sichtbarkeit bei Google und vor Ort, Recht und Datenschutz (Sichtprüfung), Technik und Sicherheit, Betrieb und Zukunft', 'Automatischer 100-Punkte-Check als Anhang', 'Bericht als PDF: Ampel je Bereich, Befund und Empfehlung je Punkt, Prioritäten Sofort / Bald / Später, Aufwand je Maßnahme', 'Maßnahmenliste mit Festpreis für die Umsetzung', 'Besprechung des Berichts, 30 Minuten am Telefon oder per Video', 'Anrechnung: Bei Beauftragung der Website in 7 Tagen oder einer Umsetzung ab 500 € innerhalb von 60 Tagen wird der Tiefen-Check voll angerechnet'],
      'nicht' => ['Keine Umsetzung der Maßnahmen (eigenes Angebot zum Festpreis)', 'Keine Rechtsberatung: Rechtstexte werden auf Vollständigkeit und Passung gesichtet, verbindlich prüft eine Anwältin oder ein Anwalt', 'Keine Keyword-Recherche und kein Wettbewerbsvergleich (Zusatz: Vergleich mit 3 Wettbewerbern, 90 €)', 'Keine laufende Überwachung (das ist Betreuung)'],
      'regeln' => ['Vorher nötig: Website-Adresse, Ziel der Website und 2 bis 3 Wunsch-Suchbegriffe (Fragebogen)', 'Lieferung innerhalb von 5 Werktagen nach Zahlung oder Auftrag', 'In Betreuung L ist ein Tiefen-Check je Jahr enthalten'],
    ],
    'website' => [
      'name' => 'Website in 7 Tagen', 'kurz' => 'Onepager oder bis zu 5 Seiten auf einer erprobten Vorlage, mit Kontaktformular, in 7 Tagen nach dem Fragebogen.',
      'preis' => $eur($P['website'][0]) . ' Pilot, ' . $eur($P['website'][1]) . ' normal', 'dauer' => '7 Tage nach vollständigem Fragebogen', 'aufwand' => 'ca. 5 Std.',
      'drin' => ['Onepager oder bis zu 5 Seiten (z. B. Start, Leistungen, Über uns, Referenzen, Kontakt)', 'Vorlage in Ihren Farben, mit Ihrem Logo und Ihren Fotos', 'Ihre Texte eingebaut und sprachlich geglättet (schreiben lasse ich mir als Zusatz bezahlen: 40 € je Seite)', 'Kontaktformular mit automatischer Bestätigung an den Absender', 'Darstellung für Handy, Tablet und Rechner', 'HTTPS, Grundeinstellungen für Google (Titel, Beschreibung, Sitemap, Google-Unternehmensprofil verknüpft)', 'Seiten für Impressum und Datenschutz mit fertiger Struktur (Texte liefern Sie oder ein Generator)', 'Eine Korrekturrunde (gesammelte Änderungswünsche, bis 2 Stunden Aufwand)', 'Einrichtung von Domain und Hosting auf Ihren Namen, alle Zugänge gehören Ihnen', 'Einweisung von 30 Minuten', '30 Tage Nachsorge: Fehler, die auf meine Arbeit zurückgehen, behebe ich kostenlos'],
      'nicht' => ['Hosting und Domain (laufen bei einem Anbieter Ihrer Wahl, meist 3 bis 10 € im Monat)', 'Rechtstexte (Impressum, Datenschutz), Fotos, Logo', 'Mehr als 5 Seiten (60 € je Seite), zweite Sprache (190 €), Online-Terminbuchung (90 €), Umzug alter Inhalte (149 €), Grafiken (ab 120 €)', 'Shops, Login-Bereiche, Mehrsprachigkeit über zwei Sprachen hinaus (nach Absprache)', 'Änderungen nach dem Livegang (Betreuung oder nach Aufwand)'],
      'regeln' => ['Die 7 Tage beginnen, wenn der Fragebogen vollständig ist (Logo, Texte, Fotos, Impressumsdaten)', 'Zahlung: 50 % bei Auftrag, 50 % beim Livegang', 'Zwischenstand nach 3 Tagen, Korrekturwünsche gesammelt in einer Runde', 'Weitere Korrekturrunden 49 € je Runde'],
    ],
    'automatik' => [
      'name' => 'Anfrage-Automatik', 'kurz' => 'Jede Anfrage landet in einer Tabelle und als Nachricht auf Ihrem Handy, der Absender bekommt sofort eine Bestätigung und später eine Bewertungsbitte.',
      'preis' => $eur($P['automatik'][0]) . ' Pilot, ' . $eur($P['automatik'][1]) . ' normal', 'dauer' => '5 Tage', 'aufwand' => 'ca. 4 Std.',
      'drin' => ['Formular auf Ihrer Website (oder Anbindung Ihres bestehenden Formulars)', 'Tabelle für alle Anfragen (Google Sheets in Ihrem Konto)', 'Nachricht aufs Handy bei jeder Anfrage (E-Mail oder Telegram)', 'Automatische Eingangsbestätigung an den Absender in Ihrem Ton', 'Bewertungsbitte nach dem Auftrag (Sie setzen den Status in der Tabelle, der Rest läuft)', 'Einrichtung auf Ihren Konten, Einweisung von 30 Minuten', '30 Tage Nachsorge'],
      'nicht' => ['Weitere Eingänge wie WhatsApp Business oder E-Mail-Postfach (90 € je Kanal)', 'KI-Kurzfassung und Antwortvorschlag (190 €), Terminerinnerung (90 €), Anbindung an Ihr Programm (ab 149 €)', 'Laufende Kosten fremder Dienste (meist unter 5 € im Monat)', 'Überwachung und Anpassung nach den 30 Tagen (das ist Betreuung, in jeder Stufe enthalten)'],
      'regeln' => ['Läuft über Ihr Google-Konto und den Automatik-Server von Lou Digital', 'Ohne Betreuung läuft die Automatik weiter, aber ohne Überwachung; Anpassungen nach Aufwand', 'Datenschutz: Datenschutzerklärung bekommt einen Absatz zur Verarbeitung (Vorlage liefere ich)'],
    ],
    'umfrage' => [
      'name' => 'Kundenumfrage', 'kurz' => 'Umfrage mit Versandlink und QR-Code, Auswertung als Bericht auf einer Seite mit drei Empfehlungen.',
      'preis' => $eur($P['umfrage'][0]) . ' Pilot, ' . $eur($P['umfrage'][1]) . ' normal', 'dauer' => '7 Tage bis zum Start, Auswertung nach Ende der Laufzeit', 'aufwand' => 'ca. 3 Std.',
      'drin' => ['Bis zu 10 Fragen aus Vorlage, auf Ihren Betrieb angepasst', 'Online-Formular, Versandlink und QR-Code für Tresen, Rechnung oder Auto', 'Laufzeit nach Wunsch (meist 4 Wochen)', 'Auswertung als Bericht auf einer Seite: Zahlen, Zitate, drei Empfehlungen', 'Besprechung der Ergebnisse, 20 Minuten'],
      'nicht' => ['Versand an Ihre Kundenliste (49 €, nur mit Einwilligung der Empfänger)', 'Weitere Runden (49 € je Runde), zweite Sprache (49 €)', 'Gewinnspiele, Gutscheine oder Telefonbefragung'],
      'regeln' => ['Anonym, keine Speicherung von Namen ohne Zustimmung', 'Die Rohdaten gehören Ihnen und werden nach der Auswertung übergeben'],
    ],
    'betreuung' => [
      'name' => 'Betreuung S / M / L / E', 'kurz' => 'Laufende Pflege für alles, was Lou Digital für Sie gebaut hat: Website, Automatik, Umfrage. Monatlich kündbar.',
      'preis' => 'S ' . $eur($T['S']) . ', M ' . $eur($T['M']) . ', L ' . $eur($T['L']) . ' im Monat, E nach Absprache (meist ab ' . LD_E_AB . ' €)', 'dauer' => 'laufend', 'aufwand' => '0,5 bis 2 Std. im Monat je Kunde',
      'drin' => ['Siehe Tabelle „Betreuung im Vergleich“: Überwachung, Sicherung, Technik-Durchgang, Wiederherstellung, Änderungszeit, Antwortzeit, Berichte, Gespräche'],
      'nicht' => ['Neue Seiten, neues Design, neue Funktionen, zweite Sprache (Festpreis vorab)', 'Hosting- und Domainkosten, Rechtstexte, Fotos, Werbung', 'Fremde Eingriffe am Hosting oder an der Domain (Behebung nach Aufwand)'],
      'regeln' => ['Monatlich kündbar zum Monatsende; Jahreszahlung: 12 Monate zum Preis von 10, Kündigung zum Jahresende', 'Zahlung im Voraus; Mehrbedarf an Änderungszeit am Monatsende', 'Wechsel der Stufe zum Monatsende'],
    ],
    'individuell' => [
      'name' => 'Nach Absprache', 'kurz' => 'Alles, was nicht in ein Paket passt: zum Festpreis nach einem kurzen Gespräch.',
      'preis' => 'Festpreis; Richtwert ' . LD_STUNDE . ' € je Stunde, Notfall-Hilfe ' . LD_NOTFALL . ' € je Stunde', 'dauer' => 'nach Angebot', 'aufwand' => 'nach Angebot',
      'drin' => ['Schriftliches Angebot mit Umfang, Preis und Termin innerhalb von 2 Werktagen nach dem Gespräch', 'Beispiele: weitere Automatisierungen, Umzug einer bestehenden Website, Schulung, mehrere Standorte, Shop-Anbindung'],
      'nicht' => ['Keine Arbeiten ohne schriftliches Angebot oder Betreuungsvertrag'],
      'regeln' => ['Ab 2 Stunden Aufwand immer Festpreis', 'Unter 2 Stunden: ' . LD_STUNDE . ' € je Stunde, erfasst in ' . LD_MIN_SCHRITT . '-Minuten-Schritten, mindestens 15 Minuten je Auftrag', 'Notfall-Hilfe (Website nicht erreichbar oder gehackt, Beginn am selben Werktag): ' . LD_NOTFALL . ' € je Stunde, mindestens 1 Stunde; mit Betreuung inklusive, wenn die Ursache in meinem Bereich liegt', 'Eilig (Wunschtermin unter 24 Stunden): +' . LD_EIL_PROZENT . ' %; Wochenende und Feiertag: +100 %, jeweils vorher angekündigt'],
    ],
  ];

  /* ---------- Betreuung: Stufen, Vergleichstabelle, Änderungszeit ---------- */
  $k['betreuung'] = [
    'stufen' => [
      'S' => ['name' => 'Betreuung S', 'motto' => 'Bleibt online und sicher', 'preis' => $T['S'], 'min' => LD_TIER_MIN['S'],
              'punkte' => ['Überwachung alle 5 Minuten, Alarm an mich', 'Sicherung wöchentlich, 30 Tage aufbewahrt', 'Technik-Durchgang monatlich', 'Wiederherstellung nach Ausfall inklusive, Beginn innerhalb von 2 Werktagen', 'Monatsbericht: Erreichbarkeit, Antwortzeit, Störungen, Sicherungen', 'Antwort innerhalb von 2 Werktagen', 'Änderungen nach Aufwand (' . LD_STUNDE . ' € je Stunde)']],
      'M' => ['name' => 'Betreuung M', 'motto' => 'Bleibt aktuell', 'preis' => $T['M'], 'min' => LD_TIER_MIN['M'],
              'punkte' => ['Alles aus S, Sicherung täglich und 90 Tage aufbewahrt', '30 Minuten Änderungszeit im Monat', 'Wiederherstellung: Beginn innerhalb von 1 Werktag', 'Monatsbericht zusätzlich mit Anfragen-Zahlen und Änderungen', 'Antwort innerhalb von 1 Werktag', 'Jahresgespräch, 30 Minuten']],
      'L' => ['name' => 'Betreuung L', 'motto' => 'Entwickelt sich weiter', 'preis' => $T['L'], 'min' => LD_TIER_MIN['L'],
              'punkte' => ['Alles aus M, Sicherung 12 Monate aufbewahrt', '60 Minuten Änderungszeit im Monat', 'Technik-Durchgang wöchentlich', 'Wiederherstellung: Beginn am selben Werktag', 'Monatsbericht zusätzlich mit drei Empfehlungen', 'Antwort am selben Werktag (bis 16 Uhr)', 'Gespräch halbjährlich', 'Tiefen-Check einmal im Jahr inklusive']],
      'E' => ['name' => 'Betreuung E', 'motto' => 'Exklusiv, nach Absprache', 'preis' => 0, 'min' => 0,
              'punkte' => ['Für mehrere Websites oder Standorte, Shops oder fremde Systeme', 'Reaktionszeit und Änderungskontingent nach Vereinbarung (schneller als ein Werktag, mehr als 60 Minuten)', 'Direkte Telefonnummer, Alarm auch an Sie', 'Monatliches Gespräch', 'Vertrag und Preis nach Gespräch, meist ab ' . LD_E_AB . ' € im Monat']],
    ],
    /* Vergleichstabelle: jede Zeile genau eine Dimension, damit nichts doppelt ist */
    'zeilen' => [
      ['Was betreut wird', '1 Website + Automatik + Umfrage von Lou Digital', 'wie S', 'wie S', 'mehrere Websites, Standorte, Shop, fremde Systeme'],
      ['Überwachung: alle 5 Minuten geprüft, Alarm an mich', 'ja', 'ja', 'ja', 'ja, Alarm auch an Sie'],
      ['Sicherung von Website und Automatik-Einstellungen', 'wöchentlich, 30 Tage', 'täglich, 90 Tage', 'täglich, 12 Monate', 'nach Absprache'],
      ['Technik-Durchgang: Zertifikat, Serverversionen, Bibliotheken, Formular-Test mit Testanfrage, Automatik-Testlauf', 'monatlich', 'monatlich', 'wöchentlich', 'wöchentlich'],
      ['Wiederherstellung nach Ausfall oder Angriff (aus der Sicherung), Beginn', 'inklusive, innerhalb von 2 Werktagen', 'inklusive, innerhalb von 1 Werktag', 'inklusive, am selben Werktag', 'nach Absprache, z. B. 4 Stunden'],
      ['Änderungszeit im Monat (was zählt, steht unten)', 'keine, nach Aufwand ' . LD_STUNDE . ' €/Std.', '30 Minuten', '60 Minuten', 'Kontingent nach Absprache'],
      ['Antwort auf Ihre Nachricht an Werktagen', 'innerhalb von 2 Werktagen', 'innerhalb von 1 Werktag', 'am selben Werktag', 'nach Absprache, direkte Nummer'],
      ['Monatsbericht als PDF im Kundenportal', 'Erreichbarkeit, Antwortzeit, Störungen, Sicherungen', 'zusätzlich Anfragen-Zahlen und Änderungen', 'zusätzlich drei Empfehlungen', 'zusätzlich Gespräch'],
      ['Gespräch: was sich bewährt hat, was fehlt (30 Minuten)', '–', 'jährlich', 'halbjährlich', 'monatlich'],
      ['Tiefen-Check (Wert ' . $eur($P['tiefencheck'][1]) . ')', '–', '–', 'einmal im Jahr inklusive', 'nach Absprache'],
      ['Laufzeit', 'monatlich kündbar', 'monatlich kündbar', 'monatlich kündbar', 'Vertrag nach Absprache'],
    ],
    'aenderung' => [
      'zaehlt' => ['Texte ändern oder ergänzen', 'Bilder tauschen (Sie liefern die Dateien)', 'Öffnungszeiten, Preise, Kontaktdaten, Team-Karten', 'Hinweisbanner oder Aktion ein- und ausblenden', 'Links, Downloads und PDFs einfügen', 'Ein Formularfeld ergänzen oder ändern', 'Texte der automatischen E-Mails (Bestätigung, Bewertungsbitte) ändern', 'Fragen der Kundenumfrage ändern'],
      'zaehlt_nicht' => ['Neue Seite oder neuer Abschnitt mit neuem Layout (Festpreis, ab 60 €)', 'Neues Design, neue Farben, neues Logo einbauen (Festpreis)', 'Neue Funktion: Terminbuchung, Galerie, Login, Shop, Newsletter (Festpreis)', 'Zweite Sprache, Texte schreiben, Grafiken erstellen (Zusatzdienste)', 'Rechtstexte prüfen oder schreiben', 'Schäden durch fremde Eingriffe am Hosting oder an der Domain (nach Aufwand)', 'Schulung über die Einweisung hinaus (' . LD_STUNDE . ' € je Stunde)'],
      'regeln' => ['Zeit wird in ' . LD_MIN_SCHRITT . '-Minuten-Schritten erfasst; jeder Wunsch zählt mindestens 5 Minuten', 'Das Kontingent gilt je Kalendermonat; nicht genutzte Minuten verfallen am Monatsende', 'Über das Kontingent hinaus: 1 € je Minute (' . LD_STUNDE . ' € je Stunde), vorher angekündigt', 'Wünsche bitte über das Kundenportal (Nachricht „Änderungswunsch“) oder per E-Mail; Umsetzung kleiner Änderungen innerhalb von 2 Werktagen (M) bzw. 1 Werktag (L) nach Freigabe', 'Die Minuten stehen im Monatsbericht und im Kundenportal unter „Betreuung“', 'Ob etwas als Änderung zählt, sage ich Ihnen vorher; im Zweifel entscheidet die Liste oben'],
      'beispiele' => [['Öffnungszeiten eintragen', 5], ['Zwei Fotos tauschen', 10], ['Neuer Textabschnitt auf einer bestehenden Seite', 15], ['Aktionsbanner mit Text und Datum', 15], ['Preisliste mit 10 Positionen aktualisieren', 20], ['Neues Teammitglied mit Foto und Text', 15]],
    ],
    'zahlung' => ['Monatlich: Preis je Monat, im Voraus, monatlich kündbar zum Monatsende', 'Jährlich: 12 Monate zum Preis von 10 (' . $eur($T['S'] * LD_JAHR_MONATE) . ' / ' . $eur($T['M'] * LD_JAHR_MONATE) . ' / ' . $eur($T['L'] * LD_JAHR_MONATE) . ' im Jahr), im Voraus, Kündigung zum Jahresende; wenn Sie die Website abgeben, erstatte ich nicht genutzte Monate anteilig'],
  ];

  /* ---------- Rabatte: genau diese fünf, mehr nicht ---------- */
  $k['rabatte'] = [
    ['Pilotpreis', 'Die ersten ' . LD_PILOT_MAX . ' Kunden zahlen die Pilotpreise und erlauben dafür eine Referenz (Logo, Ergebnis, ehrliche Bewertung). Gilt automatisch, solange Plätze frei sind.'],
    ['Kombi-Rabatt', 'Website in 7 Tagen und Anfrage-Automatik zusammen: ' . LD_KOMBI_PROZENT . ' % auf beide, weil beides in einem Durchgang entsteht.'],
    ['Startpaket', 'Website in 7 Tagen plus Betreuung (S, M oder L) zusammen bestellt: erster Betreuungsmonat geschenkt.'],
    ['Jahreszahlung', 'Betreuung für 12 Monate im Voraus: zwei Monate geschenkt.'],
    ['Empfehlung', 'Jeder Kunde bekommt einen Code (LD-…). Der Geworbene bekommt ' . LD_EMPF_PROZENT . ' % auf das erste Paket, der Werber ' . LD_EMPF_GUTSCHRIFT . ' € Gutschrift. Gilt, sobald der geworbene Auftrag bezahlt ist.'],
  ];
  $k['rabatt_regeln'] = ['Rabatte in Prozent (Pilot, Kombi, Empfehlung) rechnen nacheinander auf die Einmalsumme', 'Pilotpreis und Sonderaktionen gelten nie zusammen; es zählt die bessere', 'Zusage: Antwort auf jede Anfrage innerhalb von 24 Stunden an Werktagen, sonst 10 % auf das erste Paket (Serviceversprechen, kein Rabattcode)'];

  /* ---------- Fragebögen an Kunden (Fragen vor dem Start) ---------- */
  $k['fragebogen'] = [
    'website' => [
      ['firma', 'Wie heißt Ihr Unternehmen genau (mit Rechtsform) und wer ist Inhaberin oder Inhaber?', 'So steht es später im Impressum und in der Fußzeile.'],
      ['leistungen', 'Welche 3 bis 6 Leistungen bieten Sie an? Je Leistung ein bis zwei Sätze: Was genau, für wen, mit welchem Ergebnis?', 'Daraus entstehen die Leistungsseiten.'],
      ['kunden', 'Wer sind Ihre Kunden und aus welcher Region kommen sie?', 'Privatkunden, Firmen, Umkreis in Kilometern.'],
      ['unterschied', 'Was machen Sie anders oder besser als andere? Drei Punkte reichen.', 'Zum Beispiel Erfahrung, Tempo, Festpreise, Erreichbarkeit.'],
      ['seiten', 'Welche Seiten wünschen Sie sich? (Bis zu 5 sind enthalten.)', 'Üblich: Start, Leistungen, Über uns, Referenzen, Kontakt.'],
      ['marke', 'Haben Sie Logo und Farben? Bitte als Dateien (SVG oder PNG) und, wenn vorhanden, die Farbcodes.', 'Ohne Logo arbeite ich mit Ihrem Namen in einer passenden Schrift.'],
      ['fotos', 'Welche Fotos gibt es (Team, Arbeiten, Räume, Fahrzeuge)? Wie viele und wie liefern Sie sie?', 'Echte Fotos wirken besser als Stockbilder; Handy-Fotos im Querformat reichen oft.'],
      ['kontakt', 'Wie sollen Kunden Sie erreichen: Telefon, E-Mail, WhatsApp, Formular? Welche Öffnungs- oder Erreichbarkeitszeiten?', ''],
      ['impressum', 'Angaben für das Impressum: Anschrift, Telefon, E-Mail, Registernummer, USt-ID (falls vorhanden), Kammer oder Berufsbezeichnung.', 'Den Text liefern Sie oder ein Generator; ich baue die Seite.'],
      ['domain', 'Gibt es schon eine Domain oder Website? Bei welchem Anbieter, und haben Sie die Zugänge?', 'Sonst empfehle ich einen Anbieter und richte alles auf Ihren Namen ein.'],
      ['vorbilder', 'Zwei Websites, die Ihnen gefallen, und etwas, das Sie auf keinen Fall möchten.', 'Links reichen.'],
      ['extras', 'Brauchen Sie Besonderes: Terminbuchung, Downloads, Stellenanzeigen, zweite Sprache, Galerie?', 'Das sind Zusatzdienste, ich nenne vorher den Preis.'],
      ['freigabe', 'Wer gibt frei und wann haben Sie Zeit für die Korrekturrunde (Datum)?', 'Die 7 Tage laufen ab vollständigem Fragebogen.'],
    ],
    'automatik' => [
      ['heute', 'Wo kommen Anfragen heute an und was passiert dann? (Formular, E-Mail, Telefon, WhatsApp)', ''],
      ['felder', 'Welche Angaben brauchen Sie von einer Anfrage? (z. B. Name, Telefon, Adresse, Wunschtermin, Fotos)', 'Weniger Felder = mehr Anfragen.'],
      ['benachrichtigung', 'Wohin soll die Nachricht bei jeder Anfrage gehen: E-Mail, Telegram? An wen?', ''],
      ['antwort', 'Wer antwortet auf Anfragen und wie schnell? Soll die Bestätigung eine Antwortzeit nennen?', ''],
      ['ton', 'Wie sollen die automatischen E-Mails klingen (Sie/Du, locker/förmlich)? Was soll drinstehen (Öffnungszeiten, Ablauf, Telefonnummer)?', ''],
      ['bewertung', 'Auf welcher Plattform sollen Kunden bewerten (Google, ProvenExpert, andere)? Bitte Link.', 'Die Bewertungsbitte geht raus, wenn Sie den Status in der Tabelle auf „erledigt“ setzen.'],
      ['google', 'Haben Sie ein Google-Konto für die Tabelle? Welche Adresse?', 'Die Tabelle gehört Ihnen und bleibt in Ihrem Konto.'],
      ['datenschutz', 'Gibt es eine Datenschutzerklärung, die wir ergänzen können?', 'Ich liefere den Absatz für die Verarbeitung.'],
    ],
    'umfrage' => [
      ['ziel', 'Was wollen Sie mit der Umfrage herausfinden? (Zufriedenheit, Wünsche, warum Kunden wiederkommen oder nicht)', ''],
      ['zielgruppe', 'Wen fragen Sie und wie viele Antworten erwarten Sie?', ''],
      ['themen', 'Welche Themen oder Fragen sind Ihnen wichtig? (bis zu 10 Fragen enthalten)', ''],
      ['weg', 'Wie erreichen Sie die Teilnehmer: QR-Code vor Ort, Link per E-Mail, auf der Rechnung, Social Media?', ''],
      ['laufzeit', 'Wie lange soll die Umfrage laufen (Start und Ende)?', 'Meist 4 Wochen.'],
      ['anonym', 'Soll die Umfrage anonym sein oder dürfen Teilnehmer ihren Namen angeben (z. B. für Rückfragen)?', ''],
      ['danach', 'Was passiert mit den Ergebnissen? Wer bekommt den Bericht?', ''],
    ],
    'tiefencheck' => [
      ['url', 'Wie lautet die Adresse Ihrer Website?', ''],
      ['ziel', 'Was soll die Website erreichen? (Anfragen, Anrufe, Bewerbungen, Information)', ''],
      ['leistungen', 'Welche Leistungen sind Ihnen am wichtigsten und wer soll sie finden?', ''],
      ['suche', 'Mit welchen 2 bis 3 Suchbegriffen sollen Kunden Sie bei Google finden? (z. B. „Malerbetrieb Karlsruhe“)', ''],
      ['wettbewerb', 'Nennen Sie 2 bis 3 Wettbewerber, mit denen Sie verglichen werden möchten.', ''],
      ['probleme', 'Was stört Sie an der Website oder was wurde Ihnen schon gesagt?', ''],
      ['technik', 'Wer hat die Website gebaut, wer pflegt sie, haben Sie die Zugänge (Domain, Hosting, System)?', ''],
      ['termin', 'Wann passt Ihnen das Gespräch zum Bericht (30 Minuten)?', ''],
    ],
    'betreuung' => [
      ['stufe', 'Welche Stufe und welche Zahlweise wünschen Sie (S, M, L oder E; monatlich oder jährlich)?', ''],
      ['website', 'Welche Website und welche Automatik sollen betreut werden (Adressen)?', ''],
      ['zugaenge', 'Bei welchem Anbieter laufen Hosting und Domain? Haben Sie Zugänge zum Hosting (Plesk, FTP, System)?', 'Ich brauche Zugang für Sicherungen und Updates; Passwörter geben Sie direkt ein, nie per E-Mail.'],
      ['kontakt', 'Wer ist Ansprechpartner für Änderungen, wer darf freigeben? Bevorzugter Weg: Portal, E-Mail, Telefon?', ''],
      ['rechnung', 'Rechnungsanschrift und E-Mail für Rechnungen.', ''],
      ['start', 'Ab wann soll die Betreuung laufen?', ''],
    ],
    'aenderung' => [
      ['was', 'Was genau soll geändert werden? Welche Seite, welche Stelle, neuer Text oder neues Bild?', 'Am besten den alten Text und den neuen Text nebeneinander.'],
      ['dateien', 'Gibt es Dateien (Bilder, PDFs)? Bitte im Portal hochladen oder per E-Mail senden.', ''],
      ['frist', 'Bis wann soll es online sein?', ''],
      ['freigabe', 'Soll ich vor dem Livegang eine Vorschau schicken oder direkt veröffentlichen?', ''],
    ],
    'exklusiv' => [
      ['umfang', 'Wie viele Websites, Standorte oder Systeme sollen betreut werden? Welche?', ''],
      ['systeme', 'Welche Systeme sind im Einsatz (CMS, Shop, Buchung, Warenwirtschaft, Newsletter)?', ''],
      ['besucher', 'Wie viele Besucher und Anfragen haben Sie etwa im Monat?', ''],
      ['reaktion', 'Welche Reaktionszeit brauchen Sie bei Störungen und bei Änderungen?', ''],
      ['volumen', 'Wie viele Änderungen fallen im Monat an (Schätzung in Stunden)?', ''],
      ['ansprechpartner', 'Wer sind die Ansprechpartner auf Ihrer Seite? Gibt es eine IT-Abteilung oder Agentur?', ''],
      ['budget', 'In welchem Rahmen bewegt sich Ihr Budget im Monat?', ''],
      ['laufzeit', 'Welche Vertragslaufzeit stellen Sie sich vor?', ''],
    ],
  ];

  /* ---------- Arbeits-Prompts für Claude (Standard-Aufbau aus lou-digital-prompts.md) ---------- */
  $std = "SELBST MACHEN: Code, Deploy, n8n-Workflows, Drive-Dateien, Tests, Docs aktualisieren.\nNUR ADRIAN: Passwörter, Schlüssel, Konten, Zahlungen, Absenden an Dritte, Kaltakquise, Port/Firewall. Claude bereitet alles vor und sagt in einem Satz, was fehlt.\nERGEBNIS: Liste „erledigt / offen / Entscheidung nötig“, Fragen als Auswahl-Optionen, Docs mit Stand + Datum.";
  $k['prompts'] = [
    'website' => [
      ['bauen', 'Website in 7 Tagen bauen', "Lou Digital – Auftrag {{heute}}: Website in 7 Tagen für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Die Website liegt als statische HTML-Seite (Vorlage Clean-Tech, Design System Lou Digital, Regeln aus lou-digital-website-stil.md) lokal gebaut und getestet vor: Seiten {{a:seiten}}.\n2. Texte aus dem Fragebogen sind eingebaut und sprachlich geglättet, Leistungen klar (was, für wen, Ergebnis), keine erfundenen Kundenstimmen.\n3. Kontaktformular sendet an {{a:kontakt}} mit Bestätigung; Impressum- und Datenschutzseite haben die Struktur, Texte liefert der Kunde.\n4. Handy-Ansicht ohne Querscrollen, Ladezeit unter 2 s, Titel/Beschreibung/Sitemap gesetzt.\n5. Vorschau-Link für den Kunden plus Liste der offenen Punkte.\n\nREIHENFOLGE: Startseite zuerst, dann Leistungen, dann Rest; kleinste funktionierende Version zuerst.\n\nFAKTEN:\nKunde: {{kunde}}, {{firma}}, {{email}}, {{telefon}}\n{{antworten}}\nPreis laut Auftrag: {{preis_website}} (Pilot/Normal laut portal.php). Zusatzdienste nur, wenn im Auftrag.\n\n" . $std . "\n\nTESTEN: lokal mit Playwright (390/768/1366 px), alle Links, Formular mit Testanfrage, Lighthouse-Werte nennen."],
      ['texte', 'Texte aus Stichpunkten schreiben (Zusatz 40 € je Seite)', "Lou Digital – Auftrag {{heute}}: Website-Texte für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Für jede Seite ({{a:seiten}}) ein Text in Ich/Wir-Form des Kunden, Sie-Anrede, kurze Sätze, keine Floskeln, keine Superlative ohne Beleg.\n2. Je Leistung: Überschrift, zwei Sätze Nutzen, drei Stichpunkte, ein Satz zum nächsten Schritt.\n3. Startseite: ein Satz, der in 5 Sekunden sagt, was angeboten wird, für wen und wo.\n\nFAKTEN:\n{{antworten}}\nRegion: {{a:kunden}}. Unterschiede: {{a:unterschied}}.\n\n" . $std . "\n\nTESTEN: Lesbarkeit (Flesch > 60), keine Rechtschreibfehler, jede Seite hat genau eine Hauptüberschrift."],
      ['korrektur', 'Korrekturrunde einarbeiten', "Lou Digital – Auftrag {{heute}}: Korrekturrunde {{firma}}\n\nZIEL (fertig, wenn …):\n1. Alle Punkte aus der Liste des Kunden sind umgesetzt oder mit „deshalb“ als Zusatz markiert (über 2 Stunden Aufwand = Festpreis-Angebot).\n2. Änderungen sind lokal getestet und live, Kunde hat eine Liste „erledigt / offen“.\n\nFAKTEN:\nWünsche des Kunden: (hier einfügen)\nWebsite: {{website}}\n\n" . $std . "\n\nTESTEN: jede geänderte Seite in 3 Breiten prüfen, Formular erneut testen."],
      ['livegang', 'Livegang und Übergabe', "Lou Digital – Auftrag {{heute}}: Livegang {{firma}}\n\nZIEL (fertig, wenn …):\n1. Website läuft unter {{website}} mit HTTPS, http und www leiten um, 404-Seite vorhanden, Sitemap und robots.txt live.\n2. Google-Unternehmensprofil mit der Website verknüpft (Adrian meldet sich an, Claude bereitet vor).\n3. Übergabe-Dokument im Kundenportal: Zugänge (ohne Passwörter), Hosting, Domain, Ablauf für Änderungen, Nachsorge 30 Tage, Betreuungsangebot.\n4. Projektstatus im Portal auf „fertig“, Rechnung 50 % (sobald Steuernummer da).\n\nFAKTEN: Hoster/Domain: {{a:domain}}. Betreuung gewünscht: {{stufe}}.\n\n" . $std . "\n\nTESTEN: alle Seiten live 200, Formular live mit Testanfrage, Handy-Test."],
    ],
    'automatik' => [
      ['bauen', 'Anfrage-Automatik bauen (n8n)', "Lou Digital – Auftrag {{heute}}: Anfrage-Automatik für {{firma}}\n\nZIEL (fertig, wenn …):\n1. n8n-Workflow „{{firma}} – Anfrage“ (Ordner Kunden) nimmt das Formular an, prüft Eingaben (Länge, HTML, Honeypot), schreibt in die Google-Tabelle des Kunden und schickt die Nachricht an {{a:benachrichtigung}}.\n2. Eingangsbestätigung an den Absender im Ton „{{a:ton}}“, höchstens 2 je Adresse und Tag.\n3. Bewertungsbitte: Workflow prüft täglich die Tabelle auf Status „erledigt“ ohne Markierung „Bewertung gesendet“, schickt die Mail mit Link {{a:bewertung}} und markiert die Zeile.\n4. Fehler-Workflow und Rate-Limits aktiv, Workflow veröffentlicht und einmal mit Testanfrage geprüft; Testzeile danach genannt.\n5. Datenschutz-Absatz für den Kunden als Text geliefert.\n\nFAKTEN:\n{{antworten}}\nGoogle-Konto des Kunden: {{a:google}}. Modell: claude-haiku-5-5 nur, wenn KI-Zusatz gebucht.\n\n" . $std . "\n\nTESTEN: Testanfrage über das Formular, Zeile in der Tabelle, Nachricht angekommen, Bestätigung im Posteingang, Bewertungsbitte mit Status-Test."],
      ['testen', 'Automatik-Testlauf (Betreuung, Technik-Durchgang)', "Lou Digital – Auftrag {{heute}}: Testlauf Automatik {{firma}}\n\nZIEL (fertig, wenn …):\n1. Testanfrage über das Formular von {{website}} läuft bis in die Tabelle, Nachricht und Bestätigung sind angekommen.\n2. n8n: Ausführungen der letzten 30 Tage geprüft, Fehler genannt, Workflow-Zähler im Portal (Läufe, Fehler, letzter Lauf) aktualisiert.\n3. Testzeile aus der Tabelle entfernt oder genannt.\n\n" . $std . "\n\nTESTEN: siehe Ziel; Ergebnis als eine Zeile für den Monatsbericht."],
    ],
    'umfrage' => [
      ['erstellen', 'Kundenumfrage erstellen', "Lou Digital – Auftrag {{heute}}: Kundenumfrage für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Umfrage mit bis zu 10 Fragen (Skala 1–5, Auswahl, ein Freitext) zu: {{a:themen}}; Ziel: {{a:ziel}}.\n2. Online-Formular (n8n LD-01 oder Vorlage) im Lou-Digital-Design, Versandlink und QR-Code als PNG/PDF im Drive-Ordner des Kunden.\n3. Antworten landen im Blatt „Umfrage-Antworten“ (eigenes Blatt je Kunde), anonym: {{a:anonym}}.\n4. Laufzeit {{a:laufzeit}} eingetragen, Erinnerung für die Auswertung in den Fristen.\n\nFAKTEN:\n{{antworten}}\n\n" . $std . "\n\nTESTEN: drei Testantworten, im Blatt sichtbar, QR-Code gescannt; Testantworten danach löschen."],
      ['auswerten', 'Umfrage auswerten (Bericht auf einer Seite)', "Lou Digital – Auftrag {{heute}}: Auswertung Kundenumfrage {{firma}}\n\nZIEL (fertig, wenn …):\n1. Bericht als PDF (eine Seite, Design System): Rücklauf, Durchschnitt je Frage, drei stärkste und drei schwächste Punkte, 3 Zitate, drei Empfehlungen mit Aufwand.\n2. Bericht im Kundenportal unter Dokumente abgelegt, Kunde per Mail informiert, Gesprächstermin (20 Minuten) vorgeschlagen.\n\nFAKTEN: Antworten im Blatt „Umfrage-Antworten“ des Kunden; Ziel der Umfrage: {{a:ziel}}.\n\n" . $std . "\n\nTESTEN: Zahlen gegen das Blatt gerechnet, PDF geöffnet und gelesen."],
    ],
    'tiefencheck' => [
      ['durchfuehren', 'Tiefen-Check durchführen (mit dem Werkzeug im Büro)', "Lou Digital – Auftrag {{heute}}: Tiefen-Check {{website}} für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Im Büro unter Tiefen-Check ist ein Check für {{website}} angelegt, der automatische 100-Punkte-Check ist gesammelt.\n2. Alle 8 Bereiche sind von Hand beurteilt (Status, Befund, Empfehlung je Punkt), mit Blick auf Ziel „{{a:ziel}}“, Suchbegriffe „{{a:suche}}“ und Wettbewerber „{{a:wettbewerb}}“; Google-Suche für die Suchbegriffe ist dokumentiert (Position, Google-Profil).\n3. Zusammenfassung (5 Sätze) und Top-Maßnahmen mit Aufwand stehen; Umsetzungsangebot als Festpreis berechnet.\n4. PDF erzeugt, als Dokument beim Kunden abgelegt, Gesprächstermin vorgeschlagen.\n\nFAKTEN:\n{{antworten}}\nPreis: {{preis_tiefencheck}}, Anrechnung bei Website-Auftrag innerhalb von 60 Tagen.\n\n" . $std . "\n\nTESTEN: Website im Chrome auf 390 px und 1366 px durchgehen, Testanfrage über das Kundenformular (Antwortzeit notieren), PDF vollständig gelesen."],
      ['bericht', 'Bericht formulieren (Zusammenfassung, Top-Maßnahmen)', "Lou Digital – Auftrag {{heute}}: Zusammenfassung Tiefen-Check {{firma}}\n\nZIEL (fertig, wenn …):\n1. Zusammenfassung in 5 Sätzen: Gesamteindruck, größte Stärke, größtes Risiko, erste Maßnahme, erwarteter Nutzen.\n2. Top-5-Maßnahmen: je ein Satz was, ein Satz warum, Aufwand in Minuten, Priorität.\n3. Empfehlung des passenden Pakets (Umsetzung zum Festpreis, Website in 7 Tagen, Betreuung) mit Preis aus portal.php.\n\nFAKTEN: Befunde aus dem Tiefen-Check-Werkzeug (Export unten einfügen). Ton: sachlich, ohne Fachjargon, Sie-Anrede, keine Emojis.\n\n" . $std],
    ],
    'betreuung' => [
      ['start', 'Betreuung einrichten', "Lou Digital – Auftrag {{heute}}: Betreuung {{stufe}} für {{firma}} einrichten\n\nZIEL (fertig, wenn …):\n1. Kunde im Portal mit Stufe {{stufe}}, Zahlweise {{zahlung}}, Start {{a:start}}; Website {{website}} wird alle 5 Minuten geprüft (portal_cron).\n2. Sicherung eingerichtet (Plan je Stufe: wöchentlich/täglich), erster Sicherungslauf erfolgreich, Ablageort notiert.\n3. Technik-Durchgang einmal komplett gemacht (Zertifikat, Versionen, Formular-Test, Automatik-Testlauf), Ergebnis als erste Nachricht im Portal.\n4. Kunde hat die Übersicht „So schicken Sie Änderungswünsche“ (Portal, Reaktionszeit, was zählt).\n\nFAKTEN:\n{{antworten}}\n\n" . $std . "\n\nTESTEN: Prüfung im Portal sichtbar, Sicherung wiederherstellbar (Stichprobe eine Datei)."],
      ['aenderung', 'Änderungswunsch umsetzen (Minuten buchen)', "Lou Digital – Auftrag {{heute}}: Änderungswunsch {{firma}}\n\nZIEL (fertig, wenn …):\n1. Vorher geprüft, ob der Wunsch als Änderung zählt (Liste im Katalog); wenn nicht: Festpreis vorschlagen und stoppen.\n2. Änderung lokal gebaut, live geschoben, in 3 Breiten geprüft.\n3. Ticket im Portal beantwortet, Minuten in 5er-Schritten eingetragen (mindestens 5), Vorschau oder Livelink genannt.\n\nFAKTEN:\n{{antworten}}\nStufe {{stufe}} ({{minuten}} Minuten im Monat, genutzt: {{minuten_genutzt}}).\n\n" . $std . "\n\nTESTEN: geänderte Stelle live geprüft, Screenshot für das Ticket."],
      ['technik', 'Technik-Durchgang (monatlich/wöchentlich)', "Lou Digital – Auftrag {{heute}}: Technik-Durchgang {{firma}}\n\nZIEL (fertig, wenn …):\n1. Zertifikat (Ablauf > 21 Tage), Server-/PHP-Version, Bibliotheken (Lenis, Skripte) geprüft; Sicherheits-Header vorhanden.\n2. Formular-Test mit Testanfrage: Bestätigung angekommen, Antwortzeit notiert; Automatik-Testlauf, falls vorhanden.\n3. Sicherung des Tages/der Woche geprüft (Datei vorhanden, Datum stimmt).\n4. Abweichungen als Ticket oder Nachricht im Portal, Zeile für den Monatsbericht.\n\nFAKTEN: Website {{website}}, Stufe {{stufe}}.\n\n" . $std],
      ['monatsbericht', 'Monatsbericht prüfen und drei Empfehlungen (L)', "Lou Digital – Auftrag {{heute}}: Monatsbericht {{firma}}\n\nZIEL (fertig, wenn …):\n1. Monatsbericht im Büro erzeugt (Kunde → Monatsbericht), Zahlen plausibel (Erreichbarkeit, Antwortzeit, Störungen, Minuten).\n2. Für M: Anfragen-Zahlen aus der Automatik ergänzt. Für L: drei Empfehlungen (je ein Satz, mit Aufwand) in die Nachricht an den Kunden.\n3. Kunde per Mail informiert.\n\n" . $std],
      ['gespraech', 'Jahres-/Halbjahresgespräch vorbereiten', "Lou Digital – Auftrag {{heute}}: Gespräch mit {{firma}} vorbereiten\n\nZIEL (fertig, wenn …):\n1. Eine Seite: Zahlen des Zeitraums (Erreichbarkeit, Anfragen, Änderungen, Störungen), was gut lief, drei Vorschläge mit Preis (Zusatzdienste, Stufe, Tiefen-Check).\n2. Drei Fragen an den Kunden (Ziele, Ärgernisse, Wünsche).\n3. Termin vorgeschlagen, Notiz im Portal.\n\n" . $std],
    ],
    'exklusiv' => [
      ['angebot', 'Betreuung E: Bedarf klären und Angebot schreiben', "Lou Digital – Auftrag {{heute}}: Angebot Betreuung E für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Bedarf aus dem Fragebogen in eine Leistungsbeschreibung übersetzt: Umfang (Websites/Systeme), Überwachung, Sicherung, Reaktionszeiten, Änderungskontingent, Berichte, Gespräche.\n2. Preis kalkuliert: Aufwand in Stunden je Monat × {{stundensatz}} € plus 20 % Puffer, gerundet; Mindestlaufzeit und Kündigung vorgeschlagen.\n3. Angebot als PDF im Design System, im Portal abgelegt; Entscheidung „senden“ bei Adrian.\n\nFAKTEN:\n{{antworten}}\nStufen S/M/L als Untergrenze (E liegt über L: mehr als 60 Minuten, schneller als ein Werktag oder mehr als eine Website).\n\n" . $std],
    ],
    'buero' => [
      ['angebot', 'Angebot als PDF erstellen', "Lou Digital – Auftrag {{heute}}: Angebot für {{firma}}\n\nZIEL (fertig, wenn …):\n1. Angebot als PDF (Vorlage „Angebot/Rechnung“ aus dem Design System): Leistung mit „drin / nicht drin“ aus dem Katalog, Preis laut portal.php (Pilot/Normal, Rabatte ausgewiesen), Lieferzeit, Zahlungsbedingungen, 14 Tage gültig, § 19 UStG.\n2. PDF im Kundenportal unter Dokumente abgelegt (Art Angebot), Auftrag im Büro auf „Angebot gesendet“.\n3. Mailtext zum Versand vorbereitet; Senden macht Adrian.\n\nFAKTEN: Auswahl laut Auftrag #{{auftrag}}: {{pakete}}; Kunde {{kunde}}, {{firma}}, {{email}}.\n\n" . $std],
      ['onboarding', 'Kunden-Onboarding (Portal, Fragebogen, Ordner)', "Lou Digital – Auftrag {{heute}}: Onboarding {{firma}}\n\nZIEL (fertig, wenn …):\n1. Portal-Konto angelegt (Startpasswort per Mail), Fragebogen für {{pakete}} im Portal freigeschaltet.\n2. Drive-Ordner „Kunden/{{firma}}“ mit Unterordnern Fragebogen, Material, Entwürfe, Rechnungen.\n3. Projekt im Portal mit Schritten und Zieldatum angelegt, Fristen im Blatt „Fristen“ eingetragen.\n4. Willkommensnachricht im Portal mit Ablauf und nächstem Schritt.\n\n" . $std],
      ['woche', 'Wochenplanung (Sonntag)', "Lou Digital – Auftrag {{heute}}: Wochenplanung\n\nZIEL (fertig, wenn …):\n1. KPI-Bericht (Leads nach Status, Aufträge, MRR, offene Tickets, Minuten) gelesen und in 5 Zeilen zusammengefasst.\n2. Drei Prioritäten für die Woche mit Zeitbudget (ca. 10 Stunden), je ein Prompt im Standard-Aufbau.\n3. Fälligkeiten der Betreuung (Technik-Durchgänge, Monatsberichte, Gespräche, Tiefen-Checks in L) für die Woche aufgelistet.\n\n" . $std],
    ],
  ];

  /* ---------- Automatisierte Abläufe: Stand und nächster Schritt ---------- */
  $k['automatisierung'] = [
    ['Anfrage von der Website', 'automatisch', 'api/anfrage.php + n8n LD-05: Preisübersicht an Kunde, Telegram, Leads-Blatt, Auftrag im Portal', ''],
    ['Fragebogen an den Kunden', 'automatisch', 'Prompt-Werkstatt → Freischalten im Portal → Kunde füllt aus → Antworten in der Werkstatt, Hinweis per Mail/Telegram', ''],
    ['Angebot als PDF', 'halbautomatisch', 'Prompt „Angebot“ in der Werkstatt, PDF ins Portal', 'Roadmap #5: /angebot per Telegram'],
    ['Website bauen', 'halbautomatisch', 'Prompt „Website bauen“ mit Fragebogen-Antworten, Vorlagen', 'Vorlagen-Bibliothek (3 Stück) fertigstellen'],
    ['Überwachung, Störungsmail, Zertifikat', 'automatisch', 'n8n LD-08 → api/portal_cron.php, alle 5 Minuten', ''],
    ['Monatsbericht', 'automatisch', 'portal_cron am Monatsanfang, PDF im Portal + Mail', 'L: drei Empfehlungen per Prompt ergänzen'],
    ['Änderungswunsch', 'halbautomatisch', 'Ticket im Portal → Prompt „Änderungswunsch“ → Minuten im Ticket → Monatsbericht', ''],
    ['Tiefen-Check', 'halbautomatisch', 'Werkzeug im Büro: 100-Punkte-Check automatisch, Beurteilung von Hand, PDF automatisch', ''],
    ['Bewertungsbitte nach Auftrag', 'automatisch', 'n8n-Workflow des Kunden (Anfrage-Automatik)', ''],
    ['Rechnung', 'manuell', 'bis Steuernummer da ist', 'Roadmap #6: /rechnung per Telegram'],
    ['Wochen-KPI', 'automatisch', 'n8n LD-04, Sonntag 18 Uhr Telegram', ''],
    ['Fristen und Prompts', 'automatisch', 'n8n LD-03, täglich 8 Uhr Telegram', ''],
    ['Sicherung der Kundenseiten', 'manuell', 'Hoster-Backup je Kunde, Plan je Stufe', 'Skript je Hoster, Ablage in Drive'],
  ];

  /* ---------- Marktvergleich (Spannen gerundet; Quellen stehen auf /pakete#vergleich) ---------- */
  $k['markt'] = [
    ['Onepager / Landingpage', '500 – 1.500 €', '1.000 – 3.000 €', $eur($P['website'][0]) . ' Pilot, ' . $eur($P['website'][1]) . ' normal'],
    ['Website mit bis zu 5 Seiten', '900 – 4.000 €', '2.500 – 8.000 €', $eur($P['website'][0]) . ' Pilot, ' . $eur($P['website'][1]) . ' normal'],
    ['Website-Prüfung von Hand mit Bericht und Gespräch', '250 – 900 €', '1.500 – 2.800 €', $eur($P['tiefencheck'][0]) . ' Pilot, ' . $eur($P['tiefencheck'][1]) . ' normal, wird angerechnet'],
    ['Wartung und Pflege im Monat (Basis bis Premium)', '30 – 150 €', '70 – 450 €', $eur($T['S']) . ' – ' . $eur($T['L']) . ', E nach Absprache'],
    ['Stundensatz für Änderungen', '60 – 100 €', '100 – 180 €', LD_STUNDE . ' € (1 € je Minute)'],
    ['Formular-Automatisierung (Standard)', '300 – 600 €', '1.800 – 2.400 €', $eur($P['automatik'][0]) . ' Pilot, ' . $eur($P['automatik'][1]) . ' normal'],
    ['Kundenumfrage mit Auswertung', '400 – 700 €', 'ab 5.000 € (Institut)', $eur($P['umfrage'][0]) . ' Pilot, ' . $eur($P['umfrage'][1]) . ' normal'],
  ];
  $k['markt_quellen'] = [
    ['kopfundstift.de, Webdesign-Kosten', 'https://kopfundstift.de/webdesign-kosten/'], ['kopfundstift.de, Wartungspakete', 'https://kopfundstift.de/wordpress-wartung/'],
    ['forge12.com, Wartung', 'https://www.forge12.com/en/wartung'], ['xictron.com, Website-Abo', 'https://www.xictron.com/de/website-abo/'], ['vcat.de, Wartung 2026', 'https://www.vcat.de/wp-content/uploads/2025/11/VCAT-Wartung-2026-WordPress.pdf'],
    ['pragma-code.de, Website-Tiefenanalyse', 'https://www.pragma-code.de/website-tiefenanalyse'], ['ahrefs.com, SEO-Kosten', 'https://ahrefs.com/de/blog/seo-kosten/'],
    ['alloq.digital, Firmenwebsite erstellen lassen', 'https://alloq.digital/de/blog/firmenwebsite-erstellen-lassen/'], ['wix.com, Website erstellen lassen Preise', 'https://de.wix.com/blog/beitrag/website-erstellen-lassen-preise'], ['sellwerk.de, Was kostet eine Website', 'https://sellwerk.de/wissenswert/was-kostet-eine-website'], ['drweb.de, Was kostet Webdesign wirklich', 'https://www.drweb.de/was-kostet-webdesign-wirklich/'],
    ['freelancermap.de, Stundensätze Webdesign', 'https://www.freelancermap.de/freelancer/webdesign'], ['blckalpaca.at, Kosten n8n-Agenturen 2026', 'https://blckalpaca.at/en/blog/n8n-agency-comparison-dach-selection-criteria-and-costs-2026'], ['nordflux.de, n8n-Berater beauftragen', 'https://nordflux.de/wissen/n8n-berater-agentur-beauftragen'],
    ['intotheminds.com, Umfrage-Preise', 'https://www.intotheminds.com/blog/de/meinung-umfrage-preis/'],
  ];
  return $k;
}

/* Prompt mit Platzhaltern füllen: {{kunde}}, {{firma}}, {{email}}, {{telefon}}, {{website}}, {{stufe}}, {{zahlung}}, {{minuten}}, {{minuten_genutzt}},
   {{heute}}, {{stundensatz}}, {{preis_<paket>}}, {{pakete}}, {{auftrag}}, {{antworten}} (alle Fragen mit Antworten), {{a:<frage-id>}} (eine Antwort) */
function ld_prompt_fuellen(string $text, array $vars, array $fragen, array $antworten): string {
  $pr = ld_preise(true);
  $v = array_merge([
    'kunde' => '(Kunde)', 'firma' => '(Firma)', 'email' => '', 'telefon' => '', 'website' => '(Website)', 'stufe' => '(Stufe)', 'zahlung' => 'monatlich', 'minuten' => '0', 'minuten_genutzt' => '0',
    'heute' => date('d.m.Y'), 'stundensatz' => (string)LD_STUNDE, 'pakete' => '', 'auftrag' => '',
  ], $vars);
  foreach ($pr as $pk => [$p, $n]) $v['preis_' . $pk] = number_format($p, 0, ',', '.') . ' €' . ($p < $n ? ' (Normalpreis ' . number_format($n, 0, ',', '.') . ' €)' : '');
  $block = [];
  foreach ($fragen as [$id, $frage]) { $a = trim((string)($antworten[$id] ?? '')); $block[] = '- ' . $frage . "\n  Antwort: " . ($a !== '' ? str_replace("\n", "\n  ", $a) : '(noch offen)'); }
  $v['antworten'] = $block ? "Antworten aus dem Fragebogen:\n" . implode("\n", $block) : '(kein Fragebogen)';
  $out = preg_replace_callback('/\{\{a:([a-z_]+)\}\}/', function ($m) use ($antworten) { $a = trim((string)($antworten[$m[1]] ?? '')); return $a !== '' ? $a : '(noch offen)'; }, $text) ?? $text;
  return preg_replace_callback('/\{\{([a-z_]+)\}\}/', function ($m) use ($v) { return array_key_exists($m[1], $v) ? (string)$v[$m[1]] : $m[0]; }, $out) ?? $out;
}

/* Fragen einer Leistung als Text zum Kopieren (für E-Mail oder WhatsApp) */
function ld_fragen_text(string $leistung, string $firma = ''): string {
  $k = ld_katalog(); $f = $k['fragebogen'][$leistung] ?? []; $name = $k['leistungen'][$leistung]['name'] ?? ($leistung === 'aenderung' ? 'Änderungswunsch' : ($leistung === 'exklusiv' ? 'Betreuung E' : $leistung));
  $t = 'Guten Tag' . ($firma !== '' ? ' (' . $firma . ')' : '') . ",\n\ndamit ich direkt loslegen kann, brauche ich ein paar Angaben zu „" . $name . "“. Kurze Stichpunkte reichen, Dateien gern als Anhang.\n\n";
  $i = 0; foreach ($f as [$id, $frage, $hinweis]) { $i++; $t .= $i . '. ' . $frage . ($hinweis !== '' ? ' (' . $hinweis . ')' : '') . "\n\n"; }
  return $t . "Viele Grüße\nAdrian Semlinger\nLou Digital, Karlsruhe\n" . LD_PHONE;
}
