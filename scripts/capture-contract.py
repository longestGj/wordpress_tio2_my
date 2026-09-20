"""Read-only source-to-runtime comparison and local link dependency inventory."""
import collections
import hashlib
import json
import urllib.error
import urllib.request
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString

root=Path(__file__).resolve().parents[1]
out=root/'docs/verification/home'
source=Path('D:/23MySec/pages/home/04_planning/visual-designs/home-root-page-hero-v1.4/homepage-root-page-hero-preview-v1.4.html')
expected=BeautifulSoup(source.read_text(encoding='utf-8'),'html.parser')
response=urllib.request.urlopen('http://127.0.0.1:8232/')
html=response.read()
actual=BeautifulSoup(html,'html.parser')

def words(section):
    result=[]
    for node in section.descendants:
        if not isinstance(node,NavigableString) or not node.strip(): continue
        if node.parent.get('aria-hidden')=='true' or node.parent.find_parent(attrs={'aria-hidden':'true'}): continue
        result.append(str(node).strip())
    return result

modules=[]
for section in expected.select('main > section'):
    name=section['data-module']
    observed=actual.select_one(f'[data-module="{name}"]')
    text_match=words(section)==words(observed)
    links=[a['href'] for a in section.select('a')]
    match=links==[a['href'] for a in observed.select('a')]
    assert text_match and match, name
    modules.append(dict(module=name,orderedTextMatch=text_match,orderedLinksMatch=match,textNodes=len(words(section))))
selector='header a, .mobile-menu a, main a, footer a'
expected_links=collections.Counter(a['href'] for a in expected.select(selector))
actual_links=collections.Counter(a['href'] for a in actual.select(selector))
assert actual_links==expected_links,(actual_links,expected_links)
inventory=[]
for href,count in sorted(actual_links.items()):
    try:
        with urllib.request.urlopen('http://127.0.0.1:8232'+href) as r: status=r.status
    except urllib.error.HTTPError as e: status=e.code
    inventory.append(dict(path=href,instances=count,status=status,owner='D32 homepage' if href=='/' else 'Subsequent page owner',dependency=None if href=='/' else 'HOME-VU-DEP-03',interpretation='Implemented homepage' if href=='/' else 'Not implemented in this authorized batch; preserved approved href'))
assert all(row['status']==(200 if row['path']=='/' else 404) for row in inventory)
out.mkdir(parents=True,exist_ok=True)
(out/'source-runtime-parity.json').write_text(json.dumps(dict(source=str(source),sourceSha256=hashlib.sha256(source.read_bytes()).hexdigest(),modules=modules,linkInstances=sum(actual_links.values()),uniqueTargets=len(actual_links),linkMultisetMatch=True),ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
(out/'dependencies.json').write_text(json.dumps(inventory,indent=2)+'\n',encoding='utf-8')
(out/'homepage-response.html').write_bytes(html)
print('Approved source parity passed;',sum(actual_links.values()),'link instances;',len(actual_links),'targets; unimplemented targets return actual 404.')
