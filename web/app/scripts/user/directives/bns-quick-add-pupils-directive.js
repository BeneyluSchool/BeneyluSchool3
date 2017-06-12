(function (angular) {
'use strict';

angular.module('bns.user.quickAddPupils', [])

  .directive('bnsQuickAddPupils', BNSQuickAddPupilsDirective)
  .controller('BNSQuickAddPupils', BNSQuickAddPupilsController)

;

function BNSQuickAddPupilsDirective () {

  return {
    scope: {
      bnsPupils: '=',
      bnsHideActions: '=?',
      bnsBusy: '=?',
      bnsValid: '=?',
      bnsName: '@',
      bnsSource: '@',
    },
    controller: 'BNSQuickAddPupils',
    controllerAs: 'ctrl',
    bindToController: true,
    templateUrl: 'views/user/directives/bns-quick-add-pupils.html',
  };

}

function BNSQuickAddPupilsController ($scope, Restangular, toast) {

  var ctrl = this;
  var route = Restangular.all('users').all('pupils').all('quick-add');
  ctrl.source = ctrl.bnsSource || '';
  ctrl.bnsPupils = [];
  ctrl.validate = validate;
  ctrl.submit = submit;
  ctrl.messageType = null;
  ctrl.message = null;
  ctrl.bnsBusy = false;
  ctrl.bnsValid = false;

  init();

  function init () {
    $scope.$watch('ctrl.source', function () {
      ctrl.bnsValid = false;
    });
    $scope.$on('quickAddPupils.validate', validate);
    $scope.$on('quickAddPupils.submit', submit);
  }

  function validate () {
    ctrl.messageType = null;
    ctrl.message = null;
    ctrl.bnsBusy = true;

    return route.all('validate').post({
      source: ctrl.source,
    })
      .then(function success (pupils) {
        ctrl.bnsPupils = pupils;
        ctrl.bnsValid = true;
      })
      .finally(function end () {
        ctrl.bnsBusy = false;
      })
    ;
  }

  function submit () {
    ctrl.messageType = null;
    ctrl.message = null;
    ctrl.bnsBusy = true;

    return route.post({
      source: ctrl.source,
    })
      .then(success)
      .finally(end)
    ;
    function success (data) {
      if (data.message && data.type) {
        toast[data.type](data.message);
        ctrl.message = data.message;
        ctrl.messageType = data.type;
      }
    }
    function end () {
      ctrl.bnsBusy = false;
    }
  }

}

})(angular);
