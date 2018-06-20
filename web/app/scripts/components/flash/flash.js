(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.flash
 *
 * @description
 * Flash messages.
 */
angular.module('bns.components.flash', [
])

  .directive('bnsFlash', BnsFlashDirective)
  .controller('BnsFlash', BnsFlashController)

;

/**
 * @ngdoc directive
 * @name bnsFlash
 * @module bns.components.flash
 *
 * @restrict EA
 *
 * @description
 * Displays the transcluded content as a flash message.
 *
 * ** Attributes **
 *  - `bnsFlashIcon` {=String}: Bns icon name (cf bns-inset).
 *  - `bnsMdIcon` {=String}: Material icon name.
 *  - `bnsIsDismissable` {=Boolean}: Whether the flash message is dismissable.
 *  - `bnsDismissPersist` {=String}: A (unique) name under which to store the dismissal (localstorage or cookie). Implies `isDismissable`.
 *
 * @example
 * <!-- simple flash, non-dismissable -->
 * <bns-flash>
 *   Some cool content ...
 * <bns-flash>
 *
 * <!-- dismissable flash, reappears on page refresh -->
 * <bns-flash is-dismissable="true">
 *   Some cool content ...
 * <bns-flash>
 *
 * <!-- dismissable flash, persisted -->
 * <bns-flash dismiss-persist="a_code_name">
 *   Some cool content ...
 * <bns-flash>
 *
 */
function BnsFlashDirective () {

  return {
    restrict: 'E',
    scope: {
      icon: '@?bnsFlashIcon',
      mdIcon: '@?bnsMdIcon',
      isDismissable: '=?bnsIsDismissable',
      dismissPersist: '@?bnsDismissPersist',
    },
    transclude: true,
    controller: 'BnsFlash',
    controllerAs: 'flash',
    bindToController: true,
    templateUrl: 'views/components/flash/bns-flash.html',
  };

}

function BnsFlashController ($scope, storage) {
  var flash = this;
  // flash.isVisible = false;
  flash.close = close;

  init();

  function init () {
    if (flash.dismissPersist) {
      flash.isDismissable = true;

      // Sync our model variable with local storage
      storage.bind($scope, 'flash.isVisible', {
        defaultValue: true,
        storeName: 'bns/flash/' + flash.dismissPersist,
      });
    }
  }

  function close () {
    flash.isVisible = false;
  }

}

}) (angular);
