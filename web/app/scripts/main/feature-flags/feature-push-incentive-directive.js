(function (angular) {
'use strict';

angular.module('bns.main.featureFlags')

  .directive('bnsFeaturePushIncentive', BnsFeaturePushIncentiveDirective)
  .controller('BnsFeaturePushIncentive', BnsFeaturePushIncentiveController)

;

function BnsFeaturePushIncentiveDirective () {

  return {
    template: '<bns-inset bns-inset-icon="shield" class="flex bns-primary text-center">'+
      '<div class="md-body-2" translate>MAIN.DESCRIPTION_PUSH_PRO</div>'+
      '<div>'+
        '<md-button ng-href="{{::push.link}}" target="_blank" class="md-raised md-accent">'+
          '<bns-icon>spot</bns-icon>'+
          '<span translate>MAIN.LINK_PUSH_INCENTIVE</span>'+
        '</md-button>'+
      '</div>'+
    '</bns-inset>',
    controller: 'BnsFeaturePushIncentive',
    controllerAs: 'push',
    bindToController: true,
  };

}

function BnsFeaturePushIncentiveController (Routing) {

  var push = this;
  push.link = Routing.generate('BNSAppSpotBundle_front', {
    code: 'LICENCE_PRO',
    origin: 'push',
  });

}

})(angular);
