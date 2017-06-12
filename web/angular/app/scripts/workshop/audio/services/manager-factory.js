'use strict';

angular.module('bns.workshop.audio.manager', [
  'pascalprecht.translate',
  'bns.core.message',
  'bns.workshop.restangular',
])

  .factory('workshopAudioManager', function ($window, $translate, message, WorkshopRestangular) {
    var srvc = {
      create: create,
      save: save,
    };

    return srvc;

    function create () {
      return {
        _embedded: {
          media: {
            label: $translate.instant('WORKSHOP.AUDIO.NEW_DOCUMENT'),
            description: '',
          },
          contributor_user_ids: [],
          contributor_group_ids: [],
        },
      };
    }

    function save (audio) {
      var fd = new $window.FormData();
      fd.append('data', audio.data, audio._embedded.media.label + '.ogg');
      fd.append('media[label]', audio._embedded.media.label);
      fd.append('media[description]', audio._embedded.media.description);
      angular.forEach(audio._embedded.contributor_user_ids, function (id) {
        fd.append('user_ids[]', id);
      });
      angular.forEach(audio._embedded.contributor_group_ids, function (id) {
        fd.append('group_ids[]', id);
      });

      return WorkshopRestangular.one('audios')
        .withHttpConfig({transformRequest: angular.identity})
        .customPOST(fd, '', undefined, { 'Content-Type': undefined })
        .then(function success (response) {
          message.success('WORKSHOP.AUDIO.SAVE_SUCCESS');
          return response;
        })
        .catch(function error (response) {
          console.error(response);
          if (response.data && response.data[0] && response.data[0].message === 'ERROR_NOT_ENOUGH_SPACE_USER') {
            message.error('WORKSHOP.AUDIO.NOT_ENOUGH_SPACE_USER');
            throw 'WORKSHOP.AUDIO.NOT_ENOUGH_SPACE_USER';
          } else {
            message.error('WORKSHOP.AUDIO.SAVE_ERROR');
            throw 'WORKSHOP.AUDIO.SAVE_ERROR';
          }
        })
      ;
    }
  })

;
