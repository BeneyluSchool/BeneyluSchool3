(function (angular) {
'use strict';

angular.module('bns.core.sceInit', [
  'bns.core.parameters',
])

  .config(SceConfig)

;

/**
 * Configures all things related to the sce policy
 *
 * @requires $sceDelegateProvider
 * @requires parametersProvider
 */
function SceConfig ($sceDelegateProvider, parametersProvider) {

  var whitelist = parametersProvider.get('resource_url_whitelist') || ['self'];
  $sceDelegateProvider.resourceUrlWhitelist(whitelist);

}

})(angular);
