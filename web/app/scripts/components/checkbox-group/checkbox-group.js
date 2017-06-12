(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.checkboxGroup
 * @description The checkboxGroup module
 */
angular.module('bns.components.checkboxGroup', [
  'bns.components.choiceGroup',
])

  .directive('bnsCheckboxGroup', BNSCheckboxGroupDirective)
  .controller('CheckboxGroupController', CheckboxGroupController)

;

/**
 * @ngdoc directive
 * @name bnsCheckboxGroup
 * @module bns.components.checkboxGroup
 *
 * @restrict E
 *
 * @description
 * A simple directive helper to sync values of child checkboxes to a model
 * array.
 *
 * A custom label template can be specified in a child `bns-choice-label`
 * element, which has access to the `choice` variable.
 *
 * A secondary control can be specified in a child `bns-choice-secondary`
 * element, which has access to the `choice` variable. It will be appended to
 * the right of the checkbox, outside of it.
 *
 * ** Attributes **
 *  - `ng-model` (required): array holding the selected values
 *  - `choices` (required): array holding all possible choices. Should contain
 *    objects with at least the following properties:
 *     - label: the text to display (will be passed to the |translate filter)
 *     - value: the actual checkbox value, will be stored in selection and
 *       submitted (if in an actual form)
 *    Optional additional properties for each choice:
 *     - group_by: a label to group choices under (same as optgroup in select)
 *     - disabled: whether the choice is disabled
 *     - icon: add an icon and put checkbox on the right (@see bns-navbar-icon)
 *  - `id`: the html id attribute of the group. Id of child elements will be
 *    infered from it
 *  - `name`: the html name of the group. Name of the child form elements will
 *    be infered from it
 *  - `bnsStatus` (string): if attribute is present, status indicators are added
 *                          based on the choice value. Another property can be
 *                          specified as attribute value, to guess status from.
 *  - `bnsVanillaInput` (bool): Specifies whether to add a vanilla checkbox
 *                              copy (invisible) alongside the mdCheckbox.
 *                              Usefull for html form submit. Defaults to false.
 *  - `bnsColor` (string): if attribute is present, will use a choice property
 *                         to add a color indicator (defaults to the 'color'
 *                         property). Another property can be specified as
 *                         attribute value.
 *  - `bnsDisabled` (bool): Whether all checkboxes should be disabled. Works as
 *                          ngDisabled. Individual choices can be disabled by
 *                          setting their `disabled` property.
 *  - `bnsGroupByIcon` (string|object): An optional icon to use in group by
 *                                      labels.
 *
 * ** Classes **
 *  - `bns-choice-right`: Checkbox controls and label are swapped, ie. label to
 *                        the left and checbkox to the right.
 *
 * @example
 * <bns-checkbox-group
 *   ng-model="myArrayOfSelectedItems"
 *   choices="myArrayOfAvailableItems"
 * ></bns-checkbox-group>
 */
function BNSCheckboxGroupDirective (choiceGroupDecorator) {

  return {
    restrict: 'E',
    require: ['bnsCheckboxGroup', '?ngModel'],
    scope: true,
    controller: 'CheckboxGroupController',
    controllerAs: 'group',
    link: postLink,
    templateUrl: function (element, attrs) {
      choiceGroupDecorator.decorateTemplate(element, attrs);

      return 'views/components/checkbox-group/bns-checkbox-group.html';
    },
  };

  function postLink (scope, element, attrs, ctrls) {
    var group = ctrls[0]; // bnsCheckboxGroup
    var model = ctrls[1]; // ngModel

    if (model) {
      group.model = model;
    }

    choiceGroupDecorator.decorateLink(scope, attrs, group);
  }

}

function CheckboxGroupController ($scope, $attrs, choiceGroupDecorator, $mdUtil, $log) {

  var group = this;

  choiceGroupDecorator.decorateController($scope, $attrs, group);

  group.toggle = toggle;
  group.has = has;
  group.add = add;

  var parentAddChoice = group.addChoice;
  group.addChoice = addChoice;

  init();

  function init () {
    // legacy warning
    if (angular.isDefined($attrs.selection)) {
      $log.warn('Attribute "selection" no longer works on bns-checkbox-group. Please use "ng-model"');
    }

    // wait for model, set by link
    $mdUtil.nextTick(function () {
      if (group.model && !angular.isArray(group.model.$viewValue)) {
        $log.warn('Auto creating bns-checkbox-group model');
        group.model.$setViewValue([]);
      }

      // init checked choices
      angular.forEach(group.choices, function (choice) {
        if (choice.checked) {
          group.add(choice.value);
        }
      });
    });
  }

  /**
   * Toggles a choice in the selection
   */
  function toggle (value) {
    if (!(group.model && angular.isArray(group.model.$viewValue))) {
      return;
    }
    var idx = group.model.$viewValue.indexOf(value);
    if (idx > -1) {
      group.model.$viewValue.splice(idx, 1);
    } else {
      group.model.$viewValue.push(value);
    }
  }

  /**
   * Checks if given choice is in the selection
   */
  function has (value) {
    return group.model && angular.isArray(group.model.$viewValue) && group.model.$viewValue.indexOf(value) > -1;
  }

  /**
   * Adds given choice to the selection
   */
  function add (value) {
    if (!(group.model && angular.isArray(group.model.$viewValue))) {
      return;
    }

    if (!has(value)) {
      group.model.$viewValue.push(value);
    }
  }

  /**
   * Adds a choice to the list of POSSIBLE choices. It is not in the selection
   * yet.
   */
  function addChoice (choice) {
    var template = parentAddChoice(choice);
    if (template.name) {
      template.name += '[]';
    }

    return template;
  }

}

}) (angular);
