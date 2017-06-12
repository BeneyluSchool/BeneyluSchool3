'use strict';

angular.module('bns.workshop.audio.accessRules', [
  'bns.core.accessRules',
  'bns.core.message',
  'bns.workshop.audio.recorder.service',
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.accessRules.workshopAudioAccessRules
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
   * @requires Recorder
   *
   * @returns {Object} The workshopAudioAccessRules service
   */
  .factory('workshopAudioAccessRules', function ($rootScope, $state, AccessRules, message, Recorder) {
    return new AccessRules([
      checkRecordingCapability
    ]);

    /**
     * Checks that the navigator has audio recording capability, when
     * transitioning to a audio-related state.
     *
     * @returns {Function} The rule destroyer function
     */
    function checkRecordingCapability () {
      // check current state, if we're in the middle of a transition
      var destroy = $rootScope.$on('$stateChangeSuccess', function (event, toState) {
        destroy(); // listener only needed once: destroy it immediatly
        if (!checkForState(toState)) {
          $state.go('app.workshop.index');
          message.info('WORKSHOP.AUDIO.NO_RECORDING_CAPACITY');
        }
      });

      // check on subsequent state changes
      return $rootScope.$on('$stateChangeStart', function (event, toState) {
        if (!checkForState(toState)) {
          event.preventDefault();
          message.info('WORKSHOP.AUDIO.NO_RECORDING_CAPACITY');
        }
      });

      function checkForState (state) {
        if (state.name.indexOf('workshop.audio.create') !== -1) {
          return Recorder.isRecordingSupported();
        }

        return true;
      }
    }
  })

;
