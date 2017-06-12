'use strict';

angular.module('bns.mediaLibrary.favoriteFlag', [])

  .directive('bnsFavoriteFlag', function () {
    return {
      replace: true,
      templateUrl: '/ent/angular/app/views/media-library/directives/bns-favorite-flag-directive.html',
      link: function (scope, element, attrs) {
        scope.item = scope.$eval(attrs.bnsFavoriteFlag);
      }
    };
  });
