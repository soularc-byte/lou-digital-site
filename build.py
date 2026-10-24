#!/usr/bin/env python3
"""Lou Digital – statische Website, Version 12 (24.10.2026).
Erzeugt alle Seiten DE/EN mit sauberen Adressen (/websites, /en/websites ...) nach dist/."""
import base64, hashlib, html, json, pathlib, shutil

ROOT = pathlib.Path(__file__).parent
DIST = ROOT / 'dist'
SITE = 'https://lou-digital.de'
EMAIL = 'info@lou-digital.de'
EMAIL_ADRIAN = 'adrian.semlinger@lou-digital.de'
PHONE = '+49 156 30163539'          # z. B. '+49 176 1234567' – sobald gesetzt, erscheint die Nummer in Fußleiste, Kontakt und Impressum
ADDRESS = ('Lachenweg 34', '76139 Karlsruhe')
VERSION = '12'

e = html.escape

def A(path):
    """Pfad mit Inhalts-Fingerabdruck, damit Browser geänderte Dateien sofort neu laden."""
    f = ROOT / 'static' / path.lstrip('/')
    return f'{path}?v={hashlib.md5(f.read_bytes()).hexdigest()[:8]}' if f.is_file() else path
SITE_CSS = (ROOT / 'static/css/base.css').read_text() + (ROOT / 'static/css/extra.css').read_text()
CSS_URL = '/css/site.css?v=' + hashlib.md5(SITE_CSS.encode()).hexdigest()[:8]
INLINE_JS = "document.documentElement.classList.add('js');setTimeout(function(){if(!window.__ldReady)document.documentElement.classList.remove('js')},2500);"
INLINE_HASH = base64.b64encode(hashlib.sha256(INLINE_JS.encode()).digest()).decode()

# Seiten: Schlüssel -> (DE-Pfad, EN-Pfad)
PAGES = {
  'home':        ('/', '/en/'),
  'websites':    ('/websites', '/en/websites'),
  'automatik':   ('/automatik', '/en/automation'),
  'check':       ('/website-check', '/en/website-check'),
  'pakete':      ('/pakete', '/en/packages'),
  'individuell': ('/individuell', '/en/custom'),
  'ueber':       ('/ueber-mich', '/en/about'),
  'fragen':      ('/fragen', '/en/faq'),
  'kontakt':     ('/kontakt', '/en/contact'),
  'impressum':   ('/impressum', '/en/imprint'),
  'datenschutz': ('/datenschutz', '/en/privacy'),
}
def url(key, lang): return PAGES[key][0 if lang == 'de' else 1]

NAV = [('websites', 'Websites', 'Websites'), ('automatik', 'Automatik', 'Automation'), ('check', 'Website-Check', 'Website check'),
       ('pakete', 'Pakete', 'Packages'), ('individuell', 'Individuell', 'Custom'), ('ueber', 'Über mich', 'About'), ('fragen', 'Fragen', 'FAQ')]

def L(lang, de, en): return de if lang == 'de' else en
def slot(a, b, show):
    """Text mit unsichtbarem Platzhalter der jeweils anderen Sprache, damit nichts verrutscht."""
    other = b if show == a else a
    return f'<span class="slot"><span>{e(show)}</span><span class="ghost" aria-hidden="true">{e(other)}</span></span>'

def kontakt_url(lang, anliegen=None):
    u = url('kontakt', lang)
    return u + (f'?pakete={anliegen}' if anliegen else '')

LOGO = '<svg viewBox="0 0 36 36" aria-hidden="true"><rect width="36" height="36" fill="#0b6e69"/><path d="M12.5 9v15.5h9.5" fill="none" stroke="#ffffff" stroke-width="3.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="26" cy="24.5" r="2.8" fill="#d2f05a"/></svg>'

def header(lang, key):
    items = []
    for k, de, en in NAV:
        cur = ' aria-current="page"' if k == key else ''
        items.append(f'<a href="{url(k, lang)}"{cur}>{slot(de, en, L(lang, de, en))}</a>')
    de_href, en_href = url(key, 'de'), url(key, 'en')
    active = lang
    sw = (f'<div class="lang" data-active="{active}" role="group" aria-label="Sprache / Language">'
          f'<a href="{de_href}" hreflang="de" lang="de" class="{"is-on" if lang=="de" else ""}" aria-label="Deutsch"{" aria-current=\"true\"" if lang=="de" else ""}>DE</a>'
          f'<a href="{en_href}" hreflang="en" lang="en" class="{"is-on" if lang=="en" else ""}" aria-label="English"{" aria-current=\"true\"" if lang=="en" else ""}>EN</a></div>')
    cta = slot('Check anfordern', 'Request check', L(lang, 'Check anfordern', 'Request check'))
    menu = slot('Menü', 'Menu', L(lang, 'Menü', 'Menu'))
    return f'''<header class="top">
  <div class="wrap">
    <a class="logo" href="{url('home', lang)}" aria-label="{L(lang, 'Lou Digital, zur Startseite', 'Lou Digital, home')}">{LOGO}<span>Lou Digital</span></a>
    <nav class="nav" id="nav" aria-label="{L(lang, 'Hauptnavigation', 'Main navigation')}">{''.join(items)}</nav>
    <div class="top-actions">
      {sw}
      <a class="btn btn--primary" href="{kontakt_url(lang, 'check')}">{cta}</a>
      <button class="menu-btn" id="menu-btn" type="button" aria-expanded="false" aria-controls="nav">{menu}</button>
    </div>
  </div>
</header>'''

def footer(lang, key):
    l = lambda k: url(k, lang)
    phone = f'<li><a href="tel:{PHONE.replace(" ", "")}">{e(PHONE)}</a></li>' if PHONE else ''
    other = 'en' if lang == 'de' else 'de'
    return f'''<footer class="foot">
  <div class="wrap">
    <p class="big" data-reveal>Lou Digital</p>
    <div class="foot-cols">
      <div data-reveal style="--d:.05s"><h3>{L(lang, 'Leistungen', 'Services')}</h3><ul>
        <li><a href="{l('websites')}">{L(lang, 'Website in 7 Tagen', 'Website in 7 days')}</a></li>
        <li><a href="{l('automatik')}">{L(lang, 'Anfrage-Automatik', 'Inquiry automation')}</a></li>
        <li><a href="{l('check')}">{L(lang, 'Kostenloser Website-Check', 'Free website check')}</a></li>
        <li><a href="{l('pakete')}">{L(lang, 'Kundenumfrage und Betreuung', 'Customer survey and maintenance')}</a></li>
      </ul></div>
      <div data-reveal style="--d:.1s"><h3>Lou Digital</h3><ul>
        <li><a href="{l('pakete')}">{L(lang, 'Pakete und Preise', 'Packages and prices')}</a></li>
        <li><a href="{l('individuell')}">{L(lang, 'Individuelles Paket', 'Custom package')}</a></li>
        <li><a href="{l('ueber')}">{L(lang, 'Über mich', 'About me')}</a></li>
        <li><a href="{l('fragen')}">{L(lang, 'Häufige Fragen', 'FAQ')}</a></li>
        <li><a href="{l('kontakt')}">{L(lang, 'Anfrage senden', 'Send an inquiry')}</a></li>
      </ul></div>
      <div data-reveal style="--d:.15s"><h3>{L(lang, 'Rechtliches', 'Legal')}</h3><ul>
        <li><a href="{l('impressum')}">{L(lang, 'Impressum', 'Legal notice')}</a></li>
        <li><a href="{l('datenschutz')}">{L(lang, 'Datenschutz', 'Privacy policy')}</a></li>
        <li class="muted">{L(lang, 'Keine Cookies', 'No cookies')}</li>
        <li class="muted">{L(lang, 'Kleinunternehmer nach § 19 UStG', 'Small business under § 19 UStG')}</li>
      </ul></div>
      <div data-reveal style="--d:.2s"><h3>{L(lang, 'Kontakt', 'Contact')}</h3><ul>
        <li>Adrian Semlinger</li>
        <li>{L(lang, 'Karlsruhe', 'Karlsruhe, Germany')}</li>
        <li><a href="mailto:{EMAIL}">{EMAIL}</a></li>
        {phone}
        <li class="muted">{L(lang, 'Antwort in der Regel innerhalb von 24 Stunden', 'Reply usually within 24 hours')}</li>
      </ul></div>
    </div>
    <div class="foot-bar">
      <span>© 2026 Lou Digital · Adrian Semlinger</span>
      <button class="fb-link" type="button" data-fb-open>{L(lang, 'Feedback geben', 'Give feedback')}</button>
      <span class="lang-text"><a href="{url(key, 'de')}" hreflang="de"{' aria-current="true"' if lang=='de' else ''}>Deutsch</a> · <a href="{url(key, 'en')}" hreflang="en"{' aria-current="true"' if lang=='en' else ''}>English</a></span>
      <button class="to-top" id="to-top" type="button">{L(lang, 'Nach oben', 'Back to top')}</button>
    </div>
  </div>
</footer>
{feedback(lang)}'''

def feedback(lang):
    return f'''<div class="fb">
  <button class="fb-tab" id="fb-open" type="button" aria-expanded="false" aria-controls="fb-panel" data-track="Feedback-Lasche">{L(lang, 'Feedback', 'Feedback')}</button>
  <form class="fb-panel" id="fb-panel" action="/api/anfrage.php" method="post" novalidate hidden aria-label="{L(lang, 'Vorschlag oder Frage', 'Suggestion or question')}">
    <div class="fb-head"><strong>{L(lang, 'Vorschlag oder Frage?', 'Suggestion or question?')}</strong><button class="fb-x" id="fb-close" type="button" aria-label="{L(lang, 'Schließen', 'Close')}">×</button></div>
    <div class="fb-body">
      <p class="fb-lead">{L(lang, 'Was kann ich an dieser Website besser machen?', 'What could I improve on this website?')}</p>
      <input type="hidden" name="anliegen" value="feedback">
      <label class="sr" for="fb-msg">{L(lang, 'Ihr Vorschlag', 'Your suggestion')}</label>
      <textarea id="fb-msg" name="nachricht" maxlength="1000" placeholder="{L(lang, 'Ihr Vorschlag, Lob oder Fehler', 'Your idea, praise or a bug')}"></textarea>
      <span class="err" id="efb-msg" hidden>{L(lang, 'Bitte ein paar Worte schreiben.', 'Please write a few words.')}</span>
      <label class="sr" for="fb-mail">{L(lang, 'E-Mail für Rückfragen', 'Email for questions')}</label>
      <input id="fb-mail" name="email" type="email" maxlength="150" autocomplete="email" placeholder="{L(lang, 'E-Mail für Rückfragen (optional)', 'Email for questions (optional)')}">
      <span class="err" id="efb-mail" hidden>{L(lang, 'Bitte prüfen Sie die E-Mail-Adresse.', 'Please check the email address.')}</span>
      <div class="hp" aria-hidden="true"><input name="website" type="text" tabindex="-1" autocomplete="off"></div>
      <label class="consent small" for="fb-ok"><input id="fb-ok" name="einwilligung" value="1" type="checkbox" required><span>{L(lang, f'Ich akzeptiere die <a href="{url("datenschutz", lang)}">Datenschutzerklärung</a>.', f'I accept the <a href="{url("datenschutz", lang)}">privacy policy</a>.')} <b class="req" aria-hidden="true">*</b></span></label>
      <span class="err err-box" id="efb-ok" hidden role="alert">{L(lang, 'Bitte akzeptieren Sie zuerst die Datenschutzerklärung.', 'Please accept the privacy policy first.')}</span>
      <button class="btn btn--primary btn--small" id="fb-send" type="submit">{L(lang, 'Senden', 'Send')}</button>
      <p class="msg" id="fb-res" hidden role="status"></p>
    </div>
  </form>
</div>'''

def page(lang, key, title, desc, body, noindex=False, extra_head=''):
    canon = SITE + url(key, lang)
    alts = (f'<link rel="alternate" hreflang="de" href="{SITE}{url(key, "de")}">\n'
            f'<link rel="alternate" hreflang="en" href="{SITE}{url(key, "en")}">\n'
            f'<link rel="alternate" hreflang="x-default" href="{SITE}{url(key, "de")}">')
    robots = '<meta name="robots" content="noindex, follow">\n' if noindex else ''
    return f'''<!doctype html>
<html lang="{lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{e(title)}</title>
<meta name="description" content="{e(desc)}">
<link rel="canonical" href="{canon}">
{alts}
{robots}<link rel="icon" href="{A('/favicon.svg')}" type="image/svg+xml">
<meta name="theme-color" content="#f5f6f3">
<meta property="og:title" content="{e(title)}">
<meta property="og:description" content="{e(desc)}">
<meta property="og:type" content="website">
<meta property="og:url" content="{canon}">
<meta property="og:image" content="{SITE}/img/og.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="Lou Digital">
<meta property="og:locale" content="{'de_DE' if lang == 'de' else 'en_GB'}">
<meta name="twitter:card" content="summary_large_image">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="manifest" href="/site.webmanifest">
<link rel="preload" href="/fonts/instrument-sans-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{CSS_URL}">
<script>{INLINE_JS}</script>
{extra_head}</head>
<body>
{header(lang, key)}
<main id="main">
{body}
</main>
{footer(lang, key)}
<script src="{A('/js/lenis.min.js')}" defer></script>
<script src="{A('/js/site.js')}" defer></script>
</body>
</html>
'''

# ---------- Bausteine ----------
def btn(href, text, kind='primary', small=False):
    return f'<a class="btn btn--{kind}{" btn--small" if small else ""}" href="{href}">{e(text)}</a>'

def fig(name, f, extra=''):
    return f'<img class="fig" src="{A(f"/img/fig-{name}.webp")}" width="900" height="900" alt="" style="--f:{f}" decoding="async"{extra}>'

def closer(lang):
    """Abschluss jeder Unterseite: Ablauf + Knopf zum Formular."""
    steps = [
        (L(lang, 'Kostenloser Check', 'Free check'), L(lang, 'Sie schicken mir Ihre Adresse, ich schicke Ihnen das Ergebnis.', 'You send me your web address, I send you the result.')),
        (L(lang, 'Fragebogen', 'Questionnaire'), L(lang, 'Logo, Fotos, Leistungen, Öffnungszeiten. Damit startet der Bau.', 'Logo, photos, services, opening hours. That’s when the build starts.')),
        (L(lang, 'Bau in 7 Tagen', 'Built in 7 days'), L(lang, 'Sie sehen Zwischenstände und geben eine Korrekturrunde.', 'You see progress and get one round of revisions.')),
        (L(lang, 'Livegang', 'Launch'), L(lang, 'Ihre Website geht online. Auf Wunsch betreue ich sie weiter.', 'Your website goes live. If you like, I keep looking after it.')),
    ]
    lis = ''.join(f'<li data-reveal style="--d:{i*0.08:.2f}s"><h3>{e(a)}</h3><p>{e(b)}</p></li>' for i, (a, b) in enumerate(steps))
    return f'''<section class="sec" id="ablauf">
  <div class="wrap close">
    <div>
      <h2 class="h2" data-reveal>{L(lang, 'Erst prüfen, dann bauen.', 'Check first, then build.')}</h2>
      <ol class="steps">{lis}</ol>
    </div>
    <div class="trio" aria-hidden="true" data-reveal="scale">
      <div class="blk blk--petrol">{fig('web', '84%')}</div>
      <div class="blk blk--ink">{fig('mail', '86%')}</div>
      <div class="blk blk--mint dots">{fig('clip', '84%')}</div>
    </div>
  </div>
  <div class="wrap" style="margin-top:36px" data-reveal>
    <div class="row-actions">{btn(kontakt_url(lang, 'check'), L(lang, 'Kostenlosen Check anfordern', 'Request a free check'))}{btn(url('kontakt', lang), L(lang, 'Anfrage senden', 'Send an inquiry'), 'line')}</div>
  </div>
</section>'''

def page_hero(title, lead):
    return f'''<section class="page-hero"><div class="wrap">
  <h1 data-reveal>{title}</h1>
  <p class="lead" data-reveal style="--d:.08s">{lead}</p>
</div></section>'''

def spec_rows(rows):
    return ''.join(f'<div><dt>{e(a)}</dt><dd>{b}</dd></div>' for a, b in rows)

# ---------- Inhalte ----------
def websites_block(lang, full):
    rows = [
        (L(lang, 'Umfang', 'Scope'), L(lang, 'Onepager oder bis zu 5 Seiten', 'One-pager or up to 5 pages')),
        (L(lang, 'Lieferzeit', 'Delivery'), L(lang, '<span class="num">7</span> Tage nach dem Fragebogen', '<span class="num">7</span> days after the questionnaire')),
        (L(lang, 'Enthalten', 'Included'), L(lang, 'Mobile Ansicht, Kontaktformular, Struktur für Impressum und Datenschutz', 'Mobile layout, contact form, structure for legal notice and privacy policy')),
        (L(lang, 'Preis', 'Price'), L(lang, '<span class="num">390 €</span> <span class="muted">statt 790 €</span><span class="pill-pilot">Pilotpreis</span>', '<span class="num">€390</span> <span class="muted">instead of €790</span><span class="pill-pilot">Pilot price</span>')),
    ]
    if full:
        rows += [
            (L(lang, 'Korrektur', 'Revisions'), L(lang, 'Eine Korrekturrunde, weitere nach Aufwand', 'One round of revisions, more by effort')),
            (L(lang, 'Eigentum', 'Ownership'), L(lang, 'Domain und Zugänge laufen auf Ihren Namen', 'Domain and accounts in your name')),
        ]
    more = '' if full else f'<a class="link" href="{url("websites", lang)}">{L(lang, "Mehr zu Websites", "More about websites")}</a>'
    return f'''<section class="sec{' sec--tight' if full else ''}" id="websites">
    <div class="wrap split">
      <div class="copy">
        {'' if full else f'<h2 class="h2" data-reveal>{L(lang, "Website in 7 Tagen", "Website in 7 days")}</h2>'}
        <p data-reveal style="--d:.05s">{L(lang, 'Ein Onepager oder bis zu fünf Seiten, gebaut auf einer erprobten Vorlage und mit Ihren Inhalten gefüllt. Sie sehen Zwischenstände, eine Korrekturrunde ist enthalten.', 'A one-pager or up to five pages, built on a proven template and filled with your content. You see progress along the way, and one round of revisions is included.')}</p>
        <dl class="spec" data-reveal style="--d:.1s">{spec_rows(rows)}</dl>
        <div class="row-actions" data-reveal style="--d:.15s">{btn(kontakt_url(lang, 'website'), L(lang, 'Website anfragen', 'Request a website'))}<a class="link" href="{url('individuell', lang)}">{L(lang, 'Mit Extras zusammenstellen', 'Build it with extras')}</a>{more}</div>
      </div>
      <div class="stage stage--soft" data-reveal="scale">{fig('web', '62%')}</div>
    </div>
  </section>'''

def automatik_block(lang, full):
    steps = [L(lang, 'Ihr Kunde füllt das Formular auf Ihrer Website aus.', 'Your customer fills in the form on your website.'),
             L(lang, 'Die Anfrage landet geordnet in Ihrer Tabelle.', 'The inquiry lands neatly in your spreadsheet.'),
             L(lang, 'Sie bekommen sofort eine Nachricht aufs Handy.', 'You get a message on your phone right away.'),
             L(lang, 'Ihr Kunde erhält automatisch eine Bestätigung.', 'Your customer automatically receives a confirmation.'),
             L(lang, 'Nach dem Auftrag geht eine Bitte um Bewertung raus.', 'After the job, a review request goes out.')]
    lis = ''.join(f'<li>{e(s)}</li>' for s in steps)
    more = '' if full else f'<a class="link" style="color:var(--signal)" href="{url("automatik", lang)}">{L(lang, "So funktioniert es", "How it works")}</a>'
    extra = ''
    if full:
        extra = f'''<p class="muted" data-reveal>{L(lang, 'Die Automatik läuft auf Werkzeugen, die nach der Übergabe weiterlaufen. Sie brauchen dafür kein eigenes Programm, nur Ihr Handy und eine Tabelle.', 'The automation runs on tools that keep working after handover. You need no extra software, just your phone and a spreadsheet.')}</p>'''
    return f'''<section class="sec band" id="automatik">
    <div class="wrap split split--rev">
      <div class="stage stage--petrol" data-reveal="scale">{fig('mail', '66%')}</div>
      <div class="copy">
        {'' if full else f'<h2 class="h2" data-reveal>{L(lang, "Anfrage-Automatik", "Inquiry automation")}</h2>'}
        <p class="muted" data-reveal style="--d:.05s">{L(lang, 'Jede Anfrage aus Ihrem Formular kommt sofort bei Ihnen an. Kein Postfach durchsuchen, keine Anfrage geht verloren.', 'Every inquiry from your form reaches you instantly. No digging through your inbox, no inquiry gets lost.')}</p>
        <ol class="flow" data-reveal style="--d:.1s">{lis}</ol>
        {extra}
        <p class="price" data-reveal>{L(lang, '<strong class="num">290 €</strong> Pilotpreis, fertig in <span class="num">5</span> Tagen', '<strong class="num">€290</strong> pilot price, ready in <span class="num">5</span> days')}</p>
        <div class="row-actions" data-reveal>{btn(kontakt_url(lang, 'automatik'), L(lang, 'Automatik anfragen', 'Request automation'), 'line')}{more}</div>
      </div>
    </div>
  </section>'''

def check_block(lang, full):
    rows = [('ok', L(lang, 'Verschlüsselt über HTTPS', 'Encrypted via HTTPS')), ('no', L(lang, 'Auf dem Handy gut lesbar', 'Easy to read on a phone')),
            ('ok', L(lang, 'Impressum vorhanden', 'Legal notice present')), ('no', L(lang, 'Datenschutzerklärung vorhanden', 'Privacy policy present')),
            ('ok', L(lang, 'Aussagekräftiger Seitentitel', 'Meaningful page title'))]
    lis = ''.join(f'<li class="{c}"><b aria-hidden="true">{"✓" if c=="ok" else "✕"}</b><span>{e(t)}</span><em>{L(lang, "bestanden", "passed") if c=="ok" else L(lang, "fehlt", "missing")}</em></li>' for c, t in rows)
    more = '' if full else f'<a class="link" href="{url("check", lang)}#sofort">{L(lang, "Sofort selbst prüfen", "Check it yourself now")}</a>'
    return f'''<section class="sec tint" id="check">
    <div class="wrap split">
      <div class="copy">
        {f'<h2 class="h2" data-reveal>{L(lang, "So sieht das Ergebnis aus", "What the result looks like")}</h2>' if full else f'<h2 class="h2" data-reveal>{L(lang, "Website-Check", "Website check")}</h2>'}
        <p data-reveal style="--d:.05s">{L(lang, 'Bevor ich etwas anbiete, prüfe ich Ihre Website an fünf Punkten. Sie bekommen ein Blatt mit dem Ergebnis und je einem Satz, was ein fehlender Punkt Sie kostet. Kostenlos und unverbindlich.', 'Before I offer anything, I check your website on five points. You get one page with the result and a sentence on what each missing point costs you. Free and without obligation.')}</p>
        <div class="result" role="group" aria-label="{L(lang, 'Beispiel eines Website-Checks', 'Example of a website check')}" data-reveal style="--d:.1s">
          <div class="result-head"><span class="muted">{L(lang, 'Beispiel: ihre-firma.de', 'Example: your-company.com')}</span><span class="score">3<span>/5</span></span></div>
          <div class="bar" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i></div>
          <ul class="checks">{lis}</ul>
        </div>
        <div class="row-actions" data-reveal>{btn(kontakt_url(lang, 'check'), L(lang, 'Kostenlosen Check anfordern', 'Request a free check'))}{more}</div>
      </div>
      <div class="stage stage--white dots" data-reveal="scale">{fig('clip', '58%')}</div>
    </div>
  </section>'''

def pakete_table(lang):
    sel = L(lang, 'Auswählen', 'Select'); on = L(lang, 'Gewählt', 'Selected')
    def pick(key, once):
        return f'<button class="pick" type="button" data-pick="{key}" data-once="{once}" aria-pressed="false" data-track="Paket wählen: {key}"><span class="pk-off">{sel}</span><span class="pk-on" aria-hidden="true">✓ {on}</span></button>'
    rows = [
        ('check', L(lang, 'Website-Check', 'Website check'), L(lang, 'Ein Blatt mit fünf Prüfpunkten und was jeder fehlende Punkt kostet.', 'One page with five checkpoints and what each missing point costs you.'), L(lang, '1 Tag', '1 day'), L(lang, 'kostenlos', 'free'), 0),
        ('website', L(lang, 'Website in 7 Tagen', 'Website in 7 days'), L(lang, 'Onepager oder bis 5 Seiten, Kontaktformular, eine Korrekturrunde.', 'One-pager or up to 5 pages, contact form, one round of revisions.'), L(lang, '7 Tage', '7 days'), L(lang, '<s>790 €</s>390 €', '<s>€790</s>€390'), 390),
        ('automatik', L(lang, 'Anfrage-Automatik', 'Inquiry automation'), L(lang, 'Formular, Tabelle, Nachricht an Sie, Bestätigung, Bewertungsbitte.', 'Form, spreadsheet, message to you, confirmation, review request.'), L(lang, '5 Tage', '5 days'), L(lang, '<s>590 €</s>290 €', '<s>€590</s>€290'), 290),
        ('umfrage', L(lang, 'Kundenumfrage', 'Customer survey'), L(lang, 'Umfrage mit Versandlink, Auswertung als Bericht auf einer Seite.', 'Survey with a shareable link, results as a one-page report.'), L(lang, '7 Tage', '7 days'), L(lang, '<s>290 €</s>190 €', '<s>€290</s>€190'), 190),
    ]
    trs = ''.join(f'<div class="tr" role="row" data-row="{k}" data-reveal style="--d:{i*0.05:.2f}s"><span class="name" role="cell">{e(n)}</span><span class="desc" role="cell">{e(d)}</span><span class="time" role="cell">{e(t)}</span><span class="cost" role="cell">{c}</span><span class="act" role="cell">{pick(k, o)}</span></div>' for i, (k, n, d, t, c, o) in enumerate(rows))
    tiers = ''.join(f'<button class="tier" type="button" data-pick="betreuung" data-tier="{t}" aria-pressed="false" data-track="Betreuung wählen: {t}" aria-label="{L(lang, "Betreuung", "Maintenance")} {t}, {L(lang, f"{p} € im Monat", f"€{p} per month")}"><b>{t}</b><small>{L(lang, f"{p} €", f"€{p}")}</small></button>' for t, p in (('S', 29), ('M', 49), ('L', 79)))
    care = f'<div class="tr" role="row" data-row="betreuung" data-reveal style="--d:.2s"><span class="name" role="cell">{L(lang, "Betreuung S / M / L", "Maintenance S / M / L")}</span><span class="desc" role="cell">{L(lang, "Überwachung, Backups, Updates, Monatsbericht. M mit 30 Minuten, L mit einer Stunde Änderungen.", "Monitoring, backups, updates, monthly report. M with 30 minutes, L with one hour of changes.")}</span><span class="time" role="cell">{L(lang, "pro Monat", "per month")}</span><span class="cost" role="cell">{L(lang, "ab 29 €", "from €29")}</span><span class="act" role="cell"><span class="tiers" role="group" aria-label="{L(lang, "Betreuung wählen", "Choose maintenance")}">{tiers}</span></span></div>'
    return f'''<div class="table table--pick" role="table" aria-label="{L(lang, 'Pakete', 'Packages')}">
        <div class="tr th" role="row"><span role="columnheader">{L(lang, 'Paket', 'Package')}</span><span role="columnheader">{L(lang, 'Inhalt', 'Contents')}</span><span role="columnheader">{L(lang, 'Lieferzeit', 'Delivery')}</span><span role="columnheader" style="text-align:right">{L(lang, 'Preis', 'Price')}</span><span role="columnheader" class="th-act">{L(lang, 'Auswahl', 'Select')}</span></div>
        {trs}{care}
        <div class="tr tr--custom" role="row" data-reveal><span class="name" role="cell">{L(lang, 'Individuelles Paket', 'Custom package')}</span><span class="desc" role="cell">{L(lang, 'Sie stellen Bausteine, Betreuung und Extras selbst zusammen und sehen den Preis sofort.', 'Combine building blocks, maintenance and extras yourself and see the price instantly.')}</span><span class="time" role="cell">{L(lang, 'nach Absprache', 'by arrangement')}</span><span class="cost" role="cell">{L(lang, 'nach Auswahl', 'by selection')}</span><span class="act" role="cell">{btn(url('individuell', lang), L(lang, 'Zusammenstellen', 'Build'), 'line', True)}</span></div>
      </div>
      <p class="fine" data-reveal>{L(lang, 'Wählen Sie ein oder mehrere Pakete aus und senden Sie die Auswahl als Anfrage. Betreuung pro Monat. Alle Preise ohne Umsatzsteuer, gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Pilotpreise gelten für die ersten drei Kunden, die dafür eine Referenz erlauben.', 'Select one or more packages and send your selection as an inquiry. Maintenance per month. No VAT is charged under the German small business rule (§ 19 UStG). Pilot prices apply to the first three clients who allow me to use them as a reference.')}</p>
      <div class="pickbar" id="pickbar" aria-hidden="true" aria-live="polite"><div class="pb-in">
        <div class="pb-txt"><strong id="pb-count"></strong><span id="pb-sum"></span></div>
        <div class="pb-act"><button class="pb-clear" id="pb-clear" type="button" tabindex="-1">{L(lang, 'Leeren', 'Clear')}</button><a class="btn btn--primary" id="pb-go" href="{url('kontakt', lang)}" tabindex="-1">{L(lang, 'Anfrage senden', 'Send inquiry')}</a></div>
      </div></div>'''

def faq_items(lang):
    return [
        (L(lang, 'Was kostet eine Website bei Ihnen?', 'How much does a website cost?'), L(lang, 'Die Website in 7 Tagen kostet 390 € als Pilotpreis für die ersten drei Kunden, danach 790 bis 990 €. Alle Preise ohne Umsatzsteuer, weil ich Kleinunternehmer nach § 19 UStG bin.', 'The website in 7 days costs €390 as a pilot price for the first three clients, then €790 to €990. No VAT is charged because I’m a small business under § 19 UStG.'), url('pakete', lang), L(lang, 'Pakete und Preise ansehen', 'See packages and prices')),
        (L(lang, 'Wem gehören Domain und Website?', 'Who owns the domain and website?'), L(lang, 'Ihnen. Domain, Hosting und Zugänge laufen auf Ihren Namen. Wenn Sie später wechseln wollen, nehmen Sie alles mit.', 'You do. Domain, hosting and accounts are in your name. If you want to switch later, you take everything with you.'), None, None),
        (L(lang, 'Was brauche ich, damit es losgehen kann?', 'What do I need to get started?'), L(lang, 'Einen ausgefüllten Fragebogen mit Logo, Fotos, Leistungen, Öffnungszeiten und den Angaben für das Impressum. Ab dann laufen die 7 Tage. Zum Einstieg reicht der kostenlose Website-Check.', 'A completed questionnaire with your logo, photos, services, opening hours and the details for the legal notice. The 7 days start from then. To begin, the free website check is enough.'), url('check', lang), L(lang, 'Zum Website-Check', 'To the website check')),
        (L(lang, 'Schreiben Sie auch Impressum und Datenschutzerklärung?', 'Do you write the legal notice and privacy policy?'), L(lang, 'Ich lege die Struktur an und baue die Seiten ein. Die Texte kommen von Ihnen oder aus einem Generator. Verbindlich prüfen lassen Sie sie am besten von einer Anwältin oder einem Anwalt.', 'I set up the structure and build the pages. The texts come from you or from a generator. For a binding review, it’s best to consult a lawyer.'), None, None),
        (L(lang, 'Was ist in der Betreuung enthalten?', 'What’s included in maintenance?'), L(lang, 'Überwachung, ob die Seite erreichbar ist, Backups, Updates und ein kurzer Monatsbericht. In M sind 30 Minuten Änderungen im Monat enthalten, in L eine Stunde.', 'Uptime monitoring, backups, updates and a short monthly report. M includes 30 minutes of changes per month, L includes one hour.'), url('individuell', lang), L(lang, 'Paket zusammenstellen', 'Build your package')),
        (L(lang, 'Wie schnell melden Sie sich nach einer Anfrage?', 'How quickly will you get back to me?'), L(lang, 'Sie bekommen sofort eine Bestätigung per E-Mail und von mir eine persönliche Antwort, in der Regel innerhalb von 24 Stunden an Werktagen. Melde ich mich später, erhalten Sie 10 % Rabatt auf Ihr erstes Paket.', 'You get a confirmation by email right away and a personal reply from me, usually within 24 hours on working days. If I take longer, you get 10% off your first package.'), url('kontakt', lang), L(lang, 'Anfrage senden', 'Send an inquiry')),
        (L(lang, 'Arbeiten Sie nur in Karlsruhe?', 'Do you only work in Karlsruhe?'), L(lang, 'Ich sitze in Karlsruhe und arbeite gerne persönlich vor Ort. Ich reise aber auch gern: Termine außerhalb von Karlsruhe sind kein Problem. Wir besprechen sie kurz vorab, und Anfahrt und Zeit plane ich im Preis ein. Alles andere läuft per E-Mail und Telefon, deutschlandweit.', 'I’m based in Karlsruhe and happy to work with you in person. I also like to travel: appointments outside Karlsruhe are no problem. We’ll discuss it briefly up front, and I’ll include travel and time in the price. Everything else runs by email and phone across Germany.'), url('kontakt', lang), L(lang, 'Termin anfragen', 'Request an appointment')),
    ]

def faq_html(lang, items):
    def one(i, it):
        q, a, href, label = it
        link = f'<a class="link faq-go" href="{href}">{e(label)}</a>' if href else ''
        return f'<details data-reveal style="--d:{i*0.04:.2f}s"><summary><span>{e(q)}</span><span class="ico" aria-hidden="true"></span></summary><div class="a"><p>{e(a)}</p>{link}</div></details>'
    return '<div class="faq">' + ''.join(one(i, it) for i, it in enumerate(items)) + '</div>'

def about_photo(cls='photo-img', sizes='(max-width: 860px) 100vw, 380px'):
    w6, w9, j6, j9 = A('/img/about-640.webp'), A('/img/about-960.webp'), A('/img/about-640.jpg'), A('/img/about-960.jpg')
    return (f'<picture><source type="image/webp" srcset="{w6} 640w, {w9} 960w" sizes="{sizes}">'
            f'<img class="{cls}" src="{j6}" srcset="{j6} 640w, {j9} 960w" sizes="{sizes}" width="640" height="800" alt="Adrian Semlinger" loading="lazy" decoding="async"></picture>')

def home(lang):
    teasers = [
        ('websites', 'petrol', 'web', L(lang, 'Website in 7 Tagen', 'Website in 7 days'), L(lang, 'Onepager oder bis zu 5 Seiten, ab 390 €.', 'One-pager or up to 5 pages, from €390.')),
        ('automatik', 'ink', 'mail', L(lang, 'Anfrage-Automatik', 'Inquiry automation'), L(lang, 'Jede Anfrage sofort auf Ihrem Handy.', 'Every inquiry instantly on your phone.')),
        ('check', 'mint', 'clip', L(lang, 'Website-Check', 'Website check'), L(lang, 'Fünf Prüfpunkte, kostenlos.', 'Five checkpoints, free of charge.')),
        ('individuell', 'lime', 'box', L(lang, 'Individuelles Paket', 'Custom package'), L(lang, 'Bausteine wählen, Preis sofort sehen.', 'Pick building blocks, see the price instantly.')),
    ]
    tz = ''.join(f'<a class="teaser teaser--{c}" href="{url(k, lang)}" data-reveal style="--d:{i*0.06:.2f}s"><span><h3>{e(t)}</h3><p>{e(p)}</p><span class="go">{L(lang, "Ansehen", "View")}</span></span><img src="{A(f"/img/fig-{f}.webp")}" width="900" height="900" alt="" loading="lazy" decoding="async"></a>' for i, (k, c, f, t, p) in enumerate(teasers))
    body = f'''<section class="hero" id="start">
    <div class="wrap">
      <div>
        <h1><span>Lou</span><span>Digital</span></h1>
        <p class="lead">{L(lang, 'Websites und Automatisierung für Unternehmen mit Anspruch.', 'Websites and automation for businesses with high standards.')}</p>
        <p class="sub">{L(lang, 'Ich baue Ihre Website in 7 Tagen und betreue sie ab 29 € im Monat. Von Karlsruhe aus, mit einem Ansprechpartner von Anfang bis Ende.', 'I build your website in 7 days and look after it from €29 a month. Based in Karlsruhe, with one point of contact from start to finish.')}</p>
        <div class="actions">{btn(kontakt_url(lang, 'check'), L(lang, 'Kostenlosen Check anfordern', 'Request a free check'))}{btn(url('pakete', lang), L(lang, 'Pakete ansehen', 'See packages'), 'line')}</div>
      </div>
      <div class="blocks" aria-hidden="true">
        <div class="blk blk--petrol">{fig('web', '94%')}</div>
        <div class="blk blk--ink">{fig('mail', '96%')}</div>
        <div class="blk blk--lime">{fig('box', '86%')}</div>
        <div class="blk blk--mint dots">{fig('clip', '98%')}</div>
      </div>
    </div>
  </section>
  <section class="sec sec--tight"><div class="wrap"><div class="teasers">{tz}</div></div></section>
  {websites_block(lang, False)}
  {automatik_block(lang, False)}
  {check_block(lang, False)}
  <section class="sec" id="pakete"><div class="wrap">
      <h2 class="h2" data-reveal>{L(lang, 'Pakete und Preise', 'Packages and prices')}</h2>
      {pakete_table(lang)}
  </div></section>
  <section class="sec tint" id="ueber"><div class="wrap about-mini">
      <div data-reveal="scale">{about_photo(sizes='220px')}</div>
      <div class="copy">
        <h2 class="h2" data-reveal>{L(lang, 'Über mich', 'About me')}</h2>
        <p class="lead" data-reveal style="--d:.05s">{L(lang, 'Ich bin Adrian Semlinger und arbeite von Karlsruhe aus.', 'I’m Adrian Semlinger and I work from Karlsruhe, Germany.')}</p>
        <p data-reveal style="--d:.1s">{L(lang, 'Kaufmännischer Hintergrund, feste Pakete, klare Preise und Werkzeuge, die nach der Übergabe weiterlaufen.', 'A business background, fixed packages, clear prices and tools that keep running after handover.')}</p>
        <div class="row-actions" data-reveal><a class="link" href="{url('ueber', lang)}">{L(lang, 'Mehr über mich', 'More about me')}</a></div>
      </div>
  </div></section>
  <section class="sec" id="fragen"><div class="wrap">
      <h2 class="h2" data-reveal>{L(lang, 'Häufige Fragen', 'Frequently asked questions')}</h2>
      {faq_html(lang, faq_items(lang)[:3])}
      <div class="row-actions" style="margin-top:24px" data-reveal><a class="link" href="{url('fragen', lang)}">{L(lang, 'Alle Fragen', 'All questions')}</a></div>
  </div></section>
  {closer(lang)}'''
    desc = L(lang, 'Lou Digital aus Karlsruhe: Website in 7 Tagen ab 390 €, Anfrage-Automatik, Kundenumfragen und Betreuung ab 29 € im Monat. Kostenloser Website-Check.',
             'Lou Digital from Karlsruhe, Germany: a website in 7 days from €390, inquiry automation, customer surveys and maintenance from €29 a month. Free website check.')
    ld = {"@context": "https://schema.org", "@type": "ProfessionalService", "name": "Lou Digital", "url": SITE + url('home', lang), "email": EMAIL,
          "founder": {"@type": "Person", "name": "Adrian Semlinger"},
          "address": {"@type": "PostalAddress", "addressLocality": "Karlsruhe", "addressRegion": "Baden-Württemberg", "addressCountry": "DE"}, "areaServed": "DE"}
    if PHONE: ld["telephone"] = PHONE
    head = f'<script type="application/ld+json">{json.dumps(ld, ensure_ascii=False)}</script>\n'
    return page(lang, 'home', L(lang, 'Lou Digital – Websites und Automatisierung aus Karlsruhe', 'Lou Digital – Websites and automation from Karlsruhe'), desc, body, extra_head=head)

def sub_websites(lang):
    body = page_hero(L(lang, 'Website in 7&nbsp;Tagen', 'Website in 7&nbsp;days'), L(lang, 'Eine klare Website, die auf jedem Gerät gut aussieht und Anfragen bringt. Fester Preis, feste Lieferzeit.', 'A clear website that looks good on every device and brings in inquiries. Fixed price, fixed delivery time.')) + websites_block(lang, True) + closer(lang)
    return page(lang, 'websites', L(lang, 'Website in 7 Tagen – Lou Digital', 'Website in 7 days – Lou Digital'), L(lang, 'Onepager oder bis zu fünf Seiten in 7 Tagen, mit Kontaktformular und mobiler Ansicht. Pilotpreis 390 €.', 'A one-pager or up to five pages in 7 days, with contact form and mobile layout. Pilot price €390.'), body)

def sub_automatik(lang):
    body = page_hero(L(lang, 'Anfrage-Automatik', 'Inquiry automation'), L(lang, 'Ihr Formular, Ihre Tabelle und Ihr Handy arbeiten zusammen. Keine Anfrage geht mehr verloren.', 'Your form, your spreadsheet and your phone work together. No inquiry gets lost again.')) + automatik_block(lang, True) + closer(lang)
    return page(lang, 'automatik', L(lang, 'Anfrage-Automatik – Lou Digital', 'Inquiry automation – Lou Digital'), L(lang, 'Anfragen aus Ihrem Formular landen in einer Tabelle und als Nachricht auf Ihrem Handy. Pilotpreis 290 €, fertig in 5 Tagen.', 'Inquiries from your form land in a spreadsheet and as a message on your phone. Pilot price €290, ready in 5 days.'), body)

def sub_check(lang):
    pts = [(L(lang, 'Verschlüsselung', 'Encryption'), L(lang, 'Läuft Ihre Seite über HTTPS? Ohne Schloss warnen Browser Ihre Besucher.', 'Does your site run on HTTPS? Without the padlock, browsers warn your visitors.')),
           (L(lang, 'Handy', 'Mobile'), L(lang, 'Ist alles auf dem Handy lesbar und bedienbar? Dort kommen die meisten Besucher her.', 'Is everything readable and usable on a phone? That’s where most visitors come from.')),
           (L(lang, 'Impressum', 'Legal notice'), L(lang, 'Ist ein Impressum vorhanden und erreichbar? Es ist für Unternehmen Pflicht.', 'Is a legal notice present and reachable? It’s mandatory for businesses in Germany.')),
           (L(lang, 'Datenschutz', 'Privacy'), L(lang, 'Gibt es eine Datenschutzerklärung? Fehlt sie, drohen Abmahnungen.', 'Is there a privacy policy? Without one, you risk formal warnings.')),
           (L(lang, 'Seitentitel', 'Page title'), L(lang, 'Sagt der Titel, was Sie anbieten und wo? Davon hängt ab, ob Google Sie findet.', 'Does the title say what you offer and where? It decides whether Google finds you.'))]
    cards = ''.join(f'<li class="cp" data-reveal style="--d:{i*0.07:.2f}s"><span class="cp-n">0{i+1}</span><h3>{e(a)}</h3><p>{e(b)}</p></li>' for i, (a, b) in enumerate(pts))
    quick = f'''<section class="sec sec--tight" id="sofort"><div class="wrap">
    <div class="quick" data-reveal="scale">
      <div class="quick-copy"><h2 class="h2">{L(lang, 'Sofort-Check', 'Instant check')}</h2><p>{L(lang, 'Geben Sie Ihre Adresse ein. In wenigen Sekunden sehen Sie, welche der fünf Punkte Ihre Website erfüllt. Kostenlos, ohne Anmeldung.', 'Enter your address. Within seconds you see which of the five points your website meets. Free, no sign-up.')}</p></div>
      <form class="quick-form" id="qcheck" novalidate>
        <label class="sr" for="qc-url">{L(lang, 'Adresse Ihrer Website', 'Your website address')}</label>
        <input id="qc-url" name="url" type="text" inputmode="url" autocomplete="url" maxlength="200" placeholder="{L(lang, 'ihre-firma.de', 'your-company.com')}">
        <button class="btn btn--primary" id="qc-go" type="submit">{L(lang, 'Jetzt prüfen', 'Check now')}</button>
        <span class="err" id="qc-err" hidden role="alert"></span>
      </form>
      <div id="qc-out" aria-live="polite"></div>
    </div>
  </div></section>'''
    body = page_hero(L(lang, 'Website-Check', 'Website check'), L(lang, 'Fünf Prüfpunkte, sofort online oder als ausführliches Blatt, kostenlos. Danach wissen Sie, was Ihre Website gerade kostet.', 'Five checkpoints, instantly online or as a detailed sheet, free. Afterwards you know what your website is costing you.')) + quick + check_block(lang, True) + f'''
  <section class="sec checkpoints"><div class="wrap">
    <div class="cp-head"><h2 class="h2" data-reveal>{L(lang, 'Was ich prüfe', 'What I check')}</h2>
    <p class="lead" data-reveal style="--d:.06s">{L(lang, 'Fünf Punkte, die darüber entscheiden, ob Besucher Ihnen vertrauen, Google Sie findet und Ihre Website rechtlich sauber ist.', 'Five points that decide whether visitors trust you, Google finds you and your website is legally sound.')}</p></div>
    <ol class="cp-grid">{cards}</ol>
    <div class="cp-cta" data-reveal><a class="btn btn--primary" href="#sofort">{L(lang, 'Jetzt kostenlos prüfen', 'Check for free now')}</a></div>
  </div></section>''' + closer(lang)
    return page(lang, 'check', L(lang, 'Kostenloser Website-Check – Lou Digital', 'Free website check – Lou Digital'), L(lang, 'Ich prüfe Ihre Website an fünf Punkten: HTTPS, Handy, Impressum, Datenschutz, Seitentitel. Kostenlos und unverbindlich.', 'I check your website on five points: HTTPS, mobile, legal notice, privacy policy, page title. Free and without obligation.'), body)

def sub_pakete(lang):
    body = page_hero(L(lang, 'Pakete und Preise', 'Packages and prices'), L(lang, 'Feste Pakete mit festen Preisen. Oder Sie stellen sich Ihr eigenes Paket zusammen.', 'Fixed packages at fixed prices. Or put together your own package.')) + f'''
  <section class="sec sec--tight"><div class="wrap">{pakete_table(lang)}</div></section>''' + closer(lang)
    return page(lang, 'pakete', L(lang, 'Pakete und Preise – Lou Digital', 'Packages and prices – Lou Digital'), L(lang, 'Website-Check kostenlos, Website ab 390 €, Anfrage-Automatik 290 €, Kundenumfrage 190 €, Betreuung ab 29 € im Monat.', 'Website check free, website from €390, inquiry automation €290, customer survey €190, maintenance from €29 a month.'), body)

def sub_individuell(lang):
    def opt(typ, name, val, idv, title, small, price, checked=False):
        return f'<label class="opt"><input type="{typ}" name="{name}" value="{val}" id="{idv}"{" checked" if checked else ""}><span><b>{e(title)}</b>{f"<small>{e(small)}</small>" if small else ""}</span><span class="p">{e(price)}</span></label>'
    b = ''.join([opt('checkbox', 'b', 'web', 'b-web', L(lang, 'Website in 7 Tagen', 'Website in 7 days'), L(lang, 'Onepager oder bis 5 Seiten', 'One-pager or up to 5 pages'), L(lang, '390 €', '€390')),
                 opt('checkbox', 'b', 'auto', 'b-auto', L(lang, 'Anfrage-Automatik', 'Inquiry automation'), L(lang, 'Formular, Tabelle, Nachricht an Sie', 'Form, spreadsheet, message to you'), L(lang, '290 €', '€290')),
                 opt('checkbox', 'b', 'survey', 'b-survey', L(lang, 'Kundenumfrage', 'Customer survey'), L(lang, 'Umfrage mit Auswertung', 'Survey with analysis'), L(lang, '190 €', '€190')),
                 opt('checkbox', 'b', 'check', 'b-check', L(lang, 'Website-Check', 'Website check'), L(lang, '5 Prüfpunkte vorab', '5 checkpoints up front'), L(lang, '0 €', '€0'), True)])
    c = ''.join([opt('radio', 'care', 'none', 'c-none', L(lang, 'Keine Betreuung', 'No maintenance'), L(lang, 'Sie übernehmen Updates selbst', 'You handle updates yourself'), L(lang, '0 €', '€0'), True),
                 opt('radio', 'care', 'S', 'c-s', L(lang, 'Betreuung S', 'Maintenance S'), L(lang, 'Überwachung, Backups, Updates, Bericht', 'Monitoring, backups, updates, report'), L(lang, '29 €/M', '€29/mo')),
                 opt('radio', 'care', 'M', 'c-m', L(lang, 'Betreuung M', 'Maintenance M'), L(lang, 'wie S, plus 30 Min. Änderungen', 'like S, plus 30 min of changes'), L(lang, '49 €/M', '€49/mo')),
                 opt('radio', 'care', 'L', 'c-l', L(lang, 'Betreuung L', 'Maintenance L'), L(lang, 'wie S, plus 1 Std. Änderungen', 'like S, plus 1 hour of changes'), L(lang, '79 €/M', '€79/mo'))])
    x = ''.join([opt('checkbox', 'x', 'pages', 'x-pages', L(lang, 'Mehr als 5 Seiten', 'More than 5 pages'), L(lang, 'Preis nach Absprache', 'Price by arrangement'), ''),
                 opt('checkbox', 'x', 'lang', 'x-lang', L(lang, 'Zweite Sprache', 'Second language'), L(lang, 'Preis nach Absprache', 'Price by arrangement'), ''),
                 opt('checkbox', 'x', 'booking', 'x-booking', L(lang, 'Online-Terminbuchung', 'Online booking'), L(lang, 'Buchungstool einbinden', 'Integrate a booking tool'), ''),
                 opt('checkbox', 'x', 'move', 'x-move', L(lang, 'Umzug Ihrer Website', 'Move your website'), L(lang, 'Inhalte und Domain übernehmen', 'Transfer content and domain'), '')])
    w = ''.join([opt('radio', 'when', 'asap', 'w-asap', L(lang, 'So bald wie möglich', 'As soon as possible'), '', '', True),
                 opt('radio', 'when', 'later', 'w-later', L(lang, 'In 1 bis 2 Monaten', 'In 1 to 2 months'), '', '')])
    body = page_hero(L(lang, 'Ihr eigenes Paket', 'Your own package'), L(lang, 'Wählen Sie, was Sie brauchen. Der Preis rechnet sich sofort mit, Extras kläre ich mit Ihnen im Gespräch.', 'Choose what you need. The price updates instantly, and we discuss extras together.')) + f'''
  <section class="sec sec--tight tint"><div class="wrap">
      <div class="builder">
        <form class="opts" id="builder" novalidate>
          <fieldset data-reveal><legend>{L(lang, 'Bausteine', 'Building blocks')}</legend><div class="opt-grid">{b}</div></fieldset>
          <fieldset data-reveal><legend>{L(lang, 'Betreuung', 'Maintenance')}</legend><div class="opt-grid">{c}</div></fieldset>
          <fieldset data-reveal><legend>Extras</legend><div class="opt-grid">{x}</div>
            <div class="field opt-text"><label for="x-wish">{L(lang, 'Eigener Wunsch', 'Your own request')} <em>(optional)</em></label><input id="x-wish" type="text" maxlength="200" placeholder="{L(lang, 'Zum Beispiel: Galerie mit Projektfotos', 'For example: gallery with project photos')}"></div>
          </fieldset>
          <fieldset data-reveal><legend>{L(lang, 'Zeitrahmen', 'Timeframe')}</legend><div class="opt-grid">{w}</div></fieldset>
        </form>
        <aside class="summary" aria-live="polite" data-reveal="scale">
          <h3>{L(lang, 'Ihre Zusammenstellung', 'Your selection')}</h3>
          <ul id="sum-list"></ul>
          <p class="empty" id="sum-empty" hidden>{L(lang, 'Wählen Sie links mindestens einen Baustein oder eine Betreuung.', 'Choose at least one building block or a maintenance plan.')}</p>
          <div class="totals">
            <div><span>{L(lang, 'Einmalig', 'One-off')}</span><strong id="sum-once">0 €</strong></div>
            <div class="m"><span>{L(lang, 'Monatlich', 'Monthly')}</span><strong id="sum-month">0 €</strong></div>
          </div>
          <p class="note" id="sum-note"></p>
          <button class="btn btn--primary" id="sum-send" type="button" data-target="{url('kontakt', lang)}">{L(lang, 'Zusammenstellung anfragen', 'Request this selection')}</button>
        </aside>
      </div>
  </div></section>'''
    return page(lang, 'individuell', L(lang, 'Individuelles Paket – Lou Digital', 'Custom package – Lou Digital'), L(lang, 'Stellen Sie Website, Automatik, Umfrage, Betreuung und Extras selbst zusammen und sehen Sie den Preis sofort.', 'Combine website, automation, survey, maintenance and extras yourself and see the price instantly.'), body)

def sub_ueber(lang):
    body = page_hero(L(lang, 'Über mich', 'About me'), L(lang, 'Ich bin Adrian Semlinger und arbeite von Karlsruhe aus.', 'I’m Adrian Semlinger and I work from Karlsruhe, Germany.')) + f'''
  <section class="sec sec--tight"><div class="wrap about">
      <div data-reveal="scale">{about_photo()}</div>
      <div class="copy">
        <p class="lead" data-reveal>{L(lang, 'Websites und Automatisierung, die nach der Übergabe einfach weiterlaufen.', 'Websites and automation that simply keep running after handover.')}</p>
        <p data-reveal style="--d:.05s">{L(lang, 'Ich komme aus dem Kaufmännischen: Einkauf, Angebote, Rechnungswesen. Deshalb weiß ich, wie viel Zeit in Abläufen steckt, die sich automatisieren lassen, und wie wichtig ein klarer Preis vor dem Start ist.', 'My background is in business: purchasing, quotes and accounting. That’s why I know how much time goes into processes that could be automated, and how important a clear price is before you start.')}</p>
        <p data-reveal style="--d:.1s">{L(lang, 'Ich arbeite mit festen Paketen und Werkzeugen, die nach der Übergabe weiterlaufen. Domain und Zugänge laufen auf Ihren Namen. Was ich baue, erkläre ich so, dass Sie es ohne mich bedienen können.', 'I work with fixed packages and tools that keep running after handover. Domain and accounts are registered in your name. I explain what I build so you can run it without me.')}</p>
        <dl class="spec" data-reveal style="--d:.15s">{spec_rows([(L(lang, 'Sitz', 'Based in'), 'Karlsruhe'), (L(lang, 'Arbeitsweise', 'Way of working'), L(lang, 'Feste Pakete, ein Ansprechpartner', 'Fixed packages, one point of contact')), (L(lang, 'E-Mail', 'Email'), f'<a class="mailto" href="mailto:{EMAIL_ADRIAN}">{EMAIL_ADRIAN}</a>'), (L(lang, 'Telefon', 'Phone'), f'<a class="mailto" href="tel:{PHONE.replace(" ", "")}">{e(PHONE)}</a>'), (L(lang, 'Allgemein', 'General'), f'<a class="mailto" href="mailto:{EMAIL}">{EMAIL}</a>')])}</dl>
        <div class="row-actions" data-reveal>{btn(url('kontakt', lang), L(lang, 'Anfrage senden', 'Send an inquiry'))}<a class="btn btn--line" href="tel:{PHONE.replace(' ', '')}" data-track="Anruf Über mich">{L(lang, 'Anrufen', 'Call')}</a><a class="link" href="mailto:{EMAIL_ADRIAN}">{L(lang, 'E-Mail an Adrian', 'Email Adrian')}</a></div>
      </div>
  </div></section>''' + closer(lang)
    return page(lang, 'ueber', L(lang, 'Über mich – Lou Digital', 'About me – Lou Digital'), L(lang, 'Adrian Semlinger, Lou Digital in Karlsruhe: Websites und Automatisierung mit festen Paketen und klaren Preisen.', 'Adrian Semlinger, Lou Digital in Karlsruhe: websites and automation with fixed packages and clear prices.'), body)

def sub_fragen(lang):
    ask = f'''<section class="sec tint" id="frage-stellen"><div class="wrap ask-wrap">
    <h2 class="h2" data-reveal>{L(lang, 'Ihre Frage fehlt?', 'Your question missing?')}</h2>
    <p class="lead" data-reveal style="--d:.05s">{L(lang, 'Stellen Sie sie hier. Sie bekommen eine persönliche Antwort, in der Regel innerhalb von 24 Stunden.', 'Ask it here. You get a personal answer, usually within 24 hours.')}</p>
    <form class="form" id="ask" action="/api/anfrage.php" method="post" novalidate data-reveal="scale">
      <input type="hidden" name="anliegen" value="frage"><input type="hidden" name="anliegen_text" value="{L(lang, 'Frage', 'Question')}">
      <div class="row2">
        <div class="field"><label for="a-name">{L(lang, 'Ihr Name', 'Your name')}</label><input id="a-name" name="name" type="text" autocomplete="name" maxlength="100" required><span class="err" id="ea-name" hidden>{L(lang, 'Bitte geben Sie Ihren Namen ein.', 'Please enter your name.')}</span></div>
        <div class="field"><label for="a-mail">{L(lang, 'Ihre E-Mail', 'Your email')}</label><input id="a-mail" name="email" type="email" autocomplete="email" maxlength="150" required><span class="err" id="ea-mail" hidden>{L(lang, 'Bitte prüfen Sie die E-Mail-Adresse.', 'Please check the email address.')}</span></div>
      </div>
      <div class="field"><label for="a-msg">{L(lang, 'Ihre Frage', 'Your question')}</label><textarea id="a-msg" name="nachricht" maxlength="1500" required placeholder="{L(lang, 'Was möchten Sie wissen?', 'What would you like to know?')}"></textarea><span class="err" id="ea-msg" hidden>{L(lang, 'Bitte schreiben Sie Ihre Frage auf.', 'Please write your question.')}</span></div>
      <div class="hp" aria-hidden="true"><label for="a-hp">{L(lang, 'Bitte leer lassen', 'Please leave empty')}</label><input id="a-hp" name="website" type="text" tabindex="-1" autocomplete="off"></div>
      <label class="consent" for="a-ok"><input id="a-ok" name="einwilligung" value="1" type="checkbox" required><span>{L(lang, f'Ich habe die <a href="{url("datenschutz", lang)}">Datenschutzerklärung</a> gelesen und bin einverstanden, dass Lou Digital meine Angaben zur Beantwortung meiner Frage speichert.', f'I have read the <a href="{url("datenschutz", lang)}">privacy policy</a> and agree that Lou Digital stores my details to answer my question.')} <b class="req" aria-hidden="true">*</b></span></label>
      <span class="err err-box" id="ea-ok" hidden role="alert">{L(lang, 'Bitte akzeptieren Sie die Datenschutzerklärung, sonst kann ich Ihre Frage nicht absenden.', 'Please accept the privacy policy, otherwise I cannot send your question.')}</span>
      <button class="btn btn--primary" type="submit" id="a-send">{L(lang, 'Frage senden', 'Send question')}</button>
      <p class="msg" id="a-res" hidden role="status"></p>
    </form>
  </div></section>'''
    body = page_hero(L(lang, 'Häufige Fragen', 'Frequently asked questions'), L(lang, 'Die wichtigsten Antworten zu Preisen, Ablauf und Betreuung.', 'The key answers on prices, process and maintenance.')) + f'''
  <section class="sec sec--tight"><div class="wrap">{faq_html(lang, faq_items(lang))}
    <div class="row-actions" style="margin-top:32px" data-reveal><a class="btn btn--primary" href="#frage-stellen">{L(lang, 'Eigene Frage stellen', 'Ask your own question')}</a></div>
  </div></section>''' + ask
    items = faq_items(lang)
    ld = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a, *_ in items]}
    head = f'<script type="application/ld+json">{json.dumps(ld, ensure_ascii=False)}</script>\n'
    return page(lang, 'fragen', L(lang, 'Häufige Fragen – Lou Digital', 'FAQ – Lou Digital'), L(lang, 'Antworten zu Preisen, Eigentum, Ablauf, Rechtstexten, Betreuung und Antwortzeit.', 'Answers on prices, ownership, process, legal texts, maintenance and response time.'), body, extra_head=head)

def sub_kontakt(lang):
    chips = [('check', L(lang, 'Website-Check', 'Website check'), L(lang, 'kostenlos', 'free')), ('website', L(lang, 'Website in 7 Tagen', 'Website in 7 days'), L(lang, '390 €', '€390')),
             ('automatik', L(lang, 'Anfrage-Automatik', 'Inquiry automation'), L(lang, '290 €', '€290')), ('umfrage', L(lang, 'Kundenumfrage', 'Customer survey'), L(lang, '190 €', '€190')),
             ('betreuung', L(lang, 'Betreuung', 'Maintenance'), L(lang, 'ab 29 €/M', 'from €29/mo')), ('individuell', L(lang, 'Individuelles Paket', 'Custom package'), ''),
             ('termin', L(lang, 'Gespräch vereinbaren', 'Book a call'), L(lang, 'Telefon, Video, vor Ort', 'phone, video, in person')), ('sonstiges', L(lang, 'Sonstiges', 'Other'), '')]
    ch = ''.join(f'<label class="chip" for="p-{v}" data-track="Auswahl: {v}"><input type="checkbox" id="p-{v}" name="pakete[]" value="{v}"><span><b>{e(t)}</b>{f"<small>{e(s)}</small>" if s else ""}</span></label>' for v, t, s in chips)
    tiers = ''.join(f'<label class="seg-o"><input type="radio" name="betreuung_stufe" value="{t}"{" checked" if t == "S" else ""}><span><b>{t}</b> {L(lang, f"{p} €/Monat", f"€{p}/month")}</span></label>' for t, p in (('S', 29), ('M', 49), ('L', 79)))
    slots = [('vormittag', L(lang, 'Vormittag (9–12 Uhr)', 'Morning (9am–12pm)')), ('mittag', L(lang, 'Mittag (12–15 Uhr)', 'Midday (12–3pm)')), ('nachmittag', L(lang, 'Nachmittag (15–18 Uhr)', 'Afternoon (3–6pm)')), ('abend', L(lang, 'Abend (18–20 Uhr)', 'Evening (6–8pm)'))]
    arts = [('telefon', L(lang, 'Telefon', 'Phone')), ('video', L(lang, 'Videocall', 'Video call')), ('vorort', L(lang, 'Vor Ort', 'In person'))]
    so = ''.join(f'<option value="{v}">{e(t)}</option>' for v, t in slots); ao = ''.join(f'<option value="{v}">{e(t)}</option>' for v, t in arts)
    tel = PHONE.replace(' ', '')
    body = page_hero(L(lang, 'Anfrage senden', 'Send an inquiry'), L(lang, 'Wählen Sie, was Sie interessiert, oder vereinbaren Sie direkt ein Gespräch. Sie bekommen sofort eine Bestätigung und von mir eine persönliche Antwort.', 'Choose what interests you, or book a call straight away. You get a confirmation right away and a personal reply from me.')) + f'''
  <section class="sec sec--tight"><div class="wrap close kontakt-grid">
    <div class="k-side">
      <div class="contact-quick" data-reveal>
        <a class="cq" href="tel:{tel}" data-track="Anruf"><span class="cq-i" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z" fill="currentColor"/></svg></span><span><b>{L(lang, 'Anrufen', 'Call')}</b><small>{e(PHONE)}</small></span></a>
        <a class="cq" href="mailto:{EMAIL}" data-track="E-Mail"><span class="cq-i" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5zm2 .9v.3l7 4.6 7-4.6v-.3z" fill="currentColor"/></svg></span><span><b>{L(lang, "E-Mail", "Email")}</b><small>{EMAIL}</small></span></a>
        <a class="cq" href="?pakete=termin#contact" data-termin data-track="Gespräch vereinbaren"><span class="cq-i" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zm-2 8h14v9H5z" fill="currentColor"/></svg></span><span><b>{L(lang, 'Gespräch vereinbaren', 'Book a call')}</b><small>{L(lang, 'Telefon, Video oder vor Ort', 'Phone, video or in person')}</small></span></a>
      </div>
      <div class="contact-facts" data-reveal>
        <p><strong>{L(lang, 'Antwortzeit', 'Response time')}</strong><br>{L(lang, 'In der Regel innerhalb von 24 Stunden an Werktagen. Melde ich mich später, erhalten Sie 10 % Rabatt auf Ihr erstes Paket.', 'Usually within 24 hours on working days. If I take longer, you get 10% off your first package.')}</p>
        <p><strong>{L(lang, 'Vor Ort und unterwegs', 'On site and on the road')}</strong><br>{L(lang, 'Ich sitze in Karlsruhe, arbeite gerne persönlich bei Ihnen und reise auch weiter. Anfahrt und Zeit besprechen wir vorab und planen sie im Preis ein.', 'I’m based in Karlsruhe, like to work with you in person and also travel further. We’ll agree travel and time up front and include them in the price.')}</p>
      </div>
      <div class="trio" aria-hidden="true" data-reveal="scale">
        <div class="blk blk--petrol">{fig('web', '84%')}</div>
        <div class="blk blk--ink">{fig('mail', '86%')}</div>
        <div class="blk blk--lime">{fig('box', '90%')}</div>
      </div>
    </div>
    <form class="form" id="contact" action="/api/anfrage.php" method="post" novalidate data-reveal="scale">
      <h3>{L(lang, 'Ihre Anfrage', 'Your inquiry')}</h3>
      <fieldset class="chips"><legend>{L(lang, 'Was interessiert Sie?', 'What are you interested in?')} <em>{L(lang, '(mehrere möglich)', '(choose any)')}</em></legend><div class="chip-grid">{ch}</div></fieldset>
      <div class="subf" id="tier-field" hidden><span class="sub-l">{L(lang, 'Welche Betreuung?', 'Which maintenance plan?')}</span><div class="seg">{tiers}</div></div>
      <div class="subf" id="termin-field" hidden>
        <span class="sub-l">{L(lang, 'Ihr Wunschtermin', 'Your preferred slot')}</span>
        <div class="row3">
          <div class="field"><label for="f-date">{L(lang, 'Tag', 'Day')}</label><input id="f-date" name="termin_datum" type="date"></div>
          <div class="field"><label for="f-art">{L(lang, 'Art', 'Type')}</label><select id="f-art" name="termin_art">{ao}</select></div>
          <div class="field f-slot"><label for="f-slot">{L(lang, 'Zeitfenster', 'Time')}</label><select id="f-slot" name="termin_zeit">{so}</select></div>
        </div>
        <span class="err" id="e-date" hidden>{L(lang, 'Bitte wählen Sie einen Tag von morgen bis in 60 Tagen, Montag bis Samstag.', 'Please choose a day from tomorrow up to 60 days ahead, Monday to Saturday.')}</span>
      </div>
      <div class="field" id="pkg-field" hidden><label for="f-pkg">{L(lang, 'Ihre Zusammenstellung', 'Your selection')}</label><textarea id="f-pkg" name="zusammenstellung" readonly></textarea><a class="link" style="justify-self:start;font-size:14px" href="{url('individuell', lang)}">{L(lang, 'Zusammenstellung ändern', 'Change selection')}</a></div>
      <div class="row2">
        <div class="field"><label for="f-name">{L(lang, 'Ihr Name', 'Your name')}</label><input id="f-name" name="name" type="text" autocomplete="name" maxlength="100" required><span class="err" id="e-name" hidden>{L(lang, 'Bitte geben Sie Ihren Namen ein.', 'Please enter your name.')}</span></div>
        <div class="field"><label for="f-company">{L(lang, 'Firma', 'Company')} <em>(optional)</em></label><input id="f-company" name="firma" type="text" autocomplete="organization" maxlength="120"></div>
      </div>
      <div class="row2">
        <div class="field"><label for="f-mail">{L(lang, 'Ihre E-Mail', 'Your email')}</label><input id="f-mail" name="email" type="email" autocomplete="email" maxlength="150" required placeholder="{L(lang, 'name@ihre-firma.de', 'name@your-company.com')}"><span class="err" id="e-mail" hidden>{L(lang, 'Bitte prüfen Sie die E-Mail-Adresse.', 'Please check the email address.')}</span></div>
        <div class="field"><label for="f-tel">{L(lang, 'Telefon', 'Phone')} <em>(optional)</em></label><input id="f-tel" name="telefon" type="tel" autocomplete="tel" maxlength="30" placeholder="{L(lang, '0721 123456', '+49 721 123456')}"><span class="err" id="e-tel" hidden>{L(lang, 'Bitte prüfen Sie die Telefonnummer.', 'Please check the phone number.')}</span></div>
      </div>
      <div class="field"><label for="f-url">{L(lang, 'Ihre Website', 'Your website')} <em id="url-hint">(optional)</em></label><input id="f-url" name="website_url" type="text" inputmode="url" maxlength="200" placeholder="{L(lang, 'ihre-firma.de', 'your-company.com')}" autocomplete="url"><span class="err" id="e-url" hidden>{L(lang, 'Bitte geben Sie eine Adresse wie ihre-firma.de ein.', 'Please enter an address like your-company.com.')}</span></div>
      <div class="field"><label for="f-msg">{L(lang, 'Ihre Nachricht', 'Your message')}</label><textarea id="f-msg" name="nachricht" maxlength="1500" required placeholder="{L(lang, 'Worum geht es? Zum Beispiel: Ich brauche eine neue Website für meine Bäckerei.', 'What is it about? For example: I need a new website for my bakery.')}"></textarea><span class="err" id="e-msg" hidden>{L(lang, 'Bitte schreiben Sie kurz, worum es geht (mindestens 10 Zeichen).', 'Please write briefly what it is about (at least 10 characters).')}</span></div>
      <div class="hp" aria-hidden="true"><label for="f-hp">{L(lang, 'Bitte leer lassen', 'Please leave empty')}</label><input id="f-hp" name="website" type="text" tabindex="-1" autocomplete="off"></div>
      <label class="consent" for="f-ok"><input id="f-ok" name="einwilligung" value="1" type="checkbox" required><span>{L(lang, f'Ich habe die <a href="{url("datenschutz", lang)}">Datenschutzerklärung</a> gelesen und bin einverstanden, dass Lou Digital meine Angaben zur Bearbeitung meiner Anfrage speichert.', f'I have read the <a href="{url("datenschutz", lang)}">privacy policy</a> and agree that Lou Digital stores my details to process my inquiry.')} <b class="req" aria-hidden="true">*</b></span></label>
      <span class="err err-box" id="e-ok" hidden role="alert">{L(lang, 'Bitte akzeptieren Sie die Datenschutzerklärung. Ohne Ihre Zustimmung kann ich die Anfrage nicht speichern und nicht absenden.', 'Please accept the privacy policy. Without your consent I cannot store or send your inquiry.')}</span>
      <p class="req-note"><b class="req">*</b> {L(lang, 'Pflichtfeld. Gesendet wird erst, wenn Name, E-Mail, Nachricht und Zustimmung vorhanden sind.', 'Required. Your inquiry is only sent once name, email, message and consent are provided.')}</p>
      <button class="btn btn--primary" type="submit" id="f-send">{L(lang, 'Anfrage senden', 'Send inquiry')}</button>
      <p class="msg" id="f-res" hidden role="status"></p>
    </form>
  </div></section>'''
    return page(lang, 'kontakt', L(lang, 'Anfrage senden – Lou Digital', 'Send an inquiry – Lou Digital'), L(lang, 'Kontakt zu Lou Digital in Karlsruhe: Pakete auswählen, Gespräch vereinbaren oder kostenlosen Website-Check anfordern.', 'Contact Lou Digital in Karlsruhe: select packages, book a call or request a free website check.'), body)

def sub_impressum(lang):
    tel = f'<br>{L(lang, "Telefon", "Phone")}: <a href="tel:{PHONE.replace(" ", "")}">{e(PHONE)}</a>' if PHONE else ''
    if lang == 'de':
        inner = f'''<h1>Impressum</h1>
<h2>Angaben gemäß § 5 DDG</h2>
<p>Lou Digital<br>Inhaber: Adrian Semlinger<br>{ADDRESS[0]}<br>{ADDRESS[1]}<br>Deutschland</p>
<h2>Kontakt</h2>
<p>E-Mail: <a href="mailto:{EMAIL}">{EMAIL}</a>{tel}</p>
<h2>Umsatzsteuer</h2>
<p>Kleinunternehmer gemäß § 19 UStG. Es wird keine Umsatzsteuer berechnet und keine Umsatzsteuer-Identifikationsnummer geführt.</p>
<h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
<p>Adrian Semlinger, {ADDRESS[0]}, {ADDRESS[1]}</p>
<h2>Verbraucherstreitbeilegung</h2>
<p>Ich bin nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>'''
    else:
        inner = f'''<h1>Legal notice</h1>
<p>The German version (<a href="{url('impressum', 'de')}" hreflang="de">Impressum</a>) is legally binding.</p>
<h2>Information pursuant to § 5 DDG</h2>
<p>Lou Digital<br>Owner: Adrian Semlinger<br>{ADDRESS[0]}<br>{ADDRESS[1]}<br>Germany</p>
<h2>Contact</h2>
<p>Email: <a href="mailto:{EMAIL}">{EMAIL}</a>{tel}</p>
<h2>VAT</h2>
<p>Small business under § 19 UStG (German VAT Act). No VAT is charged and no VAT identification number is held.</p>
<h2>Responsible for content under § 18 (2) MStV</h2>
<p>Adrian Semlinger, {ADDRESS[0]}, {ADDRESS[1]}, Germany</p>
<h2>Consumer dispute resolution</h2>
<p>I am neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.</p>'''
    body = f'<section class="legal"><div class="wrap" data-reveal="fade">{inner}</div></section>'
    return page(lang, 'impressum', L(lang, 'Impressum – Lou Digital', 'Legal notice – Lou Digital'), L(lang, 'Impressum von Lou Digital, Adrian Semlinger, Karlsruhe.', 'Legal notice of Lou Digital, Adrian Semlinger, Karlsruhe.'), body, noindex=True)

def sub_datenschutz(lang):
    if lang == 'de':
        inner = f'''<h1>Datenschutz&shy;erklärung</h1>
<h2>1. Verantwortlicher</h2>
<p>Adrian Semlinger, Lou Digital, {ADDRESS[0]}, {ADDRESS[1]}, E-Mail: <a href="mailto:{EMAIL}">{EMAIL}</a></p>
<h2>2. Grundsätze</h2>
<p>Diese Website setzt keine Cookies und lädt keine Inhalte von fremden Servern. Schriften und Skripte liegen auf dem eigenen Webspace. Für den Seitenwechsel wird nur eine kurze Auswahl (Paket-Zusammenstellung) im Sitzungsspeicher Ihres Browsers abgelegt, die beim Schließen des Tabs gelöscht wird. Zur Verbesserung der Website nutze ich eine eigene Reichweitenmessung ohne Cookies (Abschnitt 6).</p>
<h2>3. Hosting und Server-Logdateien</h2>
<p>Die Website liegt bei der netcup GmbH, Daimlerstraße 25, 76185 Karlsruhe, auf Servern in Deutschland. Mit netcup besteht ein Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO.</p>
<p>Beim Aufruf der Website speichert der Server automatisch technische Daten: IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browser und Betriebssystem sowie die zuvor besuchte Seite. Das dient dem sicheren und stabilen Betrieb (Art. 6 Abs. 1 lit. f DSGVO). Die Logdateien werden nach spätestens 14 Tagen gelöscht, sofern sie nicht zur Aufklärung eines Sicherheitsvorfalls länger benötigt werden.</p>
<h2>4. Anfrageformular und E-Mail</h2>
<p>Wenn Sie das Anfrageformular nutzen, werden Ihre Angaben (Auswahl, Name, gegebenenfalls Firma, E-Mail-Adresse, gegebenenfalls Telefonnummer und Website, Nachricht, gegebenenfalls Ihre Paket-Zusammenstellung) verschlüsselt an den Webserver übertragen und per E-Mail an mein Postfach bei netcup weitergeleitet. Sie erhalten eine automatische Eingangsbestätigung an die angegebene Adresse. Zum Schutz vor Missbrauch wird Ihre IP-Adresse in gekürzter, nicht rückrechenbarer Form höchstens eine Stunde gespeichert, um zu viele Anfragen in kurzer Zeit zu begrenzen.</p>
<p>Ich verarbeite die Angaben, um Ihre Anfrage zu beantworten und gegebenenfalls ein Angebot zu erstellen (Art. 6 Abs. 1 lit. b DSGVO). Pflichtangaben sind nur Name und E-Mail-Adresse, beim Website-Check zusätzlich die Adresse der Website. Ich lösche Anfragen, wenn sie erledigt sind und keine gesetzlichen Aufbewahrungspflichten bestehen; Unterlagen zu Aufträgen bewahre ich nach den steuer- und handelsrechtlichen Fristen auf. Dasselbe gilt, wenn Sie mir direkt eine E-Mail schreiben.</p>
<p>Wenn Sie einen Gesprächstermin anfragen, verarbeite ich zusätzlich Ihren Wunschtag, das Zeitfenster und die Art des Gesprächs, um den Termin mit Ihnen abzustimmen (Art. 6 Abs. 1 lit. b DSGVO). Über die Feedback-Lasche können Sie mir Vorschläge schicken; eine E-Mail-Adresse ist dort freiwillig und dient nur Rückfragen.</p>
<h2>5. Website-Check</h2>
<p>Für den Website-Check ruft mein Server die von Ihnen genannte öffentliche Website einmal auf und prüft sie an fünf Punkten. Beim Sofort-Check wird das Ergebnis nur angezeigt und nicht gespeichert. Fordern Sie den Check per Formular an, speichere ich das Ergebnis zusammen mit Ihrer Anfrage, um es Ihnen zu schicken (Art. 6 Abs. 1 lit. b DSGVO).</p>
<h2>6. Reichweitenmessung ohne Cookies</h2>
<p>Um zu verstehen, welche Inhalte gelesen und genutzt werden, und die Website zu verbessern, erfasst ein eigenes Skript auf diesem Server beim Besuch: aufgerufene Seite, angeklickte Schaltflächen und Links mit ihrer ungefähren Position, wie weit eine Seite gelesen wurde, abgeschickte Formulare (ohne Inhalte), die Domain der Seite, von der Sie kamen, Sprache, Gerätetyp, Betriebssystem und Browser. Dabei werden keine Cookies gesetzt und nichts in Ihrem Browser gespeichert. Ihre IP-Adresse wird nicht gespeichert, sondern nur kurz zusammen mit der Browserkennung und einem täglich wechselnden Zufallswert zu einem verkürzten Hash umgerechnet, damit Besucher pro Tag gezählt werden können; ein Rückschluss auf Ihre Person ist damit nicht möglich. Die Daten bleiben auf dem Webspace bei netcup und werden nicht weitergegeben. Rohdaten lösche ich nach 14 Monaten.</p>
<p>Rechtsgrundlage ist mein berechtigtes Interesse an einer nutzerfreundlichen und wirksamen Website (Art. 6 Abs. 1 lit. f DSGVO). Wenn Ihr Browser „Do Not Track“ oder „Global Privacy Control“ sendet, findet keine Messung statt. Sie können jederzeit widersprechen, eine E-Mail genügt.</p>
<h2>7. Ihre Rechte</h2>
<p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch gegen Verarbeitungen auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Eine erteilte Einwilligung können Sie jederzeit für die Zukunft widerrufen. Eine E-Mail an <a href="mailto:{EMAIL}">{EMAIL}</a> genügt.</p>
<p>Sie können sich bei einer Datenschutz-Aufsichtsbehörde beschweren, zum Beispiel beim Landesbeauftragten für den Datenschutz und die Informationsfreiheit Baden-Württemberg, Lautenschlagerstraße 20, 70173 Stuttgart.</p>
<h2>8. Stand</h2>
<p>Oktober 2026</p>'''
    else:
        inner = f'''<h1>Privacy policy</h1>
<p>The German version (<a href="{url('datenschutz', 'de')}" hreflang="de">Datenschutzerklärung</a>) is legally binding.</p>
<h2>1. Controller</h2>
<p>Adrian Semlinger, Lou Digital, {ADDRESS[0]}, {ADDRESS[1]}, Germany, email: <a href="mailto:{EMAIL}">{EMAIL}</a></p>
<h2>2. Principles</h2>
<p>This website sets no cookies and loads no content from third-party servers. Fonts and scripts are hosted on this server. Only a short selection (your package selection) is kept in your browser’s session storage when you move between pages; it is deleted when you close the tab. To improve the website, I use my own audience measurement without cookies (section 6).</p>
<h2>3. Hosting and server log files</h2>
<p>The website is hosted by netcup GmbH, Daimlerstraße 25, 76185 Karlsruhe, Germany, on servers in Germany. A data processing agreement under Art. 28 GDPR is in place with netcup.</p>
<p>When you visit the website, the server automatically stores technical data: IP address, date and time, page requested, browser and operating system, and the referring page. This serves the secure and stable operation of the website (Art. 6 (1) (f) GDPR). Log files are deleted after 14 days at the latest unless they are needed longer to investigate a security incident.</p>
<h2>4. Inquiry form and email</h2>
<p>When you use the inquiry form, your details (topic, name, company if given, email address, phone number and website if given, message, your package selection if any) are sent encrypted to the web server and forwarded by email to my mailbox at netcup. You receive an automatic confirmation at the address you entered. To prevent abuse, a shortened, irreversible form of your IP address is kept for at most one hour to limit too many inquiries in a short time.</p>
<p>I process the details to answer your inquiry and, if applicable, prepare a quote (Art. 6 (1) (b) GDPR). Only name and email address are required, plus the website address for a website check. I delete inquiries once they are settled and no statutory retention obligations apply; records of orders are kept for the periods required by tax and commercial law. The same applies if you email me directly.</p>
<p>If you request an appointment, I also process the preferred day, time slot and type of call in order to arrange it with you (Art. 6 (1) (b) GDPR). You can send me suggestions via the feedback tab; an email address is optional there and only used for questions.</p>
<h2>5. Website check</h2>
<p>For the website check, my server visits the public website you name once and checks it on five points. With the instant check, the result is only displayed and not stored. If you request the check via the form, I store the result with your inquiry in order to send it to you (Art. 6 (1) (b) GDPR).</p>
<h2>6. Audience measurement without cookies</h2>
<p>To understand which content is read and used and to improve the website, a script on this server records during your visit: the page viewed, buttons and links clicked with their approximate position, how far a page was read, forms submitted (without their content), the domain of the referring page, language, device type, operating system and browser. No cookies are set and nothing is stored in your browser. Your IP address is not stored; it is only briefly combined with the browser identifier and a random value that changes daily into a shortened hash so that visitors can be counted per day. This does not allow conclusions about you as a person. The data stays on the web space at netcup and is not shared. I delete raw data after 14 months.</p>
<p>The legal basis is my legitimate interest in a user-friendly and effective website (Art. 6 (1) (f) GDPR). If your browser sends “Do Not Track” or “Global Privacy Control”, no measurement takes place. You can object at any time; an email is sufficient.</p>
<h2>7. Your rights</h2>
<p>You have the right to access, rectification, erasure, restriction of processing, data portability and to object to processing based on Art. 6 (1) (f) GDPR. You can withdraw consent at any time with effect for the future. An email to <a href="mailto:{EMAIL}">{EMAIL}</a> is sufficient.</p>
<p>You can lodge a complaint with a data protection supervisory authority, for example the State Commissioner for Data Protection and Freedom of Information Baden-Württemberg, Lautenschlagerstraße 20, 70173 Stuttgart, Germany.</p>
<h2>8. Last updated</h2>
<p>October 2026</p>'''
    body = f'<section class="legal"><div class="wrap" data-reveal="fade">{inner}</div></section>'
    return page(lang, 'datenschutz', L(lang, 'Datenschutz – Lou Digital', 'Privacy policy – Lou Digital'), L(lang, 'Datenschutzerklärung von Lou Digital.', 'Privacy policy of Lou Digital.'), body, noindex=True)

def notfound():
    body = f'''<section class="nf"><div class="wrap">
  <h1 data-reveal>404</h1>
  <p class="lead" data-reveal style="--d:.06s;margin-top:20px">Diese Seite gibt es nicht. <span lang="en">This page doesn’t exist.</span></p>
  <div class="row-actions" style="margin-top:28px" data-reveal>{btn('/', 'Zur Startseite')}{btn('/en/', 'English home', 'line')}</div>
</div></section>'''
    return page('de', 'home', 'Seite nicht gefunden – Lou Digital', 'Diese Seite gibt es nicht.', body, noindex=True)

BUILDERS = {'home': home, 'websites': sub_websites, 'automatik': sub_automatik, 'check': sub_check, 'pakete': sub_pakete, 'individuell': sub_individuell,
            'ueber': sub_ueber, 'fragen': sub_fragen, 'kontakt': sub_kontakt, 'impressum': sub_impressum, 'datenschutz': sub_datenschutz}

def outpath(path):
    if path.endswith('/'): return DIST / path.lstrip('/') / 'start.html'
    return DIST / (path.lstrip('/') + '.html')

HTACCESS = r'''# Lou Digital, Version 12 (24.10.2026)
Options -Indexes -MultiViews
DirectoryIndex start.html
ErrorDocument 404 /404.html
AddDefaultCharset utf-8
AddType font/woff2 .woff2
AddType image/webp .webp
AddType application/manifest+json .webmanifest

<IfModule mod_rewrite.c>
RewriteEngine On
# www und http -> https://lou-digital.de
RewriteCond %{HTTP_HOST} ^www\.lou-digital\.de$ [NC]
RewriteRule ^ https://lou-digital.de%{REQUEST_URI} [R=301,L]
# direkte .html-Aufrufe auf saubere Adressen umleiten (nur echte Anfragen, keine Schleifen)
RewriteCond %{THE_REQUEST} \s/+((?:[^\s?]*/)?)(?:index|start)\.html[\s?] [NC]
RewriteRule ^ /%1 [R=301,L]
RewriteRule ^start/?$ / [R=301,L]
# Startseiten werden intern aus start.html geliefert (index.html gibt es nicht)
RewriteRule ^$ /start.html [L]
RewriteRule ^en/?$ /en/start.html [L]
RewriteCond %{THE_REQUEST} \s/+([^\s?]+?)\.html[\s?] [NC]
RewriteCond %{REQUEST_URI} !^/404\.html$
RewriteRule ^ /%1 [R=301,L]
# /en ohne Schrägstrich
RewriteRule ^en$ /en/ [R=301,L]
# Schrägstrich am Ende entfernen (außer Ordner)
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)/$ /$1 [R=301,L]
# saubere Adressen: /websites -> websites.html
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{DOCUMENT_ROOT}/$1.html -f
RewriteRule ^(.+)$ /$1.html [L]
</IfModule>

<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()"
Header always set Strict-Transport-Security "max-age=31536000"
Header always set Cross-Origin-Opener-Policy "same-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'sha256-__INLINE__'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests"
Header always unset X-Powered-By
Header unset X-Powered-By
</IfModule>

# Versteckte Dateien und Hilfsdateien nicht ausliefern
<FilesMatch "^\.(?!well-known)|\.(md|py|log|bak|sql|ini|sh)$">
Require all denied
</FilesMatch>

<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType image/webp "access plus 1 month"
ExpiresByType image/jpeg "access plus 1 month"
ExpiresByType text/css "access plus 1 week"
ExpiresByType application/javascript "access plus 1 week"
ExpiresByType text/html "access plus 0 seconds"
</IfModule>
'''

def build():
    if DIST.exists(): shutil.rmtree(DIST)
    shutil.copytree(ROOT / 'static', DIST)
    (DIST / 'css').mkdir(exist_ok=True)
    (DIST / 'css/site.css').write_text(SITE_CSS)
    (DIST / 'css/base.css').unlink(); (DIST / 'css/extra.css').unlink()
    for key, fn in BUILDERS.items():
        for lang in ('de', 'en'):
            p = outpath(url(key, lang)); p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(fn(lang))
    (DIST / '404.html').write_text(notfound())

    (DIST / '.htaccess').write_text(HTACCESS.replace('__INLINE__', INLINE_HASH))
    (DIST / 'robots.txt').write_text(f"User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /statistik/\nSitemap: {SITE}/sitemap.xml\n")
    urls = []
    for key in PAGES:
        if key in ('impressum', 'datenschutz'): continue
        for lang in ('de', 'en'):
            urls.append(f'''  <url><loc>{SITE}{url(key, lang)}</loc><lastmod>2026-10-24</lastmod>
    <xhtml:link rel="alternate" hreflang="de" href="{SITE}{url(key, 'de')}"/>
    <xhtml:link rel="alternate" hreflang="en" href="{SITE}{url(key, 'en')}"/></url>''')
    (DIST / 'sitemap.xml').write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n' + '\n'.join(urls) + '\n</urlset>\n')
    print('built', sum(1 for _ in DIST.rglob('*.html')), 'pages')

if __name__ == '__main__':
    build()
