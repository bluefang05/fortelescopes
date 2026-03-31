from pathlib import Path
from PIL import Image, ImageDraw, ImageChops
import math

OUT = Path(r"C:/xampp/htdocs/fortelescopes/assets/logo")
OUT.mkdir(parents=True, exist_ok=True)

SIZES = [16, 32, 48, 64, 96, 128, 180, 192, 256, 384, 512, 768, 1024, 1536, 2048]
DARK = (8, 18, 34, 255)


def star_points(cx, cy, r_outer, r_inner):
    pts = []
    for i in range(8):
        ang = -math.pi / 2 + i * (math.pi / 4)
        r = r_outer if i % 2 == 0 else r_inner
        pts.append((cx + math.cos(ang) * r, cy + math.sin(ang) * r))
    return pts


def draw_logo(size: int) -> Image.Image:
    im = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)

    cx = cy = size / 2
    r = size * 0.46

    # Dark solid circle base
    d.ellipse((cx - r, cy - r, cx + r, cy + r), fill=DARK)

    # Build cutout mask (transparent telescope + details)
    cut = Image.new("L", (size, size), 0)
    c = ImageDraw.Draw(cut)

    # Tube on rotated layer
    tube = Image.new("L", (size, size), 0)
    t = ImageDraw.Draw(tube)
    tube_w = size * 0.40
    tube_h = size * 0.115
    x0 = cx - tube_w * 0.5
    y0 = cy - tube_h * 0.62
    x1 = x0 + tube_w
    y1 = y0 + tube_h
    t.rounded_rectangle((x0, y0, x1, y1), radius=tube_h * 0.5, fill=255)

    ep_w = size * 0.075
    t.rounded_rectangle((x0 - ep_w * 1.0, y0 + tube_h * 0.12, x0 + ep_w * 0.15, y0 + tube_h * 0.88), radius=tube_h * 0.45, fill=255)
    t.rounded_rectangle((x0 - ep_w * 1.55, y0 + tube_h * 0.20, x0 - ep_w * 0.82, y0 + tube_h * 0.80), radius=tube_h * 0.4, fill=255)

    tube = tube.rotate(-24, resample=Image.Resampling.BICUBIC, center=(cx, cy))
    cut = ImageChops.lighter(cut, tube)
    c = ImageDraw.Draw(cut)

    # Objective ring cutout
    ox = cx + size * 0.19
    oy = cy - size * 0.11
    ro = size * 0.108
    ri = size * 0.078
    c.ellipse((ox - ro, oy - ro, ox + ro, oy + ro), fill=255)
    c.ellipse((ox - ri, oy - ri, ox + ri, oy + ri), fill=0)
    c.polygon(star_points(ox, oy, size * 0.032, size * 0.012), fill=255)

    # Mount + smile cutouts
    c.polygon([
        (cx - size * 0.09, cy + size * 0.05),
        (cx + size * 0.07, cy + size * 0.01),
        (cx + size * 0.01, cy + size * 0.11),
        (cx - size * 0.17, cy + size * 0.17),
    ], fill=255)
    c.arc((cx - size * 0.24, cy - size * 0.02, cx + size * 0.34, cy + size * 0.34), start=203, end=338, fill=255, width=max(2, int(size * 0.03)))

    # Clip cutouts to circle only
    circle = Image.new("L", (size, size), 0)
    cd = ImageDraw.Draw(circle)
    cd.ellipse((cx - r, cy - r, cx + r, cy + r), fill=255)
    cut = ImageChops.multiply(cut, circle)

    alpha = im.split()[3]
    alpha = ImageChops.subtract(alpha, cut)
    im.putalpha(alpha)
    return im

for s in SIZES:
    draw_logo(s).save(OUT / f"{s}.png")

svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2048 2048" role="img" aria-label="Fortelescopes logo">
  <title>Fortelescopes logo</title>
  <defs>
    <mask id="cut" maskUnits="userSpaceOnUse" x="0" y="0" width="2048" height="2048">
      <rect width="2048" height="2048" fill="white"/>
      <g fill="black" stroke="black">
        <g transform="rotate(-24 1024 1024)">
          <rect x="620" y="858" width="820" height="236" rx="118" ry="118"/>
          <rect x="463" y="890" width="162" height="176" rx="88" ry="88"/>
          <rect x="373" y="916" width="114" height="128" rx="64" ry="64"/>
        </g>
        <circle cx="1410" cy="799" r="221"/>
        <circle cx="1410" cy="799" r="160" fill="white" stroke="none"/>
        <path d="M1410 726l28 54 60 19-60 19-28 54-28-54-60-19 60-19z"/>
        <path d="M863 1123l302-96-96 205-376 127z"/>
        <path d="M531 1252c193 47 596 97 982-177" fill="none" stroke-width="66" stroke-linecap="round"/>
      </g>
    </mask>
  </defs>
  <circle cx="1024" cy="1024" r="942" fill="#081222" mask="url(#cut)"/>
</svg>
'''
(OUT / 'logo.svg').write_text(svg, encoding='utf-8')

print('Regenerated dark-circle logo assets in', OUT)
