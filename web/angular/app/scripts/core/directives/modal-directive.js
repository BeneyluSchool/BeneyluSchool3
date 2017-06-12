'use strict';

angular.module('bns.core.modal', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.core.modal.bnsModal
   *
   * @description
   * Displays the base DOM of a modal
   *
   * **Attributes configuration**
   *
   * - `modal-close`:  {Function} A callback to be executed when wanting to
   *                   close the modal (by clicking on the overlay or the close
   *                   button). This callback is responsible for *actually*
   *                   closing the modal. If not specified, the modal will not
   *                   be dismissible
   * - `modal-title`:  {String} The title of the modal. If no title and no close
   *                   callback are specified, the modal header will not be
   *                   displayed.
   * - `modal-overlay`:   {Boolean} Whether to display a modal overlay. Defaults
   *                      to `true`.
   * - `modal-icon`: {String} The name of a bns-icon to be used as title icon
   *                 and background.
   */
  .directive('bnsModal', function (url) {
    return {
      replace: true,
      transclude: true,
      restrict: 'E',
      scope: true,
      link: function (scope, element, attr, CoreModalCtrl) {
        CoreModalCtrl.init();
      },
      templateUrl: url.view('core/modal.html'),
      controller: 'CoreModalCtrl'
    };
  })

  .controller('CoreModalCtrl', function ($scope, $attrs) {
    var ctrl = this;

    ctrl.init = function () {
      ctrl.closeCallback = null;
      $scope.showCloseButton = false;
      $scope.showCloseOverlay = true;

      // check if a close callback is specified
      if ($attrs.modalClose) {
        ctrl.closeCallback = $scope.$eval($attrs.modalClose);

        // tell the template to display a close button
        $scope.showCloseButton = true;
      }

      // expose the modal title
      if ($attrs.modalTitle) {
        $scope.title = $attrs.modalTitle;
      }

      // expose whether to show overlay
      if ($attrs.modalOverlay) {
        $scope.showCloseOverlay = !!$scope.$eval($attrs.modalOverlay);
      }

      // use an icon
      if ($attrs.modalIcon) {
        $scope.icon = $attrs.modalIcon;
      }

      // expose busy state
      if ($attrs.modalBusy) {
        $scope.$watch(function () {
          return $scope.$eval($attrs.modalBusy);
        }, function (busy) {
          $scope.busy = !!busy;
        });
      }
    };

    /**
     * Wrapper for the close callback
     */
    $scope.close = function () {
      // if a valid callback was specified, simply execute it
      if (ctrl.closeCallback && angular.isFunction(ctrl.closeCallback)) {
        ctrl.closeCallback();
      } else {
        if (console) {
          console.info('No modal close callback specified');
        }
      }
    };

  });
