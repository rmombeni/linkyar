# LinkYar (لینکیار) — Build Prompt (Full Spec)

You are a senior WordPress plugin engineer. Build a complete, production-ready WordPress plugin from scratch, inside the current folder, with NO build step (plain PHP + vanilla JS/CSS), NO composer/npm dependencies, compatible with PHP 7.4+ and WordPress 6.0+.

## PROJECT: LinkYar (لینکیار) — Persian internal-linking assistant

A freemium WordPress plugin that helps site owners with internal linking. Brand: "لینکیار" (Persian: لینک = link, یار = helper). Slug: linkyar. Text domain: linkyar. Prefix all functions/classes/hooks/options with linkyar_ / Linkyar. UI language: Persian (fa_IR, RTL) as the primary admin language, with all strings wrapped in __() / _e() and a .pot file for translation. Full plugin header in linkyar.php with License: GPL-2.0-or-later.

## FEATURE 1 — Link Suggestions in the editor (پیشنهاد لینک داخلی)
- Register a meta box "پیشنهادهای لینک داخلی" shown on post editor screens (post, page; filterable via linkyar_suggested_post_types).
- On editor load, fetch suggestions via admin-ajax (action linkyar_get_suggestions, nonce-checked, cap edit_posts).
- Server logic (class Linkyar_Suggestions):
  * Strip common Persian + English stopwords from the current post title (lists: و، در، به، از، که، این، آن، با، برای، های، را، یک، برای، is، the، a، of، for...).
  * Score other published posts (exclude current ID): count keyword matches — title matches weigh 3x, content matches 1x; +1 bonus if same category. Use a single WP_Query with 's' for efficiency plus category boost.
  * Return top 8 with: post_title, guid/link, matched keywords array, score.
- In the meta box (plain JS enqueued in editor): render list; each item shows title + matched keywords; a button "درج لینک" inserts a paragraph block at the end of the content containing the linked title using wp.data.dispatch('core/editor').insertBlocks and createBlock('core/paragraph', { content: '<a href="URL">TITLE</a>' }); a second button "کپی لینک" copies the URL.
- Also show a small "تازه‌سازی" (refresh) link.

## FEATURE 2 — Smart Auto-linking (اتولینک هوشمند)
- Admin submenu "قوانین اتولینک" under the plugin's own top-level admin menu.
- CRUD table of rules, each rule: keyword(s) (comma-separated), target (post select via wp_dropdown_pages-like or URL input), max_links_per_post (int, default 1), case_sensitive (bool), only_first (bool), exclude_categories (multi-select), enabled (bool).
- Store rules in a single option (array) linkyar_autolink_rules.
- Frontend hook: apply_filters('the_content') and 'the_excerpt', only on singular views for performance.
- Replacement algorithm:
  * Split content on existing <a ...>...</a> blocks and replace keywords only in non-anchor text segments (never nest links).
  * Skip inside <pre>, <code>, <script>, <style>.
  * Use word-boundary regex for Latin (\b) and, for Persian words, lookarounds that respect ZWNJ (half-space U+200C) — match the keyword as a whole token; do not break words like «می‌خواهم» when keyword is «خواهم».
  * Enforce max_links_per_post across the whole content; if only_first is set, replace only the first occurrence.
- Cache rules in a transient (12h) with a flush hook on save (register settings + rules CRUD).

## FEATURE 3 — Broken Link Report (گزارش لینک‌های شکسته)
- Admin submenu "گزارش لینک‌ها".
- "شروع اسکن" button → AJAX batch scan (action linkyar_scan_links, nonce, cap manage_options):
  * Pass 1: iterate published posts in batches of 20 per request; extract internal <a href> values (regex); normalize relative URLs against home_url(); keep only same-host links; collect unique URLs with source post IDs.
  * Pass 2: for each unique URL, wp_remote_head (timeout 5, redirection 3, sslverify=false acceptable but note), classify: ok (2xx), broken (404/410), redirect (301/302 + final URL), error (timeout/other). Store persistent results in option linkyar_link_report { url, status, http_code, final_url, sources: [post IDs], checked_at }.
  * Run ~25 URL checks per AJAX request, return progress { processed, total }, client shows a progress bar; disable the button while running.
- Results table with filter tabs (همه / خراب / ریدایرکت): columns لینک، وضعیت، کد، پست‌های مبدأ، آخرین بررسی. Re-scan button. Empty state text.

## SETTINGS PAGE
- Top-level menu "لینکیار" with icon and submenus: پیشنهاد لینک (enabled toggle, suggestion count 5–15), اتولینک (enabled toggle, default max links), گزارش لینک‌ها (scan controls), تنظیمات (excluded post types checkboxes, excluded category IDs, capability filter).
- Use the Settings API (register_setting, add_settings_section/field) with proper sanitize callbacks. Nonce/action fields everywhere.

## SECURITY & CODE STANDARDS (non-negotiable)
- Follow WordPress Coding Standards (phpcs ruleset: WordPress-Core; include phpcs.xml.dist).
- Escape all output: esc_html, esc_url, esc_attr, wp_kses where needed. Sanitize all input: sanitize_text_field, absint, sanitize_key.
- All AJAX handlers: check_ajax_referer + current_user_can.
- No direct file access (if ( ! defined( 'ABSPATH' ) ) exit;).
- Internationalization: load_plugin_textdomain in init; include linkyar.pot and a fa_IR translation (linkyar-fa_IR.po/.mo) covering ALL admin strings.
- Admin styles: RTL-aware (dir=rtl on admin pages is automatic for fa), clean modern design with the brand color #0e7490 (teal); admin.css + admin.js enqueued only on plugin pages.
- Performance: no extra queries on frontend for logged-out users beyond the_content filter; cache where possible.

## FILE STRUCTURE (create all)
```
linkyar.php          — plugin header, constants (PLYAR_VERSION), loader
uninstall.php           — delete options on uninstall
readme.txt              — WordPress.org-style (Persian description + English)
README.md               — BILINGUAL (fa + en): install, usage of each feature, screenshots placeholders, FAQ, license
CHANGELOG.md
LICENSE                 — GPL-2.0-or-later text
phpcs.xml.dist          — WordPress-Core ruleset
includes/
  class-plugin.php      — bootstrap, hooks, menu registration
  class-suggestions.php — Feature 1 (ajax + scoring)
  class-autolink.php    — Feature 2 (rules + the_content)
  class-report.php      — Feature 3 (scan + results)
  class-settings.php    — settings API + sanitize
  class-assets.php      — enqueue admin js/css
  class-i18n.php        — textdomain load
admin/
  js/admin.js           — suggestions meta box + autolink rules CRUD + report scanner
  css/admin.css         — RTL-friendly admin styles, WP-admin default font only
includes/
  class-jalali.php      — Jalali date helper: uses wp-parsidate's parsidate() when active, else date_i18n() fallback (used for «آخرین بررسی» and dashboard dates)
languages/
  linkyar.pot
  linkyar-fa_IR.po   — with translated Persian strings
  linkyar-fa_IR.mo
```

## DELIVERABLES
1. Complete, working code for all three features.
2. A plugin zip-ready folder (the current folder).
3. Brief comments in code (English) explaining each hook and algorithm.
4. README.md bilingual with badges (version, WP tested, license), installation steps in Persian, usage guide for each feature with screenshots placeholders like [screenshot-1.png].

## ACCEPTANCE CHECKLIST (verify yourself before finishing)
- Activation hook runs without fatal errors (use register_activation_hook only for version storage).
- Menu + 4 submenus render, RTL, Persian.
- Suggestions AJAX returns valid JSON and meta box renders in the editor.
- the_content auto-link works on a demo post, respects max_links, skips existing links and code blocks.
- Scan completes in batches, report table filters work.
- phpcs clean (WordPress-Core) — run `phpcs --standard=WordPress-Core` if available.
- No PHP notices/warnings on a debug site (WP_DEBUG true).
- With WP-Parsidate active: admin dates are Jalali (۴ آبان ۱۴۰۵), layout stays RTL, no font conflicts, no CSS/JS enqueues outside plugin pages.
- Plugin works correctly with WP-Parsidate BOTH active and inactive (graceful fallback).

## UI/UX REQUIREMENTS — modern, polished, user-friendly (non-negotiable)

This plugin must feel like a premium, modern SaaS product — not a typical cluttered WP admin plugin. Design language: clean, airy, rounded, teal brand (#0e7490 primary, #14b8a6 accent, #0f172a dark slate text, #f8fafc background, #f1f5f9 cards, #ef4444 danger, #10b981 success, #f59e0b warning). Light theme only. Subtle shadows (0 1px 3px rgba(0,0,0,0.08)), 8px corner radius base, generous whitespace, 14px base font.

### Font & RTL — MUST harmonize with WP-Parsidate (فارسیساز)
- **Do NOT load any custom font.** Use the WordPress admin dashboard's default font stack as-is (body font). The site may run wp-parsidate (https://fa.wordpress.org/plugins/wp-parsidate/) which controls Persian font + RTL + Jalali dates in the admin; this plugin must blend in seamlessly and never override, enqueue, or fight that font/RTL.
- Compatibility target (tested with): **WP-Parsidate (فارسیساز)** active — dates must render in **Jalali (شمسی)** using wp-parsidate's Persian date functions when available (e.g. its parsidate() / jdate helpers), with a graceful fallback to WordPress' date_i18n() when the plugin is not active.
- All dates shown anywhere (last scan, report rows "آخرین بررسی", KPI labels) MUST be Jalali when wp-parsidate is active, formatted like «۴ آبان ۱۴۰۵» (or with year ۱۴۰۵), never Gregorian.
- RTL: rely on the admin's existing dir=rtl (WP sets it automatically for fa_IR, and wp-parsidate reinforces it). All layouts must be RTL-first (right-aligned headers, slide-in panel from the LEFT edge since RTL flips, logical CSS properties preferred).
- Numbers: Persian numerals for human-facing values (۴، ۶) where natural; keep technical values Latin where standard (URLs, versions, HTTP codes).
- Icons: use Dashicons (WP core) — no external icon fonts.

### Onboarding wizard (first-run)
- On first activation, show a 3-step wizard (welcome → pick post types → create first autolink rule) with progress dots, back/next buttons, skip button. Dismissible; re-openable from dashboard. Wizard stores option linkyar_onboarded.

### Dashboard page (plugin home)
- KPI cards row (4): تعداد پستهای دارای لینک داخلی، تعداد لینک داخلی فعال، لینکهای خراب، قوانین اتولینک فعال.
- Quick actions: شروع اسکن لینکها، افزودن قانون، ساخت پیشنهاد جدید.
- Mini list: ۵ پست بدون لینک داخلی (weakest pages) + ۵ لینک خراب اخیر.
- Status panel: آخرین اسکن، وضعیت اتولینک (فعال/غیرفعال toggle)، نسخه افزونه.

### Every screen must have
- Page header with icon + title + one-line description + primary CTA button.
- Empty states (illustration-ish icon + title + hint + CTA button) — never a bare table.
- Loading states (skeleton rows or spinner with "در حال بارگذاری…").
- Success/error toasts (top-right, auto-dismiss 4s, teal for success, red for error) via a tiny vanilla JS toast system.
- Confirm dialogs for destructive actions (حذف قانون؟) using a custom modal (not window.confirm).
- Inline help: small "؟" tooltip (title attr or custom popover) explaining each setting.
- All buttons/inputs large enough for touch (min 36px height), clear labels, placeholder examples like «مثال: افزونه وردپرس».

### Editor meta box (Feature 1) UX
- Box with header row (title + refresh icon), search field to filter suggestions, list items with: post title, matched keywords as small chips, hover reveal two icon buttons (درج لینک / کپی لینک). Show "در حال جستجو…" skeleton while loading; empty state "پستی مرتبط پیدا نشد". Max height with internal scroll.
- Inserted link uses a nice anchor text (the post title) and adds it as a new paragraph at the end; toast confirms "لینک اضافه شد".

### Rules table (Feature 2) UX
- Table with row actions on hover (ویرایش / غیرفعالسازی / حذف). Add-rule form in a slide-in panel (right side, RTL aware) or modal — never a separate page. Form fields with labels, placeholders, and live keyword preview (shows how the keyword will appear). Toggle switches (styled checkboxes) for booleans.
- Inline validation: red border + message on error (keyword empty, target not selected).

### Report page (Feature 3) UX
- Big "شروع اسکن" button; while scanning show animated progress bar with percentage and "در حال بررسی ۴۵ از ۱۲۰…"; disable button during scan.
- Results as filterable tabs with counts badges (همه / خراب / ریدایرکت); table rows: status pill (سبز=سالم، قرمز=خراب، زرد=ریدایرکت), URL (monospace, clickable), code badge, sources as links, checked time. "حذف نتیجه" per row + "اسکن مجدد" button.

### Accessibility & mobile
- WCAG AA contrast; keyboard navigable (tab order, focus rings visible); aria-labels on icon buttons; alt texts. Admin pages usable at 360px width (tables collapse to cards on small screens).

### UX principles to follow
- Never show raw PHP errors to users; wrap in friendly messages.
- One primary action per screen; secondary actions less prominent.
- Consistent terminology: «قانون»، «کلیدواژه»، «صفحهٔ هدف»، «اسکن»، «لینک شکسته».
- Every destructive/expensive action needs confirmation; every long task needs progress feedback.
- Settings save via Settings API with "ذخیره تغییرات" button + toast; auto-reload where needed.
