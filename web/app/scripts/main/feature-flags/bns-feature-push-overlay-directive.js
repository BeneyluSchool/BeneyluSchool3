(function (angular) {
'use strict';

angular.module('bns.main.featureFlags')

  .directive('bnsFeaturePushOverlay', BnsFeaturePushOverlayDirective)
  .controller('BnsUnlockFeatureDialog', BnsUnlockFeatureDialogController)

;

function BnsFeaturePushOverlayDirective (dialog) {

  return {
    link: postLink,
  };

  function postLink (scope, element) {
    element.on('click', function (event) {
      return dialog.custom({
        targetEvent: event,
        title: 'MAIN.TITLE_UNLOCK_FEATURE',
        content: 'coucou',
        templateUrl: 'views/main/feature-flags/unlock-feature-dialog.html',
        controller: 'BnsUnlockFeatureDialog',
        controllerAs: 'unlock',
        clickOutsideToClose: true,
      });
    });
  }

}

function BnsUnlockFeatureDialogController (global, dialog) {

  var unlock = this;
  unlock.showMore = false;
  unlock.payUrl = global('pay_url');
  unlock.plansUrl = global('plans_url');
  unlock.abort = function (){ dialog.cancel(); };

}

})(angular);
