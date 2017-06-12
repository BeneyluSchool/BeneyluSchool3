'use strict';

angular.module('bns.core.debounce', [])

  /**
   * @ngdoc service
   * @name bns.core.debounce
   * @kind function
   *
   * @description
   * Use `$timeout` and `$q` to call the given function after the given time using debounce.
   *
   * @param {Function} func Function to be called after the promise has been resolved.
   * @param {Integer} wait Duration in milliseconds of the debounce.
   * @param {Boolean} immediate Determine if the callback function should be called at
   * the initialization of the service.
   *
   * @requires $timeout
   * @requires $q
   */
  .factory('debounce', function ($timeout, $q) {

    // The service is actually this function, which we call with the func
    // that should be debounced and how long to wait in between calls
    return function debounce(func, wait, immediate) {
      var timeout;
      // Create a deferred object that will be resolved when we need to actually call the func
      var deferred = $q.defer();

      return function () {
        var context = this, args = arguments;
        var later = function () {
          timeout = null;
          if (!immediate) {
            deferred.resolve(func.apply(context, args));
            deferred = $q.defer();
          }
        };

        var callNow = immediate && !timeout;
        if (timeout) {
          $timeout.cancel(timeout);
        }

        timeout = $timeout(later, wait);
        if (callNow) {
          deferred.resolve(func.apply(context, args));
          deferred = $q.defer();
        }
        return deferred.promise;
      };
    };
  });
