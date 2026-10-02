# NivCreative – אתר סטטי (אפשרות 1)

ה-HTML המקורי מ-Stitch, ללא שינוי בעיצוב. ההבדלים מהייצוא:
- Tailwind מקומפל ל-`assets/tailwind.css` (במקום ה-CDN שאינו מיועד לפרודקשן).
- הטופס שולח באמת דרך `send.php`.
- נוספו title / description / Open Graph.

## העלאה ל-Hostinger
1. hPanel → Websites → Manage → File Manager → תיקיית `public_html` (למחוק את `default.php` אם קיים).
2. Upload → מעלים את `nivcreative-site.zip` ואז Extract בתוך `public_html`.
   חשוב: `index.html` חייב להיות ישירות ב-`public_html`, לא בתיקייה פנימית.
3. ב-`send.php` מחליפים את `$TO` במייל שיקבל לידים. כדאי שיהיה מייל מהדומיין שנוצר ב-Hostinger (Emails).
4. מנקים מטמון דפדפן ובודקים: האתר, ושליחת הטופס.

הטופס שולח דרך `send.php` (PHP mail). אם המיילים נכנסים לספאם או לא מגיעים, מחליפים את `FORM_ENDPOINT` ב-`index.html` בכתובת Formspree.

## תמונות
התמונות עדיין מקושרות משרתי Google של Stitch ועלולות לפוג. להורדה מקומית (פעם אחת, במחשב שלך):
`./download-images.sh`
אחר כך אפשר להחליף אותן בתמונות אמיתיות של הפרויקטים.

## שינויים בעיצוב
עורכים את `index.html` ואז מקמפלים מחדש:
`npx tailwindcss@3 -c tailwind.config.js -i input.css -o assets/tailwind.css --minify`

הקובץ `original-stitch-export.html` הוא הייצוא המקורי, לגיבוי.
