(function (angular) {
'use strict';

angular.module('bns.viewer.aspectRatio', [])

  .directive('bnsViewerAspectRatio', BNSViewerAspectRatioDirective)

;

function BNSViewerAspectRatioDirective ($window, $timeout) {

  return {
    link: postLink,
    priority: -500,
  };

  function postLink (scope, element) {
    var $frames;

    $timeout(function () {
      $frames = element.find('iframe');
      $frames.each(function () {
        angular.element(this)
          .data('aspectRatio', this.height / this.width)
          .removeAttr('height')
          .removeAttr('width')
        ;
      });

      resize();
    });

    angular.element($window).on('resize', resize);

    scope.$on('$destroy', function cleanup () {
      angular.element($window).off('resize', resize);
    });

    function resize () {
      $frames.each(function () {
        var $frame = angular.element(this);
        var newWidth = $frame.parent().width();
        var newHeight = $frame.parent().height();
        var ratio = $frame.data('aspectRatio');
        if (newWidth * ratio < newHeight) {
          // width is the limit
          $frame
            .width(newWidth)
            .height(newWidth * ratio)
          ;
        } else {
          // height is the limit
          $frame
            .height(newHeight)
            .width(newHeight / ratio)
          ;
        }
      });
    }
  }

}

})(angular);
