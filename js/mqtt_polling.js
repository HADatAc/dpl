(function (Drupal, drupalSettings) {
    let interval = null;
    const config = drupalSettings.mqtt_record;
  
    function poll() {
      fetch(`/dpl/mqtt/record/ajax?archive_id=${config.archive_id}&ip=${config.ip}&port=${config.port}&topic=${config.topic}`)
        .then(response => response.json())
        .then(data => {
          if (data.status === 'ok') {
            console.log("Nova mensagem gravada na linha: " + data.row);
          }
        });
    }
  
    Drupal.behaviors.mqttPolling = {
      attach: function (context, settings) {
        if (!interval) {
          interval = setInterval(poll, 5000); // 5 segundos
        }
      }
    };
  })(Drupal, drupalSettings);
  