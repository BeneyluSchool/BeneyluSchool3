(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbarIcon', BNSNavbarIconDirective)

;

/**
 * @ngdoc directive
 * @name bnsNavbarIcon
 * @module bns.main.navbar
 *
 * @description
 * Specialized for display of icons in the navbar and apps modal.
 *
 * ** Attributes **
 *  - `item {Object|String}`: an app, a group, or the name of an icon
 *  - `flags {Object}`: map of conditionals (same as used by ng-class),
 *                      indicating if a flag should be used. Flags are small
 *                      icons displayed in a corner of the main icon.
 *
 * @requires Routing
 * @requires Users
 * @requires parameters
 */
function BNSNavbarIconDirective (Routing, Users, parameters) {
  /*jshint unused:false*/

  return {
    templateUrl: 'views/main/navbar/bns-navbar-icon.html',
    scope: {
      item: '=',
      flags: '=',
    },
    link: postLink,
  };

  function postLink (scope) {
    var iconPath = parameters.app_base_path + '/app/images';

    scope.$watch('item', function () {
      refreshIcon();
    });

    // Check if a flag should be displayed. Stop on the first found.
    scope.$watch('flags', function () {
      scope.visibleFlags = {};
      for (var flag in scope.flags) {
        if (scope.flags[flag]) {
          scope.visibleFlags[flag] = icon(flag, 'png');
        }
      }
    });

    function refreshIcon () {
      scope.svg = false;
      scope.img = false;

      if (scope.item) {
        if ('PROFILE' === scope.item.unique_name || 'me' === scope.item.id || 'me' === scope.item) {
          scope.img = Routing.generate('bns_my_avatar');
        } else if (scope.item.image_url) {
          scope.img = scope.item.image_url;
        } else if (scope.item.icon) {
          // generic icon, on an app
          scope.svg = icon(scope.item.icon);
        } else if (scope.item.unique_name) {
          if ('GROUP' === scope.item.unique_name && scope.item.group_type) {
            scope.sprite = genericSprite(scope.item.group_type);
          } else {
            // generic app
            scope.sprite = appSprite(scope.item);
          }
        } else if (scope.item.type) {
          // group
          scope.sprite = groupSprite(scope.item);
        } else {
          // generic icon
          scope.svg = icon(scope.item);
        }
      }
    }

    function appIcon (app) {
      return iconPath + '/modules/' + app.unique_name.replace('_', '-').toLowerCase() + '/icon.svg';
    }

    function groupIcon (group) {
      return iconPath + '/modules/' + group.type.replace('_', '-').toLowerCase() + '/icon.svg';
    }

    function icon (name, ext) {
      return iconPath + '/main/navbar/' + name + '.' + (ext || 'svg');
    }

    function appSprite (app) {
      return 'sprite-' + app.unique_name.replace('_', '-').toLowerCase() + '-40';
    }

    function groupSprite (group) {
      return 'sprite-' + group.type.replace('_', '-').toLowerCase() + '-40';
    }

    function genericSprite (name) {
      return 'sprite-' + name.replace('_', '-').toLowerCase() + '-40';
    }
  }

}

}) (angular);
