/**
 * Created by Slaiem on 07/03/2017.
 */
(function (angular) {
  'use strict';
  angular.module('bns.mediaLibrary.mediaElementConfig',[])
    .constant('BNS_MEDIAELEMENT_CONFIG',{
      enableAutosize: false,
      plugins: ['flash','silverlight'],
      pluginPath: '/ent/medias/js/resource/',
      flashName: 'flashmediaelement.swf',
      silverlightName: 'silverlightmediaelement.xap'
    });

})(angular);
