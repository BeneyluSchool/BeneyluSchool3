(function (angular) {
'use strict'  ;

/**
 * @ngdoc module
 * @name bns.components.sidebar
 *
 * @description
 * The sidebar component, and various subcomponents
 */
angular.module('bns.components.sidebar', [
  'angularLocalStorage',
])

  .directive('bnsSidebarToggle', BNSSidebarToggleDirective)
  .directive('bnsSidebarHeader', BNSSidebarHeaderDirective)
  .factory('Sidebar', SidebarFactory)
  .service('sidebar', SidebarService)

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
 * @requires sidebar
 */
function BNSSidebarToggleDirective ($compile, sidebar) {

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
      'ng-click': '_toggleSidebar()',
    };
    angular.forEach(attrs, function (value, attr) {
      // skip angular attributes
      if (attr.indexOf('$') >= 0) {
        return;
      }

      attr = attrs.$attr[attr]; // revert to denormalized name

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
      scope._toggleSidebar = function () {
        return sidebar.toggle();
      };
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
 *  - `show-toggle` {Boolean}: Whether to show a sidebar toggle button. Defaults
 *                             to false.
 */
function BNSSidebarHeaderDirective (navbar) {

  return {
    restrict: 'EA',
    scope: {
      app: '=',
      mode: '@',
      showDescription: '=',
      showToggle: '=',
    },
    templateUrl: 'views/components/sidebar/bns-sidebar-header.html',
    link: postLink,
  };

  function postLink (scope) {
    scope.mode = scope.mode || 'back';

    var unwatch = scope.$watch('app', function (app) {
      if (!app) {
        return;
      }

      var targetUrl = (app._links[scope.mode] || app._links.front).href;
      var targetState = navbar.getStateFromUrl(targetUrl);
      if (targetState) {
        scope.targetStateName = targetState.state.name;
      } else {
        scope.targetUrl = targetUrl;
      }
      unwatch();
    });
  }

}

/**
 * @ngdoc service
 * @name Sidebar
 * @module bns.components.sidebar
 *
 * @description
 * Handles a sidebar. Status is persisted via localstorage.
 *
 * **Attributes**
 *  - `canLockOpen` {=Boolean}: Whether the sidebar can be locked open. Defaults
 *                              to true. The actual locked open state also
 *                              depends on the screen size.
 * **Methods**
 *  - `open`: Opens the sidebar (locked or not).
 *  - `close`: Closes the sidebar (locked or not).
 *  - `toggle`: Toggles the sidebar (locked or not).
 *  - `getIsLockedOpen` {=Boolean}: Checks if the sidebar is locked open.
 *  - `getLockBreakpoint` {=Boolean}: Gets whether the screen size allows for a
 *                                    locked open sidebar.
 */
function SidebarFactory ($q, $mdSidenav, $mdMedia, storage) {

  function Sidebar (componentName, baseStorageKey) {
    this.componentName = componentName;
    this.baseStorageKey = baseStorageKey;
    this.canLockOpen = true;

    if (null === this._get('locked')) {
      this._set('locked', true);
    }
  }

  Sidebar.prototype.open = function () {
    var self = this;

    if (this.getLockBreakpoint() && this.canLockOpen) {
      return openLocal();
    }

    var sidebarComponent = $mdSidenav(this.componentName, true);
    if (sidebarComponent.then) {
      return sidebarComponent.then(function (instance) {
        return instance.open().then(openLocal);
      });
    } else {
      return sidebarComponent.open().then(openLocal);
    }

    function openLocal () {
      return $q.when(self._set('locked', true));
    }
  };

  Sidebar.prototype.close = function () {
    var self = this;

    if (this.getLockBreakpoint() && this.canLockOpen) {
      return closeLocal();
    }

    var sidebarComponent = $mdSidenav(this.componentName, true);
    if (sidebarComponent.then) {
      return sidebarComponent.then(function (instance) {
        return instance.close().then(closeLocal);
      });
    } else {
      return sidebarComponent.close().then(closeLocal);
    }

    function closeLocal () {
      return $q.when(self._set('locked', false));
    }
  };

  Sidebar.prototype.toggle = function () {
    var self = this;

    if (this.getLockBreakpoint() && this.canLockOpen) {
      return toggleLocal();
    }

    return $mdSidenav(this.componentName).toggle().then(toggleLocal);

    function toggleLocal () {
      return $q.when(self._set('locked', !self._get('locked')));
    }
  };

  Sidebar.prototype.getIsLockedOpen = function () {
    return this.getLockBreakpoint() && this.canLockOpen && this._get('locked');
  };

  Sidebar.prototype.getLockBreakpoint = function () {
    return $mdMedia('gt-md');
  };

  /**
   * Sets a localstorage value in the sidebar namespace.
   */
  Sidebar.prototype._set = function (name, value) {
    return storage.set(this.baseStorageKey + '/' + name, value);
  };

  /**
   * Gets a localstorage value in the sidebar namespace.
   */
  Sidebar.prototype._get = function (name) {
    return storage.get(this.baseStorageKey + '/' + name);
  };

  return Sidebar;

}

/**
 * @ngdoc service
 * @name sidebar
 * @module bns.components.sidebar
 *
 * @description
 * The main app sidebar
 *
 * TODO: move it out of the components namespace
 *
 * @requires Sidebar
 */
function SidebarService (Sidebar) {

  return new Sidebar('left', 'bns/sidebar');

}

})(angular);
