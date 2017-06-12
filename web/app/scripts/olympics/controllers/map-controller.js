(function (angular) {
'use strict';

angular.module('bns.olympics.mapController', [
  'bns.olympics.state',
])

  .controller('OlympicsMap', OlympicsMapController)
  .controller('OlympicsMenu', OlympicsMenuController)

;

function OlympicsMapController ($scope, $mdBottomSheet, dialog, olympicsState, canManage) {

  var ctrl = this;
  ctrl.canManage = canManage;
  ctrl.toggleMenu = toggleMenu;
  ctrl.showDialog = showDialog;
  ctrl.state = olympicsState;
  ctrl.position = ctrl.state.last;

  function toggleMenu () {
    if (ctrl.menuShown) {
      $mdBottomSheet.hide();
    } else {
      ctrl.menuShown = true;
      $mdBottomSheet.show({
        templateUrl: 'views/olympics/menu.html',
        controller: 'OlympicsMenu',
        controllerAs: 'menu',
        bindToController: true,
        locals: {
          canManage: ctrl.canManage,
        },
      })
      .finally(function menuClosed () {
        ctrl.menuShown = false;
      });
    }
  }

  function showDialog (topic, $event) {
    return dialog.show({
      templateUrl: 'views/olympics/dialog.html',
      parent: '#app-olympics',
      locals: {
        topic: topic,
      },
      targetEvent: $event,
    });
  }

}

function OlympicsMenuController (Routing, $mdBottomSheet, dialog, olympicsState) {

  var menu = this;
  menu.close = $mdBottomSheet.hide;
  menu.reset = reset;

  function reset ($event) {
    return dialog.confirm({
      targetEvent: $event,
      title: 'Recommence les Jeux',
      content: 'Tu veux mettre tes médailles en jeu ?<br>Tu devras visiter à nouveau chaque site de compétition pour les gagner.',
      cancel: 'Annuler',
      ok: 'Confirmer',
    })
      .then(function () {
        olympicsState.setPlayed('tennis', false);
        olympicsState.setPlayed('fencing', false);
        olympicsState.setPlayed('rowing', false);
        olympicsState.setLastPlayed(null);

        return menu.close();
      })
    ;
  }

}

})(angular);
