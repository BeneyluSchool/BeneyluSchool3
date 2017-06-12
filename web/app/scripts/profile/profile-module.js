(function (angular) {
'use strict';

angular.module('bns.profile', [])

  .directive('bnsProfileSwitch', BNSProfileSwitchDirective)
  .controller('BNSProfileSwitch', BNSProfileSwitchController)
  .directive('bnsProfileComment', BNSProfileCommentDirective)
  .controller('BNSProfileComment', BNSProfileCommentController)

;

function BNSProfileSwitchDirective () {

  return {
    controller : 'BNSProfileSwitch',
    scope : true
  };

}

function BNSProfileSwitchController ($scope, $attrs, $http, toast) {

  var link = $attrs.url;

  $http({
    method: 'POST',
    url: link,
  }).then(function successCallback (response) {
    $scope.active = response.data.moderate;
  }, function errorCallback () {
    $scope.isSigning = false;
  });

  $scope.onChange = onChange;

  function onChange () {
    var link = $attrs.url;
    $http({
      method: 'POST',
      url: link,
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      data : {state : !$scope.active}
    }).then(function successCallback() {
      if ($scope.active) {
        toast.success($attrs.success);
      } else {
        toast.success($attrs.fail);
      }
    });
  }

}

function BNSProfileCommentDirective (){

  return {
    controller : 'BNSProfileComment',
    scope : true
  };

}

function BNSProfileCommentController ($scope, toast) {

  $scope.addComment = function() {
    toast.success('Commentaire publié');
  };

}

})(angular);
