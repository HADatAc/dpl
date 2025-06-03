(function ($, Drupal, drupalSettings) {
  let recordingInterval = null;

  Drupal.dplStartPolling = function () {
    const settings = drupalSettings.dplStreamRecorder || {};
    const { ip, port, topic, archiveId } = settings;
    console.log('[dplStartPolling] Configuração:', settings);


    if (!ip || !port || !topic || !archiveId) {
      console.error('Dados incompletos para iniciar o polling.');
      return;
    }

    console.log('Iniciando gravação com polling a cada 5s...');

    if (recordingInterval) {
      console.log('[dplStartPolling] Limpando intervalo anterior');
      clearInterval(recordingInterval);
    }

    recordingInterval = setInterval(() => {
      console.log('[dplStartPolling] Requisição AJAX ao endpoint de escrita');
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
  window.dplStartPolling = Drupal.dplStartPolling;

  Drupal.behaviors.dplStreamRecorder = {
    attach: function (context, settings) {
      console.log('JS dplStreamRecorder comportamentos carregados');

      $('.dpl-start-record', context).each(function () {
        const $btn = $(this);

        // Evita múltiplas ligações
        if ($btn.data('dpl-record-bound')) return;
        $btn.data('dpl-record-bound', true);

        $btn.on('click', function (e) {
          e.preventDefault();
          console.log('Botão record clicado');

          const url = $btn.data('url');

          $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            success: function () {
              console.log('Controller chamado com sucesso.');
              // O polling será iniciado via AjaxResponse -> InvokeCommand
            },
            error: function () {
              alert('Erro ao iniciar gravação.');
            }
          });
        });
      });

      // Descomente e adapte para o botão STOP RECORD, se necessário
      // $('.dpl-stop-record', context).each(function () {
      //   const $btn = $(this);
      //   if ($btn.data('dpl-stop-bound')) return;
      //   $btn.data('dpl-stop-bound', true);

      //   $btn.on('click', function (e) {
      //     e.preventDefault();
      //     if (recordingInterval) {
      //       clearInterval(recordingInterval);
      //       recordingInterval = null;
      //       alert('Gravação parada.');
      //     }
      //   });
      // });
    }
  };
  
  Drupal.behaviors.dplPollingInit = {
    attach: function () {
      if (typeof Drupal.dplStartPolling === 'function') {
        console.log('[dplPollingInit] Iniciando polling via behavior');
        Drupal.dplStartPolling();
      } else {
        console.error('dplStartPolling não definida ainda');
      }
    }
  };

})(jQuery, Drupal, drupalSettings);