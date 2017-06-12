/*jshint latedef: false*/

'use strict';

angular.module('bns.mediaLibrary.api', [])

  /**
   * @ngdoc service
   * @name  bns.mediaLibrary.api.mediaLibraryAPI
   * @kind  function
   *
   * @description
   * Handles actual API calls, successes and failures.
   *
   * @requires message
   * @requires MediaLibraryRestangular
   * @requires mediaLibraryManager
   *
   * @returns {Object} The MediaLibraryAPI service
   */
  .factory('mediaLibraryAPI', function (message, MediaLibraryRestangular, mediaLibraryManager) {

    return {
      toggleLocker: toggleLocker
    };

    /**
     * Toggles the locker status of the given folder, by issuing an API call and
     * updating local object upon success.
     *
     * @param {Object} folder A media library folder
     * @returns {Boolean} false if locker could not be toggled
     */
    function toggleLocker (folder) {
      if (!mediaLibraryManager.canToggleLocker(folder)) {
        console.warn('Cannot toggle locker on folder', folder);

        return false;
      }

      function success (newFolder) {
        folder.is_locker = newFolder.is_locker;
        if (folder.is_locker) {
          message.success('MEDIA_LIBRARY.LOCKERIZE_FOLDER_SUCCESS');
        } else {
          message.success('MEDIA_LIBRARY.UNLOCKERIZE_FOLDER_SUCCESS');
        }
      }

      function failure (result) {
        console.error('toggleLocker', result);
        if (folder.is_locker) {
          message.error('MEDIA_LIBRARY.UNLOCKERIZE_FOLDER_ERROR');
        } else {
          message.error('MEDIA_LIBRARY.LOCKERIZE_FOLDER_ERROR');
        }
      }

      MediaLibraryRestangular.one('media-folder', folder.marker)
        .one('toggle-locker', '')
        .patch({})
        .then(success, failure)
      ;
    }

  });
