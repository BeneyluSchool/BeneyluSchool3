'use strict';

angular.module('bns.userDirectory.groupImage', [
  'bns.core.url',
  'bns.core.stringHelpers',
])

  .directive('bnsGroupImage', function () {
    return {
      replace: true,
      scope: {
        group: '=bnsGroupImage',
        role: '=',
      },
      controller: 'GroupImageController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('GroupImageController', function ($scope, $element, $compile, url, stringHelpers) {
    var ctrl = this;

    init();

    function init () {
      var template = '<span class="group-image'+(ctrl.role?' user-avatar':'')+'">';
      var defaultImage = stringHelpers.snakeToDash(ctrl.group.type.toLowerCase());

      // fallback for role images
      if (ctrl.role && ['pupil', 'parent', 'teacher', 'director', 'ent-referent'].indexOf(defaultImage) === -1) {
        defaultImage = 'jim';
      }

      template += '<img ng-src="' + url.image('user-directory/' + defaultImage + '.png') + '">';
      template += '</span>';

      $element.html(template);
      var compiledEl = $compile($element.contents())($scope);
      $element.replaceWith(compiledEl);
    }
  })

;
