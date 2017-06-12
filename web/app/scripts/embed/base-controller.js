(function (angular) {
'use strict';

angular.module('bns.embed.baseController', [])

  .controller('EmbedBase', EmbedBaseController)

;

function EmbedBaseController ($sce, item) {

  var ctrl = this;
  ctrl.item = item;
  ctrl.item.href = $sce.trustAsResourceUrl(ctrl.item.href);

}

})(angular);
