(function (angular) {
'use strict'  ;

/**
 * @ngdoc module
 * @name bns.components.sidebar
 *
 * @description
 * The sidebar component, and various subcomponents
 */
angular.module('bns.components.sidebar', [])

  .directive('bnsSidebarToggle', BNSSidebarToggleDirective)
  .directive('bnsSidebarHeader', BNSSidebarHeaderDirective)

;

/**
 * @ngdoc directive
 * @name bnsSidebarToggle
 * @module bns.components.sidebar
 *
 * @description
 * A macro directive for the sidebar toggle button.
 *
 * @requires $compile
 */
function BNSSidebarToggleDirective ($compile) {

  return {
    restrict: 'AE',
    terminal: true,
    compile: compile,
    priority: 1050,
  };

  function compile (element, attrs) {
    var btn = angular.element('<md-button><md-icon>menu</md-icon></md-button>');
    var btnAttrs = {
      'class': 'md-icon-button bns-sidebar-toggle',
      'aria-label': 'Menu',
      'ng-click': 'app.toggleSidebar()',
    };
    angular.forEach(attrs, function (value, attr) {
      // skip angular attributes
      if (attr.indexOf('$') >= 0) {
        return;
      }
      // concatenat classes, else replace whole attr
      if ('class' === attr) {
        btnAttrs[attr] += ' ' + value;
      } else {
        btnAttrs[attr] = value;
      }
    });
    btn.attr(btnAttrs);

    // Hacky way to have element template replace. Can't use "replace: true"
    // because md-button needs the template too.
    element.append(btn);

    var linked = $compile(element.children().unwrap());

    return function postLink (scope) {
      linked(scope);
    };
  }

}

/**
 * @ngdoc directive
 * @name bnsSidebarHeader
 * @module bns.components.sidebar
 *
 * @description
 * A macro directive for the sidebar header.
 *
 * ** Attributes **
 *  - `app` {Object}: the current app
 *  - `mode` {String}: the current navigation mode (front, back)
 *  - `show-description` {Boolean}: Whether to show the app description.
 *                                  Defaults to false.
 */
function BNSSidebarHeaderDirective () {

  return {
    restrict: 'EA',
    scope: {
      app: '=',
      mode: '@',
      showDescription: '=',
    },
    templateUrl: 'views/components/sidebar/bns-sidebar-header.html',
    link: postLink,
  };

  function postLink (scope) {
    scope.mode = scope.mode || 'back';
  }

}

})(angular);
