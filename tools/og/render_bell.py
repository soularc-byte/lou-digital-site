import sys, subprocess, time, base64
from playwright.sync_api import sync_playwright
q = sys.argv[1] if len(sys.argv) > 1 else ''
out = sys.argv[2] if len(sys.argv) > 2 else '/home/claude/work/3d/out/fig-bell.png'
srv = subprocess.Popen([sys.executable, '-m', 'http.server', '8099', '--bind', '127.0.0.1'], cwd='/home/claude/work/3d', stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(1.0)
try:
    with sync_playwright() as p:
        b = p.chromium.launch(args=['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'])
        pg = b.new_page(viewport={'width': 900, 'height': 900}, device_scale_factor=1)
        msgs = []
        pg.on('console', lambda m: msgs.append(m.text))
        pg.on('pageerror', lambda e: msgs.append('ERR ' + str(e)))
        pg.goto('http://127.0.0.1:8099/bell.html' + q)
        pg.wait_for_function('window.__done === true', timeout=180000)
        pg.wait_for_timeout(300)
        data = pg.evaluate("document.getElementById('c').toDataURL('image/png')")
        open(out, 'wb').write(base64.b64decode(data.split(',')[1]))
        print('ok', out, msgs[:5])
        b.close()
finally:
    srv.terminate()
