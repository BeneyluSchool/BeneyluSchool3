'use strict';

angular.module('bns.core.dragdrop')

  /**
   * @ngdoc directive
   * @name bns.core.dragdrop.bnsDraggable
   * @kind function
   *
   * @description
   * Adds draggable behavior to the attached dom element, via the jQuery UI
   * draggable component. Also keeps in sync the underlying model.
   *
   * ** Attributes **
   * - `bnsDraggable` - Main attribute, used to customize the directive:
   *   - `model`: the underlying model object (or array)
   * - `bnsDraggableItem` - The item being dragged
   * - `bnsDraggableUiOptions` - Object that holds all jQuery UI options for a
   *                             draggable component.
   * - `bnsDraggableEnabled` - Enables/disables the jQuery UI component.
   *                           ** Defaults to nothing, i.e. falsey.
   *
   * @example
   * <any ng-repeat="item in myCollection"
   *   bns-draggable="{ model: myCollection }"
   *   bns-draggable-item="item"
   *   bns-draggable-ui-options="{ delay: 250 }"
   *   bns-draggable-enabled="myBooleanValueThatCanChangeOverTime"
   * ></any>
   *
   * @requires dragdropHelper
   *
   * @returns {Object} The bnsDraggable directive
   */
  .directive('bnsDraggable', function (dragdropHelper) {
    return {
      restrict: 'A',
      link: function (scope, element, attrs) {
        var
          // default jQuery UI options
          uiOptions = {
            revert: 'invalid',
          },
          options,
          model;

        angular.extend(uiOptions, scope.$eval(attrs.bnsDraggableUiOptions));

        element.draggable(uiOptions);

        // when drag starts, store everything we need in the helper
        element.on('dragstart', function () {
          // refresh options
          angular.extend(uiOptions, scope.$eval(attrs.bnsDraggableUiOptions));
          element.draggable(uiOptions);

          options = scope.$eval(attrs.bnsDraggable);
          if (!(options && options.model)) {
            console.warn('No model set', element);
          }
          model = options && options.model || undefined;

          var item = scope.$eval(attrs.bnsDraggableItem);
          dragdropHelper.dragged = {
            model: model,
            item: item,
            scope: scope
          };
        });

        // toggle draggable when its control attr changes
        scope.$watch(function () {
          return scope.$eval(attrs.bnsDraggableEnabled);
        }, function (value) {
          if (value) {
            element.draggable('enable');
          } else {
            element.draggable('disable');
          }
        });
      }
    };
  });
