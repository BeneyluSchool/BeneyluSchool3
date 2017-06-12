'use strict';

angular.module('bns.core.flash.directive', [])

  /**
   * Displays flash messages.
   *
   * Epured and customized version of flash-alert directive provided by
   * angular-flash.
   *
   * The idea here is to maintain a $scope.flash.visible property, that can be
   * used by templates to show/hide the message with advanced animations
   */
  .directive('bnsFlash', function (flash, $timeout) {

    return {
      scope: true,
      link: function ($scope, element, attr) {
        var timeoutHandle, subscribeHandle;

        function show(message, type) {
          if (timeoutHandle) {
            $timeout.cancel(timeoutHandle);
          }

          $scope.flash.type = type;
          $scope.flash.message = message;
          $scope.flash.visible = true;

          if (!message) {
            $scope.hide();
            return;
          }

          var delay = Number(attr.duration || 5000);
          if (delay > 0) {
            timeoutHandle = $timeout($scope.hide, delay);
          }
        }

        subscribeHandle = flash.subscribe(show, attr.bnsFlash, attr.id);

        $scope.flash = {};

        $scope.hide = function () {
          $scope.flash.visible = false;
        };

        $scope.$on('$destroy', function () {
          flash.clean();
          flash.unsubscribe(subscribeHandle);
        });

        /**
        * Fixes timing issues: display the last flash message sent before this directive subscribed.
        */

        if (attr.bnsFlash && flash[attr.bnsFlash]) {
          show(flash[attr.bnsFlash], attr.bnsFlash);
        }

        if (!attr.bnsFlash && flash.message) {
          show(flash.message, flash.type);
        }
      }
    };
  });
