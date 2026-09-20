import hashlib
import json
import urllib.request
import subprocess
from pathlib import Path
from bs4 import BeautifulSoup
from support.runtime import runtime_settings

root=Path(__file__).resolve().parents[1]
runtime=runtime_settings()
entries=[]
for folder in ['wp-content/plugins/tio2-content','wp-content/themes/tio2-malaysia']:
    for path in sorted((root/folder).rglob('*')):
        if path.is_file(): entries.append((path.relative_to(root).as_posix(),hashlib.sha256(path.read_bytes()).hexdigest()))
entries.sort()
expected='wp-'+hashlib.sha256(''.join(f'{path}\0{digest}\n' for path,digest in entries).encode()).hexdigest()
response=urllib.request.urlopen(runtime['base_url']+'/')
soup=BeautifulSoup(response.read(),'html.parser')
assert response.headers['X-Site-Scope']=='tio2-my'
if runtime['release']:
    assert response.headers['X-Tio2-Release']==runtime['release']
assert soup.select_one('meta[name="tio2-artifact"]')['content']==expected
snapshot=json.loads(subprocess.check_output(['docker','compose','--env-file',runtime['env_file'],'-f',runtime['compose_file'],'run','--rm','wpcli','eval-file','/workspace/scripts/snapshot.php','export'],cwd=root,text=True,encoding='utf-8'))
content_hash=hashlib.sha256(json.dumps(snapshot['content'],ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
assert soup.select_one('meta[name="tio2-content-sha256"]')['content']==content_hash
rfq_response=urllib.request.urlopen(runtime['base_url']+'/request-a-quote/')
rfq_soup=BeautifulSoup(rfq_response.read(),'html.parser')
rfq_hash=hashlib.sha256(json.dumps(snapshot['rfq_content'],ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
assert rfq_soup.select_one('meta[name="tio2-artifact"]')['content']==expected
assert rfq_soup.select_one('meta[name="tio2-content-sha256"]')['content']==content_hash
assert rfq_soup.select_one('meta[name="tio2-rfq-content-sha256"]')['content']==rfq_hash
record=dict(build_id=expected,content_sha256=content_hash,rfq_content_sha256=rfq_hash,site_scope='tio2-my',liveSourceAndDatabaseMatch=True)
(Path(runtime['output_dir'])/'identity.json').write_text(json.dumps(record,indent=2)+'\n',encoding='utf-8')
print('Actual live theme/plugin fingerprint matches source:',expected)
print('Live content fingerprint matches database snapshot:',content_hash)
print('Live RFQ content fingerprint matches database snapshot:',rfq_hash)
