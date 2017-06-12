'use strict';

angular.module('bns.mediaLibrary.navigationTree')

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.navigationTree.directive.bnsNavigationTree
   * @kind function
   *
   * @description
   * Simple directive to create childscopes on navigation tree roots.
   *
   * @example
   * <any bns-navigation-tree tree="myNavigationTree" template="'path/to/my/template'"></any>
   *
   * @returns {Object} The bnsNavigationTree directive
   */
  .directive('bnsNavigationTree', function () {
    return {
      scope: true,
      link: function (scope, element, attrs, ctrl) {
        ctrl.init(attrs);
      },
      controller: function ($scope) {
        this.init = function (attrs) {
          $scope.tree = $scope.$eval(attrs.tree);
          $scope.model = $scope.tree.roots;
          $scope.template = $scope.$eval(attrs.template);
        };
      },
      templateUrl: '/ent/angular/app/views/media-library/navigation/bns-navigation-tree-directive.html',
    };
  });
