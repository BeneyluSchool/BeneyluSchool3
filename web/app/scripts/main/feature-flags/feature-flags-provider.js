(function (angular) {
'use strict';

angular.module('bns.main.featureFlags')

  .provider('featureFlags', FeatureFlagsProvider)

;

function FeatureFlagsProvider () {

  this.initialFlags = {};

  this.setInitialFlags = function (flags) {
    this.initialFlags = flags;
  };

  this.$get = ['$q', '$timeout', 'Restangular', function featureFlagsFactory ($q, $timeout, Restangular) {
    return new FeatureFlags($q, $timeout, Restangular, this.initialFlags);
  }];

}

function FeatureFlags ($q, $timeout, Restangular, initialFlags) {

  this.flags = initialFlags || {};
  this._pending = {};

  FeatureFlags.prototype.has = function (flag) {
    return angular.isDefined(this.flags[flag]);
  };

  FeatureFlags.prototype.get = function (flag, fetch) {
    if (this.has(flag) && !fetch) {
      return $q.when(this.flags[flag]);
    } else {
      return this._load(flag);
    }
  };

  FeatureFlags.prototype.set = function (flag, value) {
    this.flags[flag] = value;

    return this.get(flag);
  };

  FeatureFlags.prototype.getAll = function (fetch) {
    if (fetch) {
      return this._load();
    }

    return $q.when(this.flags);
  };

  FeatureFlags.prototype.setAll = function (flags) {
    this.flags = flags;

    return this.getAll();
  };

  FeatureFlags.prototype.check = function (flag) {
    return this.get(flag)
      .then(function (flagValue) {
        if (!flagValue) {
          throw 'feature unavailable';
        }
      })
    ;
  };

  FeatureFlags.prototype._load = function (flag) {
    var self = this;
    if (!self._pending[flag]) {
      self._pending[flag] = Restangular.all('feature-flags').get(flag)
        .then(success)
        .catch(error)
      ;
    }

    return self._pending[flag];

    function success (response) {
      delete self._pending[flag];

      if (flag) {
        return self.set(flag, response.value);
      }
      return self.setAll(response);
    }
    function error (response) {
      console.error('Error loading feature flags');
      throw response;
    }
  };
}

})(angular);
