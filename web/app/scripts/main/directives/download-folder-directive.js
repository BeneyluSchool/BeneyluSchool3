(function (angular) {
'use strict';

angular.module('bns.main.downloadFolder', [
  'bns.components.toast',
  'bns.user.groups',
  'bns.user.users',
])

  .directive('bnsDownloadFolder', BNSDownloadFolderDirective)
  .controller('BNSDownloadFolder', BNSDownloadFolderController)

;

function BNSDownloadFolderDirective () {

  return {
    restrict: 'E',
    templateUrl: 'views/main/directives/bns-download-folder.html',
    scope: {
      name: '@',
      right: '@',
      preparationSheet: '@',
    },
    controller: 'BNSDownloadFolder',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSDownloadFolderController (Restangular, toast, Users) {

  var ctrl = this;
  ctrl.getFolder = getFolder;
  ctrl.canSeeFolder = false;

  if (!(ctrl.name && ctrl.right)) {
    return console.warn('Cannot download folder without name and right');
  }

  init();

  function init () {
    return Users.hasCurrentRight(ctrl.right).then(function success (result) {
      ctrl.canSeeFolder = result;
    });
  }

  function getFolder () {
    ctrl.busy = true;
    return Restangular.one(ctrl.name, 'download-folder')
      .get()
      .then(function success () {
        toast.success('MAIN.FLASH_FOLDER_DOWNLOAD_SUCCESS');
      })
      .catch(function error () {
        toast.error('MAIN.FLASH_FOLDER_DOWNLOAD_ERROR');
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
