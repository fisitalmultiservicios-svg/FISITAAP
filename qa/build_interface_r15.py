"""Reuse the approved HTML styles verbatim; business behavior stays in PHP/native JS."""
import hashlib,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
SOURCES=[ROOT/'disenos/ux-2026-10-27/styles.css',ROOT/'disenos/linea-fisitaap-2026-10-27/hybrid.css']
TARGET=ROOT/'actualizaciones/1.8.0/web/assets/linea-reference-r15.css'
def content():
    # The prototype prints only its #modal. Production keeps its thermal-receipt rules.
    base='\n'.join(line for line in SOURCES[0].read_text().split('\n') if not line.startswith('@media print{'))
    return '/* Shared directly with the approved hybrid HTML. No demo business logic. */\n'+base+'\n'+SOURCES[1].read_text()
def main():
    TARGET.write_text(content());print('Approved reference styles:',hashlib.sha256(TARGET.read_bytes()).hexdigest())
if __name__=='__main__':main()
