'use strict';

angular.module('bns.workshop.document.state', [])

  /**
   * Holds application-wide states of the document
   *
   * @return {Object}
   */
  .factory('WorkshopDocumentState', function () {
    var service = {
      /**
       * The current document
       *
       * @type {Object}
       */
      document: null,

      /**
       * The current page
       *
       * @type {Object}
       */
      page: null,

      /**
       * WidgetGroup being edited
       *
       * @type {Object}
       */
      editedWidgetGroup: null,

      /**
       * Whether to ignore local state constraints
       *
       * @type {Boolean}
       */
      ignoreStateConstraints: false,

      /**
       * Whether a widget is currently edited and has local changes
       *
       * @type {Boolean}
       */
      dirty: false,
    };

    return service;
  });
