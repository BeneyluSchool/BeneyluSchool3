'use strict';

angular.module('bns.workshop.document.editor', [
  'bns.core.modelEditor',
  'bns.user.users',
  'bns.workshop.restangular',
])

  .factory('workshopDocumentEditor', function documentEditor (ModelEditor, Users, WorkshopRestangular) {
    var service = new ModelEditor();

    service.doCommit = function (document) {
      return getFormData(document).then(function (formData) {
        return WorkshopRestangular.one('documents', document.id)
          .patch(formData)
        ;
      });
    };

    // only touch to these properties
    service._mask = {
      is_locked: true,
      _embedded: {
        media: {
          label: true,
          description: true,
        },
        contributor_user_ids: true,
        contributor_group_ids: true,
      }
    };

    return service;


    /* ---------------------------------------------------------------------- *\
     *    Internals
    \* ---------------------------------------------------------------------- */

    /**
     * Builds API-compliant data from the given document
     *
     * @param {Object} document
     * @returns {Object} a promise
     */
    function getFormData (document) {
      var data = {
        media: {
          label: document._embedded.media.label,
          // is_private: document._embedded.media.is_private,
          description: document._embedded.media.description,
        },
        user_ids: document._embedded.contributor_user_ids,
        group_ids: document._embedded.contributor_group_ids,
      };

      return Users.me().then(function (me) {
        // admin settings
        if (me.rights.workshop_document_manage_lock) {
          data.is_locked = document.is_locked;
        }
        if (me.rights.school_competition_manage) {
          data.attempts_number = document.attempts_number;
        }

        return data;
      });
    }
  })

;
