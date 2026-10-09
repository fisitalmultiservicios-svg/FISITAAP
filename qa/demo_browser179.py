"""Real-browser tab isolation smoke test on the disposable migration fixture."""
import json
import pathlib
import re
import subprocess
import sys
from playwright.sync_api import sync_playwright
from migration179 import STAGE, SERVER, DELIVERY, run
config = STAGE / 'config.php'
original = config.read_text()
ip = run(['docker','inspect','-f','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',SERVER]).decode().strip()
base = 'http://' + ip
checks=[]
def passed(label):
    checks.append(label)
    print('PASS',label,flush=True)
try:
    config.write_text(original.replace('http://localhost', base))
    run(['docker','exec',SERVER,'curl','--noproxy','*','-sS','http://localhost/preparar-mudanza.php'])
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        ctx=browser.new_context()
        page=ctx.new_page()
        page.goto(base+'/demo')
        page.get_by_role('button',name='Probar panel',exact=True).first.click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/admin'))
        first=page.url
        token=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")')
        assert token in first
        page.goto(first+'/productos')
        assert page.locator('aside').filter(has_text='Demo privada').count()>=1
        passed('start sets tab storage and navigation remains in the private demo')
        other=ctx.new_page()
        other.goto(first)
        other.wait_for_url(base+'/demo')
        passed('a new tab without demo storage returns to the selector')
        page.close()
        other.get_by_role('button',name='Probar panel',exact=True).first.click()
        other.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/admin'))
        fresh=other.evaluate('sessionStorage.getItem("fisitaap-demo-tab")')
        assert fresh!=token
        passed('entry after closing the demo starts a new disposable copy')
        other.get_by_role('button',name='Terminar y descartar',exact=True).click()
        other.wait_for_url(base+'/demo')
        passed('explicit finish returns the browser to the demo selector')
        browser.close()
    (DELIVERY/'QA-NAVEGADOR.json').write_text(json.dumps({'environment':'Chromium with real JavaScript on isolated PHP 8.3 fixture','passed':len(checks),'checks':checks},indent=2)+'\n')
finally:
    config.write_text(original)
    run(['docker','exec',SERVER,'curl','--noproxy','*','-sS','http://localhost/preparar-mudanza.php'])
