'use strict';

angular.module('bns.core.bindValue', [])

  /**
   * @ngdoc directive
   * @name bns.core.bindValue.bnsBindValue
   * @kind function
   *
   * @description
   * The bnsBindValue directive provides 2-way data-binding on elements not
   * handled by angular (for example hidden inputs). Additionally it listen to
   * 'change' events and updates the angular model accordingly.
   * ** Requires the ng-model attribute. **
   *
   * @example
   * <input type="hidden" ng-model="myModel" bns-bind-value>
   *
   * @returns {Object} The bnsBindValue directive.
   */
  .directive('bnsBindValue', function () {
    return {
      restrict: 'A',
      require: 'ngModel',
      link: function (scope, element, attrs, ngModelCtrl) {

        // watch for change in the model, and update HTML attr
        scope.$watch(function () { return ngModelCtrl.$viewValue; }, function (newValue) {
          element.val(newValue);
        });

        // listen to change event, and update the model
        element.on('change', function () {
          scope.$apply(function () {
            ngModelCtrl.$setViewValue(element.val());
          });
        });
      }
    };
  });
