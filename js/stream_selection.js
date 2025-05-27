/**
 * @file
 * Behavior to load stream data files & messages via AJAX on row click.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.streamSelection = {
    attach: function (context) {
      var table = $('#dpl-streams-table', context);

      // Guard: only bind once per table.
      if (!table.data('dpl-bound')) {
        table.data('dpl-bound', true);

        // Handle row click or radio change.
        table.on('click change', 'tbody tr, tbody tr input[type=radio]', function (event) {
          // Identify the <tr> element.
          var row = $(event.target).closest('tr');
          var key = row.find('input[type=radio]').val();
          if (!key) {
            return;
          }

          // Highlight the selected row.
          row.addClass('selected').siblings().removeClass('selected');

          // Show the hidden cards.
          $('#stream-data-files-container, #message-stream-container').show();

          // Perform AJAX call.
          $.getJSON(drupalSettings.dpl.ajaxUrl, {
            studyUri: drupalSettings.dpl.studyUri,
            streamUri: key
          })
          .done(function (data) {
            // Inject HTML into each container.
            $('#data-files-table').html(data.files);
            $('#message-stream-table').html(data.messages);
          })
          .fail(function () {
            alert('Failed to load stream data.');
          });
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
