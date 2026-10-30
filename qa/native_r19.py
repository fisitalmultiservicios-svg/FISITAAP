"""Existing Windows native bridge protocol compatibility; no executable rebuild."""
import json,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 r=subprocess.run(['node','--test','test/directory_r19.test.js'],cwd=ROOT/'desktop',text=True,capture_output=True);assert r.returncode==0,r.stdout+r.stderr
 out={'revision':'1.8.0-R19','passed':1,'checks':['Existing Windows local engine transports the directory-only native challenge; it does not expose device token/password hashes, grant cash authority, replace offline users or rewrite pending sales/shifts'],'environment':'Actual Node Windows-local-server engine with fictional remote response, temporary journal and loopback server. No installed executable or Android hardware.'};(ROOT/'actualizaciones/1.8.0/QA-NATIVO-R19.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS R19 native bridge compatibility')
if __name__=='__main__':main()
