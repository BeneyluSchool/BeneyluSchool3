'use strict';

angular.module('bns.mediaLibrary.privateFlag', [])

  .directive('bnsPrivateFlag', function () {
    return {
      replace: true,
      templateUrl: '/ent/angular/app/views/media-library/directives/bns-private-flag-directive.html',
      link: function (scope, element, attrs) {
        scope.item = scope.$eval(attrs.bnsPrivateFlag);
      }
    };
  });
