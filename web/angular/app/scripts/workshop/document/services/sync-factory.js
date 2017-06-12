'use strict';

angular.module('bns.workshop.document.sync', [
  'bns.core.objectHelpers',
  'bns.realtime.socket',
  'bns.workshop.document.state',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.sync.workshopDocumentSync
   * @kind function
   *
   * @description
   * WorkshopDocument-specific data sync over websocket.
   *
   * ** Methods **
   * - `start()`: starts data sync, ie registers websocket listeners for all
   *              document-related data.
   * - `stop()`: stops data sync, ie removes all websocket listeners
   *
   * TODO: move `syncUpdates` and `unsyncUpdates` in a generic service.
   *
   * @requires $rootScope
   * @requires _
   * @requires objectHelpers
   * @requires socket
   * @requires WorkshopDocumentState
   */
  .factory('workshopDocumentSync', function ($rootScope, _, objectHelpers, socket, WorkshopDocumentState) {
    var service = {
      start: start,
      stop: stop,
      syncUpdates: syncUpdates,
      unsyncUpdates: unsyncUpdates,
      syncObjectUpdates: syncObjectUpdates,
      unsyncObjectUpdates: unsyncObjectUpdates,
      _channelBase: null,
    };

    return service;

    function start () {
      if (!WorkshopDocumentState.document) {
        console.warn('Cannot start sync without document');
        return;
      }
      service._channelBase = 'WorkshopDocument(' + WorkshopDocumentState.document.id + ')';

      service.syncObjectUpdates(service._channelBase, WorkshopDocumentState.document, documentCallback);
      service.syncUpdates(service._channelBase + ':pages', WorkshopDocumentState.document._embedded.pages, pagesCallback);
      service.syncUpdates(service._channelBase + ':widget_groups', WorkshopDocumentState.document._embedded.widget_groups, widgetGroupsCallback, null, null, true);
      service.syncUpdates(service._channelBase + ':locks', WorkshopDocumentState.document._embedded.locks, null, lockFinder, lockRemover);

      function documentCallback (event, item) {
        $rootScope.$emit('workshop.content.'+event, item);
        $rootScope.$emit('workshop.document.'+event, item);
      }

      function pagesCallback (event, item, array) {
        if ('updated' === event) {
          // reorder pages based on their position
          array.sort(function (page1, page2) {
            if (page1.position < page2.position) {
              return -1;
            } else if (page1.position > page2.position) {
              return 1;
            } else {
              // position conflict: updated page has same position as an old
              // page => give it priority
              if (page1.id === item.id) {
                return -1;
              } else if (page2.id === item.id) {
                return 1;
              }
            }

            return 0;
          });
        }

        // ensure distinct page positions
        var position = 0;
        angular.forEach(array, function (page) {
          position++;
          page.position = position;
        });

        // angular-friendly event
        $rootScope.$emit('workshop.document.page.'+event, item, array);
      }

      function widgetGroupsCallback (event, item, array, oldItem) {
        // Reorder other widgets in the old zone. If no old item, it's a
        // newly-inserted, so no need to reorder previous zone.
        if (oldItem) {
          var oldZoneWidgetGroups = _.filter(array, { page_id: item.page_id, zone: oldItem.zone });
          angular.forEach(oldZoneWidgetGroups, function (widgetGroup) {
            if (widgetGroup.id !== item.id) {
              if (widgetGroup.position > oldItem.position) {
                widgetGroup.position--;
              }
            }
          });
        }

        var newZoneWidgetGroups = _.filter(array, { page_id: item.page_id, zone: item.zone });
        angular.forEach(newZoneWidgetGroups, function (widgetGroup) {
          if (widgetGroup.id !== item.id) {
            if (widgetGroup.position >= item.position) {
              widgetGroup.position++;
            }
          }
        });

        // reorder all widgets of the page
        // var pageWidgetGroups = _.sortByAll(_.filter(array, { page_id: item.page_id }), ['zone', 'position']);
        // var positions = {};
        // angular.forEach(pageWidgetGroups, function (widgetGroup) {
        //   if (!positions[widgetGroup.zone]) {
        //     positions[widgetGroup.zone] = 0;
        //   }
        //   positions[widgetGroup.zone]++;
        //   widgetGroup.position = positions[widgetGroup.zone];
        // });

        // angular-friendly event
        $rootScope.$emit('workshop.document.widgetGroup.'+event, item, array);
        $rootScope.$emit('workshop.document.widgetGroup.save', item, array);
      }

      function lockFinder (array, item) {
        return _.find(array, {
          user_id: item.user_id,
          widget_group_id: item.widget_group_id,
        });
      }

      function lockRemover (array, item) {
        return _.remove(array, {
          user_id: item.user_id,
          widget_group_id: item.widget_group_id,
        });
      }
    }

    function stop () {
      service.unsyncObjectUpdates(service._channelBase);
      service.unsyncUpdates(service._channelBase + ':pages');
      service.unsyncUpdates(service._channelBase + ':widget_groups');
      service.unsyncUpdates(service._channelBase + ':locks');
      service._channelBase = null;
    }

    /**
     * Register listeners to sync an array with updates on a model
     *
     * Takes the array we want to sync, the model name that socket updates are sent from,
     * and an optional callback function after new items are updated.
     *
     * @param {String} modelName
     * @param {Array} array
     * @param {Function} cb
     * @param {Function} finder
     * @param {Function} remover
     * @param {Boolean} recursive Whether to perform a recursive merge of
     *                            updated objects
     */
    function syncUpdates (modelName, array, cb, finder, remover, recursive) {
      cb = cb || angular.noop;

      // default finder
      finder = finder || function (array, item) {
        return _.find(array, {id: item.id});
      };

      // default remover
      remover = remover || function (array, item) {
        return _.remove(array, {id: item.id});
      };

      /**
       * Syncs item creation/updates on 'model:save'
       */
      socket.on(modelName + ':save', function (item) {
        var existingItem = finder(array, item);
        var oldItem = angular.copy(existingItem);
        var event = 'created';

        // replace existingItem if it exists
        // otherwise just add item to the collection
        if (existingItem) {
          // do not replace, but update properties
          // var index = array.indexOf(existingItem);
          // array.splice(index, 1, item);
          if (recursive) {
            objectHelpers.softMerge(existingItem, item, 'id', true, true, true);
          } else {
            objectHelpers.softMerge(existingItem, item, 'id', true);
          }
          event = 'updated';
        } else {
          array.push(item);
        }

        cb(event, item, array, oldItem);
      });

      /**
       * Syncs removed items on 'model:remove'
       */
      socket.on(modelName + ':remove', function (item) {
        var event = 'deleted';
        remover(array, item);
        cb(event, item, array);
      });
    }

    /**
     * Removes listeners for a models updates on the socket
     *
     * @param modelName
     */
    function unsyncUpdates (modelName) {
      socket.removeAllListeners(modelName + ':save');
      socket.removeAllListeners(modelName + ':remove');
    }

    /**
     * Register listeners to sync an object with updates on a model
     *
     * Takes the object we want to sync, the model name that socket updates are sent from,
     * and an optional callback function after new items are updated.
     *
     * @param {String} modelName
     * @param {Object} obj
     * @param {Function} cb
     */
    function syncObjectUpdates (modelName, obj, cb) {
      cb = cb || angular.noop;

      /**
       * Syncs item creation/updates on 'model:save'
       */
      socket.on(modelName + ':save', function (item) {
        objectHelpers.softMerge(obj, item, 'id', true, true, true);
        var event = 'updated';
        cb(event, item);
      });
    }

    /**
     * Removes listeners for a object model updates on the socket
     *
     * @param modelName
     */
    function unsyncObjectUpdates (modelName) {
      socket.removeAllListeners(modelName + ':save');
    }
  })

;
