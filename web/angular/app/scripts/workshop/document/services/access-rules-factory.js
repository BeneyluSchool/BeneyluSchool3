'use strict';

angular.module('bns.workshop.document.accessRules', [
  'bns.core.accessRules',
  'bns.core.message',
  'bns.user.users',
  'bns.workshop.document.state',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.accessRules.workshopDocumentAccessRules
   * @kind function
   *
   * @description
   * Collection of various workshop access rules, using states.
   *
   * ** Methods **
   * - `enable()`: Enables all access rules
   * - `disable()`: Disables all access rules
   *
   * @requires $rootScope
   * @requires $state
   * @requires AccessRules
   * @requires message
   * @requires Users
   * @requires WorkshopDocumentState
   *
   * @returns {Object} The workshopDocumentAccessRules service
   */
  .factory('workshopDocumentAccessRules', function ($rootScope, $state, AccessRules, message, Users, WorkshopDocumentState) {
    return new AccessRules([
      disableThemePanel,
      checkLayoutForKit,
      disableQuestionnairePages,
      disableQuestionnaireLayout,
      checkLockAccess,
    ]);

    // prevent access to theme panel, always
    function disableThemePanel () {
      return $rootScope.$on('$stateChangeStart', function (event, toState) {
        if ('app.workshop.document.base.theme' === toState.name) {
          event.preventDefault();
        }
      });
    }

    // prevent access to kit panel, if page has no layout
    function checkLayoutForKit () {
      return $rootScope.$on('$stateChangeStart', function (event, toState) {
        if ('app.workshop.document.base.kit' === toState.name) {
          if (!(WorkshopDocumentState.page && WorkshopDocumentState.page.layout_code)) {
            console.info('Prevent kit: page has no layout');
            event.preventDefault();
          }
        }
      });
    }

    function disableQuestionnairePages () {
      return $rootScope.$on('$stateChangeStart', function (event, toState) {
        if ('app.workshop.document.base.pages' === toState.name) {
          if (WorkshopDocumentState.document && WorkshopDocumentState.document.is_questionnaire) {
            console.info('Prevent pages: questionnaire');
            event.preventDefault();
          }
        }
      });
    }

    function disableQuestionnaireLayout () {
      return $rootScope.$on('$stateChangeStart', function (event, toState) {
        if ('app.workshop.document.base.layout' === toState.name) {
          if (WorkshopDocumentState.document && WorkshopDocumentState.document.is_questionnaire) {
            console.info('Prevent layout: questionnaire');
            event.preventDefault();
          }
        }
      });
    }

    // prevent access to locked documents
    function checkLockAccess () {
      return $rootScope.$on('workshop.document.updated', function (event, document) {
        if (document.is_locked) {
          Users.me().then(function (me) {
            if (!me.rights.workshop_document_manage_lock) {
              WorkshopDocumentState.ignoreStateConstraints = true;
              message.info('WORKSHOP.DOCUMENT.LOCKED_CANNOT_EDIT');
              $state.go('app.workshop.index').then(function () {
                WorkshopDocumentState.ignoreStateConstraints = false;
              });
            }
          });
        }
      });
    }
  })

;
