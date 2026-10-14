"""Publish the reviewed source commit and public downloads; keep signing material private."""
import base64, json, os, pathlib, subprocess

ROOT = pathlib.Path(__file__).resolve().parents[1]

def main():
    token = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN')
    if not token:
        raise RuntimeError('GitHub authorization unavailable; files remain local.')
    env = os.environ.copy()
    for name in ('GIT_TRACE', 'GIT_CURL_VERBOSE', 'GIT_TRACE_CURL'):
        env.pop(name, None)
    env.update(GIT_CONFIG_COUNT='1',
               GIT_CONFIG_KEY_0='http.https://github.com/.extraheader',
               GIT_CONFIG_VALUE_0='AUTHORIZATION: basic ' +
               base64.b64encode(('x-access-token:' + token).encode()).decode())

    def git(*args):
        result = subprocess.run(['git', *args], cwd=ROOT, env=env, capture_output=True)
        if result.returncode:
            raise RuntimeError('Git publication failed at ' + args[0] +
                               '; credentials and response details were not printed.')
        return result.stdout.decode().strip()

    if git('status', '--porcelain'):
        raise RuntimeError('Commit reviewed deliverables before publishing.')
    head = git('rev-parse', 'HEAD')
    git('fetch', 'origin', 'main')
    ancestor = subprocess.run(['git', 'merge-base', '--is-ancestor', 'origin/main', head],
                              cwd=ROOT, capture_output=True)
    if ancestor.returncode:
        raise RuntimeError('Remote main changed; it was not overwritten.')
    print('Uploading Windows installer through Git LFS.', flush=True)
    git('lfs', 'push', 'origin', 'HEAD')
    print('Windows object uploaded; publishing download folder.', flush=True)
    git('push', 'origin', 'HEAD:refs/heads/qa-1.8.0-en-proceso')
    git('push', 'origin', 'HEAD:refs/heads/main')
    print(json.dumps({'source_commit': head,
                      'downloads_url': 'https://github.com/fisitalmultiservicios-svg/FISITAAP/tree/main/descargas/1.8.0'}), flush=True)

if __name__ == '__main__':
    main()
