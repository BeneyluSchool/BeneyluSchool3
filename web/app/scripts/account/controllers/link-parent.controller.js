(function (angular) {
'use strict';

angular.module('bns.account.linkParentController', [
  'restangular'
])

  .controller('AccountLinkParent', AccountLinkParentController)

;

function AccountLinkParentController ($log, Restangular, toast, $window) {

  var ctrl = this;
  ctrl.step = 'parent';
  ctrl.busy = false;
  ctrl.selectParent = selectParent;

  init();

  function init () {
    ctrl.busy = true;

    return Restangular.one('users-link').one('parents').all('linkable').getList()
      .then(function success (parents) {
        ctrl.parents = parents;
      })
      .catch(function error (response) {
        if (response.status === 400 && response.data && response.data.redirect) {
          $window.location = response.data.redirect;
        }
        toast.error('ACCOUNT.FLASH_LOAD_PARENT_ACCOUNTS_ERROR');
        $log.error(response);
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function selectParent (parent) {
    ctrl.busy = ctrl.overlay = true;

    return Restangular.one('users-link').one('parents').one('link').post(null, {
      user_id: parent.id,
    })
      .then(function success (result) {
        if (result.linked) {
          ctrl.success = result.redirect;
        } else {
          toast.error('ACCOUNT.FLASH_LINK_PARENT_ACCOUNT_ERROR');
        }
      })
      .catch(function error (response) {
        toast.error('ACCOUNT.FLASH_LINK_PARENT_ACCOUNT_ERROR');
        $log.error(response);
      })
      .finally(function end () {
        ctrl.busy = ctrl.overlay = false;
      })
    ;
  }

}

})(angular);
