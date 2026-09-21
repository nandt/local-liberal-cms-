(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.facebookMessengerShare = {
    attach: function (context, settings) {
      var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

      // Only execute if it's a mobile device
      if (isMobile) {
        var messengerUrl = $('.fb-messenger', context).attr('mobile-url');
        $('.fb-messenger', context).attr('href', messengerUrl);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
