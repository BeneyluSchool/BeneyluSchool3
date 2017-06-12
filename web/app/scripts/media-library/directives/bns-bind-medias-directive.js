/**
 * Created by Slaiem on 07/03/2017.
 */
(function (angular) {
  'use strict';

  angular.module('bns.mediaLibrary.bindMedias', [
    'bns.mediaLibrary.mediaElementConfig'

  ])

    .directive('bnsBindMedias', BNSBindMediasDirective);

  function BNSBindMediasDirective(BNS_MEDIAELEMENT_CONFIG) {
    return {
      link: function postLink(scope, element, attrs) {
        scope.$watch(attrs.ngBindHtml, function () {
          element.find('audio,video').mediaelementplayer(BNS_MEDIAELEMENT_CONFIG);
        });

      },
      //priorité plus haute que BindHtml pour que son post s'execute après
      priority: 1
    };
  }
})(angular);
