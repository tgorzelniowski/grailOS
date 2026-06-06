# GrailOS — scraper (URL → CSV)

Narzędzie pobiera strony produktów/dropów (headless Chromium przez Playwright),
wyciąga dane (JSON-LD schema.org + OpenGraph, a dla trudnych stron selektory
per-sklep) i zapisuje **CSV zgodny z importerem** w panelu admina.

To narzędzie jest **całkowicie odseparowane** od strony — nic nie zapisuje do bazy.
Tylko produkuje plik CSV, który ręcznie wgrywasz w panelu.

## Instalacja (jednorazowo)

Wymaga Pythona 3.9+.

```bash
cd scraper
python -m venv venv
venv\Scripts\activate        # Windows (PowerShell: venv\Scripts\Activate.ps1)
pip install -r requirements.txt
playwright install chromium
```

## Użycie — produkty

1. Wklej linki produktów do `urls.txt` (po jednym w linii; wzór: `urls.example.txt`).
2. Uruchom:

```bash
python scrape_products.py --in urls.txt --out products.csv
# opcjonalnie wymuś markę/kategorię dla całej paczki:
python scrape_products.py --in urls.txt --brand "Corteiz" --category sneakers
```

3. Wgraj `products.csv` w: **Admin → Katalog → Import CSV**.

Kolumny CSV: `sku, name, brand, category, price, image_url, description, images, links, source_url`.
Każdy wiersz ma automatycznie ustawiony `links` = `Sklep|url|cena`, więc import z różnych
sklepów **scala linki** na tym samym produkcie (dedup po sku lub marka+nazwa).

## Użycie — dropy

```bash
python scrape_drops.py --in drop_urls.txt --out drops.csv --brand "Nike"
```

Kolumny: `name, brand, drop_date, image_url, link, description, images, source_url`.
**Uzupełnij `drop_date`** w CSV przed importem (np. `2026-06-20 10:00`) — data rzadko
jest w danych strukturalnych strony. Wgraj w: **Admin → Dropy → Import dropów CSV**.

## Strony bez JSON-LD (Supreme, Corteiz, własne sklepy marek)

Dopisz selektory CSS w `site_adapters.py` w słowniku `SELECTORS` (klucz = fragment domeny):

```python
SELECTORS = {
    'corteiz': {
        'name':        'h1.product-title',
        'price':       'span.price',
        'image':       'img.product__main-photo',
        'description': 'div.product__description',
    },
}
```

Adapter uzupełnia tylko **puste** pola — dane z JSON-LD mają pierwszeństwo.

## Workflow przy wielu sklepach / kolejnych linkach

Ten sam but z innego sklepu → osobny `urls.txt` → kolejny `products.csv` → ponowny import.
Importer nie zrobi duplikatu — **dolepi nowy link do sklepu** do istniejącego produktu
i uzupełni brakujące pola.

## Uwagi prawne / etyka

- Respektuj `robots.txt` i regulaminy serwisów. Scraping bywa sprzeczny z ToS.
- Nie uderzaj zbyt często (narzędzie chodzi sekwencyjnie, 1 strona naraz).
- Zdjęcia trafiają do CSV jako **zdalne URL-e** (hotlink). Jeśli chcesz je trzymać u siebie,
  pobierz je osobno i podmień ścieżki (przyszła iteracja).
