"""One-time authoring import; never called by WordPress or a visitor request.

The prototype is a design/content source. Emit editable initial values and a
technical field schema, with escaped PHP template slots when explicitly asked.
"""
import argparse
import hashlib
import json
import re
import shutil
from collections import defaultdict
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString

ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path('D:/23MySec')
HTML = SOURCE / 'pages/home/04_planning/visual-designs/home-root-page-hero-v1.4/homepage-root-page-hero-preview-v1.4.html'
THEME = ROOT / 'wp-content/themes/tio2-malaysia'
PLUGIN = ROOT / 'wp-content/plugins/tio2-content'
parser = argparse.ArgumentParser()
parser.add_argument('--templates', action='store_true')
args = parser.parse_args()
soup = BeautifulSoup(HTML.read_text(encoding='utf-8'), 'html.parser')
fields, values, tokens, mapping = {}, {}, {}, []

def field(key, value, kind='text', group=None, label=None, **extra):
    assert key not in fields, key
    fields[key] = dict(type=kind, group=group or key.split('.')[0], label=label or key.replace('.', ' / '), **extra)
    values[key] = value
    return key

def token(code):
    key = f'TIO2SLOT{len(tokens):04}END'
    tokens[key] = code
    return key

for section in soup.select('main > section'):
    group = section['data-module']
    counts = defaultdict(int)
    for node in list(section.descendants):
        if not isinstance(node, NavigableString) or not node.strip():
            continue
        parent = node.parent
        if parent.find_parent(attrs={'aria-hidden': 'true'}) or parent.get('aria-hidden') == 'true':
            continue
        role = {'h1': 'heading', 'h2': 'heading', 'h3': 'card-title', 'p': 'paragraph', 'a': 'link-label', 'summary': 'group-title'}.get(parent.name, parent.name)
        if 'eyebrow' in parent.get('class', []): role = 'eyebrow'
        if parent.parent and 'grades' in parent.parent.get('class', []): role = 'grade'
        counts[role] += 1
        key = field(f'{group}.{role}.{counts[role]}', str(node).strip())
        node.replace_with(token(f"<?php echo esc_html(tio2_field('{key}')); ?>"))
    for i, link in enumerate(section.select('a[href]'), 1):
        key = field(f'{group}.link.{i}', link['href'], 'path')
        link['href'] = token(f"<?php echo esc_url(tio2_field('{key}')); ?>")
    if group == 'hero':
        field('hero.image', '0', 'image', label='Hero photograph (WordPress media ID)')
        field('hero.image-alt', '', 'alt', label='Hero image alternative text (empty for decorative image)')
        img = section.select_one('img')
        img['src'] = token("<?php echo esc_url(tio2_image_url('hero.image')); ?>")
        img['alt'] = token("<?php echo esc_attr(tio2_field('hero.image-alt')); ?>")
        img['fetchpriority'] = 'high'
        img['decoding'] = 'async'
        img.parent.attrs.pop('aria-hidden', None)
    for el in section.select('[data-tablet-visible], [data-visible-min]'):
        el.attrs.pop('data-tablet-visible', None)
        el.attrs.pop('data-visible-min', None)
    if args.templates:
        output = str(section)
        for key, code in tokens.items(): output = output.replace(key, code)
        target = THEME / 'template-parts' / f'{group}.php'
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text("<?php defined('ABSPATH') || exit; ?>\n" + output + '\n', encoding='utf-8')

for i, a in enumerate(soup.select('.desktop-nav a') + [soup.select_one('.header-rfq')]):
    field(f'header.nav.{i}.label', a.get_text(strip=True), group='header')
    field(f'header.nav.{i}.href', a['href'], 'path', group='header')
field('header.logo', 'assets/brand/logo-primary.svg', 'asset', choices=['assets/brand/logo-primary.svg'])
field('footer.logo', 'assets/brand/logo-reverse.svg', 'asset', choices=['assets/brand/logo-reverse.svg'])
field('footer.description', soup.select_one('.footer-brand p').get_text())
for i, col in enumerate(soup.select('.footer-col')):
    field(f'footer.column.{i}.title', col.h2.get_text())
    for j, a in enumerate(col.select('a')):
        field(f'footer.column.{i}.link.{j}.label', a.get_text())
        field(f'footer.column.{i}.link.{j}.href', a['href'], 'path')
for i, a in enumerate(soup.select('.legal-utilities a')):
    field(f'footer.legal.{i}.label', a.get_text())
    field(f'footer.legal.{i}.href', a['href'], 'path')
field('footer.cookie-label', 'Cookie Settings')
field('footer.copyright', soup.select_one('.copyright').get_text())
field('seo.title', 'Malaysia Titanium Dioxide Supplier | TiO₂ Malaysia')
field('seo.description', 'Explore titanium dioxide grades, applications, destination markets and document request paths through TiO₂ Malaysia for international industrial buyers.')
field('seo.share-title', values['seo.title'])
field('seo.share-description', values['seo.description'])

for folder in [PLUGIN, ROOT/'content', ROOT/'docs', THEME/'assets/brand', THEME/'assets/fonts', ROOT/'content/media']:
    folder.mkdir(parents=True, exist_ok=True)
(PLUGIN/'schema.json').write_text(json.dumps(fields, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')
(ROOT/'content/initial-home.json').write_text(json.dumps(dict(site_scope='tio2-my', schema_version=1, fields=values), ensure_ascii=False, indent=2)+'\n', encoding='utf-8')

assets = {
    'brand/logo/candidates/v0.1/tio2-malaysia-primary-horizontal-v0.1.svg': THEME/'assets/brand/logo-primary.svg',
    'brand/logo/candidates/v0.1/tio2-malaysia-reverse-monochrome-v0.1.svg': THEME/'assets/brand/logo-reverse.svg',
    'brand/logo/candidates/v0.1/tio2-malaysia-symbol-v0.1.svg': THEME/'assets/brand/symbol.svg',
    'brand/logo/candidates/v0.1/tio2-malaysia-favicon-safe-v0.1.svg': THEME/'assets/brand/favicon.svg',
    'pages/home/04_planning/visual-designs/assets/homepage-hero-tio2-material-v0.6.png': ROOT/'content/media/hero.png',
    'pages/home/04_planning/visual-designs/home-applications-aligned-v1.1/dependencies/Inter-Variable.ttf': THEME/'assets/fonts/Inter-Variable.ttf',
}
for origin, target in assets.items():
    shutil.copyfile(SOURCE/origin, target)
    mapping.append(dict(source=str(SOURCE/origin), target=target.relative_to(ROOT).as_posix(), sha256=hashlib.sha256(target.read_bytes()).hexdigest()))
(ROOT/'docs/source-map.json').write_text(json.dumps(dict(prototype=str(HTML), prototype_sha256=hashlib.sha256(HTML.read_bytes()).hexdigest(), assets=mapping, field_count=len(fields)), indent=2)+'\n', encoding='utf-8')
if args.templates:
    css = soup.style.string.strip()
    css = re.sub(r'url\("[^\"]*Inter-Variable.ttf"\)', 'url("fonts/Inter-Variable.ttf")', css)
    css = css.replace('font-display:block', 'font-display:swap').replace('overflow-x:hidden;', '')
    (THEME/'assets/design.css').write_text('/* Adapted approved V1.1 body / V1.4 Hero design. */\n'+css+'\n', encoding='utf-8')
print(json.dumps(dict(fields=len(fields), assets=len(assets), templates=args.templates)))
