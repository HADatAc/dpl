(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    var actionsDisabled = false;

    function disableAllActionButtons() {
      actionsDisabled = true;
      $('.stream-topic-subscribe, .stream-topic-unsubscribe, .stream-topic-record, .stream-topic-ingest, .stream-topic-suspend')
        // marca como desactivado para leitores de ecrã
        .attr('aria-disabled', 'true')
        // impede o clique e “empalidece” o link
        .css({
          'pointer-events': 'none',
          'opacity': '0.6'
        });
    }
    function enableAllActionButtons() {
      actionsDisabled = false;
      $('.stream-topic-subscribe, .stream-topic-unsubscribe, .stream-topic-record, .stream-topic-ingest, .stream-topic-suspend')
        .removeAttr('aria-disabled')
        .css({
          'pointer-events': 'auto',
          'opacity': '1'
        });
    }

    // function handleAction(selector, logLabel, reloadOnSuccess = true) {
    //   $(document).on('click', selector, function (e) {
    //     // 1) Se já estamos a aguardar resposta, ignora clicks posteriores
    //     if (actionsDisabled) {
    //       e.preventDefault();
    //       return;
    //     }

    //     e.preventDefault();
    //     console.log(logLabel + ' button clicked');

    //     // 2) Desactiva TODOS
    //     disableAllActionButtons();

    //     var $btn = $(this);
    //     var url = $btn.attr('data-url');
    //     var streamValue = $btn.attr('data-stream-uri');

    //     // 3) Chama a API
    //     var ajaxReq = $.ajax({
    //       url: url,
    //       method: 'POST',
    //       dataType: 'json'
    //     });

    //     if (reloadOnSuccess) {
    //       ajaxReq
    //         .done(function (data) {
    //           if (data.status === 'ok') {
    //             console.log(data.message || (logLabel + ' succeeded'));
    //             // reloadTopics devolve o jqXHR de getJSON
    //             reloadTopics(streamValue)
    //               .always(function () {
    //                 // 5) Só aqui re-activa tudo
    //                 enableAllActionButtons();
    //               });
    //           }
    //           else {
    //             console.error(data.message);
    //             enableAllActionButtons();
    //           }
    //         })
    //         .fail(function (xhr) {
    //           var err = (xhr.responseJSON && xhr.responseJSON.message)
    //             ? xhr.responseJSON.message
    //             : 'Unexpected error occurred.';
    //           console.error(err);
    //           enableAllActionButtons();
    //         });
    //     }
    //     else {
    //       ajaxReq.always(function () {
    //         enableAllActionButtons();
    //       });
    //     }
    //   });
    // }

    // — Handlers existentes —

    function handleAction(selector, logLabel, reloadOnSuccess = true) {
      $(document).on('click', selector, function (e) {
        if (actionsDisabled) { e.preventDefault(); return; }
        e.preventDefault();
        disableAllActionButtons();

        var $btn        = $(this);
        var url         = $btn.attr('data-url');
        var streamValue = $btn.attr('data-stream-uri');
        var topicValue  = $btn.attr('data-topic-uri');

        $.ajax({ url: url, method: 'POST', dataType: 'json' })
          .done(function (data) {
            if (data.status === 'ok') {
              console.log(data.message || (logLabel + ' succeeded'));
              // agora passamos também o topicValue
              reloadTopics(streamValue, topicValue)
                .always(enableAllActionButtons);
            }
            else {
              console.error(data.message);
              enableAllActionButtons();
            }
          })
          .fail(function (xhr) {
            var err = (xhr.responseJSON && xhr.responseJSON.message)
              ? xhr.responseJSON.message
              : 'Unexpected error occurred.';
            console.error(err);
            enableAllActionButtons();
          });
      });
    }

    handleAction('.stream-topic-subscribe',   'Subscribe');
    handleAction('.stream-topic-unsubscribe', 'Unsubscribe');
    // — Novos handlers —
    handleAction('.stream-topic-record',  'Record');
    handleAction('.stream-topic-ingest',  'Ingest');
    handleAction('.stream-topic-suspend', 'Suspend');

    // function reloadTopics(streamUri) {
    //   if (!drupalSettings.std || !drupalSettings.std.ajaxUrl || !drupalSettings.std.studyUri) {
    //     console.warn('dplStreamRecorder: Missing std.ajaxUrl or std.studyUri');
    //     return $.Deferred().resolve().promise();
    //   }

    //   $('#edit-ajax-cards-container').hide();
    //   $('#stream-topic-list-container').hide();
    //   $('#stream-data-files-container').hide();
    //   $('#message-stream-container').hide();

    //   return $.getJSON(drupalSettings.std.ajaxUrl, {
    //     studyUri:  drupalSettings.std.studyUri,
    //     streamUri: streamUri
    //   })
    //   .done(function (data) {
    //     $('#edit-ajax-cards-container').show();
    //     $('#topic-list-table').html(data.topics);
    //     $('#stream-topic-list-container').show();
    //     $('#stream-data-files-container')
    //       .removeClass('col-md-12').addClass('col-md-7');
    //     $('#message-stream-container')
    //       .removeClass('col-md-12').addClass('col-md-5');
    //   })
    //   .fail(function () {
    //     console.warn('dplStreamRecorder: Failed to reload topics.');
    //   });
    // }

    function reloadTopics(streamUri, topicUri) {
      if (!drupalSettings.std || !drupalSettings.std.ajaxUrl || !drupalSettings.std.studyUri) {
        console.warn('dplStreamRecorder: Missing std.ajaxUrl or std.studyUri');
        return $.Deferred().resolve().promise();
      }

      $('#edit-ajax-cards-container').hide();
      $('#stream-topic-list-container').hide();
      $('#stream-data-files-container').hide();
      $('#message-stream-container').hide();

      return $.getJSON(drupalSettings.std.ajaxUrl, {
        studyUri:  drupalSettings.std.studyUri,
        streamUri: streamUri
      })
      .done(function (data) {
        $('#edit-ajax-cards-container').show();
        $('#topic-list-table').html(data.topics);
        // **simula a seleção do rádio** logo que injetar o HTML:
        if (topicUri) {
          var $radio = $('#topic-list-table')
            .find('input.topic-radio[value="' + topicUri + '"]');
          if ($radio.length) {
            // marca e aciona o click, para disparar o handler que carrega arquivos+mensagens
            $radio.prop('checked', true).trigger('click');
          }
        }
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
