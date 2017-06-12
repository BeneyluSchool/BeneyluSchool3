'use strict';

angular.module('bns.workshop.document.layouts', [
  'bns.core.message',
  'bns.workshop.restangular',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.layouts.workshopDocumentLayouts
   * @kind function
   *
   * @description
   * Manager of the workshop document layout data.
   *
   * @returns {Object} The workshopDocumentLayouts service
   */
  .factory('workshopDocumentLayouts', function workshopDocumentLayouts (message, WorkshopRestangular) {
    var service = {
      _layouts: null,
      _types: null,
      load: load,
      getList: getList,
      getTypesList: getTypesList,
    };

    return service;

    /**
     * Gets the list of layouts, eventually already cached, as a promise
     *
     * @returns {Object} A promise
     */
    function getList () {
      if (!service._layouts) {
        service._layouts = service.load();
      }

      return service._layouts;
    }

    /**
     * Gets the list of layout types, eventually already cached, as a promise
     *
     * @returns {Object} A promise
     */
    function getTypesList () {
      if (!service._types) {
        service._types = service.getList().then(function (layouts) {
          var layoutTypes = {};
          angular.forEach(layouts, function (layout) {
            var typeCode = layout.type.code;
            if (!layoutTypes[typeCode]) {
              layoutTypes[typeCode] = angular.copy(layout.type);
            }
          });

          return layoutTypes;
        });
      }

      return service._types;
    }

    /**
     * Loads the list of layouts from API
     *
     * @returns {Object} The list of layouts promise
     */
    function load () {
      return WorkshopRestangular.all('layouts').getList()
        .catch(function error (response) {
          message.error('WORKSHOP.DOCUMENT.GET_LAYOUTS_ERROR');
          console.error('[GET layouts]', response);
          throw 'WORKSHOP.DOCUMENT.GET_LAYOUTS_ERROR';
        })
      ;
    }
  })

;
