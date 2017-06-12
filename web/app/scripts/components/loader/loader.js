(function (angular) {
'use strict';

angular.module('bns.components.loader', [])

  .factory('BnsLoader', BnsLoaderFactory)
  .service('bnsLoader', BnsLoaderService)
  .directive('bnsLoader', BnsLoaderDirective)

;

function BnsLoaderFactory ($timeout) {

  var BNS_LOADER_SUCCESS_DURATION = 5000;

  function BnsLoader () {
    this.pendingCount = 0;
    this.successCount = 0;
  }

  BnsLoader.prototype.success = function () {
    var loader = this;
    loader.successCount++;

    $timeout(function () {
      loader.successCount = Math.max(0, loader.successCount - 1);
    }, BNS_LOADER_SUCCESS_DURATION);
  };

  BnsLoader.prototype.pending = function () {
    this.pendingCount++;
  };

  BnsLoader.prototype.finished = function (all) {
    this.pendingCount = all ? 0 : Math.max(0, this.pendingCount - 1);
  };

  BnsLoader.prototype.observePromise = function (promise, watchSuccess) {
    var loader = this;
    watchSuccess = undefined !== watchSuccess ? !!watchSuccess : true;

    loader.pending();
    if (watchSuccess) {
      promise.then(loaderSuccess);
    }
    promise.finally(loaderEnd);

    return promise;

    function loaderSuccess (response) {
      loader.success();

      return response;
    }

    function loaderEnd () {
      loader.finished();
    }
  };

  return BnsLoader;

}

function BnsLoaderService (BnsLoader) {

  return new BnsLoader();

}

function BnsLoaderDirective (bnsLoader) {

  return {
    restrict: 'E',
    scope: {},
    template:
      '<md-progress-circular md-mode="indeterminate" md-diameter="24" ng-show="loader.pendingCount"></md-progress-circular>' +
      '<md-icon ng-show="loader.successCount && !loader.pendingCount" class="text-accent">check</md-icon>',
    link: postLink,
  };

  function postLink (scope) {
    scope.loader = bnsLoader;
  }

}

})(angular);
