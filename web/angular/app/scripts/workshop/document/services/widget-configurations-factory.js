'use strict';

angular.module('bns.workshop.document.widgetConfigurations', [
  'bns.core.message',
  'bns.workshop.restangular',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.widgetConfigurations.workshopDocumentWidgetConfigurations
   * @kind function
   *
   * @description
   * Manager of the workshop document widget configuration data.
   *
   * @returns {Object} The workshopDocumentWidgetConfigurations service
   */
  .factory('workshopDocumentWidgetConfigurations', function workshopDocumentWidgetConfigurations (message, WorkshopRestangular) {
    var service = {
      _configurations: null,
      load: load,
      getList: getList,
    };

    return service;

    /**
     * Gets the list of widget configurations, eventually already cached, as a
     * promise
     *
     * @returns {Object} A promise
     */
    function getList () {
      if (!service._configurations) {
        service._configurations = service.load();
      }

      return service._configurations;
    }

    /**
     * Loads the list of layouts from API
     *
     * @returns {Object} The list of layouts promise
     */
    function load () {
      return WorkshopRestangular.all('widget-configurations').getList()
        .catch(function error (response) {
          message.error('WORKSHOP.DOCUMENT.GET_WIDGET_CONFIGURATIONS_ERROR');
          console.error('[GET widget-configurations]', response);
          throw 'WORKSHOP.DOCUMENT.GET_WIDGET_CONFIGURATIONS_ERROR';
        })
      ;
    }
  })

;
