(function (Drupal) {
  Drupal.behaviors.passwordToggle = {
    attach: function (context) {
      context.querySelectorAll('.password-toggle').forEach(function (toggle) {
        if (toggle.dataset.passwordToggleInitialized) return;
        toggle.dataset.passwordToggleInitialized = 'true';

        // Find the input inside the same input-group
        var input = toggle.closest('.input-group')
                          .querySelector('input.form-control');
        if (!input) return;

        toggle.addEventListener('click', function () {
          input.type = (input.type === 'password') ? 'text' : 'password';
          var icon = toggle.querySelector('i');
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      });
    }
  };
})(Drupal);
