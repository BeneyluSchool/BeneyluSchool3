/**
 * Various fixes of our old vendors to work with latest angular and
 *  angular-material.
 */

'use strict';

/* global window */

(function ($) {

  if (!$) {
    return;
  }

  /*
   * Mimics $().removecClass() of jQuery 1.9+, where passing an undefined value
   * does NOT remove all classes of the element.
   * Used extensively by angular-material 0.11.4+
   */
  var originalRemoveClass = $.fn.removeClass;
  $.fn.removeClass = function removeClass (value) {
    if (typeof value === 'undefined') {
      return;
    }

    return originalRemoveClass.call(this, value);
  };

  /*
   * Mimics $().attr('type', value) of jQuery 1.9+, where it is alloewd to change the type attribute.
   */
  var originalAttr = $.fn.attr;
  $.fn.attr = function attr (name, value) {
    if ('type' === name) {
      return $.fn.prop.apply(this, arguments);
    }

    return originalAttr.apply(this, arguments);
  }
}) (window.jQuery);
