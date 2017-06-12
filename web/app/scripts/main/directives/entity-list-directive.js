(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.entityList
 */
angular.module('bns.main.entityList', [])

  .directive('bnsEntityList', BNSEntityListDirective)

;

/**
 * @ngdoc directive
 * @name bnsEntityList
 * @module bns.main.entityList
 *
 * @description
 * Displays a list of groups and users as readonly chips. The optional
 * transcluded content is used as a label, displayed left of the list.
 *
 * ** Attributes **
 *  - `list` {Array}: the entity list, used as ng-model
 *
 * @example
 * <bns-entity-list list="myArrayOfGroupsAndUsers">
 *   <div class="foo">An optional label</div>
 * </bns-entity-list>
 */
function BNSEntityListDirective () {

  return {
    scope: {
      list: '=',
    },
    transclude: true,
    template: '<div class="entity-list-label" ng-transclude></div>'+
      '<md-chips class="md-contact-chips bns-chips-sm" ng-model="list" readonly="true">'+
        '<md-chip-template>'+
          '<img ng-if="::$chip.avatar_url" ng-src="{{::$chip.avatar_url}}" class="bns-avatar size-24">'+
          '<span class="entity-list-item-label">{{ $chip.label || $chip.full_name }}</span>'+
        '</md-chip-template>'+
      '</md-chips>',
  };

}

})(angular);
