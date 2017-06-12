'use strict';

angular.module('bns.workshop.document.config.states', [
  'ui.router',
  'bns.core.url',
  'bns.workshop.document.state',
  'bns.workshop.document.manager',
  'bns.workshop.document.accessRules',
  'bns.workshop.document.panelState',
])

  .config(function ($stateProvider, URL_BASE_VIEW) {
    var widgetGroupLockSkipRemove = null;


    /* ---------------------------------------------------------------------- *\
     *    State change handler
    \* ---------------------------------------------------------------------- */

    var onDocumentEnter = ['$rootScope', 'document', 'WorkshopDocumentState', 'workshopDocumentManager', 'workshopDocumentAccessRules',
                  function ($rootScope,   document,   WorkshopDocumentState,   workshopDocumentManager,   workshopDocumentAccessRules) {
      $rootScope.hideDockBar = true;
      WorkshopDocumentState.document = document;
      workshopDocumentManager.document = document;
      workshopDocumentAccessRules.enable();
    }];

    var onDocumentExit = ['$rootScope', 'workshopDocumentAccessRules',
                 function ($rootScope,   workshopDocumentAccessRules) {
      $rootScope.hideDockBar = false;
      workshopDocumentAccessRules.disable();
    }];

    var onPageEnter = ['page', 'WorkshopDocumentState',
              function (page,   WorkshopDocumentState) {
      WorkshopDocumentState.page = page;
    }];

    var onPanelKitEditEnter = ['$state', 'widgetGroup', 'WorkshopDocumentState', 'workshopDocumentPanelState', 'workshopDocumentLockManager',
                      function ($state,   widgetGroup,   WorkshopDocumentState,   workshopDocumentPanelState,   workshopDocumentLockManager) {
      WorkshopDocumentState.editedWidgetGroup = widgetGroup;
      workshopDocumentPanelState.large = true;
      workshopDocumentPanelState.expanded = true;

      // ask for a lock on the widget group. If failed (ie. already locked),
      // redirect back to the default kit but do not attempt to clear the lock.
      workshopDocumentLockManager.add(widgetGroup)
        .catch(function (response) {
          // do not attempt to remove the lock (see onPanelKitEditExit)
          widgetGroupLockSkipRemove = widgetGroup.id;
          console.warn(response);
          $state.go('^');
        })
      ;
    }];

    var onPanelKitEditExit = ['widgetGroup', 'WorkshopDocumentState', 'workshopDocumentPanelState', 'workshopDocumentLockManager',
                     function (widgetGroup,   WorkshopDocumentState,   workshopDocumentPanelState,   workshopDocumentLockManager) {
      WorkshopDocumentState.editedWidgetGroup = null;
      workshopDocumentPanelState.large = false;

      // clean lock
      if (widgetGroupLockSkipRemove === widgetGroup.id) {
        widgetGroupLockSkipRemove = null;
      } else {
        workshopDocumentLockManager.remove(widgetGroup);
      }
    }];

    var setLargePanel = ['workshopDocumentPanelState', function (workshopDocumentPanelState) {
      workshopDocumentPanelState.large = true;
    }];

    var setNormalPanel = ['workshopDocumentPanelState', function (workshopDocumentPanelState) {
      workshopDocumentPanelState.large = false;
    }];


    /* ---------------------------------------------------------------------- *\
     *    States
    \* ---------------------------------------------------------------------- */

    $stateProvider
      .state('app.workshop.document', {
        url: '/documents/{documentId:[0-9]+}',
        abstract: false, // redirection is handled by controller
        resolve: {
          'document': ['WorkshopRestangular', '$stateParams', function (WorkshopRestangular, $stateParams) {
            return WorkshopRestangular.one('documents', $stateParams.documentId).get()
              .then(function (document) {
                if (!(document && document.id)) {
                  throw 'WORKSHOP.DOCUMENT.GET_DOCUMENT_ERROR';
                }

                return document;
              })
              .catch(function (response) {
                console.error('[GET document]', response);
                throw 'WORKSHOP.DOCUMENT.GET_DOCUMENT_ERROR';
              })
            ;
          }],
          'me': ['Users', function (Users) {
            return Users.me();
          }],
        },
        views: {
          topbar: {
            templateUrl: URL_BASE_VIEW + '/workshop/document/topbar.html',
            controller: 'WorkshopDocumentTopbarController',
            controllerAs: 'ctrl',
          },
          workshop: {
            templateUrl: URL_BASE_VIEW + '/workshop/document/document.html',
            controller: 'WorkshopDocumentMainController',
            controllerAs: 'ctrl',
          },
          // initiate sidebar and panel at this state, to avoid reload on each
          // page change (cf child state)
          'workshop.sidebar@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/sidebar.html',
            controller: 'WorkshopDocumentSidebarController',
            controllerAs: 'ctrl',
          },
          'workshop.panel@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel.html',
            // TODO: break into small controllers
            // controller: 'WorkshopDocumentsPanelCtrl',
          },
        },
        onEnter: onDocumentEnter,
        onExit: onDocumentExit,
        onInactivate: onDocumentExit,
        onReactivate: onDocumentEnter,
      })
      .state('app.workshop.document.base', {
        url: '/pages/{pagePosition:[0-9]+}',
        abstract: true,
        views: {
          'workshop.scene': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/scene.html',
            controller: 'WorkshopDocumentSceneController',
            controllerAs: 'ctrl',
          }
        },
        resolve: {
          'page': ['document', '$stateParams', function (document, $stateParams) {
            // assume pages are embedded in the correct order
            var index = $stateParams.pagePosition - 1;
            var embeddedPage = document._embedded.pages[index];
            if (!embeddedPage) {
              throw 'WORKSHOP.DOCUMENT.GET_PAGE_FROM_POSITION_ERROR';
            }

            return embeddedPage;
          }],
        },
        onEnter: onPageEnter,
      })
      .state('app.workshop.document.base.index', {
        url: '/index',
        views: {
          // this view is defined in workshop.document (grandparent), hence the
          // @ notation
          'workshop.panel.content@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel/content-general.html',
            controller: 'WorkshopDocumentPanelGeneralController',
            controllerAs: 'ctrl',
          }
        },
        onEnter: setLargePanel,
        onExit: setNormalPanel,
      })
      .state('app.workshop.document.base.pages', {
        url: '/pages',
        views: {
          'workshop.panel.content@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel/content-pages.html',
            controller: 'WorkshopDocumentPanelPagesController',
            controllerAs: 'ctrl',
          }
        }
      })
      .state('app.workshop.document.base.layout', {
        url: '/layout',
        views: {
          'workshop.panel.content@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel/content-layout.html',
            controller: 'WorkshopDocumentPanelLayoutController',
            controllerAs: 'ctrl',
          }
        }
      })
      .state('app.workshop.document.base.kit', {
        url: '/kit',
        views: {
          'workshop.panel.content@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel/content-kit.html',
            controller: 'WorkshopDocumentPanelKitController',
            controllerAs: 'ctrl',
          }
        }
      })
      .state('app.workshop.document.base.kit.edit', {
        url: '/{widgetGroupId:[0-9]+}',
        views: {
          'workshop.panel.content@app.workshop.document': {
            templateUrl: URL_BASE_VIEW + '/workshop/document/panel/content-kit-edit.html',
            controller: 'WorkshopDocumentPanelKitEditController',
            controllerAs: 'ctrl',
          }
        },
        resolve: {
          widgetGroup: ['$stateParams', 'document', function ($stateParams, document) {
            var id = parseInt($stateParams.widgetGroupId);
            for (var i = 0; i < document._embedded.widget_groups.length; i++) {
              if (document._embedded.widget_groups[i].id === id) {
                return document._embedded.widget_groups[i];
              }
            }

            throw 'WORKSHOP.DOCUMENT.WIDGET_LOAD_ERROR';
          }],
        },
        onEnter: onPanelKitEditEnter,
        onExit: onPanelKitEditExit,
      })
      .state('app.workshop.document.base.theme', {
        url: '/theme',
        views: {
          'workshop.panel.content@app.workshop.document': {
            templateUrl: '/ent/angular/app/views/workshop/panel/content-theme.html'
          }
        }
      })

      .state('app.workshop.document.export', {
        url: '/export',
        views: {
          '@app': {
            template: '<div bns-workshop-document="document" hide-nav="true" print="true" class="workshop-document-export"></div>',
            controller: ['document', '$scope', function (document, $scope) {
              $scope.document = document;
            }],
          }
        }
      })
    ;

  })

;
