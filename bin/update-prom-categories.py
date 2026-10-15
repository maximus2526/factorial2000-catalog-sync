"""
Refresh includes/marketplace/data/prom-categories.tsv from Prom's public category list.

Prom publishes the marketplace categories (path, link, id) as an XLS file:
https://my.prom.ua/cabinet/export_categories/xls (linked from the help article
«Імпорт через YML — формат файлу»). The plugin ships a compact copy so the admin
can pick a category by name; run this when Prom changes its catalog.

Usage:
    pip install xlrd
    python bin/update-prom-categories.py [path/to/categories.xls]

Without an argument the file is downloaded.
"""

import datetime
import os
import sys
import tempfile
import urllib.request

import xlrd

SOURCE = 'https://my.prom.ua/cabinet/export_categories/xls'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET = os.path.join(ROOT, 'includes', 'marketplace', 'data', 'prom-categories.tsv')


def download() -> str:
    path = os.path.join(tempfile.mkdtemp(), 'prom-categories.xls')
    request = urllib.request.Request(SOURCE, headers={'User-Agent': 'Mozilla/5.0'})

    with urllib.request.urlopen(request, timeout=120) as response, open(path, 'wb') as out:
        out.write(response.read())

    return path


def main() -> None:
    source = sys.argv[1] if len(sys.argv) > 1 else download()
    sheet = xlrd.open_workbook(source).sheets()[0]

    # Columns: Категорія1..4, Посилання_категорії, Ідентифікатор_категорії.
    rows = []

    for r in range(1, sheet.nrows):
        names = [str(sheet.cell_value(r, c)).strip() for c in range(4)]
        names = [n.replace('\t', ' ') for n in names if n]
        cid = sheet.cell_value(r, 5)

        if not names or cid in ('', None):
            continue

        rows.append((int(cid), ' > '.join(names)))

    rows.sort(key=lambda row: row[1].lower())

    os.makedirs(os.path.dirname(TARGET), exist_ok=True)

    with open(TARGET, 'w', encoding='utf-8', newline='\n') as out:
        out.write('# Prom.ua marketplace categories: id<TAB>path. Source: %s (%s). Regenerate: bin/update-prom-categories.py\n'
                  % (SOURCE, datetime.date.today().isoformat()))

        for cid, path in rows:
            out.write('%d\t%s\n' % (cid, path))

    print('%d categories -> %s' % (len(rows), TARGET))


if __name__ == '__main__':
    main()
