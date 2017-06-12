(function (angular) {
'use strict';

angular.module('bns.breakfastTour.splashController', [
  'bns.components.toast',
])

  .controller('BreakfastTourSplashController', BreakfastTourSplashController)

;

function BreakfastTourSplashController (Restangular, toast) {

  var ctrl = this;
  ctrl.getFolder = getFolder;
  ctrl.canSeeFolder = false;

  init();

  function init () {
    Restangular.one('users/me').get()
      .then(function success (user) {
        if (user && user.rights && user.rights.breakfast_tour_activation) {
          ctrl.canSeeFolder = true;
        }
      })
    ;
  }

  function getFolder () {
    ctrl.busy = true;
    return Restangular.one('breakfast-tour/download-folder')
      .get()
      .then(function success () {
        toast.success('BREAKFAST_TOUR.FLASH_FOLDER_DOWNLOAD_SUCCESS');
      })
      .catch(function error () {
        toast.error('BREAKFAST_TOUR.FLASH_FOLDER_DOWNLOAD_ERROR');
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
