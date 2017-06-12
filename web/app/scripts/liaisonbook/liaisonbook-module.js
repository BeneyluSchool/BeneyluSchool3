(function (angular) {
  'use strict';
var LiaisonBook = angular.module('bns.liaisonbook', []);

LiaisonBook.controller('LiaisonbookSignature', function(Routing, $scope, $attrs, $http, toast) {

  $scope.sign = function(id) {
    $scope.isSigning = true;
    var link = Routing.generate('BNSAppLiaisonBookBundle_front_sign', { 'liaisonBookId': id });

    $http({
      method: 'GET',
      url: link,
    }).then(function successCallback(response) {
      if (response.data === 'true') {
        $scope.isSigned = true;
        toast.success($attrs.successMessage);
      }
    }, function errorCallback() {
      $scope.isSigning = false;
    });
    return false;
  };
});

LiaisonBook.directive('bnsLiaisonbookSign', function(){
  return {
    controller : 'LiaisonbookSignature',
    scope : true
  };
});

})(angular);
