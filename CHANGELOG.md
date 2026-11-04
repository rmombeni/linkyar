# Changelog

## 1.0.0 — 2026-11-04

### Added
- **پیشنهاد لینک داخلی (Link Suggestions):** editor meta box with scored suggestions
  (title match ×3, content match ×1, same-category bonus), search filter, one-click
  block-editor link insertion and copy-to-clipboard.
- **اتولینک هوشمند (Smart Auto-linking):** rule CRUD with slide-in panel, per-rule
  keyword budgets, ZWNJ-aware Persian word boundaries, existing-anchor and
  `<pre>/<code>/<script>/<style>` protection, 12h rules cache.
- **گزارش لینک‌های شکسته (Broken Link Report):** two-phase AJAX batch scanner
  (20 posts / 25 URL checks per request) with progress bar, filter tabs, status
  pills and per-row actions.
- **داشبورد:** KPI cards, weakest-pages list, recent broken links, system status
  panel and quick actions.
- **راه‌اندازی سه‌مرحله‌ای (Onboarding wizard)** on first activation.
- **تاریخ جلالی:** WP-Parsidate (فارسیساز) integration with `date_i18n()` fallback,
  Persian numerals for human-facing values.
- Settings API screens (suggestions, autolink, general), RTL-first modern UI,
  empty/loading states, toasts, custom confirm modals, mobile-friendly tables.
- Translations: `languages/linkyar.pot`, `linkyar-fa_IR.po`, `linkyar-fa_IR.mo`.
- Security: nonces + capability checks on every AJAX handler, escaped output,
  sanitized input, no direct file access, `phpcs.xml.dist` (WordPress-Core).
