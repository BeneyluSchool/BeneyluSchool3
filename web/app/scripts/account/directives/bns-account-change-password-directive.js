(function (angular) {
'use strict';

angular.module('bns.account.changePassword', [])

  .directive('bnsAccountChangePassword', BNSAccountChangePasswordDirective)
  .controller('BNSAccountChangePassword', BNSAccountChangePasswordController)

;

function BNSAccountChangePasswordDirective () {

  return {
    scope: {
      redirect: '@',
    },
    restrict: 'E',
    controller: 'BNSAccountChangePassword',
    controllerAs: 'ctrl',
    bindToController: true,
    templateUrl: 'views/account/directives/bns-account-change-password.html',
  };

}

function BNSAccountChangePasswordController ($state, $translate, Restangular, toast, $window) {

  var ctrl = this;
  ctrl.data = {
    password: '',
    plain_password: '',
  };
  ctrl.busy = false;
  ctrl.errors = [];
  ctrl.submit = submit;

  function submit () {
    ctrl.busy = true;
    ctrl.errors = [];

    return Restangular.one('users').one('password').post('change', {
      password: ctrl.data.password,
      plain_password: ctrl.data.plain_password,
      redirect: angular.isDefined(ctrl.redirect),
    })
      .then(function success (response) {
        if (response && response.redirect) {
          // force a redirection to the url send by the server
          $window.location = response.redirect;
        } else if (angular.isDefined(ctrl.redirect)) {
          return $state.go('classroom');
        } else {
          toast.success('ACCOUNT.FLASH_CHANGE_PASSWORD_SUCCESS');
        }
      })
      .catch(function error (response) {
        if (400 === response.status) {
          if (response.data.errors && response.data.errors.children) {
            angular.forEach(response.data.errors.children, function (messageGroup) {
              angular.forEach(messageGroup.errors, function (message) {
                ctrl.errors.push($translate.instant('ACCOUNT.'+message, {length: 8}));
              });
            });
          }
        } else {
          toast.error('ACCOUNT.FLASH_CHANGE_PASSWORD_ERROR');
          throw response;
        }
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
