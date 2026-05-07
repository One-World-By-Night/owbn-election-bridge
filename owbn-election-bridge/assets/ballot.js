/**
 * OWBN Election Bridge — ballot view interactivity.
 *
 * Responsibilities:
 *   - Track per-card selection state for the "remaining" counter
 *   - Submit-all loop: fire wpvp_cast_ballot per card sequentially, update UI
 *   - Candidate modal: click a candidate name to load their full application
 */
(function ($) {
	'use strict';

	if (typeof OEB_BALLOT === 'undefined') return;

	var $document = $(document);

	// ── Helpers ────────────────────────────────────────────────────────────

	function currentLangSlug() {
		var m = window.location.pathname.match(/^\/([a-z]{2}(?:-[a-z]{2})?)\//i);
		return m ? m[1] : '';
	}

	function isCardEligible($card) {
		return $card.attr('data-eligible') === '1' && $card.attr('data-state') === 'open';
	}

	// Returns the user's current selection on a card, or null if no selection.
	// Singleton: string. Ranked: array of strings (top-N, no gaps).
	function readSelection($card) {
		var type = $card.attr('data-voting-type') || 'singleton';
		if (type === 'singleton') {
			var $checked = $card.find('input[type="radio"]:checked');
			return $checked.length ? $checked.val() : null;
		}
		// Ranked: collect non-empty selects in order, dedupe trailing empties.
		var ranks = [];
		$card.find('.oeb-ballot-card__rank').each(function () {
			var v = $(this).val();
			if (v) ranks.push(v);
		});
		return ranks.length ? ranks : null;
	}

	function recountRemaining() {
		var $ballot = $('.oeb-ballot').first();
		if (!$ballot.length) return;
		var total = parseInt($ballot.attr('data-total-eligible'), 10) || 0;
		var ready = 0;
		$ballot.find('.oeb-ballot-card').each(function () {
			var $c = $(this);
			if (!isCardEligible($c)) return;
			if ($c.hasClass('is-voted-this-session')) return;
			if (readSelection($c) !== null) ready++;
		});
		var remaining = total - $ballot.find('.oeb-ballot-card.is-voted-this-session').length;
		var $counter = $ballot.find('.oeb-ballot__counter');
		var fmt = (OEB_BALLOT.i18n && OEB_BALLOT.i18n.remaining_format) || '%1$d of %2$d positions remaining';
		$counter.text(fmt.replace('%1$d', remaining).replace('%2$d', total));

		var $btn = $ballot.find('.oeb-ballot__submit-all');
		$btn.prop('disabled', ready === 0);
	}

	// ── Submit-all flow ────────────────────────────────────────────────────

	function submitOne($card) {
		var voteId = parseInt($card.attr('data-vote-id'), 10);
		var type   = $card.attr('data-voting-type') || 'singleton';
		var sel    = readSelection($card);
		var $status = $card.find('.oeb-ballot-card__inline-status');

		return new Promise(function (resolve) {
			if (sel === null) {
				$status.removeClass('is-success is-error').text('');
				resolve({ skipped: true });
				return;
			}

			// Build ballot_data per voting type, matching what wpvp_cast_ballot expects.
			var ballotData;
			if (type === 'singleton') {
				ballotData = sel;
			} else {
				ballotData = sel; // already an array
			}

			$status.removeClass('is-success is-error').text(OEB_BALLOT.i18n.submitting || 'Submitting…');

			$.post(OEB_BALLOT.ajax_url, {
				action:      'wpvp_cast_ballot',
				nonce:       OEB_BALLOT.wpvp_nonce,
				vote_id:     voteId,
				ballot_data: JSON.stringify(ballotData)
			}).done(function (resp) {
				if (resp && resp.success) {
					$card.addClass('is-voted is-voted-this-session');
					$card.attr('data-has-voted', '1');
					$status.addClass('is-success').text('✓ ' + (OEB_BALLOT.i18n.voted || 'Voted'));
					if (!$card.find('.oeb-ballot-card__voted-badge').length) {
						$card.find('.oeb-ballot-card__title').after(
							'<span class="oeb-ballot-card__voted-badge">✓ ' + (OEB_BALLOT.i18n.voted || 'Voted') + '</span>'
						);
					}
					resolve({ success: true });
				} else {
					var msg = (resp && resp.data && resp.data.message) || OEB_BALLOT.i18n.submit_failed || 'Submission failed.';
					$status.addClass('is-error').text(msg);
					resolve({ success: false, error: msg });
				}
			}).fail(function () {
				$status.addClass('is-error').text(OEB_BALLOT.i18n.submit_failed || 'Submission failed.');
				resolve({ success: false, error: 'request failed' });
			});
		});
	}

	$document.on('click', '.oeb-ballot__submit-all', function (e) {
		e.preventDefault();
		var $btn    = $(this);
		var $ballot = $btn.closest('.oeb-ballot');
		var $status = $ballot.find('.oeb-ballot__status');

		// Identify cards: eligible + has selection vs eligible + no selection.
		var cards = [];
		var unvoted = 0;
		$ballot.find('.oeb-ballot-card').each(function () {
			var $c = $(this);
			if (!isCardEligible($c)) return;
			if ($c.hasClass('is-voted-this-session')) return;
			if (readSelection($c) === null) {
				unvoted++;
				return;
			}
			cards.push($c);
		});

		if (cards.length === 0) {
			$status.removeClass('is-success').addClass('is-error').text(OEB_BALLOT.i18n.no_selection || 'No selection made.');
			return;
		}

		// Confirm if any positions left unvoted.
		var totalEligibleRemaining = $ballot.find('.oeb-ballot-card').filter(function () {
			var $c = $(this);
			return isCardEligible($c) && !$c.hasClass('is-voted-this-session');
		}).length;
		var unvotedCount = totalEligibleRemaining - cards.length;
		if (unvotedCount > 0) {
			var msg = (OEB_BALLOT.i18n.confirm_unvoted || 'You have %d unvoted positions. Submit anyway?').replace('%d', unvotedCount);
			if (!window.confirm(msg)) return;
		}

		$btn.prop('disabled', true);
		$status.removeClass('is-error is-success').text('');

		// Sequential submit so errors are isolated to specific cards.
		var ok = 0, fail = 0;
		var run = Promise.resolve();
		cards.forEach(function ($c) {
			run = run.then(function () {
				return submitOne($c).then(function (res) {
					if (res.success) ok++; else if (res.success === false) fail++;
					recountRemaining();
				});
			});
		});
		run.then(function () {
			if (fail === 0) {
				$status.addClass('is-success').text(OEB_BALLOT.i18n.all_done || 'All votes submitted.');
			} else {
				$status.addClass('is-error').text(
					(OEB_BALLOT.i18n.all_done || 'All votes submitted.') + ' (' + fail + ' failed)'
				);
			}
			$btn.prop('disabled', false);
			recountRemaining();
		});
	});

	// Selection change updates remaining counter.
	$document.on('change', '.oeb-ballot-card input[type="radio"], .oeb-ballot-card .oeb-ballot-card__rank', function () {
		recountRemaining();
	});

	// ── Candidate modal ────────────────────────────────────────────────────

	$document.on('click', '.oeb-ballot-card__candidate-name[data-post-id]', function (e) {
		e.preventDefault();
		var $name = $(this);
		var postId = parseInt($name.attr('data-post-id'), 10);
		if (!postId) return;

		var $modal   = $('.oeb-ballot-modal').first();
		var $body    = $modal.find('.oeb-ballot-modal__body');
		var $newtab  = $modal.find('.oeb-ballot-modal__newtab');

		$modal.removeAttr('hidden');
		$body.html('<p style="text-align:center;padding:24px;">' + (OEB_BALLOT.i18n.loading || 'Loading…') + '</p>');

		$.post(OEB_BALLOT.ajax_url, {
			action:  'oeb_ballot_modal',
			nonce:   OEB_BALLOT.oeb_nonce,
			post_id: postId,
			lang:    currentLangSlug()
		}).done(function (resp) {
			if (resp && resp.success) {
				$body.html(resp.data.html || '');
				if (resp.data.permalink) $newtab.attr('href', resp.data.permalink);
			} else {
				$body.html('<p>' + (OEB_BALLOT.i18n.load_error || 'Could not load application.') + '</p>');
			}
		}).fail(function () {
			$body.html('<p>' + (OEB_BALLOT.i18n.load_error || 'Could not load application.') + '</p>');
		});
	});

	function closeModal() {
		var $modal = $('.oeb-ballot-modal').first();
		$modal.attr('hidden', '');
		$modal.find('.oeb-ballot-modal__body').empty();
	}

	$document.on('click', '.oeb-ballot-modal__close, .oeb-ballot-modal__overlay', closeModal);
	$document.on('keydown', function (e) {
		if (e.key === 'Escape') closeModal();
	});

	// Initial counter sync (in case template renders pre-checked options).
	$(function () { recountRemaining(); });

})(jQuery);
