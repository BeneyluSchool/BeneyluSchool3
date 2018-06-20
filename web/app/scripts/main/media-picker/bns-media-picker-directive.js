'use strict';

angular.module('bns.mediaPicker', [

])

  .directive('bnsMediaPicker', function () {
    return {
      restrict: 'AE',
      scope: {
        media:'=', //current media and target
        target: '=', //media Id
        type: '@',      // a media type restriction
        size: '@', // the thumb size to use
        allowEmpty: '=',// whether to allow empty selection
        autoOpen: '=',  // wether to automatically trigger the media picker
        label: '=bnsLabel', // the label to use
        selectionLabel: '@', // the button label to use to validate selection
      },
      templateUrl: 'views/main/media-picker/bns-media-picker.html',
      controller: 'MediaPickerController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('MediaPickerController', function ($element, $scope, $timeout) {
    var ctrl = this;
    ctrl.active = false;
    ctrl.namespace = Math.floor(Math.random() * 1000);
    ctrl.remove = remove;

    init();

    function init () {

      $element.on('click', '.media-selection', function () {
        ctrl.active = true;
      });

      // watch for local changes, and emit the corresponding event
      $scope.$watch('ctrl.media', function (media) {
        if (undefined !== media) {
          if (media) {
            $scope.$emit('object.media.changed', ctrl.object, ctrl.target, media.id, media);
          } else {
            $scope.$emit('object.media.changed', ctrl.object, ctrl.target, null, media);
          }
        }
      });

      // $scope.$watch('resourceId', function (newResourceId) {
      //   if (undefined !== newResourceId) {
      //     $scope.$emit('widget.resource.changed', $scope.target, newResourceId);
      //   }
      // });

      angular.element('body').on('mediaLibrary.selection.done.'+ctrl.namespace, function (evt, data) {
        if (!(data && data.selection && data.selection.length && ctrl.active)) {
          return;
        }

        $scope.$apply(function () {
          ctrl.media = data.selection[0];
          ctrl.active = false;
        });
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
