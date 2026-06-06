"""
Scraper dropów GrailOS — URL → CSV dla importera dropów.

Użycie:
  python scrape_drops.py --in drop_urls.txt --out drops.csv --brand "Nike"

UWAGA: data dropu (drop_date) rzadko jest w danych strukturalnych strony —
kolumna zostaje pusta, uzupełnij ją w CSV przed importem (format: 2026-06-20 10:00).
Wynikowy CSV wgrywasz w: Admin → Dropy → Import dropów CSV
"""
import argparse
from common import (run_browser, get_page_data, extract_product,
                    read_urls, write_csv, DROP_COLS)
from site_adapters import fill_with_selectors


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--in',  dest='infile',  default='drop_urls.txt')
    ap.add_argument('--out', dest='outfile', default='drops.csv')
    ap.add_argument('--brand', default='', help='Wymuś markę dla wszystkich URL-i')
    ap.add_argument('--limit', type=int, default=0)
    args = ap.parse_args()

    urls = read_urls(args.infile)
    if args.limit:
        urls = urls[:args.limit]
    rows = []

    def handler(page):
        for i, url in enumerate(urls, 1):
            try:
                data = get_page_data(page, url)
                p = extract_product(data, url, args.brand, '')
                fill_with_selectors(page, url, p)  # uzupełnij braki selektorami
                if not p['name']:
                    print(f"[{i}/{len(urls)}] POMINIĘTO (brak nazwy): {url}")
                    continue
                rows.append({
                    'name': p['name'],
                    'brand': p['brand'],
                    'drop_date': '',            # uzupełnij ręcznie w CSV
                    'image_url': p['image_url'],
                    'link': url,
                    'description': p['description'],
                    'images': p['images'],
                    'source_url': url,
                })
                print(f"[{i}/{len(urls)}] OK: {p['name']}")
            except Exception as e:
                print(f"[{i}/{len(urls)}] BŁĄD: {url} -> {e}")

    run_browser(handler)
    write_csv(args.outfile, DROP_COLS, rows)
    print(f"\nZapisano {len(rows)} dropów → {args.outfile}")
    print("Pamiętaj uzupełnić kolumnę drop_date przed importem!")


if __name__ == '__main__':
    main()
