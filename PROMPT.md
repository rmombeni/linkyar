# PayvandYar (پیوندیار) — Build Prompt (Full Spec)

You are a senior WordPress plugin engineer. Build a complete, production-ready WordPress plugin from scratch, inside the current folder, with NO build step (plain PHP + vanilla JS/CSS), NO composer/npm dependencies, compatible with PHP 7.4+ and WordPress 6.0+.

## PROJECT: PayvandYar (پیوندیار) — Persian internal-linking assistant

A freemium WordPress plugin that helps site owners with internal linking. Brand: "پیوندیار" (Persian: پیوند = link, یار = helper). Slug: payvandyar. Text domain: payvandyar. Prefix all functions/classes/hooks/options with plyar_ / Plyar. UI language: Persian (fa_IR, RTL) as the primary admin language, with all strings wrapped in __() / _e() and a .pot file for translation. Full plugin header in payvandyar.php with License: GPL-2.0-or-later.

## FEATURE 1 — Link Suggestions in the editor (پیشنهاد لینک داخلی)
- Register a meta box "پیشنهادهای لینک داخلی" shown on post editor screens (post, page; filterable via plyar_suggested_post_types).
- On editor load, fetch suggestions via admin-ajax (action plyar_get_suggestions, nonce-checked, cap edit_posts).
- Server logic (class Plyar_Suggestions):
  * Strip common Persian + English stopwords from the current post title (lists: و، در، به، از، که، این، آن، با، برای، های، را، یک، برای، is، the، a، of، for...).
  * Score other published posts (exclude current ID): count keyword matches — title matches weigh 3x, content matches 1x; +1 bonus if same category. Use a single WP_Query with 's' for efficiency plus category boost.
  * Return top 8 with: post_title, guid/link, matched keywords array, score.
- In the meta box (plain JS enqueued in editor): render list; each item shows title + matched keywords; a button "درج لینک" inserts a paragraph block at the end of the content containing the linked title using wp.data.dispatch('core/editor').insertBlocks and createBlock('core/paragraph', { content: '<a href="URL">TITLE</a>' }); a second button "کپی لینک" copies the URL.
- Also show a small "تازه‌سازی" (refresh) link.

## FEATURE 2 — Smart Auto-linking (اتولینک هوشمند)
- Admin submenu "قوانین اتولینک" under the plugin's own top-level admin menu.
- CRUD table of rules, each rule: keyword(s) (comma-separated), target (post select via wp_dropdown_pages-like or URL input), max_links_per_post (int, default 1), case_sensitive (bool), only_first (bool), exclude_categories (multi-select), enabled (bool).
- Store rules in a single option (array) plyar_autolink_rules.
- Frontend hook: apply_filters('the_content') and 'the_excerpt', only on singular views for performance.
- Replacement algorithm:
  * Split content on existing <a ...>...</a> blocks and replace keywords only in non-anchor text segments (never nest links).
  * Skip inside <pre>, <code>, <script>, <style>.
  * Use word-boundary regex for Latin (\b) and, for Persian words, lookarounds that respect ZWNJ (half-space U+200C) — match the keyword as a whole token; do not break words like «می‌خواهم» when keyword is «خواهم».
  * Enforce max_links_per_post across the whole content; if only_first is set, replace only the first occurrence.
- Cache rules in a transient (12h) with a flush hook on save (register settings + rules CRUD).

## FEATURE 3 — Broken Link Report (گزارش لینک‌های شکسته)
- Admin submenu "گزارش لینک‌ها".
- "شروع اسکن" button → AJAX batch scan (action plyar_scan_links, nonce, cap manage_options):
  * Pass 1: iterate published posts in batches of 20 per request; extract internal <a href> values (regex); normalize relative URLs against home_url(); keep only same-host links; collect unique URLs with source post IDs.
  * Pass 2: for each unique URL, wp_remote_head (timeout 5, redirection 3, sslverify=false acceptable but note), classify: ok (2xx), broken (404/410), redirect (301/302 + final URL), error (timeout/other). Store persistent results in option plyar_link_report { url, status, http_code, final_url, sources: [post IDs], checked_at }.
  * Run ~25 URL checks per AJAX request, return progress { processed, total }, client shows a progress bar; disable the button while running.
- Results table with filter tabs (همه / خراب / ریدایرکت): columns لینک، وضعیت، کد، پست‌های مبدأ، آخرین بررسی. Re-scan button. Empty state text.

## SETTINGS PAGE
- Top-level menu "پیوندیار" with icon and submenus: پیشنهاد لینک (enabled toggle, suggestion count 5–15), اتولینک (enabled toggle, default max links), گزارش لینک‌ها (scan controls), تنظیمات (excluded post types checkboxes, excluded category IDs, capability filter).
- Use the Settings API (register_setting, add_settings_section/field) with proper sanitize callbacks. Nonce/action fields everywhere.

## SECURITY & CODE STANDARDS (non-negotiable)
- Follow WordPress Coding Standards (phpcs ruleset: WordPress-Core; include phpcs.xml.dist).
- Escape all output: esc_html, esc_url, esc_attr, wp_kses where needed. Sanitize all input: sanitize_text_field, absint, sanitize_key.
- All AJAX handlers: check_ajax_referer + current_user_can.
- No direct file access (if ( ! defined( 'ABSPATH' ) ) exit;).
- Internationalization: load_plugin_textdomain in init; include payvandyar.pot and a fa_IR translation (payvandyar-fa_IR.po/.mo) covering ALL admin strings.
- Admin styles: RTL-aware (dir=rtl on admin pages is automatic for fa), clean modern design with the brand color #0e7490 (teal); admin.css + admin.js enqueued only on plugin pages.
- Performance: no extra queries on frontend for logged-out users beyond the_content filter; cache where possible.

## FILE STRUCTURE (create all)
```
payvandyar.php          — plugin header, constants (PLYAR_VERSION), loader
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
  css/admin.css         — RTL-friendly admin styles
languages/
  payvandyar.pot
  payvandyar-fa_IR.po   — with translated Persian strings
  payvandyar-fa_IR.mo
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
