(function (angular) {
'use strict';

angular.module('bns.userDirectory.distribution.deleteDialogController', [])

  .controller('UserDirectoryDistributionDeleteDialog', UserDirectoryDistributionDeleteDialogController)

;

function UserDirectoryDistributionDeleteDialogController (_, $mdDialog, CollectionMap, toast, userDirectoryDistributions, preselected) {

  var ctrl = this;
  ctrl.cancel = $mdDialog.cancel;
  ctrl.submit = submit;
  ctrl.selection = new CollectionMap([]);
  ctrl.busy = false;

  init();

  function init () {
    angular.forEach(preselected, function (list) {
      ctrl.selection.add(list);
    });
  }

  function submit () {
    if (ctrl.busy) {
      return;
    }

    var ids = _.map(ctrl.selection.list, 'id');

    return userDirectoryDistributions.remove(ids)
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      toast.success('USER_DIRECTORY.FLASH_DELETE_DISTRIBUTION_LISTS_SUCCESS');

      return $mdDialog.hide(ctrl.selection.list);
    }
    function error (response) {
      toast.error('USER_DIRECTORY.FLASH_DELETE_DISTRIBUTION_LISTS_ERROR');

      throw response;
    }
    function end () {
      ctrl.busy = false;
    }
  }

}

})(angular);
