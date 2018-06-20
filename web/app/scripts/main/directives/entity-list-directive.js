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
 *  - `bns-entity-image` {String} Property path of the image to use. Defaults to
 *                                `avatar_url`
 *  - `bns-entity-name` {String} Property path of the name to use. Defaults to
 *                                `label` then `full_name`.
 *
 * @example
 * <bns-entity-list list="myArrayOfGroupsAndUsers">
 *   <div class="foo">An optional label</div>
 * </bns-entity-list>
 */
function BNSEntityListDirective (_) {

  return {
    scope: {
      list: '=',
      entityImage: '@bnsEntityImage',
      entityName: '@bnsEntityName',
    },
    transclude: true,
    template: '<div class="entity-list-label" ng-transclude></div>'+
      '<md-chips class="md-contact-chips bns-chips-sm" ng-model="list" readonly="true">'+
        '<md-chip-template>'+
          '<img ng-if="::getEntityImage($chip)" ng-src="{{::getEntityImage($chip)}}" class="bns-avatar size-24">'+
          '<span class="entity-list-item-label">{{ getEntityName($chip) }}</span>'+
        '</md-chip-template>'+
      '</md-chips>',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    scope.getEntityImage = buildGetter([attrs.bnsEntityImage, 'avatar_url']);
    scope.getEntityName = buildGetter([attrs.bnsEntityName, 'label', 'full_name']);

    // builds a getter function that will try all the given property paths until
    // a defined value is found
    function buildGetter (paths) {
      return function ($chip) {
        var value;
        for (var i = 0; i < paths.length; i++) {
          value = _.get($chip, paths[i]);
          if (undefined !== value) {
            break;
          }
        }

        return value;
      };
    }
  }

}

})(angular);
