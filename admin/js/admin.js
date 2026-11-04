/**
 * LinkYar admin script — vanilla JS, no dependencies.
 * Sections: utils · toasts · modal · panel · suggestions · rules · report · dashboard · wizard.
 */
(function () {
	'use strict';

	var cfg = window.LinkyarAdmin || {};
	var S = cfg.strings || {};
	var NONCES = cfg.nonces || {};
	var AJAX = cfg.ajaxUrl || '';

	/* -----------------------------------------------------------------
	 * Utils
	 * ----------------------------------------------------------------- */

	function $(sel, root) {
		return (root || document).querySelector(sel);
	}

	function $$(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function esc(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function faNum(value) {
		return String(value).replace(/[0-9]/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(parseInt(d, 10));
		});
	}

	function post(action, data, nonce) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', nonce || '');

		Object.keys(data || {}).forEach(function (key) {
			var val = data[key];
			if (Array.isArray(val)) {
				val.forEach(function (item) {
					body.append(key + '[]', item);
				});
			} else if (val != null) {
				body.append(key, val);
			}
		});

		return fetch(AJAX, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (res) {
			return res.json();
		});
	}

	/* -----------------------------------------------------------------
	 * Toasts
	 * ----------------------------------------------------------------- */

	function ensureToastHost() {
		var host = $('#linkyar-toasts');
		if (!host) {
			host = document.createElement('div');
			host.id = 'linkyar-toasts';
			host.className = 'linkyar-toasts';
			host.setAttribute('aria-live', 'polite');
			document.body.appendChild(host);
		}
		return host;
	}

	function toast(message, type) {
		var host = ensureToastHost();
		var el = document.createElement('div');
		el.className = 'linkyar-toast' + (type === 'error' ? ' linkyar-toast--error' : '');
		el.setAttribute('role', 'status');
		el.textContent = message;
		host.appendChild(el);

		setTimeout(function () {
			el.classList.add('is-leaving');
			setTimeout(function () {
				if (el.parentNode) {
					el.parentNode.removeChild(el);
				}
			}, 220);
		}, 4000);
	}

	/* -----------------------------------------------------------------
	 * Confirm modal (custom — never window.confirm)
	 * ----------------------------------------------------------------- */

	function confirmDialog(title, text) {
		return new Promise(function (resolve) {
			var backdrop = document.createElement('div');
			backdrop.className = 'linkyar-modal-backdrop';
			backdrop.innerHTML =
				'<div class="linkyar-modal" role="alertdialog" aria-modal="true">' +
				'<h3 class="linkyar-modal__title">' + esc(title) + '</h3>' +
				'<p class="linkyar-modal__text">' + esc(text || '') + '</p>' +
				'<div class="linkyar-modal__actions">' +
				'<button type="button" class="linkyar-btn linkyar-btn--ghost" data-act="cancel">' + esc(S.cancel || 'انصراف') + '</button>' +
				'<button type="button" class="linkyar-btn linkyar-btn--confirm" data-act="ok">' + esc(S.confirm || 'تأیید') + '</button>' +
				'</div></div>';

			function close(result) {
				document.removeEventListener('keydown', onKey);
				if (backdrop.parentNode) {
					backdrop.parentNode.removeChild(backdrop);
				}
				resolve(result);
			}

			function onKey(e) {
				if (e.key === 'Escape') {
					close(false);
				}
			}

			backdrop.addEventListener('click', function (e) {
				if (e.target === backdrop) {
					close(false);
				}
				var act = e.target.closest('[data-act]');
				if (act) {
					close(act.getAttribute('data-act') === 'ok');
				}
			});
			document.addEventListener('keydown', onKey);

			document.body.appendChild(backdrop);
			var okBtn = backdrop.querySelector('[data-act="ok"]');
			if (okBtn) {
				okBtn.focus();
			}
		});
	}

	/* -----------------------------------------------------------------
	 * Feature 1 — Suggestions meta box
	 * ----------------------------------------------------------------- */

	function initSuggestions() {
		var box = $('#linkyar-suggestions');
		if (!box) {
			return;
		}

		var listEl = $('.linkyar-sugg__list', box);
		var searchEl = $('.linkyar-sugg__search', box);
		var refreshEl = $('.linkyar-sugg__refresh', box);
		var items = [];
		var nonce = box.getAttribute('data-nonce');
		var postId = box.getAttribute('data-post-id');
		var count = box.getAttribute('data-count') || '8';

		function skeleton() {
			listEl.innerHTML = '<div class="linkyar-sugg__skeleton" aria-hidden="true"><span></span><span></span><span></span></div>';
		}

		function render() {
			var q = (searchEl.value || '').trim().toLowerCase();
			var visible = items.filter(function (item) {
				if (!q) {
					return true;
				}
				if (item.title.toLowerCase().indexOf(q) !== -1) {
					return true;
				}
				return (item.keywords || []).some(function (kw) {
					return String(kw).toLowerCase().indexOf(q) !== -1;
				});
			});

			if (!visible.length) {
				listEl.innerHTML = '<div class="linkyar-sugg__empty">' + esc(S.no_suggestions || 'پستی مرتبط پیدا نشد') + '</div>';
				return;
			}

			listEl.innerHTML = visible.map(function (item) {
				var chips = (item.keywords || []).map(function (kw) {
					return '<span class="linkyar-chip">' + esc(kw) + '</span>';
				}).join('');

				return (
					'<div class="linkyar-sugg__item" role="listitem">' +
					'<div class="linkyar-sugg__main">' +
					'<span class="linkyar-sugg__title" title="' + esc(item.title) + '">' + esc(item.title) + '</span>' +
					'<div class="linkyar-sugg__keywords">' + chips + '</div>' +
					'</div>' +
					'<div class="linkyar-sugg__actions">' +
					'<button type="button" class="linkyar-icon-btn linkyar-sugg__insert" data-url="' + esc(item.url) + '" data-title="' + esc(item.title) + '" ' +
					'aria-label="' + esc(S.insert_link || 'درج لینک') + '" title="' + esc(S.insert_link || 'درج لینک') + '">' +
					'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>' +
					'<button type="button" class="linkyar-icon-btn linkyar-sugg__copy" data-url="' + esc(item.url) + '" ' +
					'aria-label="' + esc(S.copy_link || 'کپی لینک') + '" title="' + esc(S.copy_link || 'کپی لینک') + '">' +
					'<span class="dashicons dashicons-admin-links" aria-hidden="true"></span></button>' +
					'</div></div>'
				);
			}).join('');
		}

		function load() {
			skeleton();
			post('linkyar_get_suggestions', {
				post_id: postId,
				count: count
			}, nonce).then(function (res) {
				if (!res || !res.success) {
					listEl.innerHTML = '<div class="linkyar-sugg__empty">' + esc((res && res.data && res.data.message) || S.error || 'خطا') + '</div>';
					return;
				}
				items = (res.data && res.data.items) || [];
				render();
			}).catch(function () {
				listEl.innerHTML = '<div class="linkyar-sugg__empty">' + esc(S.error || 'خطا') + '</div>';
			});
		}

		function currentEditorPostId() {
			if (window.wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
				var edited = wp.data.select('core/editor').getEditedPostAttribute('id');
				if (edited) {
					return edited;
				}
			}
			var input = document.getElementById('post_ID');
			return input ? input.value : postId;
		}

		function insertLink(url, title) {
			var anchor = '<a href="' + url + '">' + title + '</a>';

			// Block editor: append a paragraph block at the end of the content.
			if (window.wp && wp.data && wp.data.dispatch && wp.blocks && wp.data.dispatch('core/editor')) {
				try {
					var block = wp.blocks.createBlock('core/paragraph', { content: anchor });
					wp.data.dispatch('core/editor').insertBlocks(block);
					toast(S.link_added || 'لینک اضافه شد');
					return;
				} catch (err) {
					/* fall through to classic editor */
				}
			}

			// Classic editor fallback: append to #content textarea.
			var area = document.getElementById('content');
			if (area) {
				var sep = area.value && !/\n\n$/.test(area.value) ? '\n\n' : '';
				area.value += sep + '<p>' + anchor + '</p>';
				toast(S.link_added || 'لینک اضافه شد');
				return;
			}

			toast(S.error || 'خطایی رخ داد. دوباره تلاش کنید.', 'error');
		}

		function copyUrl(url) {
			function fallback() {
				var ta = document.createElement('textarea');
				ta.value = url;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try {
					document.execCommand('copy');
					toast(S.copied || 'لینک کپی شد');
				} catch (err) {
					toast(S.error || 'خطا', 'error');
				}
				document.body.removeChild(ta);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () {
					toast(S.copied || 'لینک کپی شد');
				}).catch(fallback);
			} else {
				fallback();
			}
		}

		box.addEventListener('click', function (e) {
			var insertBtn = e.target.closest('.linkyar-sugg__insert');
			if (insertBtn) {
				currentEditorPostId(); // keep parity with server-side post id
				insertLink(insertBtn.getAttribute('data-url'), insertBtn.getAttribute('data-title'));
				return;
			}

			var copyBtn = e.target.closest('.linkyar-sugg__copy');
			if (copyBtn) {
				copyUrl(copyBtn.getAttribute('data-url'));
				return;
			}

			if (e.target.closest('.linkyar-sugg__refresh')) {
				refreshEl.classList.add('is-spinning');
				load();
				setTimeout(function () {
					refreshEl.classList.remove('is-spinning');
				}, 800);
			}
		});

		searchEl.addEventListener('input', render);

		load();
	}

	/* -----------------------------------------------------------------
	 * Feature 2 — Autolink rules CRUD
	 * ----------------------------------------------------------------- */

	function initRules() {
		var page = $('.linkyar-rules');
		if (!page) {
			return;
		}

		var nonce = page.getAttribute('data-nonce');
		var panel = $('#linkyar-rule-panel');
		var backdrop = $('#linkyar-rule-backdrop');
		var tbody = $('#linkyar-rules-table tbody');
		var tableCard = $('#linkyar-rules-table') ? $('#linkyar-rules-table').closest('.linkyar-table-card') : null;

		var fields = {
			id: $('#linkyar-rule-id'),
			keywords: $('#linkyar-rule-keywords'),
			targetPost: $('#linkyar-rule-target-post'),
			targetUrl: $('#linkyar-rule-target-url'),
			max: $('#linkyar-rule-max'),
			first: $('#linkyar-rule-first'),
			caseSensitive: $('#linkyar-rule-case'),
			enabled: $('#linkyar-rule-enabled'),
			cats: $('#linkyar-rule-cats')
		};

		var kwError = $('#linkyar-rule-keywords-error');
		var targetError = $('#linkyar-rule-target-error');
		var panelTitle = $('#linkyar-rule-panel-title');

		// Cache rules from the DOM (id + data) for edit prefill.
		function ruleFromRow(row) {
			var id = parseInt(row.getAttribute('data-id'), 10);
			return {
				id: id,
				keywords: $$('.linkyar-chip', row).map(function (chip) {
					return chip.textContent;
				}).join(', '),
				target_url: row.querySelector('.linkyar-url') ? row.querySelector('.linkyar-url').getAttribute('href') : '',
				max_links: row.children[2] ? parseInt(row.children[2].textContent.replace(/[۰-۹]/g, function (d) {
					return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
				}), 10) || 1 : 1,
				enabled: row.querySelector('.linkyar-rule-toggle') ? row.querySelector('.linkyar-rule-toggle').checked : true
			};
		}

		function openPanel(rule) {
			kwError.hidden = true;
			targetError.hidden = true;
			fields.keywords.classList.remove('is-error');
			fields.targetPost.classList.remove('is-error');
			fields.targetUrl.classList.remove('is-error');

			if (rule) {
				panelTitle.textContent = 'ویرایش قانون';
				fields.id.value = rule.id || 0;
				fields.keywords.value = rule.keywords || '';
				fields.targetUrl.value = rule.target_url || '';
				fields.targetPost.value = rule.target_post || 0;
				fields.max.value = rule.max_links || 1;
				fields.first.checked = !!rule.only_first;
				fields.caseSensitive.checked = !!rule.case_sensitive;
				fields.enabled.checked = rule.enabled !== 0 && rule.enabled !== false;
				$$('option', fields.cats).forEach(function (opt) {
					opt.selected = (rule.exclude_categories || []).map(String).indexOf(opt.value) !== -1;
				});
			} else {
				panelTitle.textContent = 'افزودن قانون';
				fields.id.value = 0;
				fields.keywords.value = '';
				fields.targetUrl.value = '';
				fields.targetPost.value = 0;
				fields.max.value = 1;
				fields.first.checked = false;
				fields.caseSensitive.checked = false;
				fields.enabled.checked = true;
				$$('option', fields.cats).forEach(function (opt) {
					opt.selected = false;
				});
			}

			updatePreview();
			panel.hidden = false;
			backdrop.hidden = false;
			fields.keywords.focus();
		}

		function closePanel() {
			panel.hidden = true;
			backdrop.hidden = true;
		}

		function updatePreview() {
			var preview = $('#linkyar-keyword-preview');
			var raw = fields.keywords.value.split(/[,،\n]+/).map(function (s) {
				return s.trim();
			}).filter(Boolean);

			if (!raw.length) {
				preview.textContent = '—';
				return;
			}

			preview.innerHTML = raw.slice(0, 3).map(function (kw) {
				return '<span class="linkyar-chip">' + esc(kw) + '</span>';
			}).join(' ');
		}

		function validate() {
			var ok = true;
			var kw = fields.keywords.value.trim();
			var hasTarget = (fields.targetPost.value && fields.targetPost.value !== '0') || fields.targetUrl.value.trim();

			kwError.hidden = !!kw;
			fields.keywords.classList.toggle('is-error', !kw);
			if (!kw) {
				ok = false;
			}

			targetError.hidden = !!hasTarget;
			fields.targetPost.classList.toggle('is-error', !hasTarget);
			fields.targetUrl.classList.toggle('is-error', !hasTarget);
			if (!hasTarget) {
				ok = false;
			}

			return ok;
		}

		function rowHtml(rule) {
			var chips = (rule.keywordList || []).map(function (kw) {
				return '<span class="linkyar-chip">' + esc(kw) + '</span>';
			}).join('');
			var enabled = !!rule.enabled;
			var maxLabel = rule.only_first ? 1 : (rule.max_links || 1);

			return (
				'<td data-label="کلیدواژه‌ها"><div class="linkyar-keywords">' + chips + '</div></td>' +
				'<td data-label="صفحهٔ هدف"><a class="linkyar-url" href="' + esc(rule.target_url) + '" target="_blank" rel="noopener noreferrer">' + esc(rule.target_url) + '</a></td>' +
				'<td data-label="حداکثر لینک">' + faNum(maxLabel) + '</td>' +
				'<td data-label="وضعیت"><label class="linkyar-toggle linkyar-toggle--sm">' +
				'<input type="checkbox" class="linkyar-rule-toggle" data-id="' + esc(rule.id) + '"' + (enabled ? ' checked' : '') + ' />' +
				'<span class="linkyar-toggle__track" aria-hidden="true"></span>' +
				'<span class="linkyar-toggle__label">' + (enabled ? 'فعال' : 'غیرفعال') + '</span></label></td>' +
				'<td data-label="عملیات"><div class="linkyar-row-actions">' +
				'<button type="button" class="linkyar-icon-btn linkyar-rule-edit" data-id="' + esc(rule.id) + '" aria-label="ویرایش" title="ویرایش"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button>' +
				'<button type="button" class="linkyar-icon-btn linkyar-icon-btn--danger linkyar-rule-delete" data-id="' + esc(rule.id) + '" aria-label="حذف" title="حذف"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' +
				'</div></td>'
			);
		}

		function ensureTable() {
			if (tbody) {
				return tbody;
			}
			// Table did not exist (empty state) — build it.
			var card = $('.linkyar-table-card', page);
			card.innerHTML =
				'<div class="linkyar-table-scroll"><table class="linkyar-table linkyar-rules-table" id="linkyar-rules-table">' +
				'<thead><tr><th>کلیدواژه‌ها</th><th>صفحهٔ هدف</th><th>حداکثر لینک</th><th>وضعیت</th><th><span class="screen-reader-text">عملیات</span></th></tr></thead>' +
				'<tbody></tbody></table></div>';
			tbody = $('#linkyar-rules-table tbody');
			return tbody;
		}

		function openEditorForId(id) {
			// Prefill from row DOM + hidden data attributes filled on save responses.
			var row = tbody && tbody.querySelector('tr[data-id="' + id + '"]');
			if (!row) {
				return;
			}
			var rule = ruleFromRow(row);
			rule.title = 'ویرایش قانون';

			// Supplement from data-* if present (filled after AJAX saves).
			if (row.dataset.targetPost) {
				rule.target_post = row.dataset.targetPost;
			}
			if (row.dataset.maxLinks) {
				rule.max_links = parseInt(row.dataset.maxLinks, 10);
			}
			if (row.dataset.onlyFirst) {
				rule.only_first = row.dataset.onlyFirst === '1';
			}
			if (row.dataset.caseSensitive) {
				rule.case_sensitive = row.dataset.caseSensitive === '1';
			}
			if (row.dataset.cats) {
				try {
					rule.exclude_categories = JSON.parse(row.dataset.cats);
				} catch (err) {
					rule.exclude_categories = [];
				}
			}
			openPanel(rule);
		}

		function saveRule() {
			if (!validate()) {
				return;
			}

			var data = {
				rule_id: fields.id.value || 0,
				keywords: fields.keywords.value,
				target_post: fields.targetPost.value || 0,
				target_url: fields.targetUrl.value || '',
				max_links: fields.max.value || 1
			};

			if (fields.first.checked) {
				data.only_first = 1;
			}
			if (fields.caseSensitive.checked) {
				data.case_sensitive = 1;
			}
			if (fields.enabled.checked) {
				data.enabled = 1;
			}

			var selectedCats = $$('option:checked', fields.cats).map(function (opt) {
				return opt.value;
			});
			if (selectedCats.length) {
				data.exclude_categories = selectedCats;
			}

			var saveBtn = $('#linkyar-rule-save');
			saveBtn.disabled = true;

			post('linkyar_save_rule', data, nonce).then(function (res) {
				saveBtn.disabled = false;
				if (!res || !res.success) {
					toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
					return;
				}

				var rule = res.data.rule;
				rule.keywordList = String(rule.keywords).split(/[,،]+/).map(function (s) {
					return s.trim();
				}).filter(Boolean);
				rule.target_post = rule.target_post;
				rule.exclude_categories = rule.exclude_categories || [];

				var body = ensureTable();
				var existing = body.querySelector('tr[data-id="' + rule.id + '"]');
				var html = rowHtml(rule);

				if (existing) {
					existing.innerHTML = html;
				} else {
					var tr = document.createElement('tr');
					tr.setAttribute('data-id', rule.id);
					tr.innerHTML = html;
					body.appendChild(tr);
				}

				// Stash full rule data for later edits.
				var row = body.querySelector('tr[data-id="' + rule.id + '"]');
				if (row) {
					row.dataset.targetPost = rule.target_post || 0;
					row.dataset.maxLinks = rule.max_links || 1;
					row.dataset.onlyFirst = rule.only_first ? '1' : '0';
					row.dataset.caseSensitive = rule.case_sensitive ? '1' : '0';
					row.dataset.cats = JSON.stringify(rule.exclude_categories);
				}

				// Remove empty-state CTA if the table just appeared.
				var emptyCta = $('#linkyar-add-rule-empty');
				if (emptyCta) {
					var emptyWrap = emptyCta.closest('.linkyar-empty');
					if (emptyWrap) {
						emptyWrap.remove();
					}
				}

				toast(res.data.message || S.saved || 'ذخیره شد');
				closePanel();
			}).catch(function () {
				saveBtn.disabled = false;
				toast(S.error || 'خطا', 'error');
			});
		}

		page.addEventListener('click', function (e) {
			if (e.target.closest('#linkyar-add-rule') || e.target.closest('#linkyar-add-rule-empty')) {
				openPanel(null);
				return;
			}
			if (e.target.closest('#linkyar-rule-close') || e.target.closest('#linkyar-rule-cancel') || e.target.closest('#linkyar-rule-backdrop')) {
				closePanel();
				return;
			}
			if (e.target.closest('#linkyar-rule-save')) {
				saveRule();
				return;
			}

			var editBtn = e.target.closest('.linkyar-rule-edit');
			if (editBtn) {
				openEditorForId(parseInt(editBtn.getAttribute('data-id'), 10));
				return;
			}

			var delBtn = e.target.closest('.linkyar-rule-delete');
			if (delBtn) {
				var ruleId = parseInt(delBtn.getAttribute('data-id'), 10);
				confirmDialog(S.confirm_delete || 'قانون حذف شود؟', '').then(function (yes) {
					if (!yes) {
						return;
					}
					post('linkyar_delete_rule', { rule_id: ruleId }, nonce).then(function (res) {
						if (!res || !res.success) {
							toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
							return;
						}
						var row = tbody && tbody.querySelector('tr[data-id="' + ruleId + '"]');
						if (row) {
							row.remove();
						}
						toast(res.data.message || S.deleted || 'حذف شد');
					}).catch(function () {
						toast(S.error || 'خطا', 'error');
					});
				});
			}
		});

		page.addEventListener('change', function (e) {
			if (e.target.classList.contains('linkyar-rule-toggle')) {
				var toggle = e.target;
				var ruleId = parseInt(toggle.getAttribute('data-id'), 10);
				post('linkyar_toggle_rule', { rule_id: ruleId }, nonce).then(function (res) {
					if (!res || !res.success) {
						toggle.checked = !toggle.checked;
						toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
						return;
					}
					var label = toggle.closest('label').querySelector('.linkyar-toggle__label');
					if (label) {
						label.textContent = toggle.checked ? 'فعال' : 'غیرفعال';
					}
					toast(res.data.message || S.saved || 'ذخیره شد');
				}).catch(function () {
					toggle.checked = !toggle.checked;
					toast(S.error || 'خطا', 'error');
				});
			}
		});

		fields.keywords.addEventListener('input', updatePreview);

		// Deep-link: ?newrule=1 opens the panel (dashboard quick action).
		if (/[?&]newrule=1/.test(window.location.search)) {
			openPanel(null);
		}
	}

	/* -----------------------------------------------------------------
	 * Feature 3 — Broken link scanner
	 * ----------------------------------------------------------------- */

	function initReport() {
		var page = $('.linkyar-report');
		if (!page) {
			return;
		}

		var nonce = page.getAttribute('data-nonce');
		var startBtns = [$('#linkyar-scan-start'), $('#linkyar-scan-start-empty')].filter(Boolean);
		var progressWrap = $('#linkyar-scan-progress');
		var bar = $('#linkyar-scan-bar');
		var pct = $('#linkyar-scan-pct');
		var label = $('#linkyar-scan-label');
		var progressbar = $('#linkyar-progressbar');
		var running = false;

		function setProgress(processed, total, text) {
			var ratio = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
			if (bar) {
				bar.style.width = ratio + '%';
			}
			if (pct) {
				pct.textContent = faNum(ratio) + '٪';
			}
			if (label && text) {
				label.textContent = text;
			}
			if (progressbar) {
				progressbar.setAttribute('aria-valuenow', String(ratio));
			}
		}

		function step(name) {
			return post('linkyar_scan_links', { step: name }, nonce);
		}

		function runScan() {
			if (running) {
				return;
			}
			running = true;
			startBtns.forEach(function (btn) {
				btn.disabled = true;
			});
			if (progressWrap) {
				progressWrap.hidden = false;
			}
			if (progressbar) {
				progressbar.classList.add('is-active');
			}
			setProgress(0, 100, S.extracting || 'در حال جمع‌آوری لینک‌ها…');

			function nextExtract() {
				step('extract').then(function (res) {
					if (!res || !res.success) {
						finishWithError(res);
						return;
					}
					var d = res.data;
					setProgress(d.processed, d.total, d.label);

					if (d.phase === 'extract') {
						nextExtract();
					} else {
						nextCheck();
					}
				}).catch(finishWithError);
			}

			function nextCheck() {
				step('check').then(function (res) {
					if (!res || !res.success) {
						finishWithError(res);
						return;
					}
					var d = res.data;
					setProgress(d.processed, d.total, d.label);

					if (d.phase === 'done') {
						toast(S.scan_done || 'اسکن کامل شد');
						setTimeout(function () {
							window.location.reload();
						}, 700);
						return;
					}
					nextCheck();
				}).catch(finishWithError);
			}

			function finishWithError(res) {
				running = false;
				startBtns.forEach(function (btn) {
					btn.disabled = false;
				});
				if (progressbar) {
					progressbar.classList.remove('is-active');
				}
				toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
			}

			step('start').then(function (res) {
				if (!res || !res.success) {
					finishWithError(res);
					return;
				}
				setProgress(res.data.processed, res.data.total, res.data.label);
				nextExtract();
			}).catch(finishWithError);
		}

		startBtns.forEach(function (btn) {
			btn.addEventListener('click', runScan);
		});

		// Auto-start via dashboard quick action (?autostart=1).
		if (/[?&]autostart=1/.test(window.location.search)) {
			runScan();
		}

		// Filter tabs.
		$$('.linkyar-tab', page).forEach(function (tab) {
			tab.addEventListener('click', function () {
				$$('.linkyar-tab', page).forEach(function (t) {
					t.classList.remove('is-active');
					t.setAttribute('aria-selected', 'false');
				});
				tab.classList.add('is-active');
				tab.setAttribute('aria-selected', 'true');

				var filter = tab.getAttribute('data-filter');
				$$('#linkyar-report-table tbody tr').forEach(function (row) {
					var status = row.getAttribute('data-status');
					var show = filter === 'all' || status === filter;
					row.style.display = show ? '' : 'none';
				});
			});
		});

		// Delete single result.
		var table = $('#linkyar-report-table');
		if (table) {
			table.addEventListener('click', function (e) {
				var delBtn = e.target.closest('.linkyar-delete-item');
				if (!delBtn) {
					return;
				}
				var url = delBtn.getAttribute('data-url');
				confirmDialog(S.confirm_delete_r || 'این نتیجه حذف شود؟', url).then(function (yes) {
					if (!yes) {
						return;
					}
					post('linkyar_delete_report_item', { url: url }, nonce).then(function (res) {
						if (!res || !res.success) {
							toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
							return;
						}
						var row = delBtn.closest('tr');
						if (row) {
							row.remove();
						}
						toast(res.data.message || S.deleted || 'حذف شد');
					}).catch(function () {
						toast(S.error || 'خطا', 'error');
					});
				});
			});
		}

		// Reset whole report.
		var resetBtn = $('#linkyar-report-reset');
		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				confirmDialog(S.confirm_delete_r || 'این نتیجه حذف شود؟', '').then(function (yes) {
					if (!yes) {
						return;
					}
					post('linkyar_reset_report', {}, nonce).then(function (res) {
						if (!res || !res.success) {
							toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
							return;
						}
						toast(res.data.message || S.deleted || 'حذف شد');
						setTimeout(function () {
							window.location.reload();
						}, 500);
					}).catch(function () {
						toast(S.error || 'خطا', 'error');
					});
				});
			});
		}
	}

	/* -----------------------------------------------------------------
	 * Dashboard — autolink status toggle
	 * ----------------------------------------------------------------- */

	function initDashboard() {
		var dash = $('.linkyar-dashboard');
		if (!dash) {
			return;
		}

		var nonce = dash.getAttribute('data-nonce');
		var toggle = $('#linkyar-autolink-toggle');
		var state = $('#linkyar-autolink-state');

		if (toggle) {
			toggle.addEventListener('change', function () {
				post('linkyar_toggle_autolink', {}, nonce).then(function (res) {
					if (!res || !res.success) {
						toggle.checked = !toggle.checked;
						toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
						return;
					}
					var on = !!res.data.enabled;
					toggle.checked = on;
					if (state) {
						state.textContent = on ? 'فعال' : 'غیرفعال';
					}
					toast(res.data.message || S.saved || 'ذخیره شد');
				}).catch(function () {
					toggle.checked = !toggle.checked;
					toast(S.error || 'خطا', 'error');
				});
			});
		}
	}

	/* -----------------------------------------------------------------
	 * Onboarding wizard — 3 steps
	 * ----------------------------------------------------------------- */

	function initWizard() {
		var page = $('.linkyar-wizard');
		if (!page) {
			return;
		}

		var nonce = page.getAttribute('data-nonce');
		var current = 1;
		var total = 3;

		var steps = $$('.linkyar-wizard__step', page);
		var dots = $$('.linkyar-dot', page);
		var btnBack = $('#wizard-back');
		var btnNext = $('#wizard-next');
		var btnSkip = $('#wizard-skip');
		var btnFinish = $('#wizard-finish');

		function show(step) {
			current = step;
			steps.forEach(function (sec) {
				sec.classList.toggle('is-active', parseInt(sec.getAttribute('data-step'), 10) === step);
			});
			dots.forEach(function (dot) {
				var n = parseInt(dot.getAttribute('data-step'), 10);
				dot.classList.toggle('is-active', n === step);
				dot.classList.toggle('is-done', n < step);
			});

			btnBack.hidden = step === 1;
			btnNext.hidden = step === total;
			btnFinish.hidden = step !== total;
			btnSkip.hidden = step === total;
		}

		function finish(skip) {
			var data = { skip: skip ? 1 : 0 };

			if (!skip) {
				data.post_types = $$('input[name="post_types[]"]:checked', page).map(function (cb) {
					return cb.value;
				});
				data.keywords = ($('#wizard-keywords') || {}).value || '';
				data.target = ($('#wizard-target') || {}).value || '';
				data.max_links = ($('#wizard-max') || {}).value || 1;
			}

			var action = skip ? btnSkip : btnFinish;
			action.disabled = true;

			post('linkyar_wizard_finish', data, nonce).then(function (res) {
				if (!res || !res.success) {
					action.disabled = false;
					toast((res && res.data && res.data.message) || S.error || 'خطا', 'error');
					return;
				}
				toast(res.data.message || S.wizard_finished || 'راه‌اندازی کامل شد');
				window.location.href = res.data.redirect;
			}).catch(function () {
				action.disabled = false;
				toast(S.error || 'خطا', 'error');
			});
		}

		btnNext.addEventListener('click', function () {
			if (current < total) {
				show(current + 1);
			}
		});
		btnBack.addEventListener('click', function () {
			if (current > 1) {
				show(current - 1);
			}
		});
		btnSkip.addEventListener('click', function () {
			finish(true);
		});
		btnFinish.addEventListener('click', function () {
			finish(false);
		});

		show(1);
	}

	/* -----------------------------------------------------------------
	 * Boot
	 * ----------------------------------------------------------------- */

	function boot() {
		initSuggestions();
		initRules();
		initReport();
		initDashboard();
		initWizard();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
