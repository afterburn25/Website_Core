"""Build deterministic visual cards for Catering's ten website themes."""

from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageOps

ROOT = Path(__file__).resolve().parents[1]
IMAGES = ROOT / "assets" / "images"
OUTPUT = IMAGES / "themes"
OUTPUT.mkdir(parents=True, exist_ok=True)

THEMES = {
    "heritage-editorial": ("Heritage Editorial", "#fffaf0", "#8b3f23"),
    "modern-culinary": ("Modern Culinary", "#fbfcfd", "#176b76"),
    "garden-celebration": ("Garden Celebration", "#fbfaf5", "#52735b"),
    "grand-wedding": ("Grand Wedding", "#fffaf2", "#9b7135"),
    "corporate-table": ("Corporate Table", "#f7f9fc", "#285b92"),
    "rustic-gathering": ("Rustic Gathering", "#fffaf1", "#7a4b2c"),
    "bayou-night": ("Bayou Night", "#111915", "#8dbb73"),
    "market-kitchen": ("Market Kitchen", "#fffaf3", "#b8432f"),
    "social-feast": ("Social Feast", "#fff9f7", "#b94770"),
    "minimal-luxe": ("Minimal Luxe", "#fdfcf9", "#6f5a3a"),
}

FONT = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", 16)
SMALL = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 10)
SERIF = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf", 27)


def crop(path: str, size: tuple[int, int]) -> Image.Image:
    return ImageOps.fit(Image.open(IMAGES / path).convert("RGB"), size, Image.Resampling.LANCZOS)


def label(draw: ImageDraw.ImageDraw, name: str, x: int, y: int, color: str, serif: bool = False) -> None:
    draw.text((x, y), name, font=SERIF if serif else FONT, fill=color)


for slug, (name, background, primary) in THEMES.items():
    hero = f"theme-packs/{slug}-hero.webp"
    feature = f"theme-packs/{slug}-feature.webp"
    accent = f"theme-packs/{slug}-accent.webp"
    canvas = Image.new("RGB", (600, 400), background)
    draw = ImageDraw.Draw(canvas)

    if slug == "heritage-editorial":
        canvas.paste(crop(hero, (345, 400)), (255, 0))
        draw.rectangle((0, 0, 255, 400), fill=background)
        draw.text((25, 28), "CATERING CO.", font=SMALL, fill=primary)
        label(draw, "Gather", 25, 105, "#24170f", True)
        label(draw, "beautifully.", 25, 140, "#24170f", True)
        draw.line((25, 190, 210, 190), fill=primary, width=3)
        draw.rounded_rectangle((25, 245, 150, 280), 18, fill=primary)
    elif slug == "modern-culinary":
        draw.rectangle((0, 0, 600, 55), fill="#13202a")
        canvas.paste(crop(hero, (360, 270)), (0, 55))
        canvas.paste(crop(feature, (240, 135)), (360, 55))
        draw.rectangle((360, 190, 600, 325), fill=primary)
        label(draw, "Food, designed", 385, 220, "white")
        draw.rectangle((35, 300, 565, 380), fill=background)
        draw.text((60, 330), "Menus   •   Events   •   Service", font=FONT, fill="#13202a")
    elif slug == "garden-celebration":
        canvas.paste(crop(hero, (600, 400)), (0, 0))
        draw.ellipse((-90, -80, 230, 250), fill="#dce8d8")
        draw.rounded_rectangle((70, 75, 420, 275), 55, fill=background)
        draw.text((105, 110), "GARDEN CELEBRATIONS", font=SMALL, fill=primary)
        label(draw, "Fresh gatherings", 105, 145, "#233126", True)
        canvas.paste(crop(accent, (135, 105)), (430, 255))
    elif slug == "grand-wedding":
        canvas.paste(crop(hero, (600, 400)), (0, 0))
        overlay = Image.new("RGBA", canvas.size, (20, 15, 12, 95))
        canvas = Image.alpha_composite(canvas.convert("RGBA"), overlay).convert("RGB")
        draw = ImageDraw.Draw(canvas)
        draw.text((245, 85), "THE CELEBRATION", font=SMALL, fill="#f2e9da")
        label(draw, "A grand table", 185, 145, "white", True)
        draw.rounded_rectangle((225, 235, 375, 275), 20, outline="white", width=2)
        draw.text((255, 247), "PLAN YOUR DAY", font=SMALL, fill="white")
    elif slug == "corporate-table":
        draw.rectangle((0, 0, 155, 400), fill="#182438")
        draw.text((25, 35), "CATERING\nOPERATIONS", font=FONT, fill="white", spacing=6)
        for y in (145, 180, 215, 250):
            draw.rectangle((25, y, 125, y + 8), fill="#52657e")
        draw.rectangle((155, 0, 600, 70), fill=background)
        draw.text((185, 25), "Event catering, organized.", font=FONT, fill="#182438")
        canvas.paste(crop(hero, (270, 180)), (180, 90))
        canvas.paste(crop(feature, (115, 180)), (460, 90))
        for x, text in ((180, "MENUS"), (315, "STAFF"), (450, "TIMELINE")):
            draw.rounded_rectangle((x, 292, x + 115, 360), 8, fill="white", outline="#dce4ef")
            draw.text((x + 15, 316), text, font=SMALL, fill=primary)
    elif slug == "rustic-gathering":
        draw.rectangle((0, 0, 600, 400), fill="#ead8bd")
        draw.rectangle((30, 25, 570, 375), fill=background, outline="#7a4b2c", width=2)
        first = crop(hero, (250, 205)).rotate(-4, expand=True)
        second = crop(feature, (210, 155)).rotate(5, expand=True)
        canvas.paste(first, (285, 90))
        canvas.paste(second, (355, 220))
        draw = ImageDraw.Draw(canvas)
        draw.text((70, 75), "MADE FOR GATHERING", font=SMALL, fill=primary)
        label(draw, "Come to", 70, 125, "#302216", True)
        label(draw, "the table", 70, 160, "#302216", True)
        draw.line((70, 220, 230, 220), fill=primary, width=3)
    elif slug == "bayou-night":
        canvas.paste(crop(hero, (465, 400)), (135, 0))
        shade = Image.new("RGBA", (465, 400), (5, 15, 10, 105))
        canvas.paste(shade, (135, 0), shade)
        draw.rectangle((0, 0, 135, 400), fill=background)
        draw.text((25, 30), "BAYOU\nNIGHT", font=FONT, fill="#f5f0df", spacing=5)
        for y in (155, 190, 225):
            draw.rectangle((25, y, 100, y + 5), fill=primary)
        label(draw, "Bold flavor", 220, 135, "white", True)
        label(draw, "after dark", 220, 170, "white", True)
    elif slug == "market-kitchen":
        draw.rectangle((0, 0, 600, 48), fill=primary)
        draw.text((25, 16), "SEASONAL MENUS  •  PICKUP  •  DELIVERY", font=SMALL, fill="white")
        label(draw, "Market Kitchen", 30, 78, "#2f2119", True)
        for index, image in enumerate((hero, feature, accent)):
            x = 25 + index * 190
            canvas.paste(crop(image, (170, 150)), (x, 145))
            draw.rounded_rectangle((x, 305, x + 170, 360), 8, fill="white", outline="#ead9ca")
            draw.text((x + 15, 323), ("SMALL BITES", "FAMILY TRAYS", "EVENT FAVORITES")[index], font=SMALL, fill=primary)
    elif slug == "social-feast":
        draw.rectangle((0, 0, 600, 60), fill=background)
        draw.text((24, 22), "SOCIAL FEAST", font=FONT, fill="#32202b")
        canvas.paste(crop(hero, (260, 215)), (20, 80))
        canvas.paste(crop(feature, (280, 130)), (300, 80))
        canvas.paste(crop(accent, (135, 165)), (300, 230))
        draw.rectangle((455, 230, 580, 395), fill=primary)
        draw.text((470, 265), "SEE\nWHAT'S\nCOOKING", font=FONT, fill="white", spacing=8)
    elif slug == "minimal-luxe":
        draw.text((40, 30), "CATERING CO.", font=SMALL, fill=primary)
        draw.line((40, 58, 560, 58), fill="#d8d1c6", width=1)
        label(draw, "Simply", 55, 105, "#1f1d1a", True)
        label(draw, "exceptional.", 55, 140, "#1f1d1a", True)
        draw.text((58, 205), "Intentional menus. Quiet luxury.\nHospitality without distraction.", font=SMALL, fill="#6b645a", spacing=5)
        canvas.paste(crop(hero, (265, 250)), (315, 85))
        draw.line((40, 360, 560, 360), fill=primary, width=2)

    output = OUTPUT / f"{slug}.webp"
    canvas.save(output, "WEBP", quality=34, method=6)
    print(output.relative_to(ROOT))
