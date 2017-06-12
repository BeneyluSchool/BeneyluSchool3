'use strict';

angular.module('bns.core.message.factory', [])

  .factory('message', function (notifier, $translate) {
    var delay = 5000,
      position = 'top center';

    var message = {};

    message.success = function (msg, interpolateParams, interpolationId) {
      return notifier.notify({
        template: $translate.instant(msg, interpolateParams, interpolationId),
        hasDelay: true,
        delay: delay,
        type: 'success',
        position: position
      });
    };

    message.error = function (msg, interpolateParams, interpolationId) {
      return notifier.notify({
        template: $translate.instant(msg, interpolateParams, interpolationId),
        hasDelay: true,
        delay: delay * 4,
        type: 'error',
        position: position
      });
    };

    message.info = function (msg, interpolateParams, interpolationId) {
      return notifier.notify({
        template: $translate.instant(msg, interpolateParams, interpolationId),
        hasDelay: true,
        delay: delay * 2,
        type: 'info',
        position: position
      });
    };

    return message;
  })

;
