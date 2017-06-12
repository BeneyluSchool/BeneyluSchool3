(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.minisite.widgets
   */
  angular.module('bns.minisite.widgets', [])

    .directive('bnsMinisiteWidgets', BNSMinisiteWidgetsDirective)

  ;

  /**
   * @ngdoc directive
   * @name bnsMinisiteWidgets
   * @module bns.minisite.widgets
   *
   * @description
   * Displays a minisite widgets collection
   *
   * ** Attributes **
   *  - `widgets` {Object} widgets collection
   */
  function BNSMinisiteWidgetsDirective (moment, $timeout) {

    return {
      templateUrl: 'views/minisite/directives/bns-minisite-widgets.html',
      scope: {
        widgets: '=',
      },
      link: postLink
    };

    function postLink(scope) {
      scope.date = moment();
      timeUpdate();
      function timeUpdate() {
        scope.date = moment();
        $timeout(timeUpdate , 1000);
      }
    }
  }

})(angular);
