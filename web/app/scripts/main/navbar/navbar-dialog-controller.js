(function (angular) {
'use strict'  ;

angular.module('bns.main.navbar')

  .controller('BNSNavbarDialogController', BNSNavbarDialogController)

;

function BNSNavbarDialogController ($rootScope, $scope, $translate, $mdSidenav, $mdMedia, $mdDialog, toast, Users, Groups, Routing, navbar, global, Beta) {

  var GROUP_USER = {
    id: 'me',
    label: '',
  };

  var TAB_INDEXES = {
    0: 'APPLICATIONS',
    1: 'ACTIVITIES',
  };

  var dialog = this;

  dialog.tabIndex = 0;
  dialog.mode = 'list';  // read and written by children directives
  dialog.currentGroup = navbar.group || GROUP_USER;
  dialog.toggleNav = toggleNav;
  dialog.selectGroup = selectGroup;
  dialog.cancel = cancel;
  dialog.goToApp = goToApp;

  init();

  function init () {
    dialog.logoutUrl = Routing.generate('disconnect_user');

    Users.me()
      .then(function success (me) {
        dialog.me = me;
      })
    ;

    Groups.getList()
      .then(function success (groups) {
        // prepend fake group for user apps
        groups.unshift(GROUP_USER);
        dialog.groups = groups;
      })
      .catch(function error (response) {
        toast.error('NAVBAR.GET_GROUPS_ERROR');
        throw response;
      })
    ;

    Beta.get()
      .then(function success (beta) {
        dialog.beta = beta;
      })
    ;

    $scope.$on('bns.navbar.app.selected', function (event, app) {
      event.stopPropagation();
      $mdDialog.hide({
        app: app,
        group: dialog.currentGroup,
      });
    });

    $scope.$watch('dialog.tabIndex', function (index) {
      $translate('NAVBAR.MY_' + TAB_INDEXES[index]).then(function (label) {
        GROUP_USER.label = label;
      });
    });

    // update local objects
    var unlistenUserFavoriteGroup = $rootScope.$on('user.favorite_group', function (event, group) {
      angular.forEach(dialog.groups, function (g) {
        g.is_favorite = (g.id === group.id);
      });
    });

    $scope.$on('$destroy', function cleanup () {
      unlistenUserFavoriteGroup();
    });
  }

  function selectGroup (group) {
    dialog.currentGroup = group;

    if ('me' === group.id) {
      dialog.mode = 'list';
    }

    if (!group.manageable || angular.isString(group.manageable)) {
      dialog.mode = 'list';
    }

    if (!$mdMedia('gt-md')) {
      toggleNav('apps-left');
    }
  }

  function toggleNav (name) {
    $mdSidenav(name).toggle();
  }

  function cancel () {
    $mdDialog.cancel();
  }

  function goToApp (name) {
    $mdDialog.hide({
      app: name,
      group: 'user',
    });
  }

}

})(angular);
