'use strict';

angular.module('bns.core.runs.stateError', [
  'bns.core.message',
])

  /**
   * Captures state change errors and display them
   *
   * @requires $rootScope
   * @requires message
   */
  .run(function ($rootScope, message) {
    $rootScope.$on('$stateChangeError', function(event, toState, toParams, fromState, fromParams, error) {
      message.error(error.message || error);
    });
  })

;
