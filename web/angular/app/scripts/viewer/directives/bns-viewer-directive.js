'use strict';

angular.module('bns.viewer.directive', [
  'bns.viewer.service',
])

  .directive('bnsViewer', function () {
    return {
      scope: {
        terminal: '=',
        media: '=',
        mediaId: '=',
      },
      controller: 'ViewerDirectiveController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('ViewerDirectiveController', function ($scope, $element, $attrs, bnsViewer) {
    var ctrl = this;
    ctrl.viewer = bnsViewer();

    init();

    function init () {
      $element.on('click', function (event) {
        if (ctrl.terminal) {
          event.stopPropagation();
        }
        event.preventDefault();
        launchViewer();
      });
    }

    function launchViewer () {
      var locals = {};

      if (ctrl.media) {
        locals.media = ctrl.media;
      } else {
        locals.mediaId= ctrl.mediaId;
      }
      if (!$attrs.disabled) {
        ctrl.viewer.activate(locals);
      }
    }
  });
