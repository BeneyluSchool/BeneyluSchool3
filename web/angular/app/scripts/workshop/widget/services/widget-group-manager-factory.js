'use strict';

angular.module('bns.workshop.widget')

  .factory('workshopWidgetGroupManager', function (_) {
    var generateId = function () {
      return Math.floor(Math.random() * 1000000);
    };

    /**
     * Creates a new widgetGroup (and its embedded widgets) from the given
     * configuration
     *
     * @param {Object} widgetConfiguration
     * @return {Object}
     */
    var createFromConfiguration = function (widgetConfiguration) {
      var widgetGroup = {
        id: generateId(),
        zone: widgetConfiguration.zone,
        position: widgetConfiguration.position,
        type: widgetConfiguration.widgetGroupType,
        _embedded: {
          widgets: []
        }
      };

      _.each(widgetConfiguration.widgetTypes, function (type, index) {
        var widget = {
          id: generateId(),
          position: index + 1,
          type: type,
          content: ''
        };
        widgetGroup._embedded.widgets.push(widget);
      });

      return widgetGroup;
    };

    return {
      createFromConfiguration: createFromConfiguration
    };
  });
