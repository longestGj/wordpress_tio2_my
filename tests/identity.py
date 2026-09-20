import hashlib
import json
import urllib.request
import subprocess
import os
from pathlib import Path
from bs4 import BeautifulSoup
root=Path(__file__).resolve().parents[1]
out=root/os.environ.get('HOME_EVIDENCE_DIR','docs/verification/home')
entries=[]
for folder in ['wp-content/plugins/tio2-content','wp-content/themes/tio2-malaysia']:
    for path in sorted((root/folder).rglob('*')):
        if path.is_file(): entries.append((path.relative_to(root).as_posix(),hashlib.sha256(path.read_bytes()).hexdigest()))
entries.sort()
expected='wp-'+hashlib.sha256(''.join(f'{path}\0{digest}\n' for path,digest in entries).encode()).hexdigest()
response=urllib.request.urlopen('http://127.0.0.1:8232/')
soup=BeautifulSoup(response.read(),'html.parser')
assert response.headers['X-Site-Scope']=='tio2-my'
assert soup.select_one('meta[name="tio2-artifact"]')['content']==expected
snapshot=json.loads(subprocess.check_output(['docker','compose','run','--rm','wpcli','eval-file','/workspace/scripts/snapshot.php','export'],cwd=root,text=True,encoding='utf-8'))
content_hash=hashlib.sha256(json.dumps(snapshot['content'],ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
assert soup.select_one('meta[name="tio2-content-sha256"]')['content']==content_hash
assert snapshot['content']==json.loads((out/'content-restored.json').read_text(encoding='utf-8'))['content']
record=dict(build_id=expected,content_sha256=content_hash,site_scope='tio2-my',liveSourceAndRestoredSnapshotMatch=True)
(out/'identity.json').write_text(json.dumps(record,indent=2)+'\n',encoding='utf-8')
print('Actual live theme/plugin fingerprint matches source:',expected)
print('Live content fingerprint matches database and restored snapshot:',content_hash)
