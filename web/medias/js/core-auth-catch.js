'use strict';

/* global document */
/* global window */

(function ($) {

  if (!$) {
    console.warn('Cannot catch jQuery ajax call without jQuery :)');
    return;
  }

  $.ajaxSetup({
    error: function(xhr, status, err) {
      if (xhr.status == 401)
        window.location = Routing.generate('disconnect_user');
    }
  });

}) (window.jQuery);
