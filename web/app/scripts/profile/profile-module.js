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
    require: 'ngModel'
  };

}

function BNSProfileSwitchController ($scope, $attrs, $http, toast, $timeout) {

  var link = $attrs.url;
  var hasInit = false;
  var method = angular.isDefined($attrs.method) ? $attrs.method : 'POST';

  $scope.$watch($attrs.ngModel, function (newValue) {
    if (undefined === newValue) {
      return;
    }
    if (!hasInit) {
      return;
    }
    onChange();
  });

  $http({
    method: method,
    url: link,
  }).then(function successCallback (response) {
    $scope[$attrs.ngModel] = response.data.moderate;
    $timeout(function () {
      hasInit = true;
    });
  }, function errorCallback () {
    $scope.isSigning = false;
  });


  function onChange () {
    var link = $attrs.url;

    $http({
      method: method,
      url: link,
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      data : {state : !$scope[$attrs.ngModel]}
    }).then(function successCallback() {
      if ($scope[$attrs.ngModel]) {
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
