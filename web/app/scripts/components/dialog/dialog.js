(function (angular) {
'use strict';

angular.module('bns.components.dialog', [])

  .factory('dialog', BNSDialogFactory)

;

function BNSDialogFactory ($mdDialog) {

  var defaults = {
    // mdDialog options
    templateUrl: 'views/components/dialog/bns-dialog.html',
    controller: ['$scope', function mdDialogCtrl($scope) {
      this.scope = $scope;
      this.hide = function () { $mdDialog.hide(true); };
      this.abort = function (){ $mdDialog.cancel(); };
    }],
    controllerAs: 'dialog',
    bindToController: true,
    clickOutsideToClose: true,

    // custom options
    ok: 'OK',
    cancel: 'Cancel',
    intent: 'primary',
    picture: 'jim-tool', // aside character image. Set to false to disable it
  };

  return {
    alert: alert,
    confirm: confirm,
    show: show,
    custom: custom,
    cancel: cancel,
    hide: hide,
  };

  function alert (config) {
    config = angular.extend({
      type: 'alert',
    }, defaults, config);

    return show(config);
  }

  function confirm (config) {
    config = angular.extend({
      type: 'confirm',
    }, defaults, config);

    return show(config);
  }

  function show (config) {
    config = angular.extend({}, defaults, config);

    if (!config.ariaLabel) {
      config.ariaLabel = config.title;
    }

    var locals = {};
    ['type', 'ok', 'cancel', 'title', 'content', 'ariaLabel'].forEach(function (key) {
      locals[key] = config[key];
    });
    config.locals = angular.extend(config.locals || {}, locals);

    return $mdDialog.show(config);
  }

  function custom (config) {
    return $mdDialog.show(config);
  }

  function cancel () {
    return $mdDialog.cancel();
  }

  function hide () {
    return $mdDialog.hide();
  }

}


}) (angular);
