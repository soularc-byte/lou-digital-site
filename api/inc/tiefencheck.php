<?php
/* Lou Digital – Tiefen-Check: Prüfliste von Hand, Bewertung, Bericht als PDF (Version 34, 31.10.2026)
   Der Tiefen-Check ist eine bezahlte Prüfung durch Adrian (nicht automatisch). Das Werkzeug im Büro (/portal/admin/tiefencheck)
   sammelt den automatischen 100-Punkte-Check als Anhang, führt durch 8 Bereiche mit rund 70 Prüfpunkten („So prüfen“, „Warum“),
   speichert Status, Befund und Empfehlung je Punkt und erzeugt den Bericht als PDF (api/inc/pdf.php).
   Status je Punkt: ok (in Ordnung), teils (Verbesserung sinnvoll), mangel (muss behoben werden), na (trifft nicht zu), offen (noch nicht beurteilt). */
declare(strict_types=1);
if (!defined('LD')) { http_response_code(404); exit; }

const TC_STATUS = ['offen' => 'noch offen', 'ok' => 'in Ordnung', 'teils' => 'Verbesserung', 'mangel' => 'Mangel', 'na' => 'trifft nicht zu'];
const TC_PRIO = [3 => 'Sofort', 2 => 'Bald', 1 => 'Später'];

/* Bereiche und Prüfpunkte. Je Punkt: [id, Titel, So prüfen, Warum, Empfehlung (Vorschlag), Gewicht 1–3, Aufwand in Minuten für die Umsetzung, auto-Id aus dem 100-Punkte-Check oder ''] */
function tc_katalog(): array {
  static $k = null; if ($k !== null) return $k;
  $k = [
    'eindruck' => ['Erster Eindruck und Nutzerführung', 'Was ein Besucher in den ersten Sekunden versteht und ob er den Weg findet.', [
      ['klar5s', 'In 5 Sekunden klar: was, für wen, wo', 'Startseite öffnen, 5 Sekunden hinsehen, Seite schließen: Was wird angeboten, für wen, in welcher Region? Wenn eine Antwort fehlt, ist der Punkt ein Mangel.', 'Besucher entscheiden in Sekunden, ob sie bleiben. Unklare Startseiten verlieren die meisten Anfragen, bevor jemand scrollt.', 'Erste Überschrift und erster Satz nennen Leistung, Zielgruppe und Ort, z. B. „Malerarbeiten für Haus und Wohnung in Karlsruhe“.', 3, 30, ''],
      ['nutzen', 'Hauptnutzen in der ersten Überschrift', 'Steht in der ersten Überschrift ein Nutzen für den Kunden oder nur der Firmenname oder „Willkommen“?', 'Überschriften werden gelesen, Fließtext überflogen. „Willkommen auf unserer Website“ sagt nichts.', 'Überschrift mit Ergebnis für den Kunden formulieren, Firmenname gehört ins Logo.', 2, 15, ''],
      ['ort', 'Ort oder Region sichtbar', 'Ist auf der Startseite ohne Scrollen zu sehen, wo das Unternehmen arbeitet?', 'Lokale Kunden und Google brauchen den Ort; sonst gehen Anfragen an Betriebe mit sichtbarem Standort.', 'Ort in Überschrift, Fußzeile und Kontaktbereich nennen; Einzugsgebiet in einem Satz.', 2, 10, 'lokal'],
      ['navigation', 'Navigation verständlich und kurz', 'Menüpunkte zählen (mehr als 7 ist zu viel) und lesen: Versteht ein Fremder jeden Begriff? Gibt es verschachtelte Untermenüs?', 'Jeder unklare Menüpunkt kostet Klicks. Besucher suchen „Leistungen“, „Preise“, „Kontakt“, nicht kreative Begriffe.', 'Höchstens 6 bis 7 Punkte mit gewöhnlichen Begriffen; Kontakt immer als letzter Punkt oder als Knopf.', 2, 20, ''],
      ['cta', 'Klarer nächster Schritt oben und unten', 'Gibt es auf der Startseite einen deutlichen Knopf (Anfragen, Anrufen, Termin), sowohl im sichtbaren Bereich als auch am Ende der Seite?', 'Ohne Aufforderung passiert nichts. Der Knopf muss dort stehen, wo der Besucher gerade überzeugt ist.', 'Einen Hauptknopf definieren (z. B. „Angebot anfragen“) und oben, nach den Leistungen und in der Fußzeile wiederholen.', 3, 20, 'handlung'],
      ['weg', 'Weg von der Startseite zur Leistung in einem Klick', 'Von der Startseite aus versuchen, in einem Klick zur wichtigsten Leistung und zum Kontakt zu kommen.', 'Jeder zusätzliche Klick halbiert grob die Zahl der Besucher, die ankommen.', 'Leistungen auf der Startseite anreißen und direkt verlinken.', 2, 20, ''],
      ['sackgassen', 'Keine Sackgassen', 'Jede Seite bis zum Ende scrollen: Endet sie mit einem nächsten Schritt (Kontakt, Leistung, Zurück) oder einfach mit der Fußzeile?', 'Seiten ohne Fortsetzung lassen Besucher abspringen.', 'Jede Seite endet mit Kontaktbereich oder Verweis auf die nächste sinnvolle Seite.', 1, 20, ''],
      ['lesbar', 'Schrift lesbar, Kontrast ausreichend', 'Fließtext mindestens 16 px, Zeilen nicht länger als 80 Zeichen, Text auf Fotos oder hellem Grau lesbar? Zur Not mit einem Kontrastprüfer messen (4,5:1).', 'Unlesbare Texte werden nicht gelesen; schwache Kontraste schließen ältere Besucher aus.', 'Schriftgröße und Kontraste anheben, Text nicht auf Fotos legen.', 2, 20, 'schrift'],
      ['bilder', 'Echte Bilder statt austauschbarer Stockfotos', 'Fotos ansehen: Zeigen sie den Betrieb, das Team, echte Arbeiten? Sind sie scharf und aktuell?', 'Echte Fotos schaffen Vertrauen; Stockfotos wirken austauschbar und werden erkannt.', 'Eigene Fotos (Team, Arbeiten, Räume) einsetzen; wenn nötig einen Fototermin einplanen.', 2, 30, ''],
      ['konsistenz', 'Einheitliches Bild: Logo, Farben, Schrift', 'Mehrere Seiten vergleichen: gleiche Farben, gleiche Schrift, gleiches Logo, gleicher Aufbau?', 'Uneinheitlichkeit wirkt ungepflegt und verunsichert.', 'Ein Satz Farben und zwei Schriften festlegen und überall anwenden.', 1, 30, 'gepflegt'],
    ]],
    'inhalte' => ['Inhalte und Texte', 'Ob Besucher verstehen, was sie bekommen, und ob die Inhalte stimmen.', [
      ['leistungen', 'Leistungen konkret: was, für wen, Ergebnis', 'Jede Leistungsseite lesen: Steht da, was genau gemacht wird, für wen und mit welchem Ergebnis? Oder nur Allgemeines?', 'Konkrete Leistungen bringen passende Anfragen; vage Texte bringen Rückfragen oder nichts.', 'Je Leistung: Überschrift, zwei Sätze Nutzen, drei Stichpunkte, nächster Schritt.', 3, 60, ''],
      ['preise', 'Preise oder Preislogik genannt', 'Gibt es Preise, Preisspannen oder wenigstens „ab“-Preise oder eine Erklärung, wie Preise entstehen?', 'Besucher ohne Preisgefühl fragen seltener an oder vergleichen woanders.', 'Mindestens „ab“-Preise oder Beispiele nennen; sonst erklären, wie das Angebot entsteht.', 2, 30, ''],
      ['sprache', 'Texte kurz, ohne Floskeln und Fachjargon', 'Drei Absätze lesen: Sätze über 25 Wörter, Floskeln („Ihr kompetenter Partner“), unerklärte Fachbegriffe markieren.', 'Floskeln sagen nichts und lassen Texte austauschbar wirken; Fachjargon schließt Laien aus.', 'Kurze Sätze, konkrete Aussagen, Fachbegriffe in einem Halbsatz erklären.', 2, 45, ''],
      ['fehler', 'Rechtschreibung und Grammatik', 'Alle Seiten mit Rechtschreibprüfung lesen (Browser-Prüfung oder Werkzeug).', 'Fehler wirken unprofessionell, besonders bei Dienstleistern, die Sorgfalt verkaufen.', 'Fehler korrigieren, danach eine zweite Person lesen lassen.', 2, 30, ''],
      ['aktuell', 'Inhalte aktuell: Jahr, Zeiten, Aktuelles', 'Nach Jahreszahlen, „Aktuelles“, Öffnungszeiten, Feiertagen, alten Aktionen suchen. Ist etwas älter als ein Jahr?', 'Veraltete Inhalte wirken wie ein geschlossener Laden.', 'Veraltetes entfernen, Jahreszahl automatisch, Öffnungszeiten prüfen.', 2, 15, 'gepflegt'],
      ['ueberuns', 'Über uns mit echten Menschen', 'Gibt es eine Seite oder einen Abschnitt mit Name, Foto, kurzer Geschichte und Haltung?', 'Menschen beauftragen Menschen; anonyme Websites verlieren gegen persönliche.', 'Inhaber und Team mit Foto, Namen und zwei Sätzen vorstellen.', 2, 30, 'ueber_uns'],
      ['referenzen', 'Referenzen, Projekte oder Bewertungen', 'Gibt es Beispiele (Projekte, Vorher/Nachher), echte Bewertungen oder Logos von Kunden?', 'Belege ersetzen Behauptungen; Google-Bewertungen auf der Seite wirken doppelt.', 'Drei Projekte mit Foto und zwei Sätzen; Google-Bewertungen einbinden oder verlinken.', 2, 45, 'vertrauen'],
      ['faq', 'Fragen und Einwände beantwortet', 'Gibt es Antworten auf typische Fragen (Preis, Dauer, Ablauf, Region, Garantie)?', 'Unbeantwortete Einwände bremsen Anfragen; gute Antworten sparen Telefonate.', 'Sechs häufige Fragen mit kurzen Antworten ergänzen.', 1, 30, ''],
      ['zweck', 'Jede Seite hat einen Zweck', 'Seitenliste durchgehen: Welche Seite bringt was? Doppelte oder leere Seiten (z. B. „News“ ohne Beiträge)?', 'Leere oder doppelte Seiten verwässern die Seite und kosten Pflege.', 'Seiten ohne Zweck löschen oder zusammenlegen.', 1, 20, ''],
      ['texte_eigen', 'Texte eigenständig, nicht kopiert', 'Einen markanten Satz bei Google in Anführungszeichen suchen: Taucht er auf anderen Websites auf?', 'Kopierte Texte sind rechtlich riskant und werden von Google abgewertet.', 'Eigene Texte schreiben, Vorlagen-Texte des Baukastens ersetzen.', 2, 60, ''],
    ]],
    'handy' => ['Handy und Bedienung', 'Mit einem echten Handy geprüft, nicht nur im Browser verkleinert.', [
      ['menue', 'Menü auf dem Handy bedienbar', 'Auf dem Handy öffnen: Lässt sich das Menü öffnen, sind alle Punkte erreichbar, schließt es wieder?', 'Mehr als die Hälfte der Besucher kommt vom Handy; ein kaputtes Menü ist eine Sackgasse.', 'Menü-Knopf gut sichtbar, Punkte groß genug, Schließen per Tippen daneben.', 3, 20, 'viewport'],
      ['tippziele', 'Knöpfe und Links groß genug (44 px)', 'Mit dem Daumen tippen: Trifft man Knöpfe und Links ohne Zoomen? Liegen Links zu dicht beieinander?', 'Zu kleine Ziele führen zu Fehltipps und Abbrüchen.', 'Knöpfe mindestens 44 px hoch, Abstand zwischen Links.', 2, 15, ''],
      ['abgeschnitten', 'Nichts abgeschnitten, kein Querscrollen', 'Alle Seiten auf dem Handy scrollen: Ragen Tabellen, Bilder oder Texte über den Rand? Lässt sich die Seite seitlich schieben?', 'Abgeschnittene Inhalte sind unbenutzbar und wirken kaputt.', 'Breiten auf 100 % begrenzen, Tabellen scrollbar oder umbauen.', 2, 20, 'starre_breiten'],
      ['formular_handy', 'Formular auf dem Handy ausfüllbar', 'Formular auf dem Handy ausfüllen: Felder groß genug, passende Tastatur (Telefon, E-Mail), Fehlermeldungen verständlich?', 'Ein Formular, das auf dem Handy nervt, wird abgebrochen.', 'Wenige Felder, richtige Eingabetypen, große Felder, klare Fehlermeldung.', 2, 20, ''],
      ['telefon_tipp', 'Telefonnummer antippbar', 'Telefonnummer auf dem Handy antippen: Öffnet sich die Telefon-App?', 'Der kürzeste Weg zur Anfrage ist ein Anruf; abgetippte Nummern verlieren Anrufe.', 'Nummer als Link (tel:) einbauen, oben sichtbar.', 2, 5, 'telefon'],
      ['tempo_handy', 'Ladezeit auf dem Handy erträglich', 'Seite im Mobilfunknetz laden und Sekunden zählen; über 3 Sekunden ist zu langsam. Messwert aus dem automatischen Check dazunehmen.', 'Jede Sekunde Ladezeit kostet Besucher, besonders unterwegs.', 'Bilder verkleinern (WebP), Skripte reduzieren, Zwischenspeicher einschalten.', 2, 45, 'ladezeit'],
      ['lesen_handy', 'Text ohne Zoomen lesbar', 'Auf dem Handy lesen: Muss man zoomen? Sind Zeilen zu lang oder Schrift zu klein?', 'Zoomen ist mühsam; die meisten lesen dann nicht.', 'Schrift ab 16 px, Zeilenabstand 1,5, Spalten auf dem Handy untereinander.', 2, 15, 'schrift'],
      ['popups', 'Keine störenden Pop-ups oder Banner', 'Beim ersten Besuch auf dem Handy: Verdecken Banner, Cookie-Fenster oder Chat-Blasen den Inhalt? Lassen sie sich leicht schließen?', 'Verdeckte Inhalte führen zum Abbruch; Google wertet aufdringliche Fenster ab.', 'Nur nötige Hinweise, klein und leicht schließbar.', 1, 15, ''],
    ]],
    'kontakt' => ['Kontakt und Anfragewege', 'Wie leicht aus einem Besucher eine Anfrage wird und was danach passiert.', [
      ['zwei_klicks', 'Kontakt in höchstens zwei Klicks', 'Von jeder Seite aus: Wie viele Klicks bis zu Telefonnummer oder Formular?', 'Kontakt ist das Ziel der Website; jeder Umweg kostet Anfragen.', 'Kontakt im Menü, Telefonnummer in der Kopfzeile, Formular in der Fußzeile.', 3, 20, 'kontakt'],
      ['formular_felder', 'Formular mit wenigen, sinnvollen Feldern', 'Felder zählen: Mehr als fünf Pflichtfelder? Werden Dinge gefragt, die man erst später braucht (Adresse, Firma)?', 'Jedes Pflichtfeld senkt die Zahl der Absender.', 'Name, Kontakt, Anliegen reichen; Rest später am Telefon.', 2, 15, 'formular'],
      ['formular_hinweis', 'Datenschutzhinweis und Bestätigung am Formular', 'Gibt es am Formular einen Hinweis zur Verarbeitung (oder Häkchen) und nach dem Senden eine sichtbare Bestätigung?', 'Ohne Hinweis drohen Abmahnungen; ohne Bestätigung wird doppelt gesendet oder angerufen.', 'Kurzer Hinweis mit Link zur Datenschutzerklärung, Bestätigungsseite oder -text nach dem Senden.', 2, 15, 'formular_hinweis'],
      ['testanfrage', 'Testanfrage: kommt eine Antwort, wie schnell?', 'Eine echte Testanfrage über das Formular senden (als Lou Digital kenntlich): Kommt eine automatische Bestätigung? Wann antwortet jemand?', 'Viele Formulare landen im Spam oder bei niemandem; der Kunde merkt es nicht.', 'Eingangsbestätigung automatisch, Antwort innerhalb eines Werktags; Anfrage-Automatik prüfen.', 3, 30, ''],
      ['zeiten', 'Telefon und Erreichbarkeitszeiten genannt', 'Stehen Telefonnummer und Zeiten, zu denen jemand abnimmt, auf der Startseite oder Kontaktseite?', 'Anrufer, die niemanden erreichen, rufen den nächsten an.', 'Zeiten nennen, außerhalb der Zeiten Rückruf anbieten.', 2, 10, 'zeiten'],
      ['messenger', 'WhatsApp oder Terminbuchung, wo es passt', 'Prüfen, ob die Zielgruppe schnelle Wege erwartet (WhatsApp-Knopf, Online-Termin) und ob sie angeboten werden.', 'Für viele Betriebe kommen die meisten Anfragen über WhatsApp oder Terminbuchung.', 'WhatsApp-Link oder Buchungstool einbinden, Datenschutz dazu.', 1, 30, 'messenger'],
      ['anfahrt', 'Adresse, Anfahrt und Karte', 'Gibt es Adresse, Hinweise zur Anfahrt oder Parken und eine datenschutzfreundliche Karte oder einen Karten-Link?', 'Kunden vor Ort wollen wissen, wie sie hinkommen.', 'Adresse mit Karten-Link (ohne eingebettete Karte ohne Zustimmung), Parkhinweis.', 1, 15, 'karte'],
      ['danach', 'Erklärt, was nach der Anfrage passiert', 'Steht irgendwo, wie es nach einer Anfrage weitergeht (Rückruf, Termin, Angebot, Dauer)?', 'Unsicherheit über den Ablauf hält vom Anfragen ab.', 'Drei Schritte „So geht es weiter“ neben dem Formular.', 1, 15, ''],
      ['vertrauen', 'Vertrauensanker: Bewertungen, Siegel, Kammer', 'Sind Bewertungen, Mitgliedschaften (Kammer, Innung), Zertifikate oder Versicherungen sichtbar und echt?', 'Vertrauensanker senken das Risiko in den Augen des Besuchers.', 'Echte Nachweise sichtbar platzieren; keine erfundenen Siegel.', 2, 20, 'vertrauen'],
    ]],
    'sichtbar' => ['Sichtbarkeit bei Google und vor Ort', 'Ob Kunden das Unternehmen finden, wenn sie danach suchen.', [
      ['gprofil', 'Google-Unternehmensprofil vorhanden und gepflegt', 'Firmenname bei Google Maps suchen: Profil vorhanden, beansprucht, richtige Kategorie, Öffnungszeiten, Fotos, Website-Link?', 'Das Profil ist für lokale Betriebe oft wichtiger als die Website selbst.', 'Profil beanspruchen, Kategorie und Zeiten prüfen, 10 Fotos, Website verlinken.', 3, 45, 'bewertungsprofil'],
      ['bewertungen', 'Bewertungen: Anzahl, Schnitt, Antworten', 'Im Google-Profil zählen: Wie viele Bewertungen, welcher Schnitt, wird auf Bewertungen geantwortet?', 'Bewertungen entscheiden mit, wer angerufen wird.', 'Bewertungsbitte nach jedem Auftrag (Automatik), auf jede Bewertung antworten.', 2, 30, ''],
      ['suchposition', 'Position bei „Leistung + Ort“', 'Die 2 bis 3 Suchbegriffe des Kunden bei Google suchen (anonymes Fenster): Auf welcher Position erscheinen Website und Profil? Wer steht davor?', 'Was auf Seite 2 steht, existiert für Kunden nicht.', 'Seitentitel und Inhalte auf die Suchbegriffe ausrichten, Profil pflegen, Bewertungen sammeln.', 3, 60, ''],
      ['titel_beschreibung', 'Seitentitel und Beschreibung je Seite sinnvoll', 'Titel und Beschreibung jeder Seite ansehen (im Quelltext oder mit Werkzeug): Nennen sie Leistung und Ort? Sind sie einmalig?', 'Titel und Beschreibung sind das Schaufenster in der Suche.', 'Je Seite ein Titel mit Leistung und Ort (bis 60 Zeichen) und eine Beschreibung (bis 155 Zeichen).', 2, 30, 'titel'],
      ['nap', 'Firmendaten überall gleich (Name, Adresse, Telefon)', 'Name, Adresse und Telefonnummer auf Website, Google-Profil und zwei Verzeichnissen vergleichen.', 'Abweichungen verwirren Google und Kunden.', 'Eine Schreibweise festlegen und überall angleichen.', 1, 30, 'strukturdaten_firma'],
      ['bilder_seo', 'Bilder beschrieben, Dateinamen sinnvoll', 'Quelltext oder Werkzeug: Haben Bilder Alt-Texte, heißen die Dateien nach dem Inhalt statt „IMG_1234“?', 'Alt-Texte helfen Google und Menschen mit Vorlesefunktion.', 'Alt-Texte und Dateinamen mit Inhalt und Ort vergeben.', 1, 30, 'alt'],
      ['verlinkung', 'Seiten untereinander sinnvoll verlinkt', 'Werden Leistungen aus Texten heraus verlinkt, nicht nur über das Menü? Sind Linktexte aussagekräftig?', 'Interne Links führen Besucher und Google zu den wichtigen Seiten.', 'Aus jedem Text auf die passende Leistung verlinken, Linktext mit Inhalt.', 1, 20, 'linktexte'],
      ['blog', 'Aktuelles oder Blog gepflegt oder entfernt', 'Gibt es einen Blog oder „Aktuelles“? Letzter Beitrag älter als 6 Monate?', 'Verwaiste Blogs wirken wie ein Stillstand.', 'Entweder regelmäßig schreiben (Projekte, Fragen) oder den Bereich entfernen.', 1, 15, ''],
      ['social', 'Social-Media-Profile verlinkt und aktiv', 'Verlinkte Profile öffnen: aktiv, passend zur Zielgruppe, gleiche Firmendaten?', 'Tote Profile schaden mehr als keine.', 'Nur aktive Profile verlinken.', 1, 10, 'social'],
    ]],
    'recht' => ['Recht und Datenschutz (Sichtprüfung)', 'Keine Rechtsberatung: geprüft wird, ob die üblichen Pflichtangaben vorhanden sind und zu den eingesetzten Diensten passen.', [
      ['impressum', 'Impressum vollständig', 'Impressum lesen: Name, Anschrift, Kontakt (E-Mail), Vertretungsberechtigte, Register, USt-ID, Kammer/Berufsrecht, Aufsichtsbehörde je nach Branche.', 'Fehlende Angaben sind der häufigste Abmahngrund.', 'Fehlende Angaben ergänzen; Generator oder Anwalt für die Formulierung.', 3, 20, 'impressum_inhalt'],
      ['dse_passt', 'Datenschutzerklärung passt zu den Diensten', 'Eingesetzte Dienste notieren (Fonts, Karten, Analyse, Formular, Hoster, Video, Social) und prüfen, ob die Erklärung jeden nennt.', 'Eine Erklärung, die nicht zur Website passt, ist wertlos.', 'Erklärung mit Generator neu erzeugen, alle Dienste ankreuzen.', 3, 30, 'datenschutz'],
      ['banner', 'Cookie-Banner nur wenn nötig und korrekt', 'Welche Cookies werden vor Zustimmung gesetzt? Gibt es einen Banner mit gleichwertigem „Ablehnen“? Oder gar keinen, obwohl Analyse läuft?', 'Falsche Banner sind abmahnbar; unnötige Banner kosten Besucher.', 'Ohne Analyse und Werbung: kein Banner nötig. Sonst Banner mit Ablehnen auf gleicher Ebene.', 2, 30, 'einwilligung'],
      ['fonts', 'Schriften und Skripte lokal statt von Google', 'Im Quelltext oder Netzwerk-Tab prüfen, ob Schriften von fonts.googleapis.com oder Skripte von fremden Servern vor Zustimmung laden.', 'Fremde Dienste ohne Zustimmung sind ein bekannter Abmahngrund.', 'Schriften und Skripte selbst hosten.', 2, 20, 'dritte'],
      ['einbettungen', 'Karten und Videos erst nach Zustimmung', 'Google Maps, YouTube, Instagram-Einbettungen: Laden sie sofort oder erst nach Klick?', 'Einbettungen übertragen Daten an Dritte, bevor der Besucher zustimmt.', 'Zwei-Klick-Lösung oder Link statt Einbettung.', 2, 20, 'video'],
      ['form_einwilligung', 'Formulare mit Einwilligung und verschlüsselt', 'Formulare: Hinweis oder Häkchen vorhanden, Versand über HTTPS?', 'Daten aus Formularen sind personenbezogen.', 'Hinweis ergänzen, HTTPS sicherstellen.', 2, 15, 'formulare'],
      ['bildrechte', 'Bildrechte und Quellen geklärt', 'Nachfragen: Woher stammen Fotos und Grafiken? Lizenz vorhanden? Personen auf Fotos einverstanden?', 'Bilder ohne Lizenz sind teuer; Mitarbeiterfotos brauchen Einwilligung.', 'Lizenzen dokumentieren, fragliche Bilder ersetzen, Einwilligungen einholen.', 2, 30, ''],
      ['preisangaben', 'Preisangaben und AGB, wo nötig', 'Werden Preise an Endkunden genannt? Dann mit Umsatzsteuer und Versand. Shop: AGB, Widerruf, Button-Beschriftung.', 'Preisangabenverordnung und Fernabsatzrecht gelten auch für kleine Shops.', 'Preise vollständig angeben; bei Shops AGB und Widerruf vom Anwalt.', 1, 20, 'shop_recht'],
    ]],
    'technik' => ['Technik und Sicherheit', 'Die Punkte aus dem automatischen Check, von Hand nachgeprüft und eingeordnet.', [
      ['https', 'HTTPS mit gültigem Zertifikat und Umleitung', 'Seite mit http:// und ohne www aufrufen: Landet man immer auf https:// und derselben Adresse? Schloss im Browser ohne Warnung?', 'Ohne HTTPS warnen Browser, Google stuft ab.', 'Zertifikat (Let’s Encrypt) und Umleitung beim Hoster einrichten.', 3, 30, 'https'],
      ['zertifikat', 'Zertifikat läuft nicht bald ab', 'Zertifikat im Browser öffnen: gültig bis? Verlängert es sich automatisch?', 'Ein abgelaufenes Zertifikat sperrt Besucher mit einer Warnseite aus.', 'Automatische Verlängerung einschalten oder Betreuung mit Überwachung.', 2, 15, 'zertifikat'],
      ['header', 'Sicherheits-Header gesetzt', 'Aus dem automatischen Check übernehmen: HSTS, X-Frame-Options, nosniff, Referrer-Policy.', 'Fehlende Header erleichtern Angriffe wie heimliches Einbetten.', 'Header in der Serverkonfiguration oder .htaccess setzen.', 1, 20, 'clickjacking'],
      ['fehlerseite', 'Fehlerseite hilft weiter', 'Eine nicht existierende Adresse aufrufen: Kommt eine gestaltete 404-Seite mit Navigation oder eine Serverseite?', 'Falsche Links passieren; die Fehlerseite soll zurückführen.', 'Eigene 404-Seite mit Links zu Start und Kontakt.', 1, 15, 'fehlerseite_hilfe'],
      ['links', 'Keine kaputten Links (Stichprobe)', 'Aus dem automatischen Check übernehmen und 10 Links von Hand prüfen, auch PDF-Downloads.', 'Kaputte Links wirken ungepflegt und führen ins Leere.', 'Links korrigieren, Prüfung in die Betreuung aufnehmen.', 2, 20, 'links'],
      ['tempo', 'Ladezeit und Seitengröße', 'Messwert aus dem automatischen Check (Antwortzeit, Größe, Komprimierung) einordnen; große Bilder notieren.', 'Langsame Seiten verlieren Besucher und Google-Plätze.', 'Bilder als WebP, Komprimierung und Cache einschalten.', 2, 45, 'tempo'],
      ['system', 'System und Erweiterungen aktuell', 'Welches System (WordPress, Baukasten)? Version sichtbar? Erweiterungen gepflegt? Beim Kunden nachfragen, wer Updates macht.', 'Veraltete Systeme sind das Haupteinfallstor für Angriffe.', 'Updates regelmäßig einspielen oder auf eine statische Seite wechseln.', 2, 30, 'system_aktuell'],
      ['backup', 'Sicherung vorhanden und wiederherstellbar', 'Beim Kunden oder Hoster nachfragen: Wird gesichert, wie oft, wurde je wiederhergestellt?', 'Ohne Sicherung ist ein Angriff oder Fehler das Ende der Website.', 'Tägliche oder wöchentliche Sicherung mit Aufbewahrung, einmal testen.', 3, 30, ''],
      ['email', 'E-Mail-Schutz der Domain (SPF, DMARC)', 'Aus dem automatischen Check übernehmen: SPF, DKIM, DMARC vorhanden und streng genug?', 'Ohne Schutz landen eigene Mails im Spam und Fälschungen im Namen der Firma sind leicht.', 'SPF, DKIM und DMARC beim Domain-Anbieter eintragen.', 2, 30, 'spf'],
    ]],
    'betrieb' => ['Betrieb und Zukunft', 'Wer die Website pflegt, was sie kostet und ob sie ein Ziel hat.', [
      ['pflege', 'Wer pflegt die Website, wie oft?', 'Beim Kunden fragen: Wer ändert Inhalte, wie schnell geht das, was kostet es?', 'Ohne klare Zuständigkeit veraltet die Website.', 'Zuständigkeit festlegen oder Betreuung buchen.', 2, 15, ''],
      ['zugaenge', 'Zugänge bekannt: Domain, Hosting, System', 'Fragen: Sind Zugänge zu Domain, Hosting und System vorhanden, auf wen laufen die Verträge?', 'Fehlende Zugänge blockieren jede Änderung und jeden Wechsel.', 'Zugänge sichern, Verträge auf den eigenen Namen holen.', 3, 30, ''],
      ['kosten', 'Hosting-Kosten und Verträge angemessen', 'Fragen: Was kostet Hosting, Domain, Baukasten im Monat? Laufzeit? Gibt es Doppelzahlungen?', 'Alte Verträge kosten oft mehr, als sie leisten.', 'Verträge prüfen, ggf. günstigeren Hoster wählen.', 1, 20, ''],
      ['domain', 'Domain-Laufzeit und Inhaberschaft', 'Domain-Laufzeit und Inhaber prüfen (Whois oder Vertrag): Läuft die Domain auf den Kunden?', 'Eine Domain auf den Namen einer alten Agentur kann verloren gehen.', 'Domain auf den Kunden übertragen, Verlängerung automatisch.', 2, 20, ''],
      ['messung', 'Besucher und Anfragen werden gemessen', 'Gibt es eine datenschutzfreundliche Statistik? Weiß der Kunde, wie viele Besucher und Anfragen kommen?', 'Ohne Zahlen lässt sich die Website nicht verbessern.', 'Statistik ohne Cookies einrichten, Anfragen in einer Tabelle zählen.', 1, 30, ''],
      ['ziel', 'Ziel der Website definiert', 'Fragen: Was soll die Website im Monat bringen (Anfragen, Anrufe, Bewerbungen)? Gibt es eine Zahl?', 'Eine Website ohne Ziel wird nicht gepflegt und nicht gemessen.', 'Ein Ziel mit Zahl festlegen und monatlich prüfen.', 1, 15, ''],
    ]],
  ];
  return $k;
}
function tc_alle_punkte(): array { $o = []; foreach (tc_katalog() as $bid => [$bt, $bd, $pts]) foreach ($pts as $p) $o[$p[0]] = ['bereich' => $bid, 'titel' => $p[1], 'wie' => $p[2], 'warum' => $p[3], 'empf' => $p[4], 'gewicht' => (int)$p[5], 'minuten' => (int)$p[6], 'auto' => $p[7]]; return $o; }

/* Priorität aus Gewicht und Status: 3 = Sofort, 2 = Bald, 1 = Später, 0 = keine Maßnahme */
function tc_prio(int $gewicht, string $status): int {
  if ($status === 'mangel') return $gewicht >= 3 ? 3 : ($gewicht === 2 ? 2 : 1);
  if ($status === 'teils') return $gewicht >= 3 ? 2 : 1;
  return 0;
}

/* Auswertung eines Checks: Score 0–100 (gewichtet, nur beurteilte Punkte), je Bereich, Maßnahmen sortiert */
function tc_auswertung(array $punkte): array {
  $kat = tc_katalog(); $alle = tc_alle_punkte();
  $sum = 0; $max = 0; $bereiche = []; $massnahmen = []; $beurteilt = 0; $gesamt = count($alle); $aufwand = 0;
  foreach ($kat as $bid => [$bt, $bd, $pts]) $bereiche[$bid] = ['titel' => $bt, 'sum' => 0, 'max' => 0, 'mangel' => 0, 'teils' => 0, 'ok' => 0, 'offen' => 0, 'na' => 0, 'n' => count($pts)];
  foreach ($alle as $id => $p) {
    $e = $punkte[$id] ?? []; $st = (string)($e['st'] ?? 'offen'); if (!isset(TC_STATUS[$st])) $st = 'offen';
    $bk = $p['bereich'];
    if ($st === 'offen') { $bereiche[$bk]['offen']++; continue; }
    if ($st === 'na') { $bereiche[$bk]['na']++; $beurteilt++; continue; }
    $beurteilt++; $w = $p['gewicht']; $v = $st === 'ok' ? 1.0 : ($st === 'teils' ? 0.5 : 0.0);
    $sum += $v * $w; $max += $w; $bereiche[$bk]['sum'] += $v * $w; $bereiche[$bk]['max'] += $w; $bereiche[$bk][$st]++;
    $pr = tc_prio($w, $st);
    if ($pr > 0) { $min = max(0, (int)($e['aw'] ?? $p['minuten'])); $aufwand += $min; $massnahmen[] = ['id' => $id, 'bereich' => $bk, 'bereich_titel' => $bereiche[$bk]['titel'], 'titel' => $p['titel'], 'status' => $st, 'prio' => $pr, 'gewicht' => $w, 'minuten' => $min, 'befund' => trim((string)($e['bf'] ?? '')), 'empf' => trim((string)($e['em'] ?? '')) !== '' ? trim((string)$e['em']) : $p['empf']]; }
  }
  usort($massnahmen, function ($a, $b) { return [$b['prio'], $b['gewicht'], $a['minuten']] <=> [$a['prio'], $a['gewicht'], $b['minuten']]; });
  foreach ($bereiche as &$b) $b['prozent'] = $b['max'] > 0 ? (int)round($b['sum'] / $b['max'] * 100) : null; unset($b);
  return ['score' => $max > 0 ? (int)round($sum / $max * 100) : 0, 'beurteilt' => $beurteilt, 'gesamt' => $gesamt, 'bereiche' => $bereiche, 'massnahmen' => $massnahmen, 'aufwand' => $aufwand,
          'sofort' => count(array_filter($massnahmen, function ($m) { return $m['prio'] === 3; })), 'bald' => count(array_filter($massnahmen, function ($m) { return $m['prio'] === 2; })), 'spaeter' => count(array_filter($massnahmen, function ($m) { return $m['prio'] === 1; }))];
}

/* Ergebnis des automatischen Checks (JSON aus api/check100.php) in die offenen Punkte übernehmen. Gibt die Zahl der vorbelegten Punkte zurück. */
function tc_auto_uebernehmen(array $auto, array &$punkte): int {
  $items = []; foreach ($auto['items'] ?? [] as $it) if (isset($it['id'])) $items[$it['id']] = $it;
  $n = 0;
  foreach (tc_alle_punkte() as $id => $p) {
    if ($p['auto'] === '' || !isset($items[$p['auto']])) continue;
    $cur = $punkte[$id] ?? []; if (($cur['st'] ?? 'offen') !== 'offen') continue;
    $it = $items[$p['auto']]; $st = (string)($it['status'] ?? 'np');
    $neu = $st === 'ok' ? 'ok' : ($st === 'fehlt' ? ($p['gewicht'] >= 2 ? 'mangel' : 'teils') : ($st === 'na' ? 'na' : 'offen'));
    if ($neu === 'offen') continue;
    $punkte[$id] = ['st' => $neu, 'bf' => 'Automatischer Check: ' . trim((string)($it['detail'] ?? '')), 'em' => (string)($cur['em'] ?? ''), 'aw' => (int)($cur['aw'] ?? $p['minuten']), 'auto' => 1];
    $n++;
  }
  return $n;
}

/* Umsetzungsangebot aus dem Aufwand: 1 € je Minute, auf 10 € gerundet; ab 500 € wird der Tiefen-Check angerechnet */
function tc_angebot(int $minuten): array {
  $preis = (int)(ceil($minuten * LD_STUNDE / 60 / 10) * 10);
  return ['minuten' => $minuten, 'preis' => $preis, 'anrechnung' => $preis >= 500];
}

/* Bericht als PDF */
function tc_pdf(array $c): string {
  require_once __DIR__ . '/pdf.php';
  $punkte = json_decode((string)$c['punkte'], true) ?: []; $aw = tc_auswertung($punkte); $kat = tc_katalog(); $alle = tc_alle_punkte();
  $auto = $c['auto'] !== '' ? (json_decode((string)$c['auto'], true) ?: null) : null;
  $firma = $c['firma'] !== '' ? $c['firma'] : $c['host'];
  $p = new LdPdf('Tiefen-Check ' . $firma, 'Lou Digital · Adrian Semlinger · Karlsruhe · info@lou-digital.de · ' . LD_PHONE);
  $p->text('LOU DIGITAL', 9, 'F2', 0, 0, 1); $p->leer(14);
  $p->titel('Tiefen-Check für ' . $firma);
  $p->zeile($c['url'] . ' · geprüft von Hand am ' . date('d.m.Y', strtotime($c['aktualisiert'])) . ' · ' . $aw['beurteilt'] . ' von ' . $aw['gesamt'] . ' Prüfpunkten beurteilt', 10);
  if ($c['ziel'] !== '') $p->zeile('Ziel der Website: ' . $c['ziel'], 10);
  $p->leer(10);
  $p->kacheln([[$aw['score'] . ' / 100', 'Gesamtwert (gewichtet)'], [(string)$aw['sofort'], 'Sofort erledigen'], [(string)$aw['bald'], 'Bald erledigen'], [(string)$aw['spaeter'], 'Später sinnvoll']]);
  $p->abschnitt('Ergebnis je Bereich');
  $rows = [['Bereich', 'Wert', 'Mängel', 'Verbesserungen', 'In Ordnung']];
  foreach ($aw['bereiche'] as $b) $rows[] = [$b['titel'], $b['prozent'] === null ? '–' : $b['prozent'] . ' %', (string)$b['mangel'], (string)$b['teils'], (string)$b['ok']];
  $p->tabelle($rows, [220, 60, 60, 90, 60]);
  if ($c['zusammenfassung'] !== '') { $p->abschnitt('Zusammenfassung'); foreach (preg_split('/\n{2,}/', $c['zusammenfassung']) as $abs) { $p->zeile(trim($abs), 10); $p->leer(4); } }
  $p->abschnitt('Die wichtigsten Maßnahmen');
  if ($aw['massnahmen']) {
    $rows = [['#', 'Maßnahme', 'Priorität', 'Aufwand']]; $i = 0;
    foreach (array_slice($aw['massnahmen'], 0, 12) as $m) { $i++; $rows[] = [(string)$i, $m['titel'] . ': ' . $m['empf'], TC_PRIO[$m['prio']], $m['minuten'] . ' Min.']; }
    $p->tabelle($rows, [24, 326, 70, 70]);
    $ang = tc_angebot($aw['aufwand']);
    $p->zeile('Umsetzung aller Maßnahmen zum Festpreis: ' . number_format($ang['preis'], 0, ',', '.') . ' € (' . $ang['minuten'] . ' Minuten geschätzt, 1 € je Minute, ohne Umsatzsteuer nach § 19 UStG). ' . ($ang['anrechnung'] ? 'Der Tiefen-Check wird dabei voll angerechnet.' : 'Bei einer Website in 7 Tagen oder einer Umsetzung ab 500 € wird der Tiefen-Check voll angerechnet.') . ' Das verbindliche Angebot bekommen Sie nach unserem Gespräch.', 10);
  } else $p->zeile('Keine offenen Maßnahmen.', 10);
  if ($c['empfehlung'] !== '') { $p->leer(6); $p->zeile('Empfehlung: ' . $c['empfehlung'], 10, 'F2'); }
  foreach ($kat as $bid => [$bt, $bd, $pts]) {
    $p->seite(); $p->abschnitt($bt); $p->zeile($bd, 9.5); $p->leer(4);
    $rows = [['Prüfpunkt', 'Status', 'Befund und Empfehlung']];
    foreach ($pts as $pt) {
      $e = $punkte[$pt[0]] ?? []; $st = (string)($e['st'] ?? 'offen'); if (!isset(TC_STATUS[$st])) $st = 'offen';
      $txt = trim((string)($e['bf'] ?? '')); $em = trim((string)($e['em'] ?? ''));
      if (in_array($st, ['mangel', 'teils'], true)) $txt .= ($txt !== '' ? "\n" : '') . 'Empfehlung: ' . ($em !== '' ? $em : $pt[4]) . ' (' . TC_PRIO[tc_prio((int)$pt[5], $st)] . ', ca. ' . (int)($e['aw'] ?? $pt[6]) . ' Min.)';
      elseif ($em !== '') $txt .= ($txt !== '' ? "\n" : '') . $em;
      $rows[] = [$pt[1], TC_STATUS[$st], $txt !== '' ? $txt : '–'];
    }
    $p->tabelle($rows, [150, 70, 270], 8.5);
  }
  if ($auto && !empty($auto['ok'])) {
    $p->seite(); $p->abschnitt('Anhang: automatischer Check mit ' . (int)($auto['gesamt'] ?? 100) . ' Prüfpunkten');
    $p->zeile('Geprüft am ' . ($c['auto_zeit'] !== '' ? date('d.m.Y H:i', strtotime($c['auto_zeit'])) : '–') . ': ' . (int)($auto['bestanden'] ?? 0) . ' von ' . (int)($auto['geprueft'] ?? 0) . ' prüfbaren Punkten bestanden, Wert ' . (int)($auto['punkte'] ?? 0) . ' von 100 (kritische Punkte zählen fünffach, wichtige doppelt).', 10);
    $rows = [['Bereich', 'Bestanden', 'Fehlt', 'Trifft nicht zu']];
    foreach ($auto['gruppen'] ?? [] as $g) $rows[] = [(string)$g['titel'], (string)(int)$g['ok'], (string)(int)$g['fehlt'], (string)(int)$g['na']];
    $p->tabelle($rows, [250, 80, 80, 80]);
    $fehlt = array_values(array_filter($auto['items'] ?? [], function ($it) { return ($it['status'] ?? '') === 'fehlt'; }));
    usort($fehlt, function ($a, $b) { return [(int)$a['stufe'], (int)$a['nr']] <=> [(int)$b['stufe'], (int)$b['nr']]; });
    if ($fehlt) { $p->leer(6); $p->zeile('Fehlende Punkte (zuerst die kritischen):', 10, 'F2'); $rows = [['Stufe', 'Punkt', 'Befund']]; $ST = [1 => 'Kritisch', 2 => 'Wichtig', 3 => 'Empfohlen']; foreach (array_slice($fehlt, 0, 45) as $it) $rows[] = [$ST[(int)$it['stufe']] ?? '', (string)$it['titel'], (string)($it['detail'] ?? '')]; $p->tabelle($rows, [60, 170, 260], 8.5); }
  }
  $p->seite(); $p->abschnitt('So geht es weiter');
  $p->zeile('1. Wir besprechen den Bericht in 30 Minuten am Telefon oder per Video. 2. Sie bekommen das verbindliche Angebot für die Umsetzung als PDF (Festpreis, Lieferzeit). 3. Nach der Umsetzung prüfe ich alle Punkte noch einmal und Sie bekommen den Vergleich vorher/nachher.', 10);
  $p->leer(6); $p->zeile('Hinweis: Die Sichtprüfung der Rechtstexte ersetzt keine Rechtsberatung. Alle Preise ohne Umsatzsteuer (§ 19 UStG). Erstellt am ' . date('d.m.Y') . ' · ' . LD_SITE . '/website-check', 8.5);
  return $p->ausgabe();
}
