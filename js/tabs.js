(function ($, Drupal) {
  Drupal.behaviors.dplTabs = {
    attach: function (context, settings) {
      // Use .once to ensure behavior is attached only once to the context
      $(context).find('.nav-tabs a').each(function () {
        var $this = $(this);

        // Ensure this click event is bound only once
        if (!$this.data('bound')) {
          $this.on('click', function (e) {
            e.preventDefault();

            // Get the target pane associated with the clicked tab
            var target = $(this.getAttribute('href'));

            // Remove 'active' class from all tabs and tab panes
            $('.nav-tabs a').removeClass('active');
            $('.tab-pane').removeClass('active');

            // Add 'active' class to the clicked tab and its associated pane
            $(this).addClass('active');
            if (target.length) {
              target.addClass('active');
            }
          });

          // Mark this element as having the click event bound
          $this.data('bound', true);
        }
      });
    }
  };
})(jQuery, Drupal);





  