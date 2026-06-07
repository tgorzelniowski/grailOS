"""
Scraper Nike (nike.com / nike.pl) → CSV dla importera katalogu.

  python scrape_nike.py                      # czyta nike_urls.txt → nike.csv
  python scrape_nike.py --in moje.txt --out wynik.csv --limit 5

UWAGA: Nike ma silną ochronę anty-bot i renderuje dużo przez JS. Bywa, że trzeba
odpalić kilka razy albo zaktualizować selektory niżej. Marka wymuszona = Nike.
Linie zaczynające się od Air Jordan zostaw jako Nike albo użyj scrape_products.py
z --brand "Air Jordan" dla osobnej paczki Jordanów.
"""
from site_base import run_product_scrape

# Nike: JSON-LD jest typu ProductGroup (warianty mają cenę + gtin) — common.py już to
# obsługuje, więc nazwa/marka/cena/EAN/opis lecą z danych strukturalnych, zdjęcie z OpenGraph.
# Selektory poniżej to tylko awaryjny fallback.
SITE = {
    'label':       'Nike',
    'brand':       'Nike',
    'default_in':  'nike_urls.txt',
    'default_out': 'nike.csv',
    'category':    '',          # zostaw z breadcrumbów (Nike sprzedaje buty i odzież)
    'headless':    True,        # Nike zwykle przechodzi na headless; przy blokadzie użyj --headful
    'stealth':     True,        # ukryj automatyzację (Nike potrafi blokować)
    'extra_wait':  2500,
    # Galeria: zdjęcia Nike to wiele rozmiarów tego samego assetu — dedup po UUID przed nazwą pliku
    'img_host':    'static.nike.com',
    'gallery_key': r'([0-9a-f-]{36})/[^/?]+\.(?:png|jpe?g|webp)',
    'img_exclude': ['/videos/'],                       # nie zbieraj klatek wideo
    'img_rewrite': (r't_(?:PDP|web_pdp)_\d+_v\d+', 't_PDP_1728_v1'),  # miniatury → duży rozmiar
    'gallery_limit': 10,
    'cookie_accept': 'button[data-testid="dialog-accept-button"], #hf_cookie_text_cookieAccept',
    'selectors': {
        'name':        'h1#pdp_product_title, h1[data-testid="product_title"]',
        'price':       '[data-testid="currentPrice-container"], [data-test="product-price"]',
        'image':       'img[data-testid="HeroImg"], picture img',
        'description': '.description-preview, [data-testid="product-description"]',
    },
}

if __name__ == '__main__':
    run_product_scrape(SITE)
