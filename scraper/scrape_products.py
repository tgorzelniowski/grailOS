"""
Scraper produktów GrailOS — URL → CSV dla importera katalogu.

Użycie:
  python scrape_products.py --in urls.txt --out products.csv
  python scrape_products.py --in urls.txt --brand "Corteiz" --category sneakers

Każdy URL = strona produktu. Wynikowy CSV wgrywasz w panelu:
  Admin → Katalog → Import CSV
"""
import argparse
from common import (run_browser, get_page_data, extract_product,
                    read_urls, write_csv, PRODUCT_COLS)
from site_adapters import fill_with_selectors


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--in',  dest='infile',  default='urls.txt')
    ap.add_argument('--out', dest='outfile', default='products.csv')
    ap.add_argument('--brand',    default='', help='Wymuś markę dla wszystkich URL-i')
    ap.add_argument('--category', default='', help='Wymuś kategorię (sneakers/hoodie/...)')
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
                row = extract_product(data, url, args.brand, args.category)
                fill_with_selectors(page, url, row)  # uzupełnij braki selektorami
                if not row['name']:
                    print(f"[{i}/{len(urls)}] POMINIĘTO (brak nazwy): {url}")
                    continue
                rows.append(row)
                print(f"[{i}/{len(urls)}] OK: {row['name']} ({row['price'] or '—'})")
            except Exception as e:
                print(f"[{i}/{len(urls)}] BŁĄD: {url} -> {e}")

    run_browser(handler)
    write_csv(args.outfile, PRODUCT_COLS, rows)
    print(f"\nZapisano {len(rows)} produktów → {args.outfile}")


if __name__ == '__main__':
    main()
