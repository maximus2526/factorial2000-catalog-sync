"""Crops shots-raw/*.png into wp-org/screenshot-N.png (CSS-pixel heights x2)."""
import os
from PIL import Image

os.makedirs('wp-org', exist_ok=True)
for name, n, css_h in [('update', 1, 1300), ('import', 2, 1450), ('marketplaces', 3, 1000), ('docs', 4, 1100)]:
    im = Image.open(f'shots-raw/{name}.png').convert('RGB')
    im.crop((0, 0, im.width, min(im.height, css_h * 2))).save(f'wp-org/screenshot-{n}.png', optimize=True)
