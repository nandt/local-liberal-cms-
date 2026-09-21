(function (Drupal, once) {
  Drupal.behaviors.unicornLoginPasswordToggle = {
    attach: function (context) {
      once('unicorn-login-password-toggle', '.unicorn-login-toggle-password', context).forEach(function (button) {
        button.addEventListener('click', function () {
          var wrapper = button.closest('.form-item');
          var input = wrapper ? wrapper.querySelector('input') : null;
          if (!input) {
            return;
          }

          var willShow = input.getAttribute('type') === 'password';
          input.setAttribute('type', willShow ? 'text' : 'password');
          button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
          button.setAttribute('aria-label', willShow ? Drupal.t('Hide password') : Drupal.t('Show password'));
        });
      });
    }
  };
})(Drupal, once);
