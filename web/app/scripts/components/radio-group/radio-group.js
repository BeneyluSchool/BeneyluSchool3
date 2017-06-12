(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name  bns.components.radioGroup
 */
angular.module('bns.components.radioGroup', [
  'bns.components.choiceGroup',
])

  .directive('bnsRadioGroup', BNSRadioGroupDirective)
  .controller('RadioGroupController', RadioGroupController)

;

/**
 * @ngdoc directive
 * @name bnsRadioGroup
 * @module bns.components.radioGroup
 *
 * @description
 * Enhances the `md-radio-group` directive. Automatically displays the given
 * collection of choices as radio buttons
 *
 * A custom label template can be specified in a child `bns-choice-label`
 * element, which has access to the `choice` variable.
 *
 * A secondary control can be specified in a child `bns-choice-secondary`
 * element, which has access to the `choice` variable. It will be appended to
 * the right of the radio button, outside of it.
 *
 * ** Attributes **
 * - `ngModel`: model used by mdRadioGroup, where choice is stored.
 * - `choices` (array): collection of choices to be displayed. Should have at
 *                      least the properties `label` and `value`.
 *                      Optional additional properties for each choice:
 *                      - group_by: a label to group choices under (same as
 *                                  optgroup in select)
 *                      - disabled: whether the choice is disabled
 * - `bnsStatus` (string): if attribute is present, status indicators are added
 *                          based on the choice value. Another property can be
 *                          specified as attribute value, to guess status from.
 * - `bnsVanillaInput` (bool): Specifies whether to add a vanilla radio button
 *                             copy (invisible) alongside the mdRadioButton.
 *                             Usefull for html form submit. Defaults to false.
 * - `bnsColor` (string): if attribute is present, will use a choice property
 *                        to add a color indicator (defaults to the 'color'
 *                        property). Another property can be specified as
 *                        attribute value.
 * - `bnsDisabled` (bool): Whether all radios should be disabled. Works as
 *                         ngDisabled. Individual choices can be disabled by
 *                         setting their `disabled` property.
 * - `bnsGroupByIcon` (string|object): An optional icon to use in group by
 *                                      labels.
 *
 * ** Classes **
 * - `bns-choice-right`: Radio controls and label are swapped, ie. label to the
 *                       left and radio button to the right.
 *
 * @example
 * <md-radio-group ng-model="myModelValue" bns-radio-group choices="myCollection">
 *   <!-- choices will be automatically displayed here -->
 * </md-radio-group>
 *
 * <!-- custom label -->
 * <md-radio-group bns-radio-group ng-model="myModelValue" choices="myCollection">
 *   <bns-choice-label><h3>{{::choice.title}}</h3> {{::choice.label}}</bns-choice-label>
 * </md-radio-group>
 *
 * <!-- status indicator, based on a choice property -->
 * <md-radio-group bns-radio-group ng-model="myModelValue" choices="myCollection" bns-status="someModelProperty"></md-radio-group>
 *
 * <!-- secondary control, using parent controller -->
 * <md-radio-group bns-radio-group ng-model="myModelValue" choices="myCollection">
 *   <bns-choice-secondary>
 *     <md-button class="md-icon-button" ng-click="ctrl.doSomeStuff(choice)">
 *       <md-icon>settings</md-icon>
 *     </md-button>
 *   </bns-choice-secondary>
 * </md-radio-group>
 */
function BNSRadioGroupDirective (choiceGroupDecorator) {

  return {
    restrict: 'A',
    require: ['bnsRadioGroup', 'mdRadioGroup', '?ngModel'],
    scope: true,
    controller: 'RadioGroupController',
    controllerAs: 'group',
    bindToController: true,
    link: postLink,
    templateUrl: function (element, attrs) {
      choiceGroupDecorator.decorateTemplate(element, attrs);

      return 'views/components/radio-group/bns-radio-group.html';
    },
  };

  function postLink (scope, element, attrs, ctrls) {
    var group = ctrls[0]; // bnsRadioGroup
    var model = ctrls[2]; // ngModel

    if (model) {
      group.model = model;
    }

    choiceGroupDecorator.decorateLink(scope, attrs, group);
  }

}

function RadioGroupController ($scope, $attrs, choiceGroupDecorator) {

  var group = this;

  choiceGroupDecorator.decorateController($scope, $attrs, group);

  group.model = null; // set by postLink later on
  group.is = is;

  /**
   * Checks if the given choice value is the currently selected one.
   *
   * @param value
   * @returns {Boolean}
   */
  function is (value) {
    return group.model && group.model.$viewValue === value;
  }

}

})(angular);
