# NivCreative – אתר סטטי (אפשרות 1)

ה-HTML המקורי מ-Stitch, ללא שינוי בעיצוב. ההבדלים מהייצוא:
- Tailwind מקומפל ל-`assets/tailwind.css` (במקום ה-CDN שאינו מיועד לפרודקשן).
- הטופס שולח באמת (Netlify Forms כברירת מחדל, ראו למטה).
- נוספו title / description / Open Graph.

## העלאה ל-Netlify (הכי פשוט)
1. app.netlify.com → Add new site → Deploy manually → גוררים את התיקייה `site/`.
2. Domain settings → מחברים את הדומיין.
3. Forms → Form notifications → מוסיפים מייל לקבלת לידים.

אירוח אחר (Vercel / Cloudflare Pages / GitHub Pages): מעלים את התיקייה כמו שהיא.
בהן אין Netlify Forms, ולכן ב-`index.html` מחליפים `var FORM_ENDPOINT = "/"`
בכתובת של Formspree (`https://formspree.io/f/XXXX`).

## תמונות
התמונות עדיין מקושרות משרתי Google של Stitch ועלולות לפוג. להורדה מקומית (פעם אחת, במחשב שלך):
`./download-images.sh`
אחר כך אפשר להחליף אותן בתמונות אמיתיות של הפרויקטים.

## שינויים בעיצוב
עורכים את `index.html` ואז מקמפלים מחדש:
`npx tailwindcss@3 -c tailwind.config.js -i input.css -o assets/tailwind.css --minify`

הקובץ `original-stitch-export.html` הוא הייצוא המקורי, לגיבוי.
