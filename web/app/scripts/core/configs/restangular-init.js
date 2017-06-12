(function (angular) {
'use strict';

angular.module('bns.core.restangularInit', [
  'restangular',
  'bns.core.parameters',
])

  .constant('API_CONFIG', {
    bns: {
      baseUrl: '/api/1.0',
      requestSuffix: '.json'
    }
  })

  .config(RestangularConfig)

;

function RestangularConfig (RestangularProvider, parametersProvider, API_CONFIG) {
  var baseUrl = parametersProvider.get('app_base_path') + API_CONFIG.bns.baseUrl;

  // setup base url and suffix from config
  RestangularProvider.setBaseUrl(baseUrl);
  RestangularProvider.setRequestSuffix(API_CONFIG.bns.requestSuffix);

  RestangularProvider.setResponseExtractor(function (data, operation, what, url, response) {
    var newResponse = data || {};

    if (operation === 'post') {
      // keep a reference to certain response headers
      newResponse.headers = {
        location: response.headers('Location')
      };
    }

    if (operation === 'getList') {
      // lists may be wrapped in pagers
      if (data._embedded && data._embedded.items) {
        newResponse = data._embedded.items;

        // add the pager params to the response
        newResponse.pager = {
          total: data.total,
          page: data.page,
          limit: data.limit,
          pages: data.pages
        };
      }
    }

    // Return the modified response
    return newResponse;
  });
}

}) (angular);
