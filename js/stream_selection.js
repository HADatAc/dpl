(function ($, Drupal, drupalSettings) {
  /**
   * @file
   * Behavior for handling stream radio selection, loading cards via AJAX,
   * and allowing de‐selection of the currently checked radio.
   */
  Drupal.behaviors.streamSelection = {
    attach: function (context, settings) {
      // Locate the streams table within this context.
      var table = $('#dpl-streams-table', context);

      // If we've already bound our handlers, bail out.
      if (table.data('dpl-bound')) {
        return;
      }
      table.data('dpl-bound', true);

      /**
       * Hide both cards (stream data files + message stream).
       */
      function hideCards() {
        $('#stream-data-files-container')
          .removeClass('col-md-6 col-md-12')
          .hide();
        $('#message-stream-container')
          .removeClass('col-md-6 col-md-12')
          .hide();
      }

      // Initially hide both cards.
      hideCards();

      // --- Trick to support clicking a selected radio to unselect it ---
      // 1) On mousedown, record if this radio was already checked.
      table.on('mousedown', 'input[type=radio]', function () {
        var $radio = $(this);
        $radio.data('wasCheckedOnMouseDown', this.checked);
      });

      // 2) On click, either uncheck-and-hide or perform normal select-and-load.
      table.on('click', 'input[type=radio]', function (e) {
        var $radio = $(this);
        var wasChecked = $radio.data('wasCheckedOnMouseDown') === true;

        if (wasChecked) {
          // Prevent the browser's default radio behavior.
          e.preventDefault();
          e.stopImmediatePropagation();

          // Temporarily remove `name` so it cannot re‐check itself.
          var groupName = $radio.attr('name');
          $radio.removeAttr('name');

          // Uncheck, clear our `waschecked` flag, remove row highlight.
          $radio.prop('checked', false)
                .data('waschecked', false)
                .closest('tr').removeClass('selected');

          // Restore the `name` attribute.
          $radio.attr('name', groupName);

          // Hide both cards.
          hideCards();

          return false;
        }

        // If it's a fresh selection: clear all others first.
        table.find('input[type=radio]')
          .data('waschecked', false)
          .closest('tr').removeClass('selected');

        // Mark this radio as checked and highlighted.
        $radio.prop('checked', true)
              .data('waschecked', true)
              .closest('tr').addClass('selected');

        // --- AJAX call to load the two cards below the table ---
        $.getJSON(drupalSettings.dpl.ajaxUrl, {
          studyUri:  drupalSettings.dpl.studyUri,
          streamUri: $radio.val()
        })
        .done(function (data) {
          // Insert the returned HTML fragments into their containers.
          $('#data-files-table').html(data.files);
          $('#data-files-pager').html(data.filesPager);
          $('#message-stream-table').html(data.messages);

          // Decide layout based on streamType.
          var type = (data.streamType || '').toLowerCase();
          if (type === 'file' || type === 'files') {
            $('#stream-data-files-container')
              .removeClass('col-md-6').addClass('col-md-12').show();
            $('#message-stream-container').hide();
          }
          else {
            $('#stream-data-files-container')
              .removeClass('col-md-12').addClass('col-md-6').show();
            $('#message-stream-container')
              .removeClass('col-md-12').addClass('col-md-6').show();
          }
        })
        .fail(function () {
          alert('Failed to load stream data. Please try again.');
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
