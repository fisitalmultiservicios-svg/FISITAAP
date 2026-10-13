"""Convert the existing application SVG to Windows ICO; no new artwork."""
import pathlib
from playwright.sync_api import sync_playwright
from PIL import Image
root=pathlib.Path(__file__).resolve().parents[1];svg=(root/'actualizaciones/1.8.0/web/assets/icon.svg').read_text();png=pathlib.Path('/workspace/fisitaap-env/app-icon180.png')
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox']);page=browser.new_page(viewport={'width':256,'height':256});page.set_content('<style>html,body{margin:0;background:transparent}svg{width:256px;height:256px}</style>'+svg);page.screenshot(path=str(png),omit_background=True);browser.close()
Image.open(png).save(root/'desktop/icon.ico',sizes=[(16,16),(32,32),(48,48),(64,64),(128,128),(256,256)])
print('Existing FISITAAP app icon converted to ICO.')
