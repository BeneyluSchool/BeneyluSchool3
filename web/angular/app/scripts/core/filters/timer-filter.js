'use strict';

angular.module('bns.core.timer', [])

  /**
   * @ngdoc filter
   * @name  bns.core.timer.timer
   * @kind function
   *
   * @description
   * Displays the input as a digital clock timer.
   *
   * @returns {Function} The timer filter
   */
  .filter('timer', function () {
    return function (time) {
      if (isNaN(time)) {
        return '';
      }

      var minuts = Math.floor(time / 60);
      var seconds = Math.floor(time % 60);

      return format(minuts) + ':' + format(seconds);
    };

    // left-padded string
    function format (time) {
      if (time < 10) {
        return '0'+time;
      }
      return ''+time;
    }
  });
