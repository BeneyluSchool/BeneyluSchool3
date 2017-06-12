(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbarHelpCounter', BNSNavbarHelpCounterDirective)
  .factory('navbarHelpCounter', NavbarHelpCounterFactory)

;

/**
 * @ngdoc directive
 * @name bnsNavbarHelpCounter
 * @module bns.main.navbar
 *
 * @description
 * Displays a bns-counter with the number of unread Intercom messages
 *
 * @requires navbarHelpCounter
 */
function BNSNavbarHelpCounterDirective (navbarHelpCounter) {

  return {
    scope: true,
    template: '<bns-counter data-value="{{counter.value}}"></bns-counter>',
    link: postLink,
  };

  function postLink (scope) {
    scope.counter = navbarHelpCounter;
  }

}

/**
 * @ngdoc service
 * @name navbarHelpCounter
 * @module bns.main.navbar
 *
 * @description
 * Centralizes Intercom api for the number of unread messages, for the currently
 * identified user
 *
 * @requires $rootScope
 * @requires $window
 * @requires $interval
 */
function NavbarHelpCounterFactory ($rootScope, $window, $interval) {

  var counter = {
    value: 0,
  };

  // setup is launched only once, when the service is instantiated
  var setup = $interval(function () {
    // wait for the Intercom lib to be available, then register the callback once
    if (!angular.isFunction($window.Intercom)) {
      return;
    }

    $window.Intercom('onUnreadCountChange', function (unreadCount) {
      counter.value = unreadCount;
      $rootScope.$digest();
    });

    $interval.cancel(setup);
  }, 200);

  return counter;

}

})(angular);
