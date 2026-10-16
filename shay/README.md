# שי יפרח – סרטון גלילה לבאנר (עותק עמוד 3541)

הסרטון נחתך מהחומרים האמיתיים שהועלו ל-`nivcreative.com/shaymedia/`: התמונה `shai_02_C9197_f0702.jpg` וסרטון ההצגה v7.
הגלילה מריצה אותו קדימה ואחורה. החיתוכים תמיד נעצרים מעל הכתוביות שצרובות בסרטון המקורי.

- `tools/render-shay-hero.py` – מרנדר את הסרטון: בחירת השוטים, תנועות המצלמה, המעברים והצבעוניות.
  ```
  python3 tools/render-shay-hero.py <תיקיית מדיה עם showreel.mp4 ו-shai_*.jpg> hero-desktop.mp4 desktop
  python3 tools/render-shay-hero.py <...> hero-mobile.mp4 mobile
  ```
- `scroll-hero-snippet.html` – ה-CSS וה-JS שנוספו לווידג'ט ה-HTML הראשון בעותק.
- `tools/patch-copy-page.py` – עוטף את ה-hero בסצנת גלילה ומוסיף את הסניפט (משמש רק לעמוד 3541). הסקריפט בונה את הווידג'ט מחדש מהנתונים של העמוד המקורי (3502), כך שאפשר להריץ אותו שוב בלי ליצור כפילות.

קבצים בספריית המדיה: `shay-scroll-hero-desktop-v3.mp4` (3549), `shay-scroll-hero-mobile-v3.mp4` (3550),
`shay-scroll-poster-desktop-v3.jpg` (3551), `shay-scroll-poster-mobile-v3.jpg` (3552).
