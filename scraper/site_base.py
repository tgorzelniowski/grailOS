"""
Wspólny silnik dla scraperów per-sklep (Nike, adidas, Footshop, Zalando...).

Każdy scraper definiuje słownik SITE i woła run_product_scrape(SITE).
Priorytet danych: JSON-LD (schema.org Product) > OpenGraph > selektory CSS (fallback).
Selektory CSS na dużych sklepach bywają zmienne (hashowane klasy) — traktuj je jako
pomoc na braki, a nie główne źródło. Jeśli przestaną działać, podmień je w pliku sklepu.

Pola SITE:
  label        : nazwa do logów (np. "Nike")
  brand        : marka wymuszona dla każdego wiersza ('' = nie wymuszaj, bierz z JSON-LD)
  default_in   : domyślny plik z linkami (np. "nike_urls.txt")
  default_out  : domyślny plik CSV (np. "nike.csv")
  category     : domyślna kategoria gdy strona jej nie poda ('' = brak)
  extra_wait   : ile ms dodatkowo czekać na doładowanie JS (np. 1500)
  cookie_accept: selektor przycisku zgody na cookies (klik przed odczytem selektorów)
  selectors    : {name, price, image, description} — fallback CSS dla pustych pól
"""
import argparse
from common import (run_browser, get_page_data, extract_product,
                    read_urls, write_csv, PRODUCT_COLS)
from site_adapters import _text, _attr
import re


def _best_img_url(el):
    """Najlepszy URL z elementu <img>/<source>: srcset (największy) > src > data-src."""
    for a in ('srcset', 'data-srcset'):
        ss = el.get_attribute(a)
        if ss:
            parts = [p.strip().split(' ')[0] for p in ss.split(',') if p.strip()]
            if parts:
                return parts[-1]  # ostatni kandydat = zwykle największy
    for a in ('src', 'data-src', 'data-original', 'content'):
        v = el.get_attribute(a)
        if v:
            return v
    return ''


def _img_key(url, key_regex):
    """Klucz deduplikacji zdjęcia. key_regex (1 grupa) np. dla Nike (UUID) / Footshop (id)."""
    import re as _re
    if key_regex:
        m = _re.search(key_regex, url)
        return m.group(1) if m else None      # brak dopasowania → odrzuć (śmieci)
    return url.split('?')[0].rsplit('/', 1)[-1]  # domyślnie: nazwa pliku bez parametrów


# Czy element <img>/<source> siedzi w linku do INNEGO produktu (sekcja „polecane")?
_LINKED_JS = ("e => { const a = e.closest('a[href]'); if (!a) return false; "
              "const h = a.getAttribute('href') || ''; "
              "return !!h && !h.startsWith('#') && !h.startsWith('javascript'); }")


def _collect_gallery(page, site):
    """Zbiera zdjęcia produktu z żywego DOM: filtr po hoście, pomija linkowane miniatury
    (polecane produkty), wyklucza wideo, dedup po kluczu i opcjonalnie podbija rozmiar."""
    import re as _re
    host      = site.get('img_host', '')
    key_regex = site.get('gallery_key')
    limit     = site.get('gallery_limit', 12)
    exclude   = site.get('img_exclude', [])
    rewrite   = site.get('img_rewrite')          # (regex, replacement) — np. podbicie rozmiaru Nike
    skip_linked = site.get('skip_linked', True)

    try:
        els = page.query_selector_all('img, source')
    except Exception:
        return []
    seen, out = set(), []
    for el in els:
        u = _best_img_url(el)
        if not u or not u.startswith('http'):
            continue
        if host and host not in u:
            continue
        if any(x in u for x in exclude):
            continue
        if skip_linked:
            try:
                if el.evaluate(_LINKED_JS):
                    continue
            except Exception:
                pass
        k = _img_key(u, key_regex)
        if k is None or k in seen:
            continue
        if rewrite:
            u = _re.sub(rewrite[0], rewrite[1], u)
        seen.add(k)
        out.append(u)
        if len(out) >= limit:
            break
    return out


def _merge_images(main, extra_str, gallery, key_regex):
    """Łączy główne+dodatkowe+galerię w (main, 'extra1|extra2...'), dedup po kluczu."""
    existing = ([main] if main else []) + [x for x in (extra_str or '').split('|') if x]
    seen = set()
    out = []
    for u in existing + gallery:
        if not u:
            continue
        k = _img_key(u, key_regex) or u
        if k in seen:
            continue
        seen.add(k)
        out.append(u)
    return (out[0] if out else ''), '|'.join(out[1:])


def _apply_site_selectors(page, row, selectors):
    """Uzupełnia PUSTE pola row selektorami CSS danego sklepu."""
    if not selectors:
        return row
    if not row.get('name') and selectors.get('name'):
        row['name'] = _text(page, selectors['name'])
    if not row.get('description') and selectors.get('description'):
        row['description'] = _text(page, selectors['description'])
    if not row.get('image_url') and selectors.get('image'):
        src = _attr(page, selectors['image'], 'src') or _attr(page, selectors['image'], 'content')
        if src:
            row['image_url'] = src
    if not row.get('price') and selectors.get('price'):
        raw = re.sub(r'[^\d.,]', '', _text(page, selectors['price'])).replace(',', '.')
        try:
            row['price'] = f"{float(raw):.2f}"
        except ValueError:
            pass
    return row


def run_product_scrape(site: dict):
    ap = argparse.ArgumentParser(description=f"Scraper {site['label']} (URL → CSV)")
    ap.add_argument('--in',  dest='infile',  default=site.get('default_in', 'urls.txt'))
    ap.add_argument('--out', dest='outfile', default=site.get('default_out', 'products.csv'))
    ap.add_argument('--category', default='', help='Wymuś kategorię dla całej paczki')
    ap.add_argument('--brand',    default='', help='Nadpisz markę (domyślnie wg sklepu)')
    ap.add_argument('--delim', default='semicolon', choices=['semicolon', 'comma', 'tab'])
    ap.add_argument('--limit', type=int, default=0, help='Przetwórz tylko pierwsze N linków')
    ap.add_argument('--headful', action='store_true',
                    help='Widoczne okno przeglądarki (pomaga na adidas/Zalando)')
    ap.add_argument('--headless', action='store_true',
                    help='Wymuś tryb bez okna (nawet jeśli sklep domyślnie używa headful)')
    args = ap.parse_args()

    headless = site.get('headless', True)
    if args.headful:  headless = False
    if args.headless: headless = True
    stealth = site.get('stealth', False)

    delim = {'semicolon': ';', 'comma': ',', 'tab': '\t'}[args.delim]
    brand = args.brand or site.get('brand', '')
    category = args.category or site.get('category', '')

    urls = read_urls(args.infile)
    if args.limit:
        urls = urls[:args.limit]
    if not urls:
        print(f"Brak linków w {args.infile}. Wklej adresy produktów (1 na linię).")
        return

    rows = []

    def handler(page):
        for i, url in enumerate(urls, 1):
            try:
                data = get_page_data(page, url)
                # zamknij baner cookies (odsłania ceny/opis dla selektorów)
                if site.get('cookie_accept'):
                    try:
                        btn = page.query_selector(site['cookie_accept'])
                        if btn:
                            btn.click(timeout=2000)
                            page.wait_for_timeout(400)
                    except Exception:
                        pass
                # dodatkowe czekanie na JS (Nike/Zalando renderują dynamicznie)
                if site.get('extra_wait'):
                    try:
                        page.wait_for_timeout(site['extra_wait'])
                    except Exception:
                        pass

                row = extract_product(data, url, brand, category)
                _apply_site_selectors(page, row, site.get('selectors', {}))

                # Pełna galeria z żywego DOM (poza jednym zdjęciem z JSON-LD/OG)
                if site.get('img_host') or site.get('gallery_key'):
                    gal = _collect_gallery(page, site)
                    if gal:
                        main, extra = _merge_images(row['image_url'], row['images'], gal,
                                                    site.get('gallery_key'))
                        row['image_url'], row['images'] = main, extra

                # Cena bywa uzupełniana selektorem PO zbudowaniu links — odśwież cenę w linku
                if row.get('links') and row.get('price'):
                    row['links'] = row['links'].rsplit('|', 1)[0] + '|' + row['price']

                if not row['name']:
                    print(f"[{i}/{len(urls)}] POMINIĘTO (brak nazwy): {url}")
                    continue
                # Wykryj stronę-wyzwanie anty-bot: nazwa = sama marka/sklep i brak ceny
                nm = row['name'].strip().lower()
                if nm in (site['label'].lower(), (brand or '').lower()) and not row['price'] and not row['description']:
                    print(f"[{i}/{len(urls)}] ZABLOKOWANE (strona-wyzwanie anty-bot) — "
                          f"uruchom z widocznym oknem (bez --headless): {url}")
                    continue
                rows.append(row)
                print(f"[{i}/{len(urls)}] OK: {row['name']} ({row['price'] or '—'})")
            except Exception as e:
                print(f"[{i}/{len(urls)}] BŁĄD: {url} -> {e}")

    mode = 'okno widoczne (headful)' if not headless else 'headless'
    print(f"Tryb przeglądarki: {mode}{' + stealth' if stealth else ''}\n")
    run_browser(handler, headless=headless, stealth=stealth)
    write_csv(args.outfile, PRODUCT_COLS, rows, delim)
    print(f"\nZapisano {len(rows)}/{len(urls)} produktów → {args.outfile}  (separator: {args.delim})")
    if len(rows) < len(urls):
        print("Część linków pominięto/błąd — sprawdź log wyżej. "
              "Dla stron z dużą ochroną (Nike/Zalando) zaktualizuj selektory w pliku sklepu.")
