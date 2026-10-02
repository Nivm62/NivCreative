# SY – עוצמה ואמונה (שי יפרח)

אתר סטטי מונפש (RTL, רספונסיבי) לפי הדף nivcreative.com/sy1.

## העלאה ל-Hostinger
1. hPanel → Websites → Manage → File Manager → `public_html` (למחוק `default.php` אם קיים).
2. Upload של `SY.zip` ואז Extract בתוך `public_html`. `index.html` חייב להיות ישירות ב-`public_html`.
3. ב-`send.php` להחליף את `$TO` במייל שיקבל את ההרשמות.
4. לנקות מטמון ולבדוק את האתר ואת שליחת הטופס.

## מה יש בפנים
אנימציות: כניסה בגלילה, טיימר לסדנה (24/11), ספירת מחירים, חלקיקים, צל/ברק על כפתורים, FAQ חלק, פס התקדמות וניווט פעיל.
אנימציות מושבתות אוטומטית למי שהגדיר "הפחת תנועה" במכשיר.

## עריכה
`npx tailwindcss@3 -c tailwind.config.js -i input.css -o assets/tailwind.css --minify`

התמונות מקושרות משרתי Google (Stitch) ועלולות לפוג – מומלץ להחליף בתמונות מקומיות בתיקיית `images/`.
