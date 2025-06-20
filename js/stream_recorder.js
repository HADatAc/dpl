/**
 * @file
 * stream_recorder.js
 *
 * Handle Subscribe/Unsubscribe actions for Stream Topics and then
 * reload the topic list for the current stream via AJAX, mirroring
 * the “topic” branch of std/stream_selection without re-clicking
 * the same radio button.
 *
 * Must be attached *after* core/drupal.ajax and std/stream_selection.
 */

(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    console.log('🛠️ dplStreamRecorder.js loaded');

    // ——————— Subscribe ———————
    $(document).on('click', '.stream-topic-subscribe', function (e) {
      e.preventDefault();
      console.log('Subscribe button clicked');
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
          // alert(data.message || 'Subscription started successfully!');
          reloadTopics(streamValue);
        }
        else {
          // alert('Error: ' + data.message);
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

    // ——————— Unsubscribe ———————
    $(document).on('click', '.stream-topic-unsubscribe', function (e) {
      e.preventDefault();
      console.log('Unsubscribe button clicked');
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
          alert(data.message || 'Subscription stopped successfully!');
          reloadTopics(streamValue);
        }
        else {
          alert('Error: ' + data.message);
        }
      })
      .fail(function (xhr) {
        var err = (xhr.responseJSON && xhr.responseJSON.message)
          ? xhr.responseJSON.message
          : 'Unexpected error occurred.';
        alert(err);
      });
    });

    /**
     * Re-fetches and re-renders the Stream Topic List for a given streamUri,
     * replicating the “topic” path in std/stream_selection.
     *
     * @param {string} streamUri
     *   Base64-encoded stream URI.
     */
    function reloadTopics(streamUri) {
      if (!drupalSettings.std || !drupalSettings.std.ajaxUrl || !drupalSettings.std.studyUri) {
        console.warn('dplStreamRecorder: Missing std.ajaxUrl or std.studyUri');
        return;
      }

      // Hide all AJAX cards
      $('#edit-ajax-cards-container').hide();
      $('#stream-topic-list-container').hide();
      $('#stream-data-files-container').hide();
      $('#message-stream-container').hide();

      // Fetch the topics for this stream
      $.getJSON(drupalSettings.std.ajaxUrl, {
        studyUri:  drupalSettings.std.studyUri,
        streamUri: streamUri
      })
      .done(function (data) {
        // Show the outer container
        $('#edit-ajax-cards-container').show();

        // Insert the new topics table
        $('#topic-list-table').html(data.topics);
        $('#stream-topic-list-container').show();

        // Adjust layout classes exactly as std/stream_selection does
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
