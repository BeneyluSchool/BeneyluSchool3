(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbarBetaPanel', BNSNavbarBetaPanelDirective)
  .controller('BNSNavbarBetaPanel', BNSNavbarBetaPanelController)

;

/**
 * @ngdoc directive
 * @name bnsNavbarBetaPanel
 * @module bns.main.navbar
 *
 * @description Shows a panel to manage beta in a group
 *
 * ** Attributes **
 *  - `group`: the current group
 *  - `init-when`: ng expression that, when provided, delays directive
 *                 initialization until it is truey
 */
function BNSNavbarBetaPanelDirective () {

  return {
    templateUrl: 'views/main/navbar/bns-navbar-beta-panel.html',
    scope: {
      group: '=',
      initWhen: '=',
    },
    controller: 'BNSNavbarBetaPanel',
    controllerAs: 'panel',
    bindToController: true,
  };

}

function BNSNavbarBetaPanelController ($scope, $attrs, $window, $timeout, $translate, $mdSidenav, toast, Beta) {

  var panel = this;
  var pendingRedirect;
  var REDIRECT_DELAY = 5000;
  panel.beta = null;        // beta info for user (does not change)
  panel.betaGroup = null;   // beta info for group (changes)

  lazyInit();

  // call actual directive initialization only when condition is met
  function lazyInit () {
    if ($attrs.initWhen) {
      var unwatch = $scope.$watch('panel.initWhen', function (canInit) {
        if (canInit) {
          unwatch();
          init();
        }
      });
    } else {
      init();
    }
  }

  function init () {
    $scope.$mdSidenav = $mdSidenav; // for sidebar toggle

    return Beta.get().then(success);

    function success (beta) {
      panel.hasInit = true;
      panel.beta = beta;
      setupForUser();
      $scope.$watch('panel.group', setupForGroup);
    }

    function setupForUser () {
      panel.canChange = panel.beta && panel.beta.can_change_mode;
      panel.manager = {
        getStatus: function () {
          return Beta.get().then(function (beta) {
            return { status: !!beta.beta_user };
          });
        },
        toggle: function () {
          return Beta.toggle()
            .then(function (beta) {
              if (pendingRedirect) {
                $timeout.cancel(pendingRedirect);
              }
              pendingRedirect = $timeout(function () {
                redirectIfNeeded(beta);
              }, REDIRECT_DELAY);
              toast.success({
                content: $translate.instant('NAVBAR.FLASH_'+(beta.beta_user?'EN':'DIS')+'ABLE_BETA_SUCCESS'),
                action: $translate.instant('NAVBAR.BUTTON_NO_THANKS'),
                hideDelay: REDIRECT_DELAY,
              })
                .then(function (abort) {
                  if (abort && pendingRedirect) {
                    $timeout.cancel(pendingRedirect);
                  }
                })
              ;

              return { status: !!beta.beta_user };
            })
            .catch(function error (response) {
              toast.error('NAVBAR.FLASH_TOGGLE_BETA_ERROR');

              throw response;
            })
          ;
        },
      };
    }

    function setupForGroup (group) {
      if (!group) {
        panel.canChangeGroup = false;
        panel.betaGroup = null;
        panel.managerGroup = null;

        return;
      }

      panel.canChangeGroup = panel.beta && panel.beta.can_change_mode_in.indexOf(panel.group.id)  > -1;
      panel.betaGroup = null;
      panel.managerGroup = {
        getStatus: function () {
          return Beta.getForGroup(group.id).then(function (betaGroup) {
            panel.betaGroup = betaGroup; // hijack the switch call to set value in panel too

            return { status: true === betaGroup.beta_group_mode ? true : false };
          });
        },
        toggle: function (value) {
          return Beta.setForGroup(group.id, value)
            .then(function () {
              if (panel.betaGroup) {
                panel.betaGroup.beta_group_mode = !!value;
              }

              return { status: !!value };
            })
            .catch(function error (response) {
              toast.error('NAVBAR.FLASH_TOGGLE_BETA_ERROR');

              throw response;
            })
          ;
        }
      };
    }

    function redirectIfNeeded (beta) {
      if (beta.beta_mode !== beta.beta_user) {
        var url = beta[(beta.beta_user?'beta':'normal')+'_redirect_url'];
        $window.location.href = url;
      }
    }
  }

}

})(angular);
