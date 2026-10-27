"""Exercise the hybrid prototype, with explicit invoice geometry and graphical tables checks."""
import importlib.util,json,pathlib,tempfile,sys
from playwright.sync_api import sync_playwright,expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/'descargas/linea-fisitaap'
SOURCE=ROOT/'disenos/linea-fisitaap-2026-10-27'
BASE='http://127.0.0.1:18090/'

def main():
    spec=importlib.util.spec_from_file_location('original_ux_suite',ROOT/'qa/ux_designs_20261027.py');suite=importlib.util.module_from_spec(spec);spec.loader.exec_module(suite)
    if '--layout-only' in sys.argv:
        report=json.loads((SOURCE/'QA.json').read_text())
    else:
        with tempfile.TemporaryDirectory(prefix='fisitaap-hybrid-qa-') as temp:
            suite.ROOT=pathlib.Path(temp);(suite.ROOT/'disenos/ux-2026-10-27').mkdir(parents=True)
            suite.OUT=OUT;suite.DESIGNS=[('fusion','index.html')];suite.main()
            report=json.loads((suite.ROOT/'disenos/ux-2026-10-27/QA.json').read_text())
        (SOURCE/'QA.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    errors=[];geometry=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        ctx=browser.new_context(viewport={'width':1440,'height':1000});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(BASE+'index.html')
        p.screenshot(animations='disabled',path=str(OUT/'resumen.png'))
        suite.route(p,'pos')
        for id in ['p1','p6']:suite.act(p,'product-add',id)
        p.locator('[data-action=page][data-direction="1"]').click();suite.act(p,'product-add','p8');p.locator('[data-action=page][data-direction="-1"]').click()
        expect(p.locator('.ticket-line')).to_have_count(3)
        expect(p.locator('#toasts .toast')).to_have_count(0)
        p.screenshot(animations='disabled',path=str(OUT/'venta-rapida.png'))
        for w,h in [(1920,1080),(1440,1000),(1366,768),(1024,768),(768,1024),(844,390),(390,844),(360,740)]:
            p.set_viewport_size({'width':w,'height':h})
            if w<=650:p.locator('[data-tab=ticket]').click()
            g=p.evaluate('''()=>{const r=e=>{const b=e.getBoundingClientRect();return {left:b.left,right:b.right,width:b.width,bottom:b.bottom}};return {ticket:r(document.querySelector('.ticket')),quick:r(document.querySelector('.pos-products')),pay:r(document.querySelector('[data-action=pay]')),scroll:document.documentElement.scrollWidth,height:innerHeight}}''')
            assert g['scroll']<=w+1,(w,g)
            assert g['pay']['bottom']<=h+1,(w,h,'pay button outside screen',g)
            if w>650:
                assert g['ticket']['right']<=g['quick']['left'],g
                assert g['ticket']['width']>g['quick']['width']*1.4,g
                assert p.locator('.pos-layout>section').first.get_attribute('class')=='card ticket'
            geometry.append({'width':w,'height':h,'invoice_left_and_larger':w>650,'pay_button_in_view':True})
            if w==390:p.screenshot(animations='disabled',path=str(OUT/'movil-facturacion.png'))
        p.set_viewport_size({'width':1440,'height':1000});suite.route(p,'tables');expect(p.locator('.hybrid-floor')).to_be_visible();expect(p.locator('.floor-table')).to_have_count(8)
        p.screenshot(animations='disabled',path=str(OUT/'mesas.png'))
        p.locator('.floor-table[data-id=t4]').click();expect(p.locator('.detail-panel h2')).to_have_text('Mesa 04')
        p.locator('.floor-table[data-id=t2]').focus();p.keyboard.press('Enter');expect(p.locator('.detail-panel h2')).to_have_text('Mesa 02')
        p.locator('[data-hybrid=floor-zoom][data-delta=".2"]').click();assert p.locator('.hybrid-floor').get_attribute('style').startswith('width:120%');p.locator('[data-hybrid=floor-reset]').click()
        p.locator('[data-action=room][data-room=Terraza]').click();expect(p.locator('.floor-table')).to_have_count(2);p.locator('[data-action=room][data-room=Todos]').click()
        p.locator('.floor-table[data-id=t2]').click();suite.act(p,'table-open','t2');expect(p.locator('h1')).to_have_text('Mesa 02');suite.act(p,'product-add','p1');assert next(t for t in suite.data(p,'fusion')['tables'] if t['id']=='t2')['cart'][0]['qty']==1
        suite.route(p,'tables');p.locator('.floor-table[data-id=t2]').click();expect(p.locator('.detail-panel')).to_contain_text('Café latte');suite.act(p,'table-pay','t2');suite.formfill(p,'payForm',{'received':2000});suite.submit(p,'payForm');expect(p.locator('#modal')).to_contain_text('Venta completada');assert next(t for t in suite.data(p,'fusion')['tables'] if t['id']=='t2')['status']=='free';suite.close(p)
        suite.route(p,'tables');suite.act(p,'new-table');suite.formfill(p,'tableForm',{'name':'Mesa 09','seats':4});suite.submit(p,'tableForm');expect(p.locator('.floor-table')).to_have_count(9)
        assert p.locator('.hybrid-floor').evaluate('(e)=>[...e.querySelectorAll(".floor-table")].every(t=>{const b=t.getBBox(),m=t.transform.baseVal.consolidate().matrix;return b.y+m.f+b.height<e.viewBox.baseVal.height})')
        suite.route(p,'tables');p.set_viewport_size({'width':390,'height':844});p.screenshot(animations='disabled',path=str(OUT/'movil-mesas.png'));assert p.evaluate('document.documentElement.scrollWidth<=innerWidth')
        p.set_viewport_size({'width':1440,'height':1000});suite.mode(p,'master');p.screenshot(animations='disabled',path=str(OUT/'maestro.png'));suite.mode(p,'catalog');p.screenshot(animations='disabled',path=str(OUT/'catalogo.png'))
        # All menu destinations in the four modes still open after the visual changes.
        destinations=0
        for m in ('owner','master','catalog','driver'):
            suite.mode(p,m);p.locator('[data-action=nav]').click();routes=p.locator('#navigation [data-route]').evaluate_all('(a)=>a.map(e=>e.dataset.route)');p.locator('[data-action=nav-close]').click()
            for route in routes:suite.route(p,route);expect(p.locator('#view h1').first).to_be_visible();destinations+=1
        assert not errors,errors
        ctx.close();browser.close()
    report.update(variant='Línea · FISITAAP',specific_checks=['invoice first in DOM, left and 60% width','invoice totals and pay button remain in viewport','mobile invoice/products tabs','SVG furniture and room filtering','keyboard and pointer table selection','floor zoom and reset','open table, add product, retain account, pay and free table'],invoice_layouts=geometry,menu_destinations=destinations,page_errors=errors)
    (SOURCE/'QA.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print('PASS hybrid: invoice geometry, graphical tables, complete table payment, 51 screens and original business flows.')

if __name__=='__main__':main()
