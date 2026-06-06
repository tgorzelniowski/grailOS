"""
Wspólne narzędzia scrapera GrailOS.
Pobiera stronę przez Playwright (headless Chromium), wyciąga dane strukturalne
(JSON-LD schema.org + meta OpenGraph) i zwraca słownik gotowy do CSV.
"""
import csv
import json
import re
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

# Ładny "store name" z domeny: www.end.com -> End, stockx.com -> Stockx
def store_name(url: str) -> str:
    host = urlparse(url).netloc.lower().replace('www.', '')
    base = host.split('.')[0] if host else 'Sklep'
    fix = {'end': 'END.', 'stockx': 'StockX', 'goat': 'GOAT', 'ssense': 'SSENSE',
           'farfetch': 'Farfetch', 'zalando': 'Zalando', 'nike': 'Nike', 'adidas': 'adidas'}
    return fix.get(base, base.capitalize())


def _clean(txt):
    if not txt:
        return ''
    txt = re.sub(r'<[^>]+>', ' ', str(txt))           # usuń HTML
    return re.sub(r'\s+', ' ', txt).strip()


def _price(val):
    if val is None:
        return ''
    s = re.sub(r'[^\d.,]', '', str(val)).replace(',', '.')
    try:
        return f"{float(s):.2f}"
    except ValueError:
        return ''


def get_page_data(page, url: str) -> dict:
    """Zwraca {jsonld:[str...], meta:{prop:content}, title, url}."""
    page.goto(url, wait_until='domcontentloaded', timeout=45000)
    try:
        page.wait_for_load_state('networkidle', timeout=8000)
    except Exception:
        pass
    try:
        page.wait_for_timeout(1500)  # daj JS chwilę na dorenderowanie
    except Exception:
        pass
    return page.evaluate("""() => {
        const ld = Array.from(document.querySelectorAll('script[type="application/ld+json"]'))
                        .map(s => s.textContent);
        const meta = {};
        document.querySelectorAll('meta[property],meta[name]').forEach(m => {
            const k = m.getAttribute('property') || m.getAttribute('name');
            if (k && !(k in meta)) meta[k] = m.getAttribute('content') || '';
        });
        // wszystkie og:image (galeria), nie tylko pierwszy
        const ogImages = Array.from(document.querySelectorAll('meta[property="og:image"]'))
                              .map(m => m.content).filter(Boolean);
        return { jsonld: ld, meta, ogImages, title: document.title, url: location.href };
    }""")


def _iter_products(node):
    """Rekurencyjnie wyłuskuje obiekty typu Product z JSON-LD (obsługa @graph/list)."""
    if isinstance(node, list):
        for n in node:
            yield from _iter_products(n)
    elif isinstance(node, dict):
        t = node.get('@type', '')
        types = t if isinstance(t, list) else [t]
        if any(str(x).lower() == 'product' for x in types):
            yield node
        if '@graph' in node:
            yield from _iter_products(node['@graph'])


def extract_from_jsonld(jsonld_list: list) -> dict:
    out = {}
    for raw in jsonld_list:
        try:
            data = json.loads(raw)
        except Exception:
            continue
        for p in _iter_products(data):
            out.setdefault('name', _clean(p.get('name')))
            # brand
            b = p.get('brand')
            if isinstance(b, dict):
                b = b.get('name')
            if b:
                out.setdefault('brand', _clean(b))
            # sku
            if p.get('sku') or p.get('mpn'):
                out.setdefault('sku', _clean(p.get('sku') or p.get('mpn')))
            # description
            if p.get('description'):
                out.setdefault('description', _clean(p.get('description')))
            # images
            img = p.get('image')
            imgs = img if isinstance(img, list) else ([img] if img else [])
            imgs = [i.get('url') if isinstance(i, dict) else i for i in imgs]
            imgs = [i for i in imgs if i]
            if imgs:
                out.setdefault('images', imgs)
            # kategoria
            if p.get('category'):
                cat = p['category']
                if isinstance(cat, list):
                    cat = cat[-1]
                out.setdefault('category', _clean(cat).split('/')[-1].split('>')[-1].strip())
            # offers / price
            offers = p.get('offers')
            if offers:
                of = offers[0] if isinstance(offers, list) else offers
                if isinstance(of, dict):
                    pr = of.get('price') or of.get('lowPrice') \
                         or (of.get('priceSpecification', {}) or {}).get('price')
                    if pr:
                        out.setdefault('price', _price(pr))
            if out.get('name') and out.get('price'):
                return out
    return out


def extract_breadcrumb_category(jsonld_list: list) -> str:
    """Kategoria z BreadcrumbList (ostatni element przed produktem)."""
    for raw in jsonld_list:
        try:
            data = json.loads(raw)
        except Exception:
            continue
        stack = data if isinstance(data, list) else [data]
        for node in stack:
            if not isinstance(node, dict):
                continue
            items = None
            if str(node.get('@type', '')).lower() == 'breadcrumblist':
                items = node.get('itemListElement')
            elif isinstance(node.get('@graph'), list):
                for g in node['@graph']:
                    if isinstance(g, dict) and str(g.get('@type','')).lower() == 'breadcrumblist':
                        items = g.get('itemListElement'); break
            if items and isinstance(items, list) and len(items) >= 2:
                names = []
                for it in items:
                    nm = it.get('name') if isinstance(it, dict) else None
                    if not nm and isinstance(it.get('item'), dict):
                        nm = it['item'].get('name')
                    if nm:
                        names.append(_clean(nm))
                if len(names) >= 2:
                    return names[-2]  # przedostatni = kategoria (ostatni to produkt)
    return ''


def extract_from_meta(meta: dict, og_images: list, title: str) -> dict:
    out = {}
    if meta.get('og:title') or title:
        out['name'] = _clean(meta.get('og:title') or title)
    if og_images:
        out['images'] = list(dict.fromkeys(og_images))   # dedup, zachowaj kolejność
    elif meta.get('og:image'):
        out['images'] = [meta['og:image']]
    if meta.get('og:description'):
        out['description'] = _clean(meta.get('og:description'))
    price = (meta.get('product:price:amount') or meta.get('og:price:amount')
             or meta.get('product:price') or '')
    if price:
        out['price'] = _price(price)
    return out


# Domeny sklepów markowych: domena -> marka (gdy strona nie podaje marki).
# Dla wielomarkowych retailerów (END., Zalando, StockX...) NIE zgadujemy marki.
BRAND_DOMAINS = {
    'nike': 'Nike', 'adidas': 'adidas', 'supreme': 'Supreme', 'bape': 'A BATHING APE',
    'palace': 'Palace', 'corteiz': 'Corteiz', 'chromehearts': 'Chrome Hearts',
    'cactusplantfleamarket': 'Cactus Plant Flea Market', 'denimtears': 'Denim Tears',
    'fearofgod': 'Fear of God', 'gallerydept': 'Gallery Dept', 'jordan': 'Air Jordan',
    'newbalance': 'New Balance', 'stussy': 'Stussy', 'carhartt': 'Carhartt',
}

def _brand_from_domain(url: str) -> str:
    host = urlparse(url).netloc.lower().replace('www.', '')
    base = host.split('.')[0] if host else ''
    return BRAND_DOMAINS.get(base, '')


def extract_product(page_data: dict, url: str, brand_override='', category_override='') -> dict:
    """Łączy JSON-LD + meta w jeden rekord produktu (CSV importera)."""
    jsonld = page_data.get('jsonld', [])
    d = extract_from_jsonld(jsonld)
    m = extract_from_meta(page_data.get('meta', {}), page_data.get('ogImages', []), page_data.get('title', ''))
    # JSON-LD ma pierwszeństwo; meta uzupełnia braki
    for k, v in m.items():
        if not d.get(k):
            d[k] = v
    # kategoria z breadcrumbów jako fallback
    if not d.get('category'):
        d['category'] = extract_breadcrumb_category(jsonld)

    name  = d.get('name', '')
    brand = brand_override or d.get('brand', '') or _brand_from_domain(url)
    price = d.get('price', '')
    images = d.get('images', []) or []
    main_img = images[0] if images else ''
    extra = '|'.join(images[1:]) if len(images) > 1 else ''
    link = f"{store_name(url)}|{url}|{price}" if price else f"{store_name(url)}|{url}|"

    return {
        'sku': d.get('sku', ''),
        'name': name,
        'brand': brand,
        'category': category_override or d.get('category', ''),
        'price': price,
        'image_url': main_img,
        'description': d.get('description', ''),
        'images': extra,
        'links': link,
        'source_url': url,
    }


PRODUCT_COLS = ['sku', 'name', 'brand', 'category', 'price', 'image_url',
                'description', 'images', 'links', 'source_url']
DROP_COLS    = ['name', 'brand', 'drop_date', 'image_url', 'link', 'description',
                'images', 'source_url']


def read_urls(path: str) -> list:
    urls = []
    with open(path, encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#'):
                urls.append(line)
    return urls


def write_csv(path: str, cols: list, rows: list, delimiter: str = ';'):
    # newline='' + utf-8-sig (BOM) — żeby Excel poprawnie otworzył polskie znaki
    with open(path, 'w', newline='', encoding='utf-8-sig') as f:
        w = csv.DictWriter(f, fieldnames=cols, delimiter=delimiter)
        w.writeheader()
        for r in rows:
            w.writerow({c: r.get(c, '') for c in cols})


def run_browser(handler):
    """Uruchamia Chromium i przekazuje (page) do handlera."""
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(
            user_agent=('Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        'AppleWebKit/537.36 (KHTML, like Gecko) '
                        'Chrome/120.0 Safari/537.36'),
            locale='pl-PL',
        )
        page = ctx.new_page()
        try:
            handler(page)
        finally:
            browser.close()
