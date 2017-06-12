'use strict';

angular.module('bns.workshop.widget.mediaPicker', [
  'bns.core.url',
])

  .directive('bnsWorkshopWidgetMediaPicker', function (url) {
    return {
      restrict: 'AE',
      scope: {
        widget: '=',    // widget object
        current: '=',   // current value
        target: '@',    // the targeted model property
        type: '@',      // a media type restriction
        allowEmpty: '=',// whether to allow empty selection
        autoOpen: '=',  // wether to automatically trigger the media picker
      },
      templateUrl: url.view('workshop/widget/directives/bns-workshop-widget-media-picker.html'),
      controller: 'WorkshopWidgetMediaPickerController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopWidgetMediaPickerController', function ($element, $scope, $timeout) {
    var ctrl = this;
    ctrl.namespace = Math.floor(Math.random() * 1000);
    ctrl.remove = remove;

    init();

    function init () {
      if (!ctrl.widget._embedded) {
        ctrl.widget._embedded = {};
      }

      var objectTarget = ctrl.target.replace('_id', '');
      if (ctrl.widget._embedded[objectTarget]) {
        ctrl.media = ctrl.widget._embedded[objectTarget];
      }

      // watch for local changes, and emit the corresponding event
      $scope.$watch('ctrl.media', function (media) {
        if (undefined !== media) {
          if (media) {
            $scope.$emit('widget.media.changed', ctrl.widget, ctrl.target, media.id, media);
          } else {
            $scope.$emit('widget.media.changed', ctrl.widget, ctrl.target, null, media);
          }
        }
      });

      // $scope.$watch('resourceId', function (newResourceId) {
      //   if (undefined !== newResourceId) {
      //     $scope.$emit('widget.resource.changed', $scope.target, newResourceId);
      //   }
      // });

      angular.element('body').on('mediaLibrary.selection.done.'+ctrl.namespace, function (evt, data) {
        if (!(data && data.selection && data.selection.length)) {
          return;
        }
        ctrl.media = data.selection[0];
      });

      $scope.$on('$destroy', function () {
        angular.element('body').off('mediaLibrary.selection.done.'+ctrl.namespace);
      });

      if (ctrl.autoOpen) {
        // let the compilation of dom finish, then invoke the media library
        $timeout(function () {
          $element.find('.media-selection').click();
        });
      }
    }

    function remove () {
      ctrl.media = false;
    }
  });
