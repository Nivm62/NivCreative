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

## וידאו בבאנר הראשי (נשלט בגלילה)
הבאנר הראשי מציג וידאו שמתקדם ונע אחורה לפי הגלילה של הגולש (scroll scrubbing):
- `assets/video/hero.mp4` (Safari/iOS) ו-`assets/video/hero.webm` (Chrome/Firefox), ותמונת פתיחה `assets/video/hero-poster.jpg`.
- הלוגיקה ב-`assets/hero-scroll.js` והעיצוב ב-`assets/hero-scroll.css` (CSS רגיל, אין צורך לקמפל Tailwind מחדש).
- מי שהגדיר במכשיר "הפחתת תנועה" יראה מסך סטטי בלי גלילה מונפשת.
- הווידאו נוצר בקוד על ידי `tools/render-hero-video.py`. ליצירה מחדש (צריך Python עם numpy ו-Pillow, וגם ffmpeg):
  ```
  python3 tools/render-hero-video.py /tmp/frames 1280 720 150
  ffmpeg -framerate 30 -i /tmp/frames/f%04d.png -c:v libx264 -pix_fmt yuv420p -crf 26 -g 6 -keyint_min 6 -sc_threshold 0 -movflags +faststart -an assets/video/hero.mp4
  ffmpeg -framerate 30 -i /tmp/frames/f%04d.png -c:v libvpx-vp9 -pix_fmt yuv420p -crf 38 -b:v 0 -g 6 -an assets/video/hero.webm
  ```
  (keyframe כל 6 פריימים זה מה שנותן גלילה חלקה קדימה ואחורה.)
- אפשר להחליף בווידאו משלכם: מקודדים אותו עם אותן הגדרות `-g 6` ושומרים באותם שמות קבצים.
