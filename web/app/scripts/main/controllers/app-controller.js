(function (angular) {
'use strict';

angular.module('bns.main.appController', [])

  .controller('AppController', AppController)

;

/**
 * @ngdoc controller
 * @name AppController
 * @module bns.main.appController
 *
 * @description
 *
 * Application-wide controller
 *  - Exposes utilities and helpers
 *  - Sets up global watchers
 *
 * @requires $scope
 * @requires $window
 * @requires $sniffer
 * @requires $mdMedia
 * @requires $mdUtil
 * @requires $timeout
 * @requires moment
 * @requires parameters
 * @requires dialog
 * @requires toast
 * @requires navbar
 * @requires sidebar
 */
function AppController ($rootScope, $scope, $window, $sniffer, $state, $stateParams, $mdMedia, $mdUtil, $timeout, moment, parameters, global, dialog, toast, navbar, sidebar) {
  var app = this;
  app.toggleSidebar = toggleSidebar;
  app.isSidebarLockedOpen = isSidebarLockedOpen;
  app.go = go;
  app.emit = emit;
  app.broadcast = broadcast;
  app.sniffer = $sniffer;
  app.media = $mdMedia;
  app.state = $state;

  $scope.$mdMedia = $mdMedia;
  $scope.Date = newDate;
  $scope.parameters = parameters;
  $scope.back = back;
  $scope.dialog = dialog;
  $scope.toast = toast;
  $scope.navbar = navbar;
  $scope.print = print;
  $scope.moment = moment;

  var onWindowResize;

  init();

  function init () {
    // lazy way to trigger a digest on window resize
    onWindowResize = $mdUtil.debounce(angular.noop, 10);
    angular.element($window).on('resize', onWindowResize);

    $scope.$on('$destroy', cleanup);
    if ($stateParams.embed) {
      console.info('Embedding app');
      $rootScope.isEmbed = true;
      angular.element('body').addClass('embedded');
    }

    $rootScope.anonymous = global('anonymous');
  }

  function cleanup () {
    angular.element($window).off('resize', onWindowResize);
  }

  function toggleSidebar () {
    sidebar.toggle();

    $timeout(function () {
      angular.element($window).trigger('resize');
    }, 325, false);
  }

  function isSidebarLockedOpen () {
    return sidebar.getIsLockedOpen();
  }

  function go (href, params, $event) {
    if ($event) {
      if (angular.element($event.target).closest('.md-secondary').length) {
        return console.warn('app.go() from secondary item: aborting');
      }
    }

    var state = $state.get(href);
    if (state) {
      params = params || {};
      return $state.go(state, params);
    }

    /* global window */
    window.location.href = href;
  }

  function emit () {
    var args = [].slice.call(arguments);
    var scope = args.shift();
    var name = args.shift();

    return scope.$emit(name, args);
  }

  function broadcast () {
    var args = [].slice.call(arguments);
    var scope = args.shift();
    var name = args.shift();

    return scope.$broadcast(name, args);
  }

  function print () {
    return $window.print();
  }

  function back () {
    if ($window.history && angular.isFunction($window.history.back)) {
      $window.history.back();
    }
  }

  /**
   * Creates a new Date object, based on the given arguments. This is a simple
   * proxy to "new Date(...)", to be used in ng expressions.
   */
  function newDate () {
    // add a dummy first argument
    [].unshift.call(arguments, null);

    return new (Date.bind.apply(Date, arguments))();

    // generic alternative:
    // return new (Function.prototype.bind.apply(Date, arguments))();
  }
}

}) (angular);
