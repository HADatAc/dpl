(function ($, Drupal, drupalSettings) {

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
              alert('Gravação iniciada com sucesso!');
              console.log('Controller chamado com sucesso.');
            },
            error: function () {
              alert('Erro ao iniciar gravação.');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);