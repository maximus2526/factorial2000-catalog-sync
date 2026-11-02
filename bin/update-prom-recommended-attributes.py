"""
Refresh includes/marketplace/data/prom-recommended-attributes.tsv from Prom's
recommended-attributes.xlsx.

The file is published in the Prom help article "Характеристики товарів":
https://prom.ua/cloud-cgi/static/support/prom/recommended-attributes.xlsx

Usage:
    pip install openpyxl
    python bin/update-prom-recommended-attributes.py [path/to/recommended-attributes.xlsx]

Without an argument the file is downloaded.
"""

import datetime
import os
import sys
import tempfile
import urllib.request

import openpyxl

SOURCE = 'https://prom.ua/cloud-cgi/static/support/prom/recommended-attributes.xlsx'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET = os.path.join(ROOT, 'includes', 'marketplace', 'data', 'prom-recommended-attributes.tsv')


def download() -> str:
    path = os.path.join(tempfile.mkdtemp(), 'recommended-attributes.xlsx')
    request = urllib.request.Request(SOURCE, headers={'User-Agent': 'Mozilla/5.0'})

    with urllib.request.urlopen(request, timeout=120) as response, open(path, 'wb') as out:
        out.write(response.read())

    return path


def sheet_rows(sheet) -> dict:
    rows = {}

    for row in sheet.iter_rows(min_row=2, values_only=True):
        if not row or row[0] in ('', None):
            continue

        try:
            category_id = int(row[0])
        except (TypeError, ValueError):
            continue

        name = '' if row[1] is None else str(row[1]).strip()
        attrs = []

        for value in row[2:6]:
            if value is None:
                continue

            value = str(value).replace('\t', ' ').replace('|', '/').strip()

            if value:
                attrs.append(value)

        rows[category_id] = (name, attrs)

    return rows


def main() -> None:
    source = sys.argv[1] if len(sys.argv) > 1 else download()
    workbook = openpyxl.load_workbook(source, read_only=True, data_only=True)

    if len(workbook.worksheets) < 2:
        raise SystemExit('Expected two sheets: Ukrainian and Russian')

    uk = sheet_rows(workbook.worksheets[0])
    ru = sheet_rows(workbook.worksheets[1])
    ids = sorted(set(uk) | set(ru))

    os.makedirs(os.path.dirname(TARGET), exist_ok=True)

    with open(TARGET, 'w', encoding='utf-8', newline='\n') as out:
        out.write(
            '# Prom.ua recommended attributes: id<TAB>name_uk<TAB>attrs_uk<TAB>name_ru<TAB>attrs_ru. '
            'Source: %s (%s). Regenerate: bin/update-prom-recommended-attributes.py\n'
            % (SOURCE, datetime.date.today().isoformat())
        )

        for category_id in ids:
            name_uk, attrs_uk = uk.get(category_id, ('', []))
            name_ru, attrs_ru = ru.get(category_id, ('', []))
            out.write(
                '%d\t%s\t%s\t%s\t%s\n'
                % (
                    category_id,
                    name_uk,
                    '|'.join(attrs_uk),
                    name_ru,
                    '|'.join(attrs_ru),
                )
            )

    print('%d categories -> %s' % (len(ids), TARGET))


if __name__ == '__main__':
    main()
