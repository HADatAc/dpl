(function ($, Drupal) {
  Drupal.behaviors.dplTabs = {
    attach(context) {
      // 1) On page load, hide every pane that isn't already .active
      var $panes = $(context).find('.tab-pane');
      $panes.filter(':not(.active)').hide();

      // 2) Bind click only once per link by marking them via data-attr
      $(context).find('.nav-tabs a:visible').each(function () {
        var $link = $(this);
        // if already bound, skip
        if ($link.attr('data-dpl-tabs-bound')) {
          return;
        }
        // mark as bound
        $link.attr('data-dpl-tabs-bound', 'true');

        // attach handler
        $link.on('click', function (e) {
          e.preventDefault();
          // deactivate all tabs + hide all panes
          $('.nav-tabs a').removeClass('active');
          $panes.removeClass('active').hide();

          // activate & show only the clicked one
          $link.addClass('active');
          var target = $($link.attr('href'));
          if (target.length) {
            target.addClass('active').show();
          }
        });
      });
    }
  };
})(jQuery, Drupal);
