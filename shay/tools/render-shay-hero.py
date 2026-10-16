"""Scroll-scrub hero video for Shay Yifrach, cut from his real photos and showreel.

Every shot gets a slow digital camera move (zoom + drift around a focal point), shots are
joined by soft dissolves, and one grade (navy shadows, steel-blue highlights, warm skin
mids, dark right side for the Hebrew headline) is applied to the whole sequence.

Usage: python3 render-shay-hero.py <media_dir> <out.mp4> <desktop|mobile>
"""
import subprocess, sys
import numpy as np
from PIL import Image, ImageFilter

MEDIA, OUT, MODE = sys.argv[1], sys.argv[2], sys.argv[3]
FPS = 24
W, H = (1920, 1080) if MODE == "desktop" else (720, 1280)
XF = 0.5          # dissolve length (s)
SUB_LIMIT = 0.86  # showreel subtitles start at ~0.87 of the frame height: never show below this

# kind, source, start(s), dur(s), focal x,y in source (0-1), focal position in output (desk / mobile), zoom from -> to
SHOTS = [
    ("photo", "shai_02_C9197_f0702.jpg", 0, 3.0, (.57, .30), (.34, .40), (.42, .80), 1.30, 1.42),
    ("video", None, 11.00, 1.6, (.35, .25), (.40, .34), (.45, .80), 1.25, 1.33),
    ("video", None, 42.75, 1.5, (.47, .22), (.34, .32), (.45, .80), 1.25, 1.32),
    ("video", None, 27.35, 1.4, (.30, .45), (.30, .50), (.45, .78), 1.30, 1.38),
    ("video", None, 34.55, 1.4, (.86, .55), (.40, .55), (.50, .78), 1.25, 1.33),
    ("video", None, 78.55, 1.9, (.58, .45), (.38, .52), (.48, .78), 1.25, 1.36),
    ("video", None, 75.05, 1.5, (.40, .45), (.36, .52), (.48, .78), 1.25, 1.33),
    ("video", None, 100.15, 1.4, (.17, .25), (.24, .34), (.42, .80), 1.22, 1.28),
    ("photo", "shai_02_C9197_f0702.jpg", 0, 2.5, (.57, .30), (.34, .40), (.42, .80), 1.50, 1.30),  # settles on the opening frame
]

def smooth(x): return x * x * (3 - 2 * x)

def read_video(start, dur):
    cmd = ["ffmpeg", "-loglevel", "error", "-ss", f"{start}", "-i", f"{MEDIA}/showreel.mp4", "-t", f"{dur + 0.1}",
           "-vf", f"fps={FPS}", "-f", "rawvideo", "-pix_fmt", "rgb24", "-"]
    raw = subprocess.run(cmd, capture_output=True, check=True).stdout
    fr = np.frombuffer(raw, np.uint8).reshape(-1, 1080, 1920, 3)
    return [Image.fromarray(f) for f in fr]

def window(sw, sh, focal, outpos, zoom, limit_bottom):
    ar = W / H
    # window size in source px: height-limited for portrait, width-limited for landscape
    if ar >= sw / sh: ww = sw / zoom; wh = ww / ar
    else: wh = sh / zoom; ww = wh * ar
    maxh = sh * limit_bottom
    if wh > maxh: wh = maxh; ww = wh * ar
    x0 = focal[0] * sw - outpos[0] * ww
    y0 = focal[1] * sh - outpos[1] * wh
    x0 = min(max(x0, 0), sw - ww)
    y0 = min(max(y0, 0), sh * limit_bottom - wh)
    return (x0, max(y0, 0), x0 + ww, max(y0, 0) + wh)

def render_shot(s):
    kind, src, start, dur, focal, pos_d, pos_m, z0, z1 = s
    pos = pos_d if MODE == "desktop" else pos_m
    n = int(round(dur * FPS))
    frames = read_video(start, dur) if kind == "video" else None
    still = Image.open(f"{MEDIA}/{src}").convert("RGB") if kind == "photo" else None
    out = []
    pad = 0
    if MODE == "mobile":
        # portrait frames need headroom above Shay for the headline: extend the top with a
        # soft upward continuation of the frame's top edge (it ends up under the dark top gradient)
        sh_ = (still or frames[0]).height
        pad = int(sh_ * 0.75)
        focal = (focal[0], (focal[1] * sh_ + pad) / (sh_ + pad))
    def padded(im):
        if not pad: return im
        # stretch the frame's top rows upwards and blur them: a clean continuation of the wall/ceiling
        strip = im.crop((0, 0, im.width, 12)).resize((im.width // 8, pad)).filter(ImageFilter.GaussianBlur(12)).resize((im.width, pad))
        c = Image.new("RGB", (im.width, im.height + pad)); c.paste(strip, (0, 0)); c.paste(im, (0, pad)); return c
    if still: still = padded(still)
    for i in range(n):
        t = smooth(i / max(n - 1, 1))
        z = z0 + (z1 - z0) * t
        im = still if still else padded(frames[min(i, len(frames) - 1)])
        lim = 1.0 if still else (SUB_LIMIT * (im.height - pad) + pad) / im.height
        box = window(im.width, im.height, focal, pos, z, lim)
        out.append(np.asarray(im.resize((W, H), Image.LANCZOS, box=box), np.float32) / 255)
    return out

# static grade layers
yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
u, v = xx / W, yy / H
if MODE == "desktop":
    # right ~45% fades towards navy for the headline; top edge calm under the floating nav
    text_side = np.clip((u - 0.40) / 0.38, 0, 1) ** 1.2
    top = np.clip(1 - v / 0.16, 0, 1) ** 2
    shade = 1 - (0.62 * text_side + 0.35 * top * (1 - text_side))
else:
    top = np.clip(1 - v / 0.66, 0, 1) ** 1.1
    shade = 1 - 0.72 * top
vig = 1 - 0.25 * np.clip(((u - .38) ** 2 * 1.4 + (v - .5) ** 2 * 1.8), 0, 1)
shade = (shade * vig)[..., None]
NAVY = np.array([6, 11, 22], np.float32) / 255
STEEL_DARK = np.array([.32, .40, .55], np.float32)  # walls become dark steel-blue, like a lit stage set
WARM = np.array([1.06, .98, .86], np.float32)   # champagne mids keep skin natural
rng = np.random.default_rng(3)

def grade(img):
    lum = img @ np.array([.2126, .7152, .0722], np.float32)
    l = lum[..., None]
    # tone curve on luminance: skin/mids stay natural, white walls roll off hard into dark steel
    knee = 0.50
    nl = np.where(l < knee, l * 1.0, knee + (l - knee) * 0.45)
    img = img * (nl / np.maximum(l, 1e-4))
    nlum = nl
    sat = 0.88
    img = nlum + (img - nlum) * sat
    hi = np.clip((l - .45) / .4, 0, 1)            # where the walls were
    img = img * (1 - 0.30 * hi) + STEEL_DARK * 0.30 * hi * (nlum / 0.4)
    mid = np.clip(1 - abs(l - .42) / .25, 0, 1)
    img = img * (1 + (WARM - 1) * mid * 0.8)
    img = NAVY + (img - NAVY) * shade
    img += rng.normal(0, 0.006, (H, W, 1)).astype(np.float32)
    return np.clip(img, 0, 1)

clips = [render_shot(s) for s in SHOTS]
xf = int(XF * FPS)
seq = clips[0]
for c in clips[1:]:
    head, tail = seq[:-xf], seq[-xf:]
    mix = [tail[k] * (1 - smooth((k + 1) / (xf + 1))) + c[k] * smooth((k + 1) / (xf + 1)) for k in range(xf)]
    seq = head + mix + c[xf:]
print(MODE, len(seq), "frames", len(seq) / FPS, "s", flush=True)

enc = subprocess.Popen(["ffmpeg", "-loglevel", "error", "-y", "-f", "rawvideo", "-pix_fmt", "rgb24", "-s", f"{W}x{H}",
                        "-r", str(FPS), "-i", "-", "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "25", "-preset", "slow",
                        "-g", "6", "-keyint_min", "6", "-sc_threshold", "0", "-movflags", "+faststart", "-an", OUT],
                       stdin=subprocess.PIPE)
for f in seq:
    enc.stdin.write((grade(f) * 255).astype(np.uint8).tobytes())
enc.stdin.close(); enc.wait()
