"""
Scraper Footshop (footshop.pl / footshop.com / footshop.eu) → CSV.

  python scrape_footshop.py                  # czyta footshop_urls.txt → footshop.csv
  python scrape_footshop.py --in moje.txt --out wynik.csv

Footshop to sklep WIELOMARKOWY — marki NIE wymuszamy, bierzemy ją z danych
strukturalnych produktu (JSON-LD). W links/source_url zapisze się "Footshop".
"""
from site_base import run_product_scrape

# Footshop NIE ma JSON-LD — dane lecą z OpenGraph (nazwa/zdjęcie/opis), a cena z CSS.
# Klasy są hashowane (np. _priceValue_1jppt_48), więc używamy selektorów [class*="..."],
# które są odporne na zmianę hasha przy kolejnych deployach sklepu.
SITE = {
    'label':       'Footshop',
    'brand':       '',          # multi-brand → markę ustaw ręcznie (--brand) lub w panelu
    'default_in':  'footshop_urls.txt',
    'default_out': 'footshop.csv',
    'category':    '',
    'headless':    True,        # Footshop działa na headless
    'stealth':     False,
    'extra_wait':  1500,
    # Galeria: to samo zdjęcie bywa jako 1752655.jpg / -full_product.jpg / -previewer.jpg → dedup po numerze
    'img_host':    'ftshp.digital/img/p/',
    'gallery_key': r'/(\d+)(?:-[a-z_]+)?\.(?:jpe?g|png|webp)',
    'cookie_accept': '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll, '
                     'button[id*="CookiebotDialogBodyButtonAccept"], [class*="cookie"] button',
    'selectors': {
        'name':        'h1',
        'price':       '[class*="priceValue"], [class*="retailPriceValue"]',
        'image':       'picture img, [class*="gallery"] img',
        'description': '[class*="productDescription"], [class*="description"]',
    },
}

if __name__ == '__main__':
    run_product_scrape(SITE)
