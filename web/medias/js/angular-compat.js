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

if (!Function.prototype.bind) {
  Function.prototype.bind = function(oThis) {
    if (typeof this !== 'function') {
      // closest thing possible to the ECMAScript 5
      // internal IsCallable function
      throw new TypeError('Function.prototype.bind - what is trying to be bound is not callable');
    }

    var aArgs   = Array.prototype.slice.call(arguments, 1),
      fToBind = this,
      fNOP    = function() {},
      fBound  = function() {
        return fToBind.apply(this instanceof fNOP
          ? this
          : oThis,
          aArgs.concat(Array.prototype.slice.call(arguments)));
      };

    if (this.prototype) {
      // Function.prototype doesn't have a prototype property
      fNOP.prototype = this.prototype;
    }
    fBound.prototype = new fNOP();

    return fBound;
  };
}
