'use strict';

angular.module('bns.core.flash.flashie', [])

  /**
   * A simple shadow flash for IE8, to make it fail silently...
   *
   * @returns {Object} The flashie service
   */
  .factory('flashie', function () {
    var noop = function () {};

    return {
      subscribe: noop,
      unsubscribe: noop,
      clean: noop
    };
  });
