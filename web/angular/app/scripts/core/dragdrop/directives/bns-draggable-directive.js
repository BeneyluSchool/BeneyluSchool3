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
   * - `bnsDraggableIndex` - The index of the model, if in a collection
   * - `bnsDraggableUiOptions` - Object that holds all jQuery UI options for a
   *                             draggable component.
   * - `bnsDraggableEnabled` - Enables/disables the jQuery UI component.
   *                           ** Defaults to nothing, i.e. falsey.
   *
   * @example
   * <any ng-repeat="item in myCollection"
   *   bns-draggable="{ model: myCollection }"
   *   bns-draggable-index="$index"
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
          options = scope.$eval(attrs.bnsDraggable),
          model;

        angular.extend(uiOptions, scope.$eval(attrs.bnsDraggableUiOptions));

        if (!(options && options.model)) {
          console.warn('No model set', element);
        }

        model = options && options.model || undefined;

        element.draggable(uiOptions);

        // when drag starts, store everything we need in the helper
        element.on('dragstart', function () {
          var index = scope.$eval(attrs.bnsDraggableIndex);
          dragdropHelper.dragged = {
            model: model,
            index: index,
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
