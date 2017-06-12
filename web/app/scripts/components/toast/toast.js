(function (angular) {
'use strict';

angular.module('bns.components.toast', [])

  .factory('toast', BNSToastFactory)
  .directive('bnsToastInvoke', BNSToastInvokeDirective)

;

/**
 * @ngdoc service
 * @name toast
 * @module bns.components.toast
 *
 * @description
 * Wrapper of $mdToast, for BNS custom presets
 *
 * ** Methods **
 *  - `simple(conf)`: shows a simple toast
 *  - `success(conf)`: shows a success toast
 *  - `error(conf)`: shows an error toast
 *
 * @requires $mdToast
 * @requires $translate
 */
function BNSToastFactory ($mdToast, $translate) {

  var defaults = {
    // mdToast options
    hideDelay: 3000,
    position: 'bottom right',
    templateUrl: 'views/components/toast/bns-toast.html',
    controller: function BNSToastController () {
      this.resolve = function () { $mdToast.hide(true); };
      this.abort = function (){ $mdToast.cancel(); };
    },
  };

  return {
    simple: simple,
    success: success,
    error: error,
    show: show,
  };

  function simple (config) {
    config = extendConfig({
      intent: 'primary',
    }, config);

    return show(config);
  }

  function success (config) {
    config = extendConfig({
      intent: 'accent',
    }, config);

    return show(config);
  }

  function error (config) {
    config = extendConfig({
      intent: 'warn',
      hideDelay: 8000,
    }, config);

    return show(config);
  }

  function show (config) {
    if (config.content) {
      config.content = $translate.instant(config.content);
    }

    var locals = {};
    ['intent', 'action', 'content'].forEach(function (key) {
      locals[key] = config[key];
    });
    config.locals = angular.extend(config.locals || {}, locals);

    return $mdToast.show($mdToast.simple(config));
  }

  function extendConfig (preset, config) {
    if (angular.isString(config)) {
      config = { content: config };
    }

    return angular.extend({}, defaults, preset, config);
  }
}

/**
 * @ngdoc directive
 * @name bnsToastInvoke
 * @module bns.components.toast
 * @restrict EA
 *
 * @description
 * Invokes a md toast upon initialization
 *
 * @example
 * <bns-toast-invoke type="myToastType" content="My toast content"></bns-toast-invoke>
 *
 * @requires $timeout
 * @requires toast
 */
function BNSToastInvokeDirective ($timeout, toast) {

  return {
    restrict: 'EA',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var method = 'simple';

    switch (attrs.type) {
      case 'success':
      case 'error':
        method = attrs.type;
        break;
    }

    $timeout(function () {
      toast[method]({
        content: attrs.content
      });
    }, 0);
  }

}

}) (angular);
