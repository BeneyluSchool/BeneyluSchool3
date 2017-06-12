'use strict';

angular.module('bns.core.dragdrop')

  /**
   * @ngdoc directive
   * @name bns.core.dragdrop.bnsDroppable
   * @kind function
   *
   * @description
   * Adds droppable behavior to the attached dom element, via the jQuery UI
   * droppable component. Also keeps in sync the underlying model.
   *
   * ** Attributes **
   * - `bnsDroppable` - Main attribute, used to customize the directive:
   *   - `model`: the underlying model object (or more likely array!)
   *   - `onDrop`: callback to be executed on successful drop. It is given 3
   *               arguments:
   *               - `from` - Contains information about the draggable: `model`,
   *                          `index` and `scope`
   *               - `to` - Contains information about the droppable: `model`
   *                        and `scope`
   *               - `item` - A reference to the `draggable` item itself
   *               The suggested pattern to apply model changes is to wrap them
   *               in $apply in the concerned scopes. For example:
   *               `from.scope.$apply(function () { from.model.splice(from.index, 1) });`
   * - `bnsDroppableUiOptions` - Object that holds all jQuery UI options for a
   *                             droppable component.
   * - `bnsDroppableEnabled` - Enables/disables the jQuery UI component.
   *                           ** Defaults to nothing, i.e. falsey. **
   *
   * @example
   * <any bns-droppable="{ model: myCollection, onDrop: aCustomCallback }"
   *   bns-droppable-ui-options="{ accept: '.my-class' }"
   *   bns-droppable-enabled="myBooleanValueThatCanChangeOverTime"
   * ></any>
   *
   * @requires dragdropHelper
   *
   * @returns {Object} The bnsDroppable directive
   */
  .directive('bnsDroppable', function (dragdropHelper) {
    return {
      link: function (scope, element, attrs) {
        var
          // default jQuery UI options
          uiOptions = {
            activeClass: 'bns-droppable-valid',   // can receive current draggable
            hoverClass: 'bns-droppable-hover',    // draggable hovers element
          },
          options = scope.$eval(attrs.bnsDroppable),
          model;

        angular.extend(uiOptions, scope.$eval(attrs.bnsDroppableUiOptions));

        if (!(options && options.model)) {
          console.warn('No model set');
        }

        model = options.model;

        element.droppable(uiOptions);

        element.on('drop', function (e) {
          // get info stored in helper
          var fromModel = dragdropHelper.dragged.model,
            fromIndex = dragdropHelper.dragged.index,
            fromScope = dragdropHelper.dragged.scope;
          if (fromModel !== undefined && fromIndex !== undefined) {
            var item = fromModel[fromIndex];

            if (options.onDrop !== undefined) {
              // execute custom callback
              var from = {
                model: fromModel,
                index: fromIndex,
                scope: fromScope
              };
              var to = {
                model: model,
                scope: scope
              };
              var ret = options.onDrop(from, to, item);

              // prevent drop, if asked to
              if (false === ret) {
                e.preventDefault();
                return false;
              }
            } else {
              // default callback with arrays: remove from old, add to new
              if (angular.isArray(model)) {
                fromScope.$apply(function () {
                  fromModel.splice(fromIndex, 1);
                });
                model.push(item);
              }
            }
          } else {
            console.warn('Cannot handle drop', fromModel, fromIndex);
          }
        });

        // toggle droppable when its control attr changes
        scope.$watch(function () {
          return scope.$eval(attrs.bnsDroppableEnabled);
        }, function (value) {
          if (value) {
            element.droppable('enable');
          } else {
            element.droppable('disable');
          }
        });
      }
    };
  });
