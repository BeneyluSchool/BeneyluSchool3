(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.appStateProvider
 */
angular.module('bns.core.appStateProvider', [])

  .provider('appState', AppStateProvider)

;

/**
 * @ngdoc provider
 * @name appStateProvider
 * @module bns.core.appStateProvider
 *
 * @description
 * Provides utility functions to create base application states, for ui-router.
 */
function AppStateProvider () {

  this.createRootState = createRootState;
  this.createBackState = createBackState;

  // dummy service, to comply with ng API
  this.$get = function () {
    return {};
  };

  /**
   * Creates a root state for an application with the given name (dash-cased)
   *
   * @param {String} name
   * @param {Boolean|String} useTheme Whether to also set the related md theme
   * @returns {Object} A router state configuration object
   */
  function createRootState (name, useTheme) {
    var theme = useTheme ? ('string' === typeof useTheme ? useTheme : '%name%') : '';
    var tokenizedName = name.toUpperCase().replace(new RegExp('[ -]', 'g'), '_');
    var template = '<ui-view id="app-%name%" class="layout-column flex app-%name%"'+(useTheme?' md-theme="'+theme+'"':'')+'></ui-view>';

    return {
      url: '/'+name,
      abstract: true,
      onEnter: ['navbar', function (navbar) {
        navbar.setApp(tokenizedName);
        angular.element('body').attr('data-app', name);
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-app');
      },
      template: template.replace(new RegExp('%name%', 'g'), name),
      resolvePolicy: { when: 'EAGER' }, // default behavior of ui-router 0.2.x
    };
  }

  function createBackState () {
    return {
      url: '/manage',
      abstract: true,
      templateUrl: 'views/main/back.html',
      onEnter: ['navbar', function (navbar) {
        angular.element('body').attr('data-mode', 'back');
        navbar.mode = 'back';
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
    };
  }

}

})(angular);
