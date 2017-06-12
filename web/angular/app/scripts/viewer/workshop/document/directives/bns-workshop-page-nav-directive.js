'use strict';

angular.module('bns.viewer.workshop.document.pageNav', [])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.page.bnsWorkshopPageNav
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of a workshop page nav item.
   *
   * ** Attributes **
   * - `bnsWorkshopPageNav` {Object}: The page to target.
   * - `offset` {Integer}: An optional offset to apply.
   * - `duration` {Integer}: The transition duration. Defaults to 0 (instant).
   *
   * @example
   * <any bns-workshop-page-nav="myPage"></any>
   *
   * @returns {Object} The bnsWorkshopPageNav directive
   */
  .directive('bnsWorkshopPageNav', function () {
    return {
      replace: true,
      scope: {
        page: '=bnsWorkshopPageNav',
        offset: '=',
        duration: '=',
      },
      templateUrl: '/ent/angular/app/views/viewer/workshop/document/directives/bns-workshop-page-nav.html',
      link: function (scope, element, attrs, ctrl) {
        ctrl.init(element, attrs);
      },
      controller: 'WorkshopPageNavCtrl',
    };
  })

  .controller('WorkshopPageNavCtrl', function ($scope, $window) {

    this.init = function (element) {
      $scope.offset = $scope.offset || 0;
      $scope.duration = $scope.duration || 0;

      element.on('click', function () {
        var target = angular.element($window.document.getElementById('workshop-page-' + $scope.page.position));
        var container = angular.element($window.document.getElementById('workshop-document')).closest('.nano-content');

        if (target.length && container.length) {
          // get the element's position relative to its parent (should stay
          // constant...) and apply offset
          var targetY = target.offset().top - target.parent().offset().top + $scope.offset;
          container.scrollTo(0, targetY, $scope.duration);
        }
      });
    };

  });
