(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbar', BNSNavbarDirective)
  .controller('BNSNavbarController', BNSNavbarController)

;

/**
 * @ngdoc directive
 * @name bnsNavbar
 * @module bns.main.navbar
 * @restrict EA
 *
 * @requires $mdBottomSheet
 */
function BNSNavbarDirective () {

  return {
    restrict: 'EA',
    templateUrl: 'views/main/navbar/bns-navbar.html',
    scope: {
      mode: '@',
      app: '@',
      hasHelp: '@',
      autoOpen: '@',
    },
    controller: 'BNSNavbarController',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSNavbarController ($rootScope, $scope, $state, $element, $window, $location, $timeout, $ocLazyLoad, LEGACY_APP_NAME, storage, dialog, NAVBAR, navbar, navbarHelp, Beta) {

  var ctrl = this;

  ctrl.navbar = navbar;
  ctrl.showHelp = showHelp;
  ctrl.isAppsDialogShown = false;
  ctrl.showAppsDialog = showAppsDialog;
  ctrl.goTo = goTo;
  ctrl.switchMode = switchMode;

  init();

  function init () {
    navbar.enabled = true;
    navbar.mode = navbar.mode || ctrl.mode || NAVBAR.DEFAULT_MODE;
    navbar.hasHelp = ctrl.hasHelp;

    // Sync our model variable with local storage
    storage.bind($scope, 'ctrl.navbar.shown', {
      defaultValue: true,
      storeName: 'bns/navbar/shown',
    });

    $scope.$watch('ctrl.navbar.shown', function (isShown) {
      angular.element('body').toggleClass('navbar-shown', isShown && !$rootScope.hideDockBar);
      $timeout(function () {
        angular.element($window).trigger('resize');
      }, 410, false);
    });

    // Load current group apps
    $scope.$watch('ctrl.navbar.group.id', function (id) {
      if (id) {
        navbar.getApps();
      }
    });

    Beta.get()
      .then(function success (beta) {
        navbar.beta = beta;
      })
    ;

    // Load current group. If initial app given, also load it
    navbar.getOrRefreshGroup().then(function () {
      if (ctrl.app) {
        navbar.setApp(ctrl.app);
      }
      // auto Open modal if needed
      if (ctrl.autoOpen) {
        ctrl.showAppsDialog(null);
      }
    });

    $timeout(function () {
      $element.find('md-toolbar').removeClass('no-transition');
    });

    var unlistenStarterKit = $rootScope.$on('starterKit.MAIN.step', function (event, step) {
      if (['1-2.1', '1-2.2', '1-2.3', '1-3.1', '1-4.1', '1-5.1', '1-5.2', '1-6.1', '1-7.1', '1-8.1'].indexOf(step.step) > -1) {
        if (ctrl.isAppsDialogShown) {
          return;
        }
        showAppsDialog();
      }
    });
    $scope.$on('$destroy', function cleanup () {
      unlistenStarterKit();
    });
  }

  /**
   * Gets a link to the given app, in current mode
   *
   * @param  {Object} app
   * @returns {String}
   */
  function getAppLink (app) {
    if ('CERISE' === app.unique_name) {
      return app.link;
    }

    var link;

    // check access
    if (app['has_access_'+navbar.mode]) {
      link = app._links[navbar.mode];
    }

    // if no access, try other modes
    angular.forEach(NAVBAR.MODES, function (mode) {
      if (!link && app['has_access_'+mode]) {
        link = app._links[mode];
      }
    });

    // if no access, or no specific link for current mode
    if (!link) {
      link = app._links[NAVBAR.DEFAULT_MODE];
    }

    // slugify locale
    if (link.href) {
      link.href = link.href.toLowerCase().replace('_', '-');
    }

    if (link.href && app.unique_name === 'SPOT') {
      var origin = app.spot_origin || 'spot';
      if (-1 !== link.href.indexOf('?')) {
        link.href += '&origin=' + origin;
      } else {
        link.href += '?origin=' + origin;
      }
    }

    return link.href;
  }

  /**
   * Shows the Apps modal dialog as a promise. It is resolved when an app is
   * chosen.
   */
  function showAppsDialog ($event) {
    ctrl.isAppsDialogShown = true;
    dialog.custom({
      clickOutsideToClose: true,
      templateUrl: 'views/main/navbar/apps-dialog.html',
      controller: 'BNSNavbarDialogController',
      controllerAs: 'dialog',
      targetEvent: $event,
    })
      .then(switchForSelection)
    ;

    /**
     * React to the selection from Apps modal
     *
     * @param  {Object} selection an object containing:
     *                            - app: the chosen app
     *                            - group: the group where it was chosen. Can be
     *                              either an actual group, or the string 'user'
     */
    function switchForSelection (selection) {
      if (angular.isNumber(selection.group.id)) {
        navbar.setGroup(selection.group).then(function () {
          goTo(selection.app);
        });
      } else {
        // no id, app in user space => no switch
        goTo(selection.app);
      }
    }
  }

  function goTo (app) {
    if (angular.isString(app)) {
      return navbar.getOrRefreshGroup().then(function (group) {
        return group.all('applications').one(app).get()
          .then(goTo)
        ;
      });
    }

    // let legacy js handle this
    if ('DIRECTORY' === app.unique_name) {
      return angular.element('body').trigger('bns.directory_invoke');
    }

    // TODO: make this a parameter
    if ('USER_DIRECTORY' === app.unique_name) {
      // 1. Make sure that the user directory app and its dependencies are loaded
      // 2. Navigate via router, to correctly trigger sticky state
      return $ocLazyLoad.load(LEGACY_APP_NAME).then(function () {
        return $ocLazyLoad.load('userDirectory').then(function () {
          return $state.go('userDirectory');
        });
      });
    } else {
      var href = getAppLink(app);
      var matchedState = navbar.getStateFromUrl(href);

      navbar.setApp(app);

      // known router state, let the router handle this
      if (matchedState) {
        return $location.url(matchedState.url);
      }

      // all else failed, make a hard navigation
      $window.location.href = href;
    }
  }

  /**
   * Switch mode and redirect to the given app (in new mode)
   *
   * @param {Object} app
   */
  function switchMode (app) {
    // if no access in other mode, simply go to app index
    switch (navbar.mode) {
      case NAVBAR.MODE_FRONT:
        if (!app.has_access_back) {
          return goTo(app);
        }
        break;
      case NAVBAR.MODE_BACK:
        if (!app.has_access_front) {
          return goTo(app);
        }
        break;
    }

    // can toggle mode: do it and go to app index in new mode
    navbar.mode = NAVBAR.MODE_FRONT === navbar.mode ? NAVBAR.MODE_BACK : NAVBAR.MODE_FRONT;
    goTo(app);
  }

  function showHelp ($event) {
    return navbarHelp.show($event);
  }

}

}) (angular);
