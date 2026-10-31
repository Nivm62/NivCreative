# -*- coding: utf-8 -*-
"""Recolors the CSS of the existing /shaymentor/ site for the /mentorshay/ design:
navy / blue  ->  black / charcoal,   blue glows -> champagne,   blue-ish text -> black or white."""
import re

CHAMP = (235, 198, 108)

def _lum(r, g, b): return 0.299 * r + 0.587 * g + 0.114 * b
def _blue(r, g, b): return b - r > 8 and b >= g

def _neutral(r, g, b):
    L = _lum(r, g, b)
    return (round(L * 1.0), round(L * 0.98), round(L * 0.94))

def _map_rgb(r, g, b, alpha=None, text=False):
    """returns (r,g,b) replacement or None when the colour is not blue-ish"""
    if not _blue(r, g, b): return None
    L = _lum(r, g, b)
    if text: return (0, 0, 0) if L < 110 else (255, 255, 255)
    if L > 100: return CHAMP
    return _neutral(r, g, b)

def recolor(css):
    out = []
    # process declaration by declaration so that we know the property name
    def decl(m):
        prop, val = m.group(1), m.group(2)
        is_text = prop.strip().lower() in ("color", "-webkit-text-fill-color", "caret-color")
        def hexrep(h):
            s = h.group(0)
            if len(s) == 4: s = "#" + "".join(c * 2 for c in s[1:])
            r, g, b = int(s[1:3], 16), int(s[3:5], 16), int(s[5:7], 16)
            m2 = _map_rgb(r, g, b, text=is_text)
            return h.group(0) if m2 is None else "#%02x%02x%02x" % m2
        def rgbrep(h):
            parts = [p.strip() for p in h.group(2).split(",")]
            try: r, g, b = [int(float(p)) for p in parts[:3]]
            except Exception: return h.group(0)
            m2 = _map_rgb(r, g, b, text=is_text)
            if m2 is None: return h.group(0)
            a = parts[3] if len(parts) > 3 else None
            return ("rgba(%d,%d,%d,%s)" % (*m2, a)) if a is not None else ("rgb(%d,%d,%d)" % m2)
        val = re.sub(r"#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b", hexrep, val)
        val = re.sub(r"(rgba?)\(([^)]*)\)", rgbrep, val)
        return prop + ":" + val
    css = re.sub(r"([\w-]+)\s*:\s*([^;{}]+)", lambda m: decl(m) if not m.group(1).startswith("data") else m.group(0), css)
    return css

def remaining_blue(css):
    bad = []
    for h in re.findall(r"#[0-9a-fA-F]{6}\b", css):
        r, g, b = int(h[1:3], 16), int(h[3:5], 16), int(h[5:7], 16)
        if _blue(r, g, b): bad.append(h)
    for t in re.findall(r"rgba?\(([^)]*)\)", css):
        try:
            r, g, b = [int(float(p)) for p in t.split(",")[:3]]
            if _blue(r, g, b): bad.append("rgb(%s)" % t)
        except Exception: pass
    return sorted(set(bad))
