'use strict';

angular.module('bns.workshop.audio.visualization', [
  'bns.mediaLibrary.wavesurfer',
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.audio.visualization.bnsWorkshopAudioVisualization
   * @kind function
   *
   * @description
   * An audio visualization as a soundwave.
   *
   * @example
   * <any bns-workshop-audio-visualization></any>
   *
   * @requires bns.mediaLibrary.wavesurfer
   * @requires bns.core.url
   *
   * @returns {Object} The bnsWorkshopAudioVisualization directive
   */
  .directive('bnsWorkshopAudioVisualization', function (url) {
    return {
      templateUrl: url.view('workshop/audio/directives/visualization.html'),
      scope: {
        value: '=',
      },
      controller: 'WorkshopAudioVisualizationController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopAudioVisualizationController', function () {
    var ctrl = this;

    init();

    function init () {
    }
  })

;
