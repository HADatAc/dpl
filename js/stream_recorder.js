(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.dplStreamRecorder = {
    attach: function (context, settings) {
      console.log('JS dplStreamRecorder comportamentos carregados');

      $('.dpl-start-record', context).each(function () {
        const $btn = $(this);
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
            success: function (data) {
              if (data.status === 'ok') {
                alert('Gravação iniciada com sucesso!');
              } else {
                alert('Erro: ' + data.message);
              }
            },
            error: function (xhr) {
              const err = xhr.responseJSON?.message || 'Erro inesperado ao iniciar gravação.';
              alert(err);
            }
          });
        });
      });

      $('.dpl-suspend-record', context).each(function () {
        const $btn = $(this);
        if ($btn.data('dpl-suspend-bound')) return;
        $btn.data('dpl-suspend-bound', true);

        $btn.on('click', function (e) {
          e.preventDefault();
          console.log('Botão suspend clicado');

          const url = $btn.data('url');

          $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            success: function (data) {
              if (data.status === 'ok') {
                alert('Gravação parada com sucesso!');
              } else {
                alert('Erro: ' + data.message);
              }
            },
            error: function (xhr) {
              const err = xhr.responseJSON?.message || 'Erro inesperado ao suspender gravação.';
              alert(err);
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
