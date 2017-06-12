'use strict';

angular.module('bns.workshop.document.manager', [
  'bns.core.message',
  'bns.workshop.restangular',
])

  .factory('workshopDocumentManager', function ($rootScope, $q, message, WorkshopRestangular) {
    var service = {
      document: null,
      addPage: addPage,
      movePage: movePage,
      removePage: removePage,
      editPage: editPage,
      addWidgetGroup: addWidgetGroup,
      editWidgetGroup: editWidgetGroup,
      removeWidgetGroup: removeWidgetGroup,
      draftWidgetGroup: draftWidgetGroup,
      changeTheme: changeTheme,
      reorganize: reorganize
    };

    return service;


    /* ---------------------------------------------------------------------- *\
     *    API
    \* ---------------------------------------------------------------------- */

    /**
     * Adds a new page at the end of current document
     */
    function addPage (position, layout) {
      if (!service.document) {
        message.error('WORKSHOP.DOCUMENT.NO_SET');
        console.error('"addPage": no current document');

        return false;
      }

      return service.document.all('pages').post({position: position, layout: layout})
        .then(addPageSuccess)
        .catch(addPageError)
      ;

      function addPageSuccess (response) {
        message.success('WORKSHOP.DOCUMENT.ADD_PAGE_SUCCESS');

        // extract the page ID
        return parseInt(response.headers.location.match(/\/pages\/([0-9]+)/)[1], 10);
      }

      function addPageError (result) {
        message.error('WORKSHOP.DOCUMENT.ADD_PAGE_ERROR');
        console.error('[POST pages]', result);
      }
    }

    /**
     * Moves the given page to the given position.
     * Assumes that the array of pages has already been reordered (by
     * ng-sortable).
     *
     * @param {Object} page A page of the current document
     * @param {Integer} position The new position
     * @returns {Object} A promise
     */
    function movePage (page, position) {
      var promise = WorkshopRestangular.one('pages', page.id).patch({ position: position });
      promise.then(movePageSuccess);
      promise.catch(movePageError);

      return promise;

      function movePageSuccess () {
        message.success('WORKSHOP.DOCUMENT.MOVE_PAGE_SUCCESS');

        // update position of all pages
        var position = 0;
        angular.forEach(service.document._embedded.pages, function (page) {
          position++;
          page.position = position;
        });
      }

      function movePageError (response) {
        message.error('WORKSHOP.DOCUMENT.MOVE_PAGE_ERROR');
        console.error('[PATCH page', response);
      }
    }

    /**
     * Removes the given page from the current document
     *
     * @param {Object} page
     * @returns {Object} A promise that is given the deleted page
     */
    function removePage (page) {
      return WorkshopRestangular.one('pages', page.id).remove()
        .then(function success () {
          message.success('WORKSHOP.DOCUMENT.DELETE_PAGE_SUCCESS');

          return page;
        })
        .catch(function error (response) {
          message.error('WORKSHOP.DOCUMENT.DELETE_PAGE_ERROR');
          console.error('[DELETE page]', response);

          throw 'WORKSHOP.DOCUMENT.DELETE_PAGE_ERROR';
        })
      ;
    }

    /**
     * Edits the given page
     *
     * @param {Object} page A page of the current document
     * @param {Object} data Page properties to be altered
     * @returns {Object} A promise that is given the edited page
     */
    function editPage (page, data) {
      return WorkshopRestangular.one('pages', page.id).patch(data)
        .catch(editPageError)
      ;

      function editPageError (response) {
        message.error('WORKSHOP.DOCUMENT.EDIT_PAGE_ERROR');
        console.error('[PATCH page]', response);

        throw 'WORKSHOP.DOCUMENT.EDIT_PAGE_ERROR';
      }
    }

    /**
     * Adds a WidgetGroup to the given Page.
     *
     * @param {Object} WidgetGroup API data
     * @param {Object} page the target Page
     *
     * @returns {Object} A promise receiving URL of the created WidgetGroup
     */
    function addWidgetGroup (widgetGroup, page) {
      return WorkshopRestangular.one('pages', page.id).all('widget-groups').post(widgetGroup)
        .then(postWidgetGroupSuccess)
        .catch(postWidgetGroupError)
      ;
      function postWidgetGroupSuccess (response) {
        message.success('WORKSHOP.DOCUMENT.CREATE_WIDGET_SUCCESS');

          if (widgetGroup.code != 'page-break') {
            return parseInt(response.headers.location.match(/\/widget-groups\/([0-9]+)/)[1], 10);
          }
      }
      function postWidgetGroupError (response) {
        message.error('WORKSHOP.DOCUMENT.CREATE_WIDGET_ERROR');
        console.error('[POST widget-group]', response);

        throw 'WORKSHOP.DOCUMENT.CREATE_WIDGET_ERROR';
      }
    }

    function reorganize (list) {
      var pageBreakers = [];
      var widgetGroups = list;

      angular.forEach(service.document._embedded.pages, function (page) {
        angular.forEach(page._embedded.widgetGroups, function (widgetGroup) {
          angular.forEach(widgetGroup._embedded.widgets, function (widget) {
            if (widget.type == 'page-break') {
              pageBreakers.push({
                object: widgetGroup,
                page: page
              });
            }
          });
        });
      });

      angular.forEach(pageBreakers, function (pageBreaker) {
        var index = -1;
        angular.forEach(widgetGroups, function (wg) {
            if (wg.id == pageBreaker.object.id) {
              index = widgetGroups.indexOf(wg);
            }
        });
        moveWidgetGroups(widgetGroups, index, pageBreaker.page);
      });
    }

    function moveWidgetGroups(widgetGroups, index, currentPage) {
      var after = [];
      var before = [];
      var nextPosition = currentPage.position + 1;
      var nextPage = false;

      angular.forEach(service.document._embedded.pages, function (page) {
        if (page.position == nextPosition) {
          nextPage = page;
        }
      });
      angular.forEach(widgetGroups, function (widgetGroup) {
        if (widgetGroups.indexOf(widgetGroup) >  index) {
          after.push(widgetGroup);
        }

        if (widgetGroups.indexOf(widgetGroup) <  index && currentPage.position == 1) {
          before.push(widgetGroup);
        }
      });

      angular.forEach(before, function (widgetGroup) {
        var patchData = {
          page_id: currentPage.id
        };
        WorkshopRestangular.one('widget-groups', widgetGroup.id).all('move').patch(patchData);
      });

      if (nextPage) {
        angular.forEach(after, function (widgetGroup) {
          var patchData = {
            page_id: nextPage.id
          };
          WorkshopRestangular.one('widget-groups', widgetGroup.id).all('move').patch(patchData);
        });
      } else {
        addPage(nextPosition, 'full').then(function(value) {
          angular.forEach(after, function (widgetGroup) {
            var patchData = {
              page_id: value
            };
            WorkshopRestangular.one('widget-groups', widgetGroup.id).all('move').patch(patchData);
          });
        });
      }

    }

    /**
     * Edits the given WidgetGroup
     *
     * @param {Object} widgetGroup A WidgetGroup of the current document
     * @param {Object} data WidgetGroup properties to be altered
     * @returns {Object} A promise that is given the edited WidgetGroup
     */
    function editWidgetGroup (widgetGroup, data) {
      return WorkshopRestangular.one('widget-groups', widgetGroup.id).patch(data)
        .then(editWidgetGroupSuccess)
        .catch(editWidgetGroupError)
      ;
      function editWidgetGroupSuccess (response) {
        message.success('WORKSHOP.DOCUMENT.EDIT_WIDGET_SUCCESS');

        return response;
      }
      function editWidgetGroupError (response) {
        message.error('WORKSHOP.DOCUMENT.EDIT_WIDGET_ERROR');
        console.error('[PATCH widget-group]', response);

        throw 'WORKSHOP.DOCUMENT.EDIT_WIDGET_ERROR';
      }
    }

    /**
     * Removes the given widgetGroup from the current document
     *
     * @param {Object} widgetGroup
     * @returns {Object} A promise that is given the deleted widgetGroup
     */
    function removeWidgetGroup (widgetGroup) {
      var move = false;
      angular.forEach(widgetGroup._embedded.widgets, function (widget) {
        if (widget.type == 'page-break') {
          move = true;
        }
      });

      if (move) {
        WorkshopRestangular.one('pages', widgetGroup.page_id).get()
          .then(function(current) {
            WorkshopRestangular.one('pages', service.document.id).all('first').get(current.position + 1)
              .then(function (nextPage) {
                var patchs = [];
                angular.forEach(nextPage._embedded.widgetGroups, function (wg) {
                  var patchData = {
                      page_id: current.id
                    };
                    patchs.push(WorkshopRestangular.one('widget-groups', wg.id).all('move').patch(patchData));
                });
                $q(function (resolve) {
                  resolve(removePage(nextPage));
                });
              });
          });
      }
      return WorkshopRestangular.one('widget-groups', widgetGroup.id).remove()
        .then(function success () {
          message.success('WORKSHOP.DOCUMENT.DELETE_WIDGET_SUCCESS');

          return widgetGroup;
        })
        .catch(function error (response) {
          message.error('WORKSHOP.DOCUMENT.DELETE_WIDGET_ERROR');
          console.error('[DELETE widget-group]', response);

          throw 'WORKSHOP.DOCUMENT.DELETE_WIDGET_ERROR';
        })
      ;
    }

    function draftWidgetGroup (widgetGroup, data) {
      return WorkshopRestangular.one('widget-groups', widgetGroup.id).all('drafts')
        .patch(data)
        .then(draftWidgetGroupSuccess)
        .catch(draftWidgetGroupError)
      ;
      function draftWidgetGroupSuccess (response) {
        return response;
      }
      function draftWidgetGroupError (response) {
        message.error('WORKSHOP.DOCUMENT.DRAFT_WIDGET_ERROR');
        console.error('[POST widget-group draft]', response);

        throw 'WORKSHOP.DOCUMENT.DRAFT_WIDGET_ERROR';
      }
    }

    /**
     * Changes the theme of the current document for the given one
     *
     * @param {Object} theme
     */
    function changeTheme (theme) {
      if (!service.document) {
        message.error('WORKSHOP.DOCUMENT.NO_SET');
        console.error('"changeTheme": no current document');

        return false;
      }

      return service.document.patch({ theme_code: theme.code })
        .then(success)
        .catch(error)
      ;

      function success () {
        // TODO: update local data
        message.success('WORKSHOP.DOCUMENT.CHANGE_THEME_SUCCESS');
        $rootScope.$emit('theme.changed');
      }

      function error (response) {
        message.error('WORKSHOP.DOCUMENT.CHANGE_THEME_ERROR');
        console.error('$scope.changeTheme', response);
      }
    }

  })

;
