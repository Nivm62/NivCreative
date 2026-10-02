module.exports = {
  darkMode: "class",
  content: ["./index.html", "./assets/sy.js"],
  theme: {
    extend: {
      colors: { "secondary-fixed": "#ffdf9b", "error-container": "#93000a", "secondary-container": "#906d00", "background": "#131315", "inverse-on-surface": "#313032", "tertiary-fixed-dim": "#ffb955", "secondary": "#edc157", "secondary-fixed-dim": "#edc157", "surface-container": "#201f21", "inverse-surface": "#e5e1e4", "on-primary-container": "#613b00", "outline-variant": "#534434", "primary": "#ffc174", "on-error": "#690005", "error": "#ffb4ab", "surface-dim": "#131315", "primary-fixed": "#ffddb8", "on-surface-variant": "#d8c3ad", "on-surface": "#e5e1e4", "on-primary": "#472a00", "inverse-primary": "#855300", "surface": "#131315", "primary-container": "#f59e0b", "tertiary-fixed": "#ffddb4", "on-secondary-container": "#fff7ee", "on-background": "#e5e1e4", "surface-container-lowest": "#0e0e10", "tertiary": "#ffc26e", "surface-tint": "#ffb95f", "surface-container-low": "#1c1b1d", "surface-container-high": "#2a2a2c", "primary-fixed-dim": "#ffb95f", "surface-variant": "#353437", "tertiary-container": "#e8a43e", "surface-container-highest": "#353437", "outline": "#a08e7a", "surface-bright": "#39393b" },
      borderRadius: { DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px" },
      spacing: { "space-md": "1rem", gutter: "1rem", "space-xl": "2.5rem", "space-sm": "0.5rem", margin: "1.5rem", "space-xs": "0.25rem", "space-lg": "1.5rem", "gutter-mobile": "0.75rem", "margin-mobile": "1.25rem" },
      fontFamily: { rubik: ["Rubik", "sans-serif"] },
      fontSize: {
        "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }],
        "label-lg": ["16px", { lineHeight: "24px", letterSpacing: "0.01em", fontWeight: "700" }],
        "label-md": ["14px", { lineHeight: "20px", letterSpacing: "0.02em", fontWeight: "600" }],
        "body-sm": ["14px", { lineHeight: "22px", fontWeight: "400" }],
        "headline-lg": ["40px", { lineHeight: "48px", letterSpacing: "-0.01em", fontWeight: "700" }],
        "headline-md": ["24px", { lineHeight: "32px", fontWeight: "700" }],
        "headline-hero": ["64px", { lineHeight: "72px", letterSpacing: "-0.02em", fontWeight: "800" }],
        "label-sm": ["12px", { lineHeight: "16px", letterSpacing: "0.04em", fontWeight: "500" }],
        "headline-sm": ["20px", { lineHeight: "28px", fontWeight: "600" }],
        "headline-lg-mobile": ["26px", { lineHeight: "34px", fontWeight: "700" }],
        "headline-hero-mobile": ["38px", { lineHeight: "46px", letterSpacing: "-0.01em", fontWeight: "800" }],
        "body-md": ["16px", { lineHeight: "26px", fontWeight: "400" }]
      },
      keyframes: {
        float: { "0%,100%": { transform: "translateY(0)" }, "50%": { transform: "translateY(-14px)" } },
        shimmer: { "0%": { transform: "translateX(120%) skewX(-20deg)" }, "100%": { transform: "translateX(-220%) skewX(-20deg)" } },
        glow: { "0%,100%": { opacity: ".55", transform: "scale(1)" }, "50%": { opacity: "1", transform: "scale(1.12)" } },
        gradmove: { "0%": { backgroundPosition: "0% 50%" }, "100%": { backgroundPosition: "200% 50%" } },
        rise: { "0%": { opacity: "0", transform: "translateY(26px)" }, "100%": { opacity: "1", transform: "none" } },
        pop: { "0%": { transform: "scale(0) rotate(-30deg)" }, "70%": { transform: "scale(1.25)" }, "100%": { transform: "scale(1) rotate(0)" } },
        drift: { "0%": { transform: "translateY(0) translateX(0)", opacity: "0" }, "15%": { opacity: ".9" }, "100%": { transform: "translateY(-340px) translateX(30px)", opacity: "0" } },
        ping2: { "0%": { transform: "scale(1)", opacity: ".6" }, "100%": { transform: "scale(2.1)", opacity: "0" } }
      },
      animation: {
        float: "float 6s ease-in-out infinite",
        shimmer: "shimmer 3.2s ease-in-out infinite",
        glow: "glow 5s ease-in-out infinite",
        gradmove: "gradmove 6s linear infinite",
        rise: "rise .9s cubic-bezier(.2,.8,.2,1) both",
        pop: "pop .55s cubic-bezier(.2,.8,.2,1) both",
        drift: "drift 9s linear infinite",
        ping2: "ping2 2.2s ease-out infinite"
      }
    }
  }
};
