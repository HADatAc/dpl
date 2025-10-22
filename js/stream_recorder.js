(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    var actionsDisabled = false;

    // --- Constants (adjust selectors if needed) ---
    var CONTAINER_SELECTOR = '#stream-topic-list-container';
    var TABLE_SCOPE_SELECTOR = '#topic-list-table';

    // --- One-time CSS injection for the overlay/spinner ---
    function injectLoadingStylesOnce() {
      if (document.getElementById('std-loading-overlay-styles')) return;
      var css = `
        .std-relative { position: relative !important; }
        .std-loading-overlay {
          position: absolute;
          inset: 0;
          display: flex;
          align-items: center;
          justify-content: center;
          background: rgba(255,255,255,0.75);
          z-index: 9999;
          pointer-events: none; /* clicks are still blocked by disabled state */
        }
        .std-loading-box {
          display: flex;
          gap: 10px;
          align-items: center;
          padding: 12px 16px;
          border-radius: 10px;
          background: rgba(0,0,0,0.05);
          box-shadow: 0 2px 10px rgba(0,0,0,0.08);
          font: 500 14px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
          color: #333;
        }
        .std-spinner {
          width: 18px;
          height: 18px;
          border: 2px solid rgba(0,0,0,0.15);
          border-top-color: #555;
          border-radius: 50%;
          animation: std-spin 0.8s linear infinite;
        }
        @keyframes std-spin { to { transform: rotate(360deg); } }
      `;
      var style = document.createElement('style');
      style.id = 'std-loading-overlay-styles';
      style.textContent = css;
      document.head.appendChild(style);
    }

    // --- Accessibility helpers ---
    function setBusy($el, busy) {
      // Communicate loading state to assistive tech
      if (!$el || !$el.length) return;
      if (busy) $el.attr('aria-busy', 'true');
      else $el.removeAttr('aria-busy');
    }

    // --- Overlay helpers (messages in EN) ---
    function showLoadingOverlay($container, message) {
      if (!$container || !$container.length) return;
      injectLoadingStylesOnce();

      $container.addClass('std-relative');
      var $overlay = $container.children('.std-loading-overlay');
      if (!$overlay.length) {
        $overlay = $('<div class="std-loading-overlay" role="status" aria-live="polite" aria-label="Loading"></div>');
        var $box = $('<div class="std-loading-box"></div>');
        $box.append('<div class="std-spinner" aria-hidden="true"></div>');
        $box.append('<span class="std-loading-text"></span>');
        $overlay.append($box);
        $container.append($overlay);
      }
      $overlay.find('.std-loading-text').text(message || 'Updating…');
      $overlay.show();
    }
    function hideLoadingOverlay($container) {
      if (!$container || !$container.length) return;
      var $overlay = $container.children('.std-loading-overlay');
      if ($overlay.length) $overlay.hide();
    }

    // --- Capture & restore “open state” (Bootstrap collapse/accordions) ---
    function captureOpenState($root) {
      // Collect IDs of elements currently expanded (.collapse.show)
      var openIds = [];
      if ($root && $root.length) {
        $root.find('.collapse.show[id]').each(function () {
          openIds.push(this.id);
        });
      }
      return openIds;
    }
    function restoreOpenState($root, openIds) {
      if (!$root || !openIds || !openIds.length) return;
      openIds.forEach(function (id) {
        var $collapse = $root.find('#' + CSS.escape(id));
        if ($collapse.length) {
          // Force show class and aria-expanded on toggle (if any)
          $collapse.addClass('show').attr('aria-expanded', 'true').attr('aria-hidden', 'false');
          // If there is a toggle button/link targeting this collapse, set aria-expanded
          var $toggles = $root.find('[data-bs-target="#' + id + '"], [data-target="#' + id + '"], a[href="#' + id + '"]');
          $toggles.attr('aria-expanded', 'true');
        }
      });
    }

    // --- UI disable/enable controls ---
    function disableAllActionButtons() {
      actionsDisabled = true;
      $('.stream-topic-subscribe, .stream-topic-unsubscribe, .stream-topic-record, .stream-topic-ingest, .stream-topic-suspend, .stream-topic-expose')
        .attr('aria-disabled', 'true')
        .prop('disabled', true)
        .css({ 'pointer-events': 'none', 'opacity': '0.6' });
    }
    function enableAllActionButtons() {
      actionsDisabled = false;
      $('.stream-topic-subscribe, .stream-topic-unsubscribe, .stream-topic-record, .stream-topic-ingest, .stream-topic-suspend, .stream-topic-expose')
        .removeAttr('aria-disabled')
        .prop('disabled', false)
        .css({ 'pointer-events': 'auto', 'opacity': '1' });
    }

    // --- Wait utilities: paints + images + idle (native Promise only) ---
    function waitForStableUI($scope, opts) {
      var frames = (opts && opts.frames) || 2;
      var idleTimeout = (opts && opts.idleTimeout) || 200;

      var raf = function () { return new Promise(function (r) { requestAnimationFrame(function () { r(); }); }); };
      var idle = function () {
        return new Promise(function (r) {
          if ('requestIdleCallback' in window) requestIdleCallback(function () { r(); }, { timeout: idleTimeout });
          else setTimeout(r, idleTimeout);
        });
      };
      var imagesReady = function () {
        var $imgs = $scope ? $scope.find('img') : $();
        if (!$imgs.length) return Promise.resolve();
        var tasks = Array.prototype.map.call($imgs, function (img) {
          if (img.complete && img.naturalWidth > 0) return Promise.resolve();
          if (img.decode) return img.decode().catch(function () {});
          return new Promise(function (res) { img.onload = img.onerror = function () { res(); }; });
        });
        return Promise.all(tasks);
      };

      return (function () {
        var chain = Promise.resolve();
        for (var i = 0; i < frames; i++) chain = chain.then(raf);
        return chain.then(imagesReady).then(idle);
      })();
    }

    // --- General action handler (Subscribe/Unsubscribe/etc.) ---
    function handleAction(selector, logLabel) {
      $(document).on('click', selector, function (e) {
        if (actionsDisabled) { e.preventDefault(); return; }
        e.preventDefault();

        disableAllActionButtons();

        var $btn        = $(this);
        var url         = $btn.attr('data-url');
        var streamValue = $btn.attr('data-stream-uri');
        var topicValue  = $btn.attr('data-topic-uri');

        var $container = $(CONTAINER_SELECTOR);
        setBusy($container, true);
        showLoadingOverlay($container, 'Updating ' + (logLabel || 'data') + '…');

        $.ajax({ url: url, method: 'POST', dataType: 'json' })
          .done(function (data) {
            if (data.status === 'ok') {
              // console.log(data.message || (logLabel + ' succeeded'));

              // Manual finally: success & error paths, then cleanup
              reloadTopics(streamValue, topicValue)
                .then(function () { enableAllActionButtons(); }, function () { enableAllActionButtons(); })
                .then(function () { setBusy($container, false); hideLoadingOverlay($container); },
                      function () { setBusy($container, false); hideLoadingOverlay($container); });

            } else {
              console.error(data.message);
              enableAllActionButtons();
              setBusy($container, false);
              hideLoadingOverlay($container);
            }
          })
          .fail(function (xhr) {
            var err = (xhr.responseJSON && xhr.responseJSON.message)
              ? xhr.responseJSON.message
              : 'Unexpected error occurred.';
            console.error(err);
            enableAllActionButtons();
            setBusy($container, false);
            hideLoadingOverlay($container);
          });
      });
    }

    handleAction('.stream-topic-subscribe',   'Subscribe');
    handleAction('.stream-topic-unsubscribe', 'Unsubscribe');
    handleAction('.stream-topic-record',      'Record');
    handleAction('.stream-topic-ingest',      'Ingest');
    handleAction('.stream-topic-suspend',     'Suspend');
    handleAction('.stream-topic-expose',      'Expose');

    // --- Reload topics, preserving open state and keeping the card open ---
    function reloadTopics(streamUri, topicUri) {
      return new Promise(function (resolve, reject) {
        if (!drupalSettings.std || !drupalSettings.std.ajaxUrl || !drupalSettings.std.studyuri) {
          console.warn('dplStreamRecorder: Missing std.ajaxUrl or std.studyUri');
          resolve(); return;
        }

        var $container = $(CONTAINER_SELECTOR);
        var $scope     = $(TABLE_SCOPE_SELECTOR);

        // 1) Capture which collapses are open inside the Stream Topic List container
        var previouslyOpenIds = captureOpenState($container);

        // IMPORTANT: Do NOT hide the Stream Topic List container, just use the overlay
        // Keep other blocks as you wish, but avoid collapsing the Stream Topic List.
        $('#edit-ajax-cards-container').hide();
        // $('#stream-topic-list-container').show(); // keep visible
        $('#stream-data-files-container').hide();
        $('#message-stream-container').hide();

        $.getJSON(drupalSettings.std.ajaxUrl, {
          studyUri:  drupalSettings.std.studyuri,
          streamUri: streamUri
        })
        .done(function (data) {
          try {
            // 2) Inject the new table HTML
            $scope.html(data.topics);

            // 3) Reattach Drupal behaviors
            if (Drupal && Drupal.attachBehaviors) {
              var scopeEl = document.getElementById('topic-list-table');
              if (scopeEl) Drupal.attachBehaviors(scopeEl);
            }

            // 4) Restore open collapse state for Stream Topic List card(s)
            restoreOpenState($container, previouslyOpenIds);

            // 5) Re-select the radio, trigger "change" (safer than click) and ensure card stays open
            if (topicUri) {
              var $radio = $scope.find('input.topic-radio[value="' + topicUri + '"]');
              if ($radio.length) {
                $radio.prop('checked', true).trigger('change');
                // If the radio normally opens a collapse via data-target, open it explicitly
                var targetId = $radio.attr('data-bs-target') || $radio.attr('data-target');
                if (targetId) {
                  var clean = targetId.replace(/^#/, '');
                  restoreOpenState($container, [clean]);
                }
              }
            }

            // 6) Reveal the rest of the layout (but keep Stream Topic List visible all the time)
            $('#edit-ajax-cards-container').show();
            $('#stream-topic-list-container').show(); // ensure visible, not collapsed
            $('#stream-data-files-container').removeClass('col-md-12').addClass('col-md-7').show();
            $('#message-stream-container').removeClass('col-md-12').addClass('col-md-5').show();

            // 7) Wait for paints/images/idle then resolve
            waitForStableUI($scope, { frames: 2, idleTimeout: 200 })
              .then(resolve, resolve);
          } catch (ex) {
            console.warn('dplStreamRecorder: Exception during reload flow:', ex);
            reject(ex);
          }
        })
        .fail(function () {
          console.warn('dplStreamRecorder: Failed to reload topics.');
          reject(new Error('reloadTopics: ajax failed'));
        });
      });
    }

  });
})(jQuery, Drupal, drupalSettings);
