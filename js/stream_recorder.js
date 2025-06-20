/**
 * @file
 * stream_recorder.js
 *
 * Handle Subscribe/Unsubscribe/Record/Ingest/Suspend actions for Stream Topics
 * and then reload the topic list for the current stream via AJAX.
 */

(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    // Reuso dos handlers existentes
    function handleAction(selector, logLabel, reloadOnSuccess = true) {
      $(document).on('click', selector, function (e) {
        e.preventDefault();
        console.log(logLabel + ' button clicked');
        var $btn = $(this);
        var url = $btn.attr('data-url');
        var streamValue = $btn.attr('data-stream-uri');

        $.ajax({
          url: url,
          method: 'POST',
          dataType: 'json'
        })
        .done(function (data) {
          if (data.status === 'ok') {
            console.log(data.message || (logLabel + ' succeeded'));
            if (reloadOnSuccess) {
              reloadTopics(streamValue);
            }
          }
          else {
            console.error(data.message);
          }
        })
        .fail(function (xhr) {
          var err = (xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : 'Unexpected error occurred.';
          console.error(err);
        });
      });
    }

    // ——————— Handlers existentes ———————
    handleAction('.stream-topic-subscribe',     'Subscribe');
    handleAction('.stream-topic-unsubscribe',   'Unsubscribe');

    // ——————— Novos handlers ———————
    handleAction('.stream-topic-record',        'Record');
    handleAction('.stream-topic-ingest',        'Ingest');
    handleAction('.stream-topic-suspend',       'Suspend');

    /**
     * Re-fetches and re-renders the Stream Topic List for a given streamUri.
     */
    function reloadTopics(streamUri) {
      if (!drupalSettings.std || !drupalSettings.std.ajaxUrl || !drupalSettings.std.studyUri) {
        console.warn('dplStreamRecorder: Missing std.ajaxUrl or std.studyUri');
        return;
      }

      // Hide todas as seções AJAX antes de recarregar
      $('#edit-ajax-cards-container').hide();
      $('#stream-topic-list-container').hide();
      $('#stream-data-files-container').hide();
      $('#message-stream-container').hide();

      // Fetch dos tópicos atualizados
      $.getJSON(drupalSettings.std.ajaxUrl, {
        studyUri:  drupalSettings.std.studyUri,
        streamUri: streamUri
      })
      .done(function (data) {
        $('#edit-ajax-cards-container').show();
        $('#topic-list-table').html(data.topics);
        $('#stream-topic-list-container').show();
        $('#stream-data-files-container')
          .removeClass('col-md-12').addClass('col-md-7');
        $('#message-stream-container')
          .removeClass('col-md-12').addClass('col-md-5');
      })
      .fail(function () {
        console.warn('dplStreamRecorder: Failed to reload topics.');
      });
    }
  });
})(jQuery, Drupal, drupalSettings);
