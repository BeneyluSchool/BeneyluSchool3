'use strict';

angular.module('bns.workshop.document.sceneController', [
  'bns.core.message',
  'bns.workshop.document.state',
])

  .controller('WorkshopDocumentSceneController', function (_, $rootScope, $scope, $state, message, WorkshopDocumentState, workshopDocumentManager) {
    var ctrl = this;

    ctrl.document = WorkshopDocumentState.document;
    ctrl.page = WorkshopDocumentState.page;
    ctrl.prevPosition = null;
    ctrl.currentPosition = ctrl.page.position;
    ctrl.nextPosition = null;
    ctrl.addPage = addPage;

    init();


    /* ---------------------------------------------------------------------- *\
     *    API
    \* ---------------------------------------------------------------------- */

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


    /* ---------------------------------------------------------------------- *\
     *    Implementation
    \* ---------------------------------------------------------------------- */

    // initialize this controller
    function init () {
      // listen to widgetGroup changes
      $scope.$on('widget-group.removed', function () {
        refresh();
      });

      $scope.$watch('ctrl.page', function () {
        updateNavPositions();
      });

      $scope.$watchCollection('ctrl.document._embedded.pages', function () {
        updateNavPositions();
      });

      var rootWatchers = [];
      rootWatchers.push($rootScope.$on('workshop.document.page.updated', function () {
        checkStatePosition();
      }));
      rootWatchers.push($rootScope.$on('workshop.document.page.deleted', function (event, page) {
        if (page.position < ctrl.currentPosition) {
          // deleted a page before => move current position
          goToPosition(ctrl.currentPosition - 1);
        } else if (page.position > ctrl.currentPosition) {
          // deleted a page after => only check positions
          checkStatePosition();
        } else {
          // deleted current page => refresh with next if it exist, else go to previous
          if (ctrl.nextPosition) {
            var index = page.position - 1;
            ctrl.page = WorkshopDocumentState.page = WorkshopDocumentState.document._embedded.pages[index];
          } else {
            goToPosition(ctrl.currentPosition - 1);
          }
        }
      }));

      $scope.$on('$destroy', function () {
        angular.forEach(rootWatchers, function (unwatcher) {
          unwatcher();
        });
      });
    }

    function updateNavPositions () {
      var currentIdx = _.findIndex(WorkshopDocumentState.document._embedded.pages, {
        'id': WorkshopDocumentState.page.id
      });
      ctrl.prevPosition = WorkshopDocumentState.document._embedded.pages[currentIdx - 1] ? WorkshopDocumentState.page.position - 1 : null;
      ctrl.nextPosition = WorkshopDocumentState.document._embedded.pages[currentIdx + 1] ? WorkshopDocumentState.page.position + 1 : null;
    }

    function checkStatePosition () {
      console.log('checkStatePosition');
      var oldPosition = ctrl.currentPosition; // saved position may be out of date
      var newPosition = ctrl.page.position;
      if (oldPosition !== newPosition) {
        goToPosition(newPosition);
      }
    }

    function goToPosition (position) {
      $state.go('app.workshop.document.base.pages', {
        pagePosition: position
      });
    }

    // refreshes the scene content
    function refresh () {
      ctrl.page.get()
        .then(function (page) {
          ctrl.page = page;
          WorkshopDocumentState.page = page;
        })
        .catch(function error (response) {
          message.error('WORKSHOP.DOCUMENT.GET_PAGE_ERROR');
          console.error('[GET page]', response);
        })
      ;
    }

  });
