(function($) {

    $(document).ready(function() {
  
      var settings = MyPluginSettings;
  
      $.ajax({
        url: '/wp-json/my-plugin/v1/uid',
        method: 'GET',
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
        }
      }).done(function(response) {
  
        // Will return your UID.
        console.log(response);
      });
  
    });
  
  })(jQuery);