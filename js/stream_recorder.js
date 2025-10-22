(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    var actionsDisabled = false;

    // --- Tunables ---
    var CONTAINER_SELECTOR   = '#stream-topic-list-container';
    var TABLE_SCOPE_SELECTOR = '#topic-list-table';
    var CLICK_DEBOUNCE_MS    = 300;   // prevent double taps
    var SAFETY_TIMEOUT_MS    = 15000; // overlay safety timeout

    // --- State for debounce & safety timers ---
    var lastActionAt = 0;
    var overlaySafetyTimer = null;

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
          pointer-events: none;
        }
        .std-loading-box {
          display: flex; gap: 10px; align-items: center;
          padding: 12px 16px; border-radius: 10px;
          background: rgba(0,0,0,0.05);
          box-shadow: 0 2px 10px rgba(0,0,0,0.08);
          font: 500 14px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
          color: #333;
        }
        .std-spinner {
          width: 18px; height: 18px; border: 2px solid rgba(0,0,0,0.15);
          border-top-color: #555; border-radius: 50%;
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

    // --- Safety timeout for overlays ---
    function startOverlaySafety($container) {
      clearOverlaySafety();
      overlaySafetyTimer = setTimeout(function () {
        // Safety fallback: never leave the UI locked if something hangs.
        console.warn('[safety-timeout] Update took too long; unlocking UI.');
        hideLoadingOverlay($container);
        setBusy($container, false);
        enableAllActionButtons();
      }, SAFETY_TIMEOUT_MS);
    }
    function clearOverlaySafety() {
      if (overlaySafetyTimer) {
        clearTimeout(overlaySafetyTimer);
        overlaySafetyTimer = null;
      }
    }

    // --- Capture & restore “open state” (Bootstrap collapse/accordions) ---
    function captureOpenState($root) {
      var openIds = [];
      if ($root && $root.length) {
        $root.find('.collapse.show[id]').each(function () { openIds.push(this.id); });
      }
      return openIds;
    }
    function restoreOpenState($root, openIds) {
      if (!$root || !openIds || !openIds.length) return;
      openIds.forEach(function (id) {
        var $collapse = $root.find('#' + CSS.escape(id));
        if ($collapse.length) {
          $collapse.addClass('show').attr({ 'aria-expanded': 'true', 'aria-hidden': 'false' });
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

      var raf  = function () { return new Promise(function (r) { requestAnimationFrame(function () { r(); }); }); };
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
          if (img.decode) return img.decode().catch(function () { /* ignore */ });
          return new Promise(function (res) { img.onload = img.onerror = function () { res(); }; });
        });
        return Promise.all(tasks);
      };

      var p = Promise.resolve();
      for (var i = 0; i < frames; i++) p = p.then(raf);
      return p.then(imagesReady).then(idle);
    }

    // --- General action handler (Subscribe/Unsubscribe/etc.) with debounce + safety ---
    function handleAction(selector, logLabel) {
      $(document).on('click', selector, function (e) {
        // Debounce: ignore clicks fired too close in time
        var now = Date.now();
        if (now - lastActionAt < CLICK_DEBOUNCE_MS) { e.preventDefault(); return; }
        lastActionAt = now;

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
        startOverlaySafety($container); // <-- start safety timer

        $.ajax({ url: url, method: 'POST', dataType: 'json' })
          .done(function (data) {
            if (data.status === 'ok') {
              // console.log(data.message || (logLabel + ' succeeded'));
              reloadTopics(streamValue, topicValue)
                .then(function () { enableAllActionButtons(); }, function () { enableAllActionButtons(); })
                .then(function () {
                  clearOverlaySafety();
                  setBusy($container, false);
                  hideLoadingOverlay($container);
                }, function () {
                  clearOverlaySafety();
                  setBusy($container, false);
                  hideLoadingOverlay($container);
                });
            } else {
              console.error(data.message);
              clearOverlaySafety();
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
            clearOverlaySafety();
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

        // Capture which collapses are open inside the Stream Topic List container
        var previouslyOpenIds = captureOpenState($container);

        // Avoid hiding the Stream Topic List container (keep it visible with overlay)
        $('#edit-ajax-cards-container').hide();
        $('#stream-data-files-container').hide();
        $('#message-stream-container').hide();

        $.getJSON(drupalSettings.std.ajaxUrl, {
          studyUri:  drupalSettings.std.studyuri,
          streamUri: streamUri
        })
        .done(function (data) {
          try {
            $scope.html(data.topics);

            if (Drupal && Drupal.attachBehaviors) {
              var scopeEl = document.getElementById('topic-list-table');
              if (scopeEl) Drupal.attachBehaviors(scopeEl);
            }

            restoreOpenState($container, previouslyOpenIds);

            if (topicUri) {
              var $radio = $scope.find('input.topic-radio[value="' + topicUri + '"]');
              if ($radio.length) {
                $radio.prop('checked', true).trigger('change');
                var targetId = $radio.attr('data-bs-target') || $radio.attr('data-target');
                if (targetId) restoreOpenState($container, [targetId.replace(/^#/, '')]);
              }
            }

            $('#edit-ajax-cards-container').show();
            $('#stream-topic-list-container').show();
            $('#stream-data-files-container').removeClass('col-md-12').addClass('col-md-7').show();
            $('#message-stream-container').removeClass('col-md-12').addClass('col-md-5').show();

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
