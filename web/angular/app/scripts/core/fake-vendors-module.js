(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name td.scroll
 *
 * @description
 * This module is required by td.tileview, but it changes the prototype of
 * angular.element to support rtl scroll
 *
 * Since we do not need this support, we replace the vendor module by an empty
 * shell, safely modifying the prototype only when necessary.
 *
 * @type {[type]}
 */
angular.module('td.scroll', [])
  .run(function () {
    // do not alter angular.element().scrollLeft()

    // angular.element().direction() is expected to exist by yd.tileview
    if (!angular.isDefined(angular.element.prototype.direction)) {
      angular.element.prototype.direction = function () {
        return 'ltr';
      };
    }
  })
;

})(angular);
