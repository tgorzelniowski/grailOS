"""
Adaptery per-sklep — dla stron bez JSON-LD / OpenGraph (np. własne sklepy marek
typu Corteiz, Supreme). Podajesz selektory CSS, a scraper uzupełni nimi
PUSTE pola (nie nadpisuje danych z JSON-LD).

Dodanie nowego sklepu: dopisz wpis do SELECTORS. Klucz = fragment domeny.
"""
import re
from urllib.parse import urlparse

SELECTORS = {
    # Przykład (odkomentuj i dopasuj selektory do realnej strony):
    # 'corteiz': {
    #     'name':        'h1.product-title',
    #     'price':       'span.price',
    #     'image':       'img.product__main-photo',   # bierze atrybut src
    #     'description': 'div.product__description',
    # },
}


def _text(page, q):
    try:
        el = page.query_selector(q)
        return el.inner_text().strip() if el else ''
    except Exception:
        return ''


def _attr(page, q, a):
    try:
        el = page.query_selector(q)
        return (el.get_attribute(a) or '').strip() if el else ''
    except Exception:
        return ''


def fill_with_selectors(page, url: str, row: dict) -> dict:
    """Uzupełnia puste pola row na podstawie selektorów dla danej domeny."""
    host = urlparse(url).netloc.lower().replace('www.', '')
    sel = next((s for dom, s in SELECTORS.items() if dom in host), None)
    if not sel:
        return row

    if not row.get('name') and sel.get('name'):
        row['name'] = _text(page, sel['name'])
    if not row.get('description') and sel.get('description'):
        row['description'] = _text(page, sel['description'])
    if not row.get('image_url') and sel.get('image'):
        row['image_url'] = _attr(page, sel['image'], 'src')
    if not row.get('price') and sel.get('price'):
        raw = re.sub(r'[^\d.,]', '', _text(page, sel['price'])).replace(',', '.')
        try:
            row['price'] = f"{float(raw):.2f}"
        except ValueError:
            pass
    return row
