"""Check public downloads, using LFS object IDs when old binaries are not hydrated."""
import hashlib,pathlib,re,sys
ROOT=pathlib.Path(__file__).resolve().parents[1]/'descargas/1.8.0'
def main():
    entries=[];pointers=0
    for file in sorted(p for p in ROOT.iterdir() if p.is_file() and p.name!='SHA256SUMS-1.8.0.txt' and not p.name.startswith('.')):
        data=file.read_bytes()
        pointer=re.fullmatch(rb'version https://git-lfs.github.com/spec/v1\noid sha256:([a-f0-9]{64})\nsize [0-9]+\n',data)
        if pointer:pointers+=1
        digest=pointer[1].decode() if pointer else hashlib.sha256(data).hexdigest()
        entries.append(digest+'  '+file.name+'\n')
    expected=''.join(entries);target=ROOT/'SHA256SUMS-1.8.0.txt'
    if '--write' in sys.argv:target.write_text(expected)
    assert target.read_text()==expected,'Public checksum list is stale; run with --write after reviewing artifacts.'
    print('PASS',len(entries),'public checksums;',pointers,'historical LFS pointers checked as object metadata, not binary downloads')
if __name__=='__main__':main()
