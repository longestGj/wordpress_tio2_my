import json
import urllib.request
from pathlib import Path
from bs4 import BeautifulSoup

root=Path(__file__).resolve().parents[1]
response=urllib.request.urlopen('http://127.0.0.1:8232/')
html=response.read().decode()
soup=BeautifulSoup(html,'html.parser')
assert response.status==200
assert soup.html['lang']=='en'
assert [x.get_text() for x in soup.select('h1')]==['Malaysia Titanium Dioxide for Industrial Buyers']
assert len(soup.select('link[rel="canonical"]'))==1
assert soup.select_one('link[rel="canonical"]')['href']=='https://tio2products.com/'
assert soup.select_one('meta[property="og:url"]')['content']=='https://tio2products.com/'
assert 'tio2malaysia.com' not in html
assert len(soup.select('title'))==1
assert soup.title.string=='Malaysia Titanium Dioxide Supplier | TiO₂ Malaysia'
assert 'noindex' in soup.select_one('meta[name="robots"]')['content']
assert [x['data-module'] for x in soup.select('main > section')]==['hero','start-here','markets','products','applications','company','documents','resources','page-rfq']
assert len(soup.select('.grades span'))==14
assert not soup.select('.grades a')
assert len(set(x.get_text() for x in soup.select('.grades span')))==14
assert len(soup.select('script[type="application/ld+json"]'))==1
graph=json.loads(soup.select_one('script[type="application/ld+json"]').string)['@graph']
assert [x['@type'] for x in graph]==['WebSite','WebPage','Organization','Brand','Product']
assert graph[4]['manufacturer']=={'@id':graph[2]['@id']}
assert 'brand' not in graph[2]
assert len(graph[1]['about'])==3
assert graph[1]['isPartOf']=={'@id':graph[0]['@id']}
assert graph[0]['publisher']=={'@id':graph[2]['@id']}
assert graph[4]['brand']=={'@id':graph[3]['@id']}
assert len(soup.select('.legal-utilities a'))==3
assert soup.select_one('.legal-utilities button').get_text()=='Cookie Settings'
assert 'googletagmanager' not in html and 'google-analytics' not in html
assert soup.select_one('.hero-media img')['alt']==''
assert soup.select_one('.hero-media img')['src'].startswith('http://127.0.0.1:8232/wp-content/uploads/')
seed=json.loads((root/'content/initial-home.json').read_text(encoding='utf-8'))['fields']
text=soup.get_text(' ',strip=True)
for key,value in seed.items():
    if key.startswith('seo.') or key.endswith('.logo') or key in ['hero.image','hero.image-alt']: continue
    if '.href' in key or '.link.' in key and key.rsplit('.',1)[1].isdigit():
        assert soup.select(f'a[href="{value}"]'), (key,value)
    else:
        assert value in text, (key,value)
print('HTTP content, editable seed, links, identity and SEO assertions passed')
