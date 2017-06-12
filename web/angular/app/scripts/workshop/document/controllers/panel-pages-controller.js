'use strict';

angular.module('bns.workshop.document.panelPagesController', [
  'ui.router',
  'bns.core.message',
  'bns.user.users',
  'bns.workshop.restangular',
  'bns.workshop.document.state',
  'bns.workshop.document.manager',
  'bns.workshop.document.deletePageModal',
  'bns.workshop.document.lockManager',
])

.controller('WorkshopDocumentPanelPagesController', function ($scope, $state, _, message, Users, WorkshopRestangular, WorkshopDocumentState, workshopDocumentManager, workshopDocumentDeletePageModal, workshopDocumentLockManager) {
  var ctrl = this;

  ctrl.document = WorkshopDocumentState.document;
  ctrl.page = WorkshopDocumentState.page;
  ctrl.addPage = addPage;
  ctrl.removePage = removePage;
  ctrl.sortableConf = {};
  ctrl.busy = false;
  ctrl._sortOrder = null;

  init();

  /**
   * Adds a new page at the end of current document
   *
   * @param {Boolean} redirect Whether to redirect to the newly-added page
   */
  function addPage (redirect) {
    workshopDocumentManager.addPage().then(function (id) {
      if (redirect) {
        // check if new page is already present
        var newPage = _.find(ctrl.document._embedded.pages, { id: id });
        if (newPage) {
          $state.go('app.workshop.document.base.pages', {
            pagePosition: newPage.position,
          });
        } else {
          // watch the page collection for when the new page arrives
          var unwatch = $scope.$watchCollection('ctrl.document._embedded.pages', function (coll) {
            var newPage = _.find(coll, { id: id });
            if (newPage) {
              unwatch();
              $state.go('app.workshop.document.base.pages', {
                pagePosition: newPage.position,
              });
            }
          });
        }
      }
    });
  }

  function removePage (page) {
    Users.me().then(function (user) {
      if (workshopDocumentLockManager.isPageLockedForUser(page, user)) {
        message.info('WORKSHOP.DOCUMENT.CANNOT_REMOVE_LOCKED_PAGE');
      } else {
        workshopDocumentDeletePageModal.page = page;
        workshopDocumentDeletePageModal.activate();
      }
    });
  }

  function init() {
    ctrl.sortableConf = {
      scroll: true,
      scrollSensitivity: 200,
      scrollSpeed: 20,
      onSort: onSort,
    };

    /**
     * Sortable handler
     *
     * @param {Object} evt the sort event
     */
    function onSort (evt) {
      var sortable = evt.sortable;
      var oldPosition = ctrl.page.position;

      ctrl.busy = true;
      sortable.option('disabled', true);

      workshopDocumentManager.movePage(evt.model, evt.newIndex + 1)
        .then(success)
        .catch(error)
        .finally(end)
      ;
      function success () {
        // compare current page new position: update state if it has changed
        var newPosition = ctrl.page.position;
        if (oldPosition !== newPosition) {
          $state.go('app.workshop.document.base.pages', {
            pagePosition: newPosition
          });
        }
      }
      function error () {
        // reload page
        $state.go('app.workshop.document.base.pages', {
          reload: true,
          pagePosition: oldPosition
        });
      }
      function end () {
        ctrl.busy = false;
        sortable.option('disabled', false);
      }
    }
  }

});
