'use strict';

angular.module('bns.workshop.document.widgetGroupSortableConfiguration', [
  'bns.core.message',
  'bns.workshop.document.manager',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.widgetGroupSortableConfiguration.widgetGroupSortableConfiguration
   * @kind function
   *
   * @description
   * Generates Sortable configuration objects, to handle drag and drop of
   * WidgetGroups inside of layout zones.
   * Said configuration includes event callbacks, that take care of updating
   * the underlying model by using the workshop data services.
   *
   * ** Methods **
   * - `get(page, zoneNumber)`: gets a configuration set up for the given page
   *                            and zone.
   *
   * @requires message
   * @requires workshopDocumentManager
   *
   * @returns {Object} The widgetGroupSortableConfiguration service
   */
  .factory('widgetGroupSortableConfiguration', function ($rootScope, message, workshopDocumentManager) {
    var service = {
      get: get,
    };

    return service;

    function get (page, zoneNumber, document) {
      var moved = null;
      var configuration = {
        group: 'widget-configuration',
        handle: '.action-sort',
        onStart: onStart,
        onSort: onSort,
        onAdd: onAdd,
        onEnd: onEnd,
        scroll: false,
      };

      return configuration;

      /*
        Sortable events lifecycle cheat-sheet

        Sort in same list:
         - 'update', 'sort', 'end'

        Sort in different lists
         - source: 'sort', 'end'
         - dest: 'sort', 'add'

        Sort abort (no movement / cancel)
         - 'end' with messed-up parameters, do not trust it :(

        During 'update' and 'sort', model collections are not yet updated. Our
        workaround:
         1 Use 'sort' to detect an actual sorting, store model.
         2 Use 'end' to update previously-stored model, if any.

       */

      function onStart () {
        angular.element('.workshop-page-view').addClass('dragging-widget');
      }

      /**
       * Sortable event handler. Something happened in the collection.
       *
       * @param {Object} evt
       */
      function onSort (evt) {
        if (!evt.model) {
          return console.warn('onSort ' + zoneNumber + ': no model');
        }

        moved = evt.model; // remember who triggered all this
      }

      /**
       * Sortable event handler. A new widgetGroup has been dropped in the
       * collection.
       *
       * @param {Object} evt
       */
      function onAdd (evt) {
        var widgetGroup = moved;

        // WidgetConfiguration drop from panel: clean it
        if (!widgetGroup) {
          widgetGroup = evt.model;
          for (var prop in widgetGroup) {
            if (['id', 'type', 'page_id', 'code', 'zone', 'position', '_links', '_embedded'].indexOf(prop) === -1) {
              delete widgetGroup[prop];
            }
          }
        }

        widgetGroup.zone = zoneNumber;
        widgetGroup.position = evt.newIndex + 1;

        applyChanges(widgetGroup, evt);
      }

      /**
       * Sortable event handler. Sort has happened in the list, no new element.
       *
       * @param {Object} evt
       */
      function onEnd (evt) {
        angular.element('.workshop-page-view').removeClass('dragging-widget');
        var widgetGroup = moved;

        // no sort happened because abort, or drop at same position (without
        // moving others)
        if (!widgetGroup) {
          return console.warn('onEnd zone ' + zoneNumber + ': no widget found');
        }

        // element has been moved around but put back into its previous position
        if (evt.oldIndex === evt.newIndex && widgetGroup.zone === zoneNumber) {
          return console.warn('onEnd zone ' + zoneNumber + ': no movement found');
        }

        if (evt.models.indexOf(evt.model) > -1) {
          applyChanges(widgetGroup, evt);

        } else {
          console.warn('onEnd zone ' + zoneNumber + ': movel has moved out');
        }

        moved = null;
      }

      /**
       * Applies changes to the given WidgetGroup
       *
       * @param {Object} widgetGroup
       * @param {Object} evt
       * @returns {Object} a promise
       */
      function applyChanges (widgetGroup, evt) {
        var sortable = evt.sortable;
        sortable.option('disabled', true);

        var zone = zoneNumber;
        var position = evt.newIndex + 1;

        if (widgetGroup.id) {
          var patchData = {
            zone: zone,
            position: position,
          };
          return workshopDocumentManager.editWidgetGroup(widgetGroup, patchData)
            .finally(end);
        } else {
          // directly update the WidgetGroupConfiguration
          widgetGroup.zone = zone;
          widgetGroup.position = position;

          return workshopDocumentManager.addWidgetGroup(widgetGroup, page)
            .then(postWidgetGroupSuccess)
            .catch(postWidgetGroupError)
            .finally(end)
          ;
        }

        function postWidgetGroupSuccess (id) {
          $rootScope.$emit('workshop.document.widgetGroup.created', id);
        }

        function postWidgetGroupError (response) {
          message.error('post widget group error');
          console.error(response);
        }
        function end () {
          sortable.option('disabled', false);
          var list = evt.models;
          workshopDocumentManager.reorganize(list);
        }
      }
    }

  })

;
