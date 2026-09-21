(function ($, Drupal, once) {
    Drupal.behaviors.ApopseisErrorHandling = {
      attach: function (context) {
        // Ensure the MutationObserver is only attached once per element using proper once() usage
        const fileElements = once("moveFileUploadErrors", ".custom-fieldset-handler", context);

        const errorCallback = (mutationsList, observer) => {
          mutationsList.forEach(function (mutation) {
              // Check if new nodes were added
              mutation.addedNodes.forEach(function (node) {
                if ($(node).is('.messages--error')) {
                    $('.form-type--managed-file').removeClass('custom-form-error');
                    $(node).closest('.form-type--managed-file').addClass('custom-form-error');
                }
                
                if ($(node).children('.messages-list').length > 0) {
                  $('.form-type--managed-file').removeClass('custom-form-error');
                  $(node).find('.form-type--managed-file').addClass('custom-form-error');
                }
              });
          });
        }
        
        // Use forEach to iterate over the elements
        fileElements.forEach(function (element) {
          // Define a mutation observer to watch for the addition of error messages
          const observer = new MutationObserver(errorCallback);
  
          // Configure the observer to watch for child nodes being added or removed
          const config = { childList: true, subtree: true };
  
          // Start observing the fieldset
          observer.observe(element, config);
        });

        const form = once("enableFileFieldOnSubmit", "form", context);

        $(form).on('submit', function (e) {
          $('input[name$="[apopseis_region_image][fids]"]').removeAttr('disabled');
          return true;
        });
      }
    };
  })(jQuery, Drupal, once);