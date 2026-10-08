"""Browser regression: exactly one native send, no automatic browser dialogs."""
from pathlib import Path
from playwright.sync_api import sync_playwright
source=Path('/workspace/FISITAAP/assets/app.js').read_text()
start=source.index('(()=>{\n  if(window.__fisitaapAndroidInstalled)')
script=source[start:source.index('// FISI-CHAT',start)]
html='''<section class="print-result-card"><div data-print-job data-auto="1" data-token="old-token"></div><button data-bridge-print>Print</button><p data-print-status></p><script type="application/json" data-print-payload>{"id":"receipt-test","text":"Receipt"}</script></section>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    page=browser.new_page()
    page.set_content(html)
    page.evaluate('window.sent=0;window.dialogs=0;window.fetch=()=>{throw Error("legacy bridge called")};window.print=()=>window.dialogs++;window.fisitaapWindows={print:async()=>{window.sent++;return {ok:true,status:"sent_to_printer"}}}')
    page.evaluate(script)
    page.wait_for_function('window.sent===1')
    assert page.evaluate('window.dialogs')==0
    page.evaluate('window.print()')
    page.wait_for_function('window.sent===2')
    assert page.evaluate('window.dialogs')==0
    page.close()
    page=browser.new_page();page.set_content(html)
    page.evaluate('window.__fisitaapAndroidInstalled=true;window.print=()=>{throw Error("browser dialog")};window.fetch=()=>{throw Error("legacy bridge")};window.fisitaapWindows={print:()=>{throw Error("wrong native")}}')
    page.evaluate(script)
    assert page.locator('[data-print-status]').inner_text()==''
    page.close()
    page=browser.new_page();page.set_content(html.replace('old-token',''))
    page.evaluate('window.dialogs=0;window.print=()=>window.dialogs++;void 0')
    page.evaluate(script)
    assert page.evaluate('window.dialogs')==0
    browser.close()
print('PASS print routing: Windows native only; Android owns printing; ordinary browser never auto-opens a dialog')
