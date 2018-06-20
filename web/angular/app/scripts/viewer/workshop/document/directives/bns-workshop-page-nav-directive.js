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

  .controller('WorkshopPageNavCtrl', function ($scope) {

    this.init = function (element) {
      $scope.offset = $scope.offset || 0;
      $scope.duration = $scope.duration || 0;

      element.on('click', function () {
        $scope.$emit('workshop.page.focus', $scope.page.position, $scope.duration, $scope.offset);
      });
    };

  });
