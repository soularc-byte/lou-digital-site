#!/usr/bin/env python3
"""Lou Digital – erneuert die ?v=-Fingerabdrücke (md5, 8 Zeichen) für CSS, JS und Bilder in allen HTML-Dateien.
Aufruf aus dem Repo-Wurzelverzeichnis: python3 tools/fingerprint.py  (nach jeder Änderung an css/, js/, img/)."""
import hashlib, os, re, sys

root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
pat = re.compile(r'((?:href|src|content)="(?:https://lou-digital\.de)?)(/(?:css|js|img|fonts)/[A-Za-z0-9_./-]+|/favicon\.svg)\?v=[0-9a-f]{8}"')
cache = {}
def fp(path):
    if path not in cache:
        f = os.path.join(root, path.lstrip('/'))
        cache[path] = hashlib.md5(open(f, 'rb').read()).hexdigest()[:8] if os.path.isfile(f) else None
    return cache[path]
changed = 0
for d, _, files in os.walk(root):
    if '/.git' in d or '/buero' in d or '/tools' in d: continue
    for n in files:
        if not n.endswith('.html'): continue
        p = os.path.join(d, n); s = open(p, encoding='utf-8').read()
        def sub(m):
            h = fp(m.group(2))
            return m.group(0) if h is None else m.group(1) + m.group(2) + '?v=' + h + '"'
        t = pat.sub(sub, s)
        if t != s:
            open(p, 'w', encoding='utf-8').write(t); changed += 1; print('aktualisiert:', os.path.relpath(p, root))
print(changed, 'Dateien geändert')
