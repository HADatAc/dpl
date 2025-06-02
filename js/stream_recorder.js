(function ($, Drupal) {
    let recordingInterval = null;
  
    // Esta função é chamada via InvokeCommand do controller
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
      }, 5000); // A cada 5 segundos
    };
  
    // Botão STOP
    $(document).on('click', '.dpl-stop-record', function (e) {
      e.preventDefault();
      if (recordingInterval) {
        clearInterval(recordingInterval);
        recordingInterval = null;
        alert('Gravação parada.');
      }
    });
  
    // Clique no botão START RECORD
    $(document).on('click', '.dpl-start-record', function (e) {
      e.preventDefault();
  
      const $btn = $(this);
      const url = $btn.data('url');
  
      $.ajax({
        url: url,
        method: 'POST',
        dataType: 'json',
        success: function (res) {
          console.log('Controller chamado com sucesso.');
          // O polling inicia-se depois via AjaxResponse do PHP
        },
        error: function () {
          alert('Erro ao iniciar gravação.');
        }
      });
    });
  
  })(jQuery, Drupal);
  