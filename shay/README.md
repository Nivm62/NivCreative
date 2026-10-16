# שי יפרח – סרטון גלילה לבאנר (עותק עמוד 3541)

הסרטון נחתך מהחומרים האמיתיים שהועלו ל-`nivcreative.com/shaymedia/`: התמונה `shai_02_C9197_f0702.jpg` וסרטון ההצגה v7.
הגלילה מריצה אותו קדימה ואחורה. החיתוכים תמיד נעצרים מעל הכתוביות שצרובות בסרטון המקורי.

- `tools/render-shay-hero.py` – מרנדר את הסרטון: בחירת השוטים, תנועות המצלמה, המעברים והצבעוניות.
  ```
  python3 tools/render-shay-hero.py <תיקיית מדיה עם showreel.mp4 ו-shai_*.jpg> hero-desktop.mp4 desktop
  python3 tools/render-shay-hero.py <...> hero-mobile.mp4 mobile
  ```
- `scroll-hero-snippet.html` – ה-CSS וה-JS שנוספו לווידג'ט ה-HTML הראשון בעותק.
- `tools/patch-copy-page.py` – עוטף את ה-hero בסצנת גלילה ומוסיף את הסניפט (משמש רק לעמוד 3541).

קבצים בספריית המדיה: `shay-scroll-hero-desktop.mp4` (3542), `shay-scroll-hero-mobile-v2.mp4` (3546),
`shay-scroll-poster-desktop.jpg` (3544), `shay-scroll-poster-mobile-v2.jpg` (3547).
