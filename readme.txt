=== LinkYar (لینکیار) ===
Contributors: rmombeni
Tags: internal linking, link suggestions, auto link, broken links, persian
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Persian internal-linking assistant: link suggestions in the editor, smart auto-linking with ZWNJ-aware matching, and a batch broken-link report.

== Description ==

لینکیار یک افزونهٔ لینک‌سازی داخلی وردپرس با رابط کاربری فارسی (RTL) است:

* **پیشنهاد لینک داخلی:** متا باکس در ویرایشگر با امتیازدهی هوشمند و درج لینک با یک کلیک.
* **اتولینک هوشمند:** قوانین کلیدواژه به صفحهٔ هدف، با احترام کامل به نیم‌فاصله و بدون تداخل با لینک‌ها/کدهای موجود.
* **گزارش لینک‌های شکسته:** اسکن دسته‌ای با نوار پیشرفت، رده‌بندی سالم/خراب/ریدایرکت.
* **داشبورد و ویزارد راه‌اندازی** و سازگاری با تاریخ جلالی (افزونهٔ فارسیساز).

LinkYar is a freemium-ready internal-linking assistant with a Persian-first RTL UI: editor link suggestions, smart auto-linking that respects Persian half-spaces (ZWNJ), and an AJAX batch scanner for broken internal links. Plain PHP 7.4+ / WordPress 6.0+, no build step, no composer/npm dependencies.

== Installation ==

1. Upload the `linkyar` folder to `/wp-content/plugins/` (or install the zip via Plugins › Add New).
2. Activate the plugin through the «افزونه‌ها» menu.
3. Follow the 3-step onboarding wizard.
4. Open «لینکیار › داشبورد» and run your first scan.

== Frequently Asked Questions ==

= Does it slow down my site? =
No. Auto-linking runs only on singular views with a 12-hour rules cache, and scans run in small AJAX batches. No extra queries for logged-out visitors.

= Is it compatible with WP-Parsidate (فارسیساز)? =
Yes. Admin dates render as Jalali (e.g. «۴ آبان ۱۴۰۵») when WP-Parsidate is active and gracefully fall back to WordPress localized dates otherwise. No custom fonts are loaded.

= Are external links checked? =
No. The scanner only follows same-host internal links.

== Screenshots ==

1. Dashboard with KPI cards and quick actions [screenshot-1.png]
2. Link suggestions meta box in the block editor [screenshot-2.png]
3. Autolink rules table and slide-in rule editor [screenshot-3.png]
4. Broken link report with progress bar and filter tabs [screenshot-4.png]

== Changelog ==

= 1.0.0 =
* Initial release: link suggestions, smart auto-linking, broken link report, dashboard, onboarding wizard, Jalali date support.

== Upgrade Notice ==

= 1.0.0 =
First stable release.
