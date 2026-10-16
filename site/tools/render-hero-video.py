# Procedural hero video for NivCreative: fly-through of a neon tunnel, built for scroll scrubbing.
import math, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

W, H = int(sys.argv[2]), int(sys.argv[3])
OUT = sys.argv[1]
FRAMES = int(sys.argv[4]) if len(sys.argv) > 4 else 150
os.makedirs(OUT, exist_ok=True)
S = W / 1280.0
BG = np.array([17, 19, 24], np.float32)
CYAN = (0, 210, 255); VIOLET = (224, 182, 255); ICE = (165, 231, 255)
rng = np.random.default_rng(7)

F = 520 * S
RING_GAP = 1.6
N_RINGS = 34
Z_TOTAL = 34.0
cx, cy = W / 2, H / 2

NS = 1400
sx = rng.uniform(-1, 1, NS); sy = rng.uniform(-1, 1, NS)
r = np.sqrt(rng.uniform(0.04, 1, NS)) * 3.2
ang = rng.uniform(0, 2 * np.pi, NS)
sx, sy = np.cos(ang) * r, np.sin(ang) * r * 0.9
sz = rng.uniform(0.3, Z_TOTAL + 20, NS)
scol = rng.choice(3, NS, p=[.55, .3, .15])

yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
rad = np.sqrt(((xx - cx) / W) ** 2 + ((yy - cy) / W) ** 2)

def ease(t): return 4*t*t*t if t < .5 else 1 - (-2*t + 2) ** 3 / 2
def smooth(a, b, x):
    x = min(max((x - a) / (b - a), 0), 1); return x * x * (3 - 2 * x)

def poly(n, R, rot, z):
    pts = []
    for k in range(n + 1):
        a = rot + 2 * math.pi * k / n
        pts.append((cx + math.cos(a) * R * F / z, cy + math.sin(a) * R * F / z * 0.92))
    return pts

for i in range(FRAMES):
    t = i / (FRAMES - 1)
    cam = Z_TOTAL * ease(t)
    speed = abs(Z_TOTAL * (ease(min(t + 1e-3, 1)) - ease(max(t - 1e-3, 0))) / 2e-3) / Z_TOTAL
    exitk = smooth(0.78, 1.0, t)
    layer = Image.new("RGB", (W, H)); d = ImageDraw.Draw(layer)
    # tunnel rings (hexagons), alternating colours, slowly rotating
    for k in range(N_RINGS, -1, -1):
        z = k * RING_GAP + 0.6 - (cam % RING_GAP) if False else (k * RING_GAP + 1.0) - cam
        if z < 0.25: continue
        depth = 1 - min(z / (N_RINGS * RING_GAP * .55), 1)
        a = depth ** 1.6 * (1 - exitk)
        if a <= 0.01: continue
        idx = k
        col = CYAN if idx % 3 else VIOLET
        c = tuple(int(v * a) for v in col)
        rot = 0.12 * idx + t * 1.4
        wdt = max(1, int(2.2 * S * F / z / 420))
        d.line(poly(6, 1.25, rot, z), fill=c, width=min(wdt, int(14 * S)))
        if idx % 2 == 0:
            for q in range(6):
                aa = rot + 2 * math.pi * q / 6
                z2 = z + RING_GAP
                d.line([(cx + math.cos(aa) * 1.25 * F / z, cy + math.sin(aa) * 1.25 * F / z * .92),
                        (cx + math.cos(aa) * 1.25 * F / z2, cy + math.sin(aa) * 1.25 * F / z2 * .92)],
                       fill=tuple(int(v * .45) for v in c), width=max(1, wdt // 2))
    # warp stars: streak length grows with camera speed
    zz = (sz - cam) % (Z_TOTAL + 20) + 0.2
    streak = 0.05 + speed * 0.9
    for j in range(NS):
        z = zz[j]; z2 = z + streak
        x1, y1 = cx + sx[j] * F / z, cy + sy[j] * F / z
        if not (-50 < x1 < W + 50 and -50 < y1 < H + 50): continue
        x2, y2 = cx + sx[j] * F / z2, cy + sy[j] * F / z2
        b = min(1, 3.0 / z) * (0.55 + 0.45 * (1 - exitk))
        col = (CYAN, VIOLET, ICE)[scol[j]]
        d.line([(x2, y2), (x1, y1)], fill=tuple(int(v * b) for v in col), width=max(1, int(min(3, 2.5 / z) * S)))
    base = np.asarray(layer, np.float32)
    glow = np.asarray(layer.filter(ImageFilter.GaussianBlur(10 * S)), np.float32)
    glow2 = np.asarray(layer.resize((W // 4, H // 4)).filter(ImageFilter.GaussianBlur(10 * S)).resize((W, H), Image.BILINEAR), np.float32)
    img = BG + base * 0.9 + glow * 1.6 + glow2 * 2.4
    # central core: pulses, then blooms into a soft nebula as we exit
    core_r = 0.05 + 0.06 * ease(t) + 0.55 * exitk ** 1.5
    core = np.exp(-(rad / core_r) ** 2)[..., None]
    mix = np.clip((xx - cx) / W + 0.5, 0, 1)[..., None]
    corecol = np.array(CYAN, np.float32) * (1 - mix) + np.array(VIOLET, np.float32) * mix
    intensity = (0.35 + 0.35 * smooth(0.2, 0.75, t)) * (1 - 0.55 * exitk)
    img += core * corecol * intensity
    # vignette + final dim so the clip hands off to the page background
    vig = np.clip(1 - (rad * 1.35) ** 2, 0.25, 1)[..., None]
    img = BG + (img - BG) * vig
    img = BG + (img - BG) * (1 - 0.45 * smooth(0.9, 1.0, t))
    Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).save(f"{OUT}/f{i:04d}.png")
    if i % 25 == 0: print(i, flush=True)
