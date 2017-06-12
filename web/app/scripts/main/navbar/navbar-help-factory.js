(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .factory('navbarHelp', NavbarHelpFactory)

;

function NavbarHelpFactory (dialog) {

  var help = {
    show: show,
  };

  function show ($event) {
    return dialog.custom({
      templateUrl: 'views/main/navbar/navbar-help-dialog.html',
      controller: ['dialog', function NavbarHelpDialogController (dialog) {
        this.hide = dialog.hide;
      }],
      controllerAs: 'dialog',
      clickOutsideToClose: true,
      targetEvent: $event,
    });
  }

  return help;

}

})(angular);
