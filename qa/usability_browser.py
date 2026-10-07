"""Navigation and layout review on a private PHP QA fixture, not production."""
from pathlib import Path
import json
import os
import re
from playwright.sync_api import sync_playwright

base = os.environ.get('FISITAAP_QA_WEB_BASE', 'http://172.18.0.1:18445')
assert base in ['http://172.18.0.1:18445', 'http://127.0.0.1:18445']
fixture = json.loads(Path('/tmp/fisitaap-r2-e2e.json').read_text())
out = Path(os.environ.get('FISITAAP_QA_OUT', '/workspace/fisitaap-qa/2026-10-07'))
results = []
routes = ['/admin', '/admin/productos', '/admin/mesas?view=basic', '/admin/mesas?view=graphic', '/admin/cobro', '/admin/clientes-buscar', '/admin/express', '/admin/fidelizacion', '/admin/compras', '/admin/impresion', '/admin/escritorio', '/admin/kpis']
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox', '--no-proxy-server'])
    context = browser.new_context(viewport={'width': 1366, 'height': 900})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base + '/owner-login')
    page.locator('[name=email]').fill('owner@r2.invalid')
    page.locator('[name=password]').fill(fixture['password'])
    page.get_by_role('button', name=re.compile('Ingresar')).click()
    page.wait_for_url('**/admin')
    for width, height in [(1366, 900), (768, 1024), (390, 844)]:
        page.set_viewport_size({'width': width, 'height': height})
        for route in routes:
            response = page.goto(base + route)
            assert response and response.status == 200, route
            assert not re.search(r'Fatal error|SQLSTATE\[', page.inner_text('body')), route
            layout = page.evaluate('''() => ({
                width: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                mainHeading: document.querySelector('h1')?.innerText || '',
                smallVisibleControls: [...document.querySelectorAll('button,a.btn')].filter(el => {
                    const rect=el.getBoundingClientRect(); return rect.width>0 && rect.height>0 && (rect.width<32 || rect.height<32);
                }).length,
                unlabelledInputs: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(el => {
                    if(el.getAttribute('aria-label')||el.getAttribute('title')||el.placeholder||el.labels?.length) return false;
                    if(['submit','button'].includes(el.type))return false;
                    return el.getBoundingClientRect().width>0;
                }).length
            })''')
            layout.update({'route': route, 'viewport': f'{width}x{height}', 'overflow': layout['documentWidth'] > width + 1, 'status': 'review' if layout['documentWidth'] > width + 1 else 'passed'})
            results.append(layout)
            if layout['overflow']:
                page.screenshot(path=str(out / f'layout-{width}-{route.split("?")[0].split("/")[-1] or "admin"}.png'), full_page=True)
    assert not errors, errors
    browser.close()
(out / 'usability-layout.json').write_text(json.dumps(results, ensure_ascii=False, indent=2))
print('PASS navigation: 12 business screens at desktop, tablet and phone sizes; no PHP or JavaScript errors')
print('REVIEW horizontal page overflow:', sum(r['overflow'] for r in results))
print('REVIEW screens with unlabelled visible inputs:', sum(r['unlabelledInputs'] > 0 for r in results))
