# لینکیار (LinkYar)

**افزونه‌ی لینک‌سازی داخلی وردپرس — Persian internal-linking assistant plugin for WordPress.**

[![Version](https://img.shields.io/badge/version-1.0.0-0e7490)](https://github.com/rmombeni/linkyar)
[![WP Tested](https://img.shields.io/badge/WordPress-6.0%2B-21759b)](https://wordpress.org/)
[![PHP Tested](https://img.shields.io/badge/PHP-7.4%2B-777bb3)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](./LICENSE)

لینکیار به مدیران سایت کمک می‌کند لینک‌سازی داخلی را سریع‌تر، هوشمندتر و سالم‌تر انجام دهند. رابط کاربری فارسی (RTL)، بدون هیچ وابستگی به composer/npm و بدون مرحلهٔ build.

LinkYar helps site owners internal-link faster, smarter and safer. Persian (RTL) UI, freemium-ready, plain PHP + vanilla JS/CSS, zero build step.

---

## امکانات / Features

| # | فارسی | English |
|---|-------|---------|
| ۱ | **پیشنهاد لینک داخلی** — متا باکس در ویرایشگر با امتیازدهی (عنوان ×۳، متن ×۱، دستهٔ مشترک +۱)، فیلتر جستجو، درج لینک با یک کلیک و کپی آدرس | **Link suggestions** — editor meta box with scoring (title ×3, content ×1, shared category +1), search filter, one-click insert and copy |
| ۲ | **اتولینک هوشمند** — قوانین کلیدواژه→صفحهٔ هدف، حداکثر لینک در هر پست، احترام به نیم‌فاصله (ZWNJ)، بدون تداخل با لینک‌ها و کدهای موجود | **Smart auto-linking** — keyword→target rules, per-post link caps, ZWNJ-aware Persian matching, never touches existing links or code blocks |
| ۳ | **گزارش لینک‌های شکسته** — اسکن دسته‌ای AJAX با نوار پیشرفت، تفکیک سالم/خراب/ریدایرکت، پست‌های مبدأ هر لینک | **Broken link report** — AJAX batch scanner with progress bar, ok/broken/redirect filters, source posts per link |
| ۴ | **داشبورد** — KPIها، پست‌های بدون لینک داخلی، لینک‌های خراب اخیر، وضعیت سامانه | **Dashboard** — KPIs, posts without internal links, recent broken links, system status |
| ۵ | **تاریخ جلالی** — سازگار با افزونهٔ فارسیساز (WP-Parsidate) با fallback خودکار | **Jalali dates** — integrates with WP-Parsidate (فارسیساز), graceful fallback |

---

## نصب / Installation

### فارسی
1. پوشهٔ `linkyar` را در `wp-content/plugins/` آپلود کنید (یا از مسیر «افزونه‌ها › افزودن › بارگذاری افزونه» فایل زیپ را نصب کنید).
2. افزونه را «فعال» کنید.
3. ویزارد سه‌مرحله‌ای راه‌اندازی باز می‌شود: نوع‌های محتوا را انتخاب کنید، در صورت تمایل اولین قانون اتولینک را بسازید و پایان را بزنید.
4. از منوی **لینکیار › داشبورد** شروع کنید و لینک‌های سایت را اسکن کنید.

### English
1. Upload the `linkyar` folder to `wp-content/plugins/` (or install the zip via *Plugins › Add New › Upload Plugin*).
2. Activate the plugin.
3. Complete the 3-step onboarding wizard (post types → first autolink rule → done).
4. Open **LinkYar › Dashboard** and run your first link scan.

---

## راهنمای استفاده / Usage guide

### ۱) پیشنهاد لینک داخلی
هنگام ویرایش پست، متا باکس **«پیشنهادهای لینک داخلی»** در کنار ویرایشگر ظاهر می‌شود:
- پیشنهادها بر اساس کلیدواژه‌های عنوان پست فعلی رتبه‌بندی می‌شوند (بدون احتساب stopwordها).
- دکمهٔ **درج لینک** یک پاراگراف جدید با لینک در انتهای محتوا اضافه می‌کند.
- دکمهٔ **کپی لینک** آدرس را در کلیپ‌بورد کپی می‌کند.
- با **تازه‌سازی** فهرست را دوباره بارگذاری کنید.
- تعداد پیشنهادها (۵ تا ۱۵) از **لینکیار › پیشنهاد لینک** قابل تنظیم است.

*[screenshot-1.png] — متا باکس پیشنهادها در ویرایشگر بلاک*

### ۲) اتولینک هوشمند
**لینکیار › اتولینک › افزودن قانون:**
- **کلیدواژه‌ها:** چند کلیدواژه با ویرگول (مثال: `افزونه وردپرس, سئو`).
- **صفحهٔ هدف:** انتخاب پست/صفحه یا وارد کردن آدرس دلخواه.
- **حداکثر لینک در هر پست** و گزینهٔ **فقط اولین مورد**.
- **دسته‌های مستثنی:** قانون روی پست‌های این دسته‌ها اعمال نمی‌شود.
- نتیجه فقط در برگه‌های تکی (singular) اعمال می‌شود؛ داخل لینک‌ها، `<pre>` و `<code>` هرگز تغییری ایجاد نمی‌شود.

*[screenshot-2.png] — جدول قوانین و پنل کشویی افزودن قانون*

### ۳) گزارش لینک‌های شکسته
**لینکیار › گزارش لینک‌ها › شروع اسکن:**
- مرحلهٔ ۱: جمع‌آوری لینک‌های داخلی (۲۰ پست در هر درخواست).
- مرحلهٔ ۲: بررسی هر آدرس با `wp_remote_head` (۲۵ آدرس در هر درخواست) و رده‌بندی: **سالم / خراب / ریدایرکت / خطا**.
- با تب‌ها فیلتر کنید، هر نتیجه را حذف کنید یا کل گزارش را پاک‌سازی کنید.

*[screenshot-3.png] — صفحهٔ گزارش با نوار پیشرفت و تب‌ها*

### ۴) داشبورد
- ۴ کارت KPI: پست‌های دارای لینک داخلی · لینک‌های داخلی سالم · لینک‌های خراب · قوانین فعال.
- فهرست ۵ پستِ بدون لینک داخلی و ۵ لینک خراب اخیر.
- کلید وضعیت اتولینک و تاریخ آخرین اسکن (جلالی).

*[screenshot-4.png] — داشبورد لینکیار*

---

## سؤالات متداول / FAQ

**آیا افزونه روی سرعت سایت تأثیر می‌گذارد؟ / Does it slow the site down?**
خیر. اتولینک فقط روی برگه‌های تکی اجرا می‌شود، قوانین ۱۲ ساعت کش می‌شوند و اسکن لینک‌ها به‌صورت دسته‌ای در پس‌زمینه انجام می‌شود. هیچ کوئری اضافه‌ای برای بازدیدکنندگانِ خارج از سیستم اجرا نمی‌شود.
No. Auto-linking runs only on singular views, rules are cached for 12h and the link scanner works in AJAX batches. No extra queries for logged-out visitors.

**با افزونهٔ «فارسیساز» (WP-Parsidate) سازگار است؟ / Is it compatible with WP-Parsidate?**
بله — تاریخ‌های پنل مدیریت با فعال بودن فارسیساز به شمسی (مثل «۴ آبان ۱۴۰۵») نمایش داده می‌شوند و بدون آن به تاریخ محلی وردپرس برمی‌گردد. هیچ فونت یا RTL سفارشی‌ای لود نمی‌شود تا با فارسیساز تداخلی پیش نیاید.
Yes — admin dates render in Jalali when WP-Parsidate is active and fall back to WordPress' localized date otherwise. No custom font/RTL is loaded, so nothing conflicts.

**لینک‌های خارجی هم بررسی می‌شوند؟ / Are external links checked?**
خیر؛ فقط لینک‌های داخلی (با دامنهٔ سایت شما) اسکن می‌شوند.
No — only same-host internal links are scanned.

**داده‌ها کجا ذخیره می‌شوند؟ / Where is data stored?**
همه در optionهای وردپرس (`linkyar_*`)؛ با حذف افزونه پاک می‌شوند (`uninstall.php`).
All in WordPress options (`linkyar_*`); removed on uninstall (`uninstall.php`).

---

## ساختار پوشه‌ها / Folder structure

```
linkyar.php              — نقطهٔ ورود و ثبت هوک‌ها / bootstrap
uninstall.php            — پاک‌سازی داده‌ها / data cleanup
includes/
  class-plugin.php       — منوها و هِلپرهای UI / menus + UI helpers
  class-suggestions.php  — فیچر ۱
  class-autolink.php     — فیچر ۲
  class-report.php       — فیچر ۳
  class-dashboard.php    — داشبورد + ویزارد / dashboard + wizard
  class-settings.php     — Settings API
  class-assets.php       — انتشار CSS/JS / asset loading
  class-i18n.php         — بارگذاری ترجمه / text domain
  class-jalali.php       — تاریخ جلالی + اعداد فارسی / Jalali dates
admin/
  js/admin.js            — کل رابط کاربری / all UI logic
  css/admin.css          — استایل RTL مدرن / modern RTL styles
languages/
  linkyar.pot            — الگوی ترجمه / translation template
  linkyar-fa_IR.po       — ترجمهٔ فارسی / Persian translation
  linkyar-fa_IR.mo       — فایل باینری / compiled translations
```

---

## مجوز / License

GPL-2.0-or-later — جزئیات در [LICENSE](./LICENSE).
