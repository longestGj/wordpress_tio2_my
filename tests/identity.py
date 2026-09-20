import hashlib
import json
import urllib.request
from pathlib import Path
from bs4 import BeautifulSoup
root=Path(__file__).resolve().parents[1]
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
assert len(soup.select_one('meta[name="tio2-content-sha256"]')['content'])==64
print('Actual live theme/plugin fingerprint matches source:',expected)
