/**
 * Created by Slaiem on 07/03/2017.
 */
(function (angular, window) {
  'use strict';
  angular.module('bns.mediaLibrary.mediaElementConfig',[])
    .constant('BNS_MEDIAELEMENT_CONFIG',{
      enableAutosize: false,
      renderers: [ 'html5', 'native_hls', 'native_dash', 'flash_video', 'native_flv', 'flash_hls', 'flash_dash'],
      pluginPath: (window && window.cdn_url ? window.cdn_url : '') + '/ent/bower_components/mediaelement/build/',
      shimScriptAccess: 'always',
    });
/* global window */
})(angular, window);
