'use strict';

angular.module('bns.workshop.manager', [
  'bns.mediaLibrary.manager',
])

  /**
   * @ngdoc service
   * @name bns.workshop.manager.workshopManager
   * @kind function
   *
   * @description
   * The workshop manager
   *
   * @requires mediaLibraryManager
   *
   * @returns {Object} The workshop manager service
   */
  .factory('workshopManager', function (mediaLibraryManager) {
    var srvc = {
      isDocument: isDocument,
      isAudio: isAudio,
    };

    return srvc;

    /**
     * Checks if the given workshop content is a document.
     *
     * @param {Object}  content
     * @returns {Boolean}
     */
    function isDocument (content) {
      return mediaLibraryManager.isMediaWorkshopDocument(content._embedded.media);
    }

    /**
     * Checks if the given workshop content is an audio document.
     *
     * @param {Object}  content
     * @returns {Boolean}
     */
    function isAudio (content) {
      return mediaLibraryManager.isMediaWorkshopAudio(content._embedded.media);
    }
  })

;
