'use strict';

angular.module('bns.workshop.document.mainController', [
  'bns.realtime.socket',
  'bns.workshop.document.sync',
  'bns.workshop.document.manager',
  'bns.workshop.document.lockManager',
])

  .controller('WorkshopDocumentMainController', function ($rootScope, $state, socket, me, document, workshopThemeStyler, workshopDocumentSync, workshopDocumentManager, WorkshopDocumentState, workshopDocumentLockManager, $scope) {
    var ctrl = this;

    ctrl.refresh = refresh;
    ctrl.changeTheme = changeTheme;

    init();

    // initializes this controller
    function init () {
      if (document.is_locked && !me.rights.workshop_document_manage_lock) {
        $state.go('app.workshop.index');
        return;
      }

      var channel = 'WorkshopDocument('+WorkshopDocumentState.document.id+')';
      socket.join(channel, function () {
        workshopDocumentSync.start();
        socket.introduce(me);
      });

      workshopDocumentLockManager.init(
        WorkshopDocumentState.document._embedded.locks,
        WorkshopDocumentState.document._embedded.widget_groups
      );

      $scope.$on('$destroy', function () {
        workshopDocumentSync.stop();
        socket.leave(channel);
      });

      // fired on 1st connect error and on reconnect error
      $scope.$on('socket:connect_error', handleSocketConnectError);

      // fired on reconnect success
      $scope.$on('socket:reconnect', handleSocketConnectSuccess);

      workshopThemeStyler.setTheme(WorkshopDocumentState.document._embedded.theme);

      // redirect to 'valid' state
      if ($state.current.name === 'app.workshop.document') {
        $state.go('app.workshop.document.base.index', {
          documentId: WorkshopDocumentState.document.id,
          pagePosition: 1,
        });
      }
    }

    /**
     * Refreshes the content
     *
     * @returns {Object} A promise
     */
    function refresh () {
      return ctrl.document.get()
        .then(function (document) {
          workshopDocumentManager.document = document;
          WorkshopDocumentState.document = document;
        })
      ;
    }

    /**
     * Changes the theme of the current document for the given one
     *
     * @param {Object} theme
     */
    function changeTheme (theme) {
      workshopDocumentManager.changeTheme(theme);
    }

    function handleSocketConnectError () {
      ctrl.socketConnectError = true;
    }

    function handleSocketConnectSuccess () {
      ctrl.socketConnectError = false;
    }

  });
