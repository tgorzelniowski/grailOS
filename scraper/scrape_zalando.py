"""
Scraper Zalando (zalando.pl i inne domeny) → CSV.

  python scrape_zalando.py                   # czyta zalando_urls.txt → zalando.csv
  python scrape_zalando.py --in moje.txt --out wynik.csv

Zalando to sklep WIELOMARKOWY — marki NIE wymuszamy (bierzemy z JSON-LD).
Klasy CSS Zalando są hashowane i często się zmieniają, więc opieramy się głównie
na JSON-LD/OpenGraph; selektory niżej to tylko awaryjny fallback.
"""
from site_base import run_product_scrape

# Zalando blokuje headless (timeouty) — DOMYŚLNIE headful + stealth. Marka i większość
# danych są w JSON-LD Product (ładuje się przy realnej przeglądarce). Klasy CSS są hashowane,
# więc selektory to tylko fallback.
SITE = {
    'label':       'Zalando',
    'brand':       '',          # multi-brand → marka z JSON-LD
    'default_in':  'zalando_urls.txt',
    'default_out': 'zalando.csv',
    'category':    '',
    'headless':    False,       # widoczne okno — kluczowe dla Zalando
    'stealth':     True,
    'extra_wait':  2500,
    # Galeria Zalando: CDN ztat.net, rozmiar w ?imwidth= → dedup po nazwie pliku (bez query)
    'img_host':    'ztat.net',
    'cookie_accept': 'button[data-testid="uc-accept-all-button"], #uc-btn-accept-banner, '
                     'button[aria-label*="akcept" i]',
    'selectors': {
        'name':        'h1',
        'price':       '[data-testid="price"], [class*="price"]',
        'image':       'picture img, img[data-testid="product-image"]',
        'description': '[data-testid="pdp-accordion-details"], [class*="description"]',
    },
}

if __name__ == '__main__':
    run_product_scrape(SITE)
