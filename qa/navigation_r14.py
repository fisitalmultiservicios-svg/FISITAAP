"""Re-run the shared catalog/panel navigation regression against R14, preserving historical R13 evidence."""
import importlib.util,json,pathlib,tempfile
ROOT=pathlib.Path(__file__).resolve().parents[1]

def main():
    spec=importlib.util.spec_from_file_location('navigation_regression_r13',ROOT/'qa/navigation_r13.py');suite=importlib.util.module_from_spec(spec);spec.loader.exec_module(suite)
    with tempfile.TemporaryDirectory(prefix='fisitaap-r14-navigation-') as temp:
        suite.ROOT=pathlib.Path(temp);(suite.ROOT/'actualizaciones/1.8.0').mkdir(parents=True)
        suite.OUT=pathlib.Path('/workspace/fisitaap-env/navigation-r14');suite.main()
        result=json.loads((suite.ROOT/'actualizaciones/1.8.0/QA-NAVEGACION-R13.json').read_text())
    result['revision']='1.8.0-R14'
    result['environment']='R14 real PHP/MariaDB/Chromium fixture; owner and cashier menu permissions; no production or external services'
    (ROOT/'actualizaciones/1.8.0/QA-NAVEGACION-R14.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')

if __name__=='__main__':main()
