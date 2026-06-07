"""
Scraper adidas (adidas.pl / adidas.com) → CSV dla importera katalogu.

  python scrape_adidas.py                    # czyta adidas_urls.txt → adidas.csv
  python scrape_adidas.py --in moje.txt --out wynik.csv

adidas zwykle udostępnia dobre dane strukturalne (JSON-LD Product), więc działa
stabilniej niż Nike. Marka wymuszona = adidas.
"""
from site_base import run_product_scrape

# adidas agresywnie blokuje headless (Akamai) — DOMYŚLNIE odpalamy z widocznym oknem
# (headful) + stealth. Wtedy strona ładuje pełne JSON-LD/treść. Jeśli i tak puste, odśwież
# stronę ręcznie w oknie albo spróbuj ponownie. Marka wymuszona = adidas.
SITE = {
    'label':       'adidas',
    'brand':       'adidas',
    'default_in':  'adidas_urls.txt',
    'default_out': 'adidas.csv',
    'category':    '',
    'headless':    False,       # widoczne okno — kluczowe dla adidas
    'stealth':     True,
    'extra_wait':  2500,
    # Galeria adidas: różne ujęcia mają różne nazwy plików (rozmiar w query) → dedup po nazwie pliku
    'img_host':    'assets.adidas.com',
    'cookie_accept': 'button[data-testid="gdpr-accept-all-button"], '
                     'button[aria-label*="akcept" i], .gl-cta--primary',
    'selectors': {
        'name':        'h1[data-testid="product-title"], h1[class*="title"], h1',
        'price':       '[data-testid="main-price"], [class*="price"]',
        'image':       'img[data-testid="pdp-gallery-image"], picture img, img[srcset]',
        'description': '[data-testid="product-description-text"], [class*="description"]',
    },
}

if __name__ == '__main__':
    run_product_scrape(SITE)
