(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.boot
 */
angular.module('bns.starterKit.boot', [])

  .directive('bnsStarterKitBoot', BnsStarterKitBootDirective)

;

/**
 * @ngdoc directive
 * @name bnsStarterKitBoot
 * @module bns.starterKit.boot
 *
 * @description
 * Allows to start a starter kit, either on specified event or automatically.
 *
 * @example
 * <!-- start on click -->
 * <button bns-starter-kit-boot="click" bns-app="HOMEWORK" bns-level="1">Starter kit</button>
 *
 * <!-- start automatically on directive link, on last active step  -->
 * <bns-starter-kit-boot bns-app="MAIN"></bns-starter-kit-boot>
 *
 * @requires starterKit
 */
function BnsStarterKitBootDirective (starterKit) {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    if (attrs.bnsStarterKitBoot) {
      // boot on specified event
      element.on(attrs.bnsStarterKitBoot, doBoot);
    } else {
      // boot automatically
      doBoot();
    }

    function doBoot () {
      return starterKit.boot(attrs.bnsApp)
        .then(function success () {
          // navigate to specified level
          if (attrs.bnsLevel) {
            var targetStep = starterKit.steps[attrs.bnsLevel][0];
            if (targetStep) {
              return starterKit.navigate(targetStep).then(function () {
                // enable starter kit if not in check mode
                if (!angular.isDefined(attrs.bnsCheck)) {
                  starterKit.enable();
                }
              });
            }
          }
        })
      ;
    }
  }

}

})(angular);
