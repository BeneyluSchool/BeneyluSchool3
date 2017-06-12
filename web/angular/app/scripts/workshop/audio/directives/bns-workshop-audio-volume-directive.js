'use strict';

angular.module('bns.workshop.audio.volume', [
  'angularAwesomeSlider',
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.audio.volume.bnsWorkshopAudioVolume
   * @kind function
   *
   * @description
   * An audio volume control, using a vertical slider.
   *
   * @example
   * <any bns-workshop-audio-volume value="my.model.value"></any>
   *
   * @requires angularAwesomeSlider
   * @requires bns.core.url
   *
   * @returns {Object} The bnsWorkshopAudioVolume directive
   */
  .directive('bnsWorkshopAudioVolume', function (url) {
    return {
      templateUrl: url.view('workshop/audio/directives/volume.html'),
      scope: {
        value: '=',
      },
      controller: 'WorkshopAudioVolumeController',
      controllerAs: 'slider',
      bindToController: true,
    };
  })

  .controller('WorkshopAudioVolumeController', function ($scope, $element, $window) {
    var slider = this;
    var steps = 7;
    var $pointer;

    init();

    function init () {
      // actually use a negative value, to have a bottom-to-top slider
      slider.internalValue = -slider.value;

      slider.options = {
        from: -(steps - 1),
        to: 0,
        step: 1,
        limits: false,
        vertical: true,
        scale: Array.apply(null, new Array(steps)).map(function () { return '|'; }),
        modelLabels: modelLabels,
        onstatechange: onstatechange,
      };

      var gotVolumeSet = false;
      $scope.$on('volume.set', function (e, val) {
        slider.value = val;
        slider.internalValue = -val;

        // remember that volume change came from the getter, for current digest
        gotVolumeSet = true;
        $window.setTimeout(function () {
          gotVolumeSet = false;
        }, 0);
      });

      function modelLabels (value) {
        return Math.abs(value);  // display positive value
      }

      var oldValue = -1;
      function onstatechange (value) {
        value = Math.abs(value);
        if (value === oldValue) {
          return;
        }
        oldValue = value;
        updatePointerValue(value);
        slider.value = value;   // sync API value

        // do not notify if change comes from getter in same digest cycle
        if (!gotVolumeSet) {
          $scope.$emit('volume.change', value);
        }
      }
    }

    function updatePointerValue (value) {
      if (!$pointer) {
        $pointer = $element.find('.jslider-pointer');
      }
      $pointer.attr('data-value', value);
    }
  })

;
