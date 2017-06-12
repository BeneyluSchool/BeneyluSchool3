'use strict';

angular.module('bns.viewer.workshop.document.widgetStyle', [])

  /**
   * @ngdoc service
   * @name bns.viewer.workshop.document.widgetStyle.workshopWidgetStyle
   * @kind function
   *
   * @description
   * Responsible for handling widget-specific styles in a workshop document.
   *
   * ** Attributes **
   * - `defaults` : A map of defaults styles, for each widget type
   *
   * ** Methods **
   * - `getDefaultsForType(type)` : Gets the default styles for the given widget
   *                                type.
   *
   * @returns {Object} The workshopWidgetStyle service
   */
  .factory('workshopWidgetStyle', function () {
    var defaults = {
      'title': {
        'line-height': '140%'
      },
      'text': {
        'line-height': '170%'
      },
      'textbox': {
        'background-color': '#fff',
        'border-width': '0.2em',
        'border-style': 'solid',
        'border-color': 'transparent',
        'line-height': '170%',
        'padding': '0.6em'
      }
    };

    return {
      /**
       * Default styles, for each widget type
       * @type {Object}
       */
      defaults: defaults,

      /**
       * Gets default styles for the given widget type
       *
       * @param  {String} type
       * @return {Object}
       */
      getDefaultsForType: function (type) {
        return angular.copy(defaults[type]) || {};
      }
    };
  });
