(function (angular) {
'use strict';

angular.module('bns.twoDegrees.menuController', [])

  .controller('TwoDegreesMenu', TwoDegreesMenuController)

;

function TwoDegreesMenuController (Routing, $mdBottomSheet) {

  var menu = this;
  menu.close = $mdBottomSheet.hide;
  menu.reset = reset;

  function reset () {
    return menu.close('reset');
  }

}

})(angular);
