'use strict';

angular.module('bns.mediaLibrary.share', [
  'pascalprecht.translate',
  'bns.core.message',
  'bns.mediaLibrary.restangular',
])

  .factory('mediaLibraryShareManager', function (_, $translate, message, MediaLibraryRestangular) {
    var service = {
      share: share,
    };

    return service;

    /**
     * Shares the given files to the given groups and users
     *
     * @param {Array} files API-compliant array of media info (id and type)
     * @param {Array} groups ids
     * @param {Array} users ids
     * @returns {Object} A promise that is given the number of errors during
     *                   share
     */
    function share (files, groups, users) {
      var data = {
        datas: files,
        groups: _.map(groups, 'id'),
        users: _.map(users, 'id'),
      };

      return MediaLibraryRestangular.one('selection-share').patch(data)
        .then(success)
        .catch(failure)
      ;
      function success (response) {
        var nbErrors = parseInt(response.content, 10);
        if (nbErrors) {
          $translate('MEDIA_LIBRARY.DISTRIBUTE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
            message.info(translation);
          });
        } else {
          message.success('MEDIA_LIBRARY.DISTRIBUTE_SELECTION_SUCCESS');
        }

        return nbErrors;
      }
      function failure (response) {
        console.error('[PATCH selection-share]', response);
        message.error('MEDIA_LIBRARY.DISTRIBUTE_SELECTION_ERROR');

        throw response;
      }
    }
  })

;
