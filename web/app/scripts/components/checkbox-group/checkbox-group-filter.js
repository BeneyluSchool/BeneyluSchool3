(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.checkboxGroupFilter
 * @description The checkboxGroupFilter module
 */
angular.module('bns.components.checkboxGroupFilter', [
  'bns.components.checkboxGroup',
])

  .directive('bnsCheckboxGroupFilter', BNSCheckboxGroupFilterDirective)
  .controller('CheckboxGroupFilterController', CheckboxGroupFilterController)

;

/**
 * @ngdoc bnsCheckboxGroupFilter
 * @name bnsCheckboxGroupFilter
 * @module bns.components.checkboxGroupFilter
 *
 * @restrict E
 *
 * @description
 * This extend the bnsCheckboxGroup directive and add a input filter
 * A simple directive helper to sync values of child checkboxes to a model
 * array.
 *
 * ** Attributes **
 *  - `selection` (required): array holding the selected values, sort of
 *    ng-model
 *  - `collection` (required): array holding all possible choices. Should contain
 *    objects with at least the following properties:
 *     - label: the text to display (will be passed to the |translate filter)
 *     - value: the actual checkbox value, will be stored in selection and
 *       submitted (if in an actual form)
 *  - `value` : allow to choose object property that will be used as value (default: "value")
 *  - `label` : allow to choose object property that will be used as label (default: "label")
 *  - `icon` : the property used for an icon (default: false) or a function(object) to build the icon property
 *  - `limit` : the number of element to show at start. If 0 (default) show all elements
 *  - `filterAttribute` : allow to filer only on an attribute
 *
 * @example
 * <bns-checkbox-group-filter
 *   selection="myArrayOfSelectedItems"
 *   collection="myArrayOfAvailableItems"
 *   value="value"
 *   label="label"
 *   icon="false"
 *   limit="10"
 * ></bns-checkbox-group-filter>
 */
function BNSCheckboxGroupFilterDirective () {

  return {
    restrict: 'E',
    scope: {
      selection: '=',
      collection: '=',
      value: '=',
      label: '=',
      icon: '=',
      limit: '=',
      filterAttribute: '@',
      deselection: '='
    },
    controller: 'CheckboxGroupFilterController',
    controllerAs: 'groupFilter',
    bindToController: true,
    templateUrl: 'views/components/checkbox-group/bns-checkbox-group-filter.html',
  };

}

function CheckboxGroupFilterController ($scope, $attrs, _, arrayUtils, filterFilter, $timeout) {
  var groupFilter = this;
  var valueAttr = groupFilter.value || 'value';
  var labelAttr = groupFilter.label || 'label';
  var iconAttr = groupFilter.icon || false;
  var limit = groupFilter.limit || 0;
  var currentLimit = limit;
  groupFilter.currentLength = 0;

  groupFilter.filters = {
    choices: [],
  };

  groupFilter.showMore = function() {
    currentLimit += limit;
    currentLimit = Math.min(currentLimit, groupFilter.collection.length);
    filter();
    limitElement();

    $timeout(function(){
      $scope.$emit('track.height');
    }, 0);
  };

  groupFilter.deselect = function() {
    groupFilter.selection = [];
  };

  init();

  function init () {
    groupFilter.filterText = '';

    filter();
    limitElement();
  }

  function filter () {
    if (!groupFilter.filters.choices) {
      return;
    }
    // reset array to keep reference (scope issue hack)
    groupFilter.filters.choices.splice(0,groupFilter.filters.choices.length);
    if (!groupFilter.filterText || groupFilter.filterText.length < 1) {
      arrayUtils.merge(groupFilter.filters.choices, _.map(groupFilter.collection, buildItem));
      return;
    }

    // allow custom filter
    var filterBy = groupFilter.filterText;
    if ($attrs.filterAttribute) {
      filterBy = {};
      filterBy[$attrs.filterAttribute] = groupFilter.filterText;
    }
    // filter the collection
    arrayUtils.merge(groupFilter.filters.choices, _.map(filterFilter(groupFilter.collection, filterBy), buildItem));

  }

  function limitElement() {
    if (!limit || !groupFilter.filters.choices) {
      return;
    }
    groupFilter.currentLength = groupFilter.filters.choices.length;
    // reset array to keep reference (scope issue hack)
    groupFilter.filters.choices.splice(currentLimit);
  }

  function buildItem (object) {
    return {
      value: object[valueAttr],
      label: object[labelAttr],
      icon: _.isFunction(iconAttr) ? iconAttr(object) : (object[iconAttr] || false),
    };
  }

  $scope.$watch('groupFilter.filterText', function(newVal, oldVal) {
    if (newVal !== oldVal) {
      currentLimit = limit;
      filter();
      limitElement();
    }
  });


}

}) (angular);
