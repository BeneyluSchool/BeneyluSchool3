'use strict';

angular.module('bns.viewer.audio.visualization', [
  'bns.viewer.wavesurfer',
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.audio.visualization.bnsViewerAudioVisualization
   * @kind function
   *
   * @description
   * An audio visualization as a soundwave.
   *
   * @example
   * <any bns-viewer-audio-visualization></any>
   *
   * @requires bns.viewer.wavesurfer
   * @requires bns.core.url
   *
   * @returns {Object} The bnsViewerAudioVisualization directive
   */
  .directive('bnsViewerAudioVisualization', function (url) {
    return {
      templateUrl: url.view('viewer/directives/bns-viewer-audio-visualization.html'),
      scope: {
        value: '=',
      },
      controller: 'ViewerAudioVisualizationController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('ViewerAudioVisualizationController', function () {
    var ctrl = this;

    init();

    function init () {
    }
  })

;
