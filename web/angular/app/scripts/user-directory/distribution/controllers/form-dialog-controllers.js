(function (angular) {
'use strict';

angular.module('bns.userDirectory.distribution.formDialogControllers', [])

  .controller('UserDirectoryDistributionCreateDialog', UserDirectoryDistributionCreateDialogController)
  .controller('UserDirectoryDistributionEditDialog', UserDirectoryDistributionEditDialogController)

;

function UserDirectoryDistributionFormDialogController (
  $mdDialog, $translate, CollectionMap, userDirectoryGroups, group, model
) {

  var ctrl = this;
  ctrl.group = group; // the group where list is being created
  ctrl.model = model || {
    name: '',
    roles: [],
    group_ids: [],
  };
  ctrl.selection = new CollectionMap([]);
  ctrl.cancel = $mdDialog.cancel;

  init();

  function init () {
    ctrl.existingUserTypes = [
      {
        label: $translate.instant('USER_DIRECTORY.DIRECTORS'),
        value: 'DIRECTOR',
      }, {
        label: $translate.instant('USER_DIRECTORY.TEACHERS'),
        value: 'TEACHER',
      }, {
        label: $translate.instant('USER_DIRECTORY.PARENTS'),
        value: 'PARENT',
      }
    ];

    return userDirectoryGroups.getList().then(function (groups) {
      ctrl.groups = groups;

      // pre-select groups
      angular.forEach(groups, function (group) {
        if (ctrl.model.group_ids.indexOf(group.id) > -1) {
          ctrl.selection.add(group);
          group.selected = true;
        } else {
          group.selected = false;
        }
      });
    });
  }

}

function UserDirectoryDistributionCreateDialogController (_, toast, model,
  $mdDialog, $translate, CollectionMap, userDirectoryGroups, group
) {

  // extend base dialog
  UserDirectoryDistributionFormDialogController.call(this,
    $mdDialog, $translate, CollectionMap, userDirectoryGroups, group, model
  );

  var ctrl = this;
  ctrl.submit = submit;

  function submit () {
    if (ctrl.busy) {
      return;
    }
    ctrl.busy = true;
    ctrl.model.group_ids = _.map(ctrl.selection.list, 'id');

    return ctrl.group.all('distribution-lists').post(ctrl.model)
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (list) {
      toast.success('USER_DIRECTORY.FLASH_CREATE_DISTRIBUTION_LIST_SUCCESS');

      return $mdDialog.hide(list);
    }
    function error (response) {
      toast.error('USER_DIRECTORY.FLASH_CREATE_DISTRIBUTION_LIST_ERROR');

      throw response;
    }
    function end () {
      ctrl.busy = false;
    }
  }

}

function UserDirectoryDistributionEditDialogController (_, toast, model,
  $mdDialog, $translate, CollectionMap, userDirectoryGroups, group
) {

  // extend base dialog
  UserDirectoryDistributionFormDialogController.call(this,
    $mdDialog, $translate, CollectionMap, userDirectoryGroups, group, model
  );

  var ctrl = this;
  ctrl.submit = submit;

  function submit () {
    if (ctrl.busy) {
      return;
    }
    ctrl.busy = true;
    ctrl.model.group_ids = _.map(ctrl.selection.list, 'id');

    return ctrl.model.patch()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (list) {
      toast.success('USER_DIRECTORY.FLASH_EDIT_DISTRIBUTION_LIST_SUCCESS');

      return $mdDialog.hide(list);
    }
    function error (response) {
      toast.error('USER_DIRECTORY.FLASH_EDIT_DISTRIBUTION_LIST_ERROR');

      throw response;
    }
    function end () {
      ctrl.busy = false;
    }
  }

}

})(angular);
