# GrailOS — scraper (URL → CSV)

Narzędzie pobiera strony produktów/dropów (headless Chromium przez Playwright),
wyciąga dane (JSON-LD schema.org + OpenGraph, a dla trudnych stron selektory
per-sklep) i zapisuje **CSV zgodny z importerem** w panelu admina.

Jest **całkowicie odseparowane** od strony — nic nie zapisuje do bazy.
Produkuje tylko plik CSV, który potem ręcznie wgrywasz w panelu.

---

## 🚀 Krok po kroku — codzienne użycie (po wejściu)

> Zakładając, że masz już zrobioną instalację (sekcja niżej). To robisz **za każdym razem**.

### 1. Otwórz terminal w folderze `scraper`
W VS Code: kliknij prawym na folder `scraper` → **Open in Integrated Terminal**.
Albo w PowerShell ręcznie:
```powershell
cd c:\xampp\htdocs\grailos\scraper
```

### 2. Włącz środowisko Pythona (venv) — KONIECZNE przy każdym wejściu
```powershell
venv\Scripts\Activate.ps1
```
Jak się uda, na początku linii pojawi się `(venv)`. 
> Jeśli PowerShell krzyczy o uprawnieniach skryptów, raz uruchom:
> `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` i potwierdź `T`.

### 3. Wklej linki do pliku `urls.txt`
Otwórz `urls.txt`, wklej **po jednym linku produktu w każdej linii**. Puste linie i linie
zaczynające się od `#` są pomijane. Wzór: `urls.example.txt`.

```
https://www.nike.com/t/air-force-1-...
https://endclothing.com/pl/...
https://www.zalando.pl/...
```

### 4. Uruchom scraper
```powershell
python scrape_products.py --in urls.txt --out products.csv
```
W konsoli widać postęp:
```
[1/3] OK: Air Force 1 (599 zł)
[2/3] OK: Yeezy Slide (—)
[3/3] POMINIĘTO (brak nazwy): https://...
```
Powstaje plik **`products.csv`** w folderze `scraper`.

### 5. Wgraj CSV w panelu admina
Wejdź na stronę → **Admin → Katalog → Import CSV** → wybierz `products.csv` →
separator zostaw **Auto** → **Importuj**.

Gotowe. ✅

---

## ⚙️ Opcje uruchomienia (produkty)

```powershell
# wymuś markę i kategorię dla całej paczki (gdy strona ich nie podaje):
python scrape_products.py --in urls.txt --brand "Corteiz" --category sneakers

# inny plik wejścia/wyjścia:
python scrape_products.py --in nike.txt --out nike.csv

# przetwórz tylko pierwsze N linków (test):
python scrape_products.py --in urls.txt --limit 3

# zmień separator CSV (domyślnie średnik — pasuje do polskiego Excela):
python scrape_products.py --in urls.txt --delim comma
```

| Flaga | Domyślnie | Znaczenie |
|-------|-----------|-----------|
| `--in` | `urls.txt` | plik z linkami (1 URL na linię) |
| `--out` | `products.csv` | plik wynikowy CSV |
| `--brand` | (puste) | wymuś markę dla wszystkich |
| `--category` | (puste) | wymuś kategorię (`sneakers`, `hoodie`, `tee`, ...) |
| `--delim` | `semicolon` | separator: `semicolon` / `comma` / `tab` |
| `--limit` | `0` (bez limitu) | przetwórz tylko pierwsze N linków |

Kolumny CSV produktów: `sku, name, brand, category, price, image_url, description, images, links, source_url`.
Każdy wiersz ma ustawiony `links` = `Sklep|url|cena`, więc import z różnych sklepów
**scala linki** na tym samym produkcie (dedup po `sku` lub marka+nazwa).

---

## 🏷️ Dedykowane scrapery per-sklep (Nike / adidas / Footshop / Zalando)

Oprócz uniwersalnego `scrape_products.py` są cztery gotowe scrapery dostrojone pod konkretne
sklepy (wymuszona marka tam gdzie trzeba, obsługa bannera cookies, dłuższe czekanie na JS,
selektory CSS awaryjne). Każdy ma **własny plik z linkami** i **własny CSV wynikowy**:

| Scraper | Plik z linkami | Wynik | Marka |
|---------|----------------|-------|-------|
| `python scrape_nike.py`     | `nike_urls.txt`     | `nike.csv`     | wymuszona **Nike** |
| `python scrape_adidas.py`   | `adidas_urls.txt`   | `adidas.csv`   | wymuszona **adidas** |
| `python scrape_footshop.py` | `footshop_urls.txt` | `footshop.csv` | z danych produktu (multi-brand) |
| `python scrape_zalando.py`  | `zalando_urls.txt`  | `zalando.csv`  | z danych produktu (multi-brand) |

> ⚠️ **WAŻNE — gdzie wgrać CSV:** pliki z tych scraperów to **PRODUKTY** → wgrywasz je w
> **Admin → Katalog → Import CSV**. **NIE** w „Admin → Dropy → Import dropów" (tamten wymaga kolumn
> `name, brand, drop_date` i odrzuci produktowy CSV z błędem „CSV musi mieć kolumny: name, brand,
> drop_date"). Dropy robisz osobno przez `scrape_drops.py`.

**Jak używać (po aktywacji venv):**
1. Wklej linki do odpowiedniego pliku, np. `nike_urls.txt` (1 URL na linię).
2. Odpal scraper, np. `python scrape_nike.py`.
3. Wgraj powstały CSV w **Admin → Katalog → Import CSV** (separator zostaw **Auto**).

Wszystkie przyjmują te same flagi co `scrape_products.py` (`--in`, `--out`, `--category`,
`--brand`, `--delim`, `--limit`), np. test na 3 linkach: `python scrape_nike.py --limit 3`.

### Jak który sklep się zachowuje (sprawdzone na żywych stronach)

- **Nike** ✅ — dane w JSON-LD typu `ProductGroup`; wyciąga nazwę, markę, cenę, **EAN/GTIN**, SKU,
  opis i zdjęcie. Domyślnie headless + stealth. Gdyby blokował → dodaj `--headful`.
- **Footshop** ✅ — brak JSON-LD, więc nazwa/zdjęcie/opis z OpenGraph, a cena z selektora CSS
  (klasy hashowane → selektory `[class*="..."]`). To sklep wielomarkowy — markę ustaw `--brand "..."`
  albo w panelu po imporcie.
- **adidas / Zalando** ⚠️ — agresywnie blokują tryb bez okna. Dlatego **domyślnie uruchamiają się
  z WIDOCZNYM oknem przeglądarki** (headful) + stealth. Zostaw `python scrape_adidas.py` bez flag —
  otworzy się okno Chromium, poczeka i zbierze dane (głównie z JSON-LD). Jak chcesz spróbować bez
  okna: `--headless` (może nie zadziałać). Marka adidas jest wymuszona; w Zalando z JSON-LD.

> **Galeria zdjęć:** scrapery zbierają teraz **wszystkie** zdjęcia produktu z żywego DOM (nie tylko
> główne) — pierwsze ląduje w `image_url`, reszta w kolumnie `images` (oddzielone `|`). Zdjęcia są
> filtrowane po hoście CDN sklepu i dedupowane (np. Nike: po UUID assetu, Footshop: po numerze pliku),
> więc nie powielają się te same ujęcia w różnych rozmiarach. Gdyby trafiło kilka zdjęć „polecanych"
> produktów, usuń je w panelu (Admin → Katalog → edycja → zdjęcia).

> **EAN/GTIN:** jeśli sklep poda kod kreskowy (np. Nike), trafia on do kolumny `ean` w CSV.
> Importer katalogu używa EAN jako najmocniejszego klucza dedupu — ten sam but z różnych sklepów
> **scali się idealnie** (doklei drugi link zamiast tworzyć duplikat).

> **Selektory awaryjne** każdego sklepu są na górze jego pliku `scrape_<sklep>.py` — gdy sklep zmieni
> layout i dane zaczną wychodzić puste, podmień je tam (prawy klik na element → Zbadaj/Inspect).

Architektura: cała logika (przeglądarka, parsowanie, zapis CSV) siedzi w `site_base.py`;
pliki `scrape_<sklep>.py` to tylko konfiguracja (słownik `SITE`). Nowy sklep = skopiuj jeden
z nich, zmień `SITE` i gotowe.

---

## 📦 Dropy

```powershell
python scrape_drops.py --in drop_urls.txt --out drops.csv --brand "Nike"
```
Kolumny: `name, brand, drop_date, image_url, link, description, images, source_url`.

⚠️ **Uzupełnij `drop_date` w CSV przed importem** (np. `2026-06-20 10:00`) — data premiery
prawie nigdy nie jest w danych strony, trzeba ją wpisać ręcznie.

Wgraj w: **Admin → Dropy → Import dropów CSV**.

> Uwaga o dedupie dropów: produkt jest „scalany" tylko gdy zgadza się `source_url` **+** nazwa,
> albo marka+nazwa+data. Jeśli scrapujesz całą kolekcję z jednym wspólnym linkiem, upewnij się,
> że produkty mają **różne nazwy** — inaczej mogą się połączyć.

---

## 🛠️ Strony bez JSON-LD (Supreme, Corteiz, własne sklepy marek)

Niektóre strony (zwłaszcza Nike — silne zabezpieczenia anty-bot, dużo JS) wyciągają się słabo.
Wtedy dopisz selektory CSS w `site_adapters.py` w słowniku `SELECTORS` (klucz = fragment domeny):

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
Jak znaleźć selektor: na stronie produktu → prawy klik na element (np. nazwę) → **Zbadaj/Inspect** →
skopiuj klasę/tag. Adapter uzupełnia tylko **puste** pola — dane z JSON-LD mają pierwszeństwo.

---

## 🔁 Wiele sklepów / dokładanie linków

Ten sam but z innego sklepu → wrzuć link do `urls.txt` → uruchom ponownie → zaimportuj `products.csv`.
Importer **nie zrobi duplikatu** — dolepi nowy link do sklepu do istniejącego produktu i uzupełni
brakujące pola (dedup po `sku`, a jak go brak — po marka+nazwa).

---

## 📥 Instalacja (jednorazowo)

Wymaga **Pythona 3.9+** (sprawdź: `python --version`).

```powershell
cd c:\xampp\htdocs\grailos\scraper
python -m venv venv
venv\Scripts\Activate.ps1
pip install -r requirements.txt
playwright install chromium
```
Po tym wystarczy już tylko aktywować venv (krok 2 wyżej) przy każdym kolejnym użyciu.

---

## ⚖️ Uwagi prawne / etyka

- Respektuj `robots.txt` i regulaminy serwisów — scraping bywa sprzeczny z ToS.
- Nie uderzaj zbyt często (narzędzie chodzi sekwencyjnie, 1 strona naraz).
- Zdjęcia trafiają do CSV jako **zdalne URL-e** (hotlink). Jeśli chcesz je trzymać u siebie,
  pobierz osobno i podmień ścieżki.
- Marki bez programów afiliacyjnych (Supreme, Corteiz, Chrome Hearts) — scraper/ręcznie.
  Dla reszty lepsze są oficjalne **feedy afiliacyjne** (Admin → Katalog → Import z feedu).
