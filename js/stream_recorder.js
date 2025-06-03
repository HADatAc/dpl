(function ($, Drupal, drupalSettings) {
  let recordingInterval = null;

  Drupal.dplStartPolling = function () {
    const settings = drupalSettings.dplStreamRecorder || {};
    const { ip, port, topic, archiveId } = settings;

    if (!ip || !port || !topic || !archiveId) {
      console.error('Dados incompletos para iniciar o polling.');
      return;
    }

    console.log('Iniciando gravação com polling a cada 5s...');

    if (recordingInterval) clearInterval(recordingInterval);

    recordingInterval = setInterval(() => {
      $.ajax({
        url: `/dpl/record-message-ajax?archive_id=${archiveId}&ip=${ip}&port=${port}&topic=${topic}`,
        method: 'GET',
        success: function (data) {
          if (data.status === 'ok') {
            console.log(`Gravada nova linha: ${data.row}`);
          } else if (data.status === 'duplicate') {
            console.log('Mensagem duplicada, ignorada.');
          } else if (data.status === 'no-message') {
            console.log('Sem nova mensagem.');
          }
        },
        error: function () {
          console.error('Erro ao comunicar com o servidor.');
        }
      });
    }, 5000);
  };

  // Agora com Drupal.behaviors
  Drupal.behaviors.dplStreamRecorder = {
    attach: function (context, settings) {
      console.log('JS dplStreamRecorder comportamentos carregados');

      // Botão START RECORD
      $('.dpl-start-record', context)
        .once('dplStartRecord')
        .on('click', function (e) {
          e.preventDefault();
          console.log('Botão record clicado');

          const $btn = $(this);
          const url = $btn.data('url');

          $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            success: function (res) {
              console.log('Controller chamado com sucesso.');
              // O polling será iniciado via AjaxResponse -> InvokeCommand
            },
            error: function () {
              alert('Erro ao iniciar gravação.');
            }
          });
        });

      // // Botão STOP RECORD
      // $('.dpl-stop-record', context)
      //   .once('dplStopRecord')
      //   .on('click', function (e) {
      //     e.preventDefault();
      //     if (recordingInterval) {
      //       clearInterval(recordingInterval);
      //       recordingInterval = null;
      //       alert('Gravação parada.');
      //     }
      //   });
    }
  };
})(jQuery, Drupal, drupalSettings);