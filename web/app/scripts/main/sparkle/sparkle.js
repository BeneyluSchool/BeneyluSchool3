(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.sparkle
 *
 * @description Make it spark!
 * Shamelessly copied from https://codepen.io/simeydotme/pen/jgcvi, props to the
 * original author.
 */
angular.module('bns.main.sparkle', [])

  .directive('bnsSparkle', BNSSparkleDirective)
  .factory('Sparkle', SparkleFactory)

;

/**
 * @ngdoc directive
 * @name bnsSparkle
 * @module bns.main.sparkle
 *
 * @description
 * Adds sparkles to an element. Supported options are:
 * - `color`: An hex color string, or 'rainbow', governing the sparks color.
 * - `count`: Number of sparks. Defaults to 30.
 * - `overlap`: Number of pixels the sparks can travel outside of the element.
 *              Defaults to 0.
 * - `speed`: Animation speed. Defaults to 1.
 * - `timeout`: When to stop the sparkle (ms). Can also be set to 'never' for
 *              neverending fabulousness. Sparkle starts autmatically if this
 *              option is set. Defaults to 0.
 * - `hover`: Whether to show sparks on hover. Defaults to true.
 *
 * @requires Sparkle
 */
function BNSSparkleDirective ($timeout, Sparkle) {

  return {
    restrict: 'A',
    link: postLink,
    priority: 100,
  };

  function postLink (scope, element, attrs) {
    element.css('position', 'relative');

    var options = scope.$eval(attrs.bnsSparkle);
    var settings = angular.extend({
      width: element.outerWidth(),
      height: element.outerHeight(),
      color: '#FFFFFF',
      count: 30,
      overlap: 0,
      speed: 1,
      timeout: 0,
      hover: true,
    }, options );

    var sparkle = new Sparkle(element, settings);

    if ('never' === settings.timeout) {
      sparkle.over();
    } else if (settings.timeout) {
      var timeout = parseInt(settings.timeout, 10);
      if (timeout) {
        sparkle.over();
        $timeout(function () {
          sparkle.out();
        }, timeout);
      }
    }

    if (settings.hover) {
      element.on({
        'mouseover focus' : function () {
          sparkle.over();
        },
        'mouseout blur' : function () {
          sparkle.out();
        }
      });
    }
  }

}

function SparkleFactory ($window) {

  function Sparkle ($parent, options) {
    this.options = options;
    this.init($parent);
  }

  Sparkle.prototype.init = function ($parent) {
    var self = this;

    this.$canvas = angular.element('<canvas>')
      .addClass('sparkle-canvas')
      .css({
        position: 'absolute',
        top: '-'+self.options.overlap+'px',
        left: '-'+self.options.overlap+'px',
        'pointer-events': 'none'
      })
      .appendTo($parent)
    ;
    this.canvas = this.$canvas[0];
    this.context = this.canvas.getContext('2d');

    this.sprite = new $window.Image();
    this.sprites = [0, 6, 13, 20];
    this.sprite.src = this.datauri;

    this.canvas.width = this.options.width + (this.options.overlap * 2);
    this.canvas.height = this.options.height + (this.options.overlap * 2);

    this.particles = this.createSparkles( this.canvas.width , this.canvas.height );

    this.anim = null;
    this.fade = false;
  };

  Sparkle.prototype.createSparkles = function (w, h) {
    var holder = [];

    for (var i = 0; i < this.options.count; i++) {
      var color = this.options.color;

      if (this.options.color === 'rainbow') {
        color = '#'+ ('000000' + Math.floor(Math.random()*16777215).toString(16)).slice(-6);
      } else if (angular.isArray(this.options.color)) {
        color = this.options.color[ Math.floor(Math.random()*this.options.color.length) ];
      }

      holder[i] = {
        position: {
          x: Math.floor(Math.random()*w),
          y: Math.floor(Math.random()*h)
        },
        style: this.sprites[ Math.floor(Math.random()*4) ],
        delta: {
          x: Math.floor(Math.random() * 1000) - 500,
          y: Math.floor(Math.random() * 1000) - 500
        },
        size: parseFloat((Math.random()*2).toFixed(2)),
        color: color
      };
    }

    return holder;
  };

  Sparkle.prototype.draw = function (time) {
    var ctx = this.context;
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    for (var i = 0; i < this.options.count; i++) {
      var derpicle = this.particles[i];
      var modulus = Math.floor(Math.random()*7);

      if (Math.floor(time) % modulus === 0) {
        derpicle.style = this.sprites[Math.floor(Math.random()*4)];
      }

      ctx.save();
      ctx.globalAlpha = derpicle.opacity;
      ctx.drawImage(this.sprite, derpicle.style, 0, 7, 7, derpicle.position.x, derpicle.position.y, 7, 7);

      if (this.options.color) {
        ctx.globalCompositeOperation = 'source-atop';
        ctx.globalAlpha = 0.5;
        ctx.fillStyle = derpicle.color;
        ctx.fillRect(derpicle.position.x, derpicle.position.y, 7, 7);
      }

      ctx.restore();
    }
  };

  Sparkle.prototype.update = function () {
    var self = this;

    this.anim = $window.requestAnimationFrame(function (time) {
      for (var i = 0; i < self.options.count; i++) {
        var u = self.particles[i];

        var randX = (Math.random() > Math.random()*2);
        var randY = (Math.random() > Math.random()*3);

        if (randX) {
          u.position.x += ((u.delta.x * self.options.speed) / 1500);
        }

        if (!randY) {
          u.position.y -= ((u.delta.y * self.options.speed) / 800);
        }

        if (u.position.x > self.canvas.width) {
         u.position.x = -7;
        } else if (u.position.x < -7) {
          u.position.x = self.canvas.width;
        }

        if (u.position.y > self.canvas.height) {
          u.position.y = -7;
          u.position.x = Math.floor(Math.random()*self.canvas.width);
        } else if (u.position.y < -7) {
          u.position.y = self.canvas.height;
          u.position.x = Math.floor(Math.random()*self.canvas.width);
        }

        if (self.fade) {
          u.opacity -= 0.02;
        } else {
          u.opacity -= 0.005;
        }

        if (u.opacity <= 0) {
          u.opacity = (self.fade) ? 0 : 1;
        }
      }

      self.draw(time);

      if (self.fade) {
        self.fadeCount -= 1;
        if (self.fadeCount < 0) {
          $window.cancelAnimationFrame( self.anim );
        } else {
          self.update();
        }
      } else {
        self.update();
      }
     });
  };

  Sparkle.prototype.cancel = function () {
    this.fadeCount = 100;
  };

  Sparkle.prototype.over = function () {
    $window.cancelAnimationFrame(this.anim);

    for (var i = 0; i < this.options.count; i++) {
      this.particles[i].opacity = Math.random();
    }

    this.fade = false;
    this.update();
  };

  Sparkle.prototype.out = function () {
    this.fade = true;
    this.cancel();
  };

  Sparkle.prototype.datauri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABsAAAAHCAYAAAD5wDa1AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDozNDNFMzM5REEyMkUxMUUzOEE3NEI3Q0U1QUIzMTc4NiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDozNDNFMzM5RUEyMkUxMUUzOEE3NEI3Q0U1QUIzMTc4NiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjM0M0UzMzlCQTIyRTExRTM4QTc0QjdDRTVBQjMxNzg2IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjM0M0UzMzlDQTIyRTExRTM4QTc0QjdDRTVBQjMxNzg2Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+jzOsUQAAANhJREFUeNqsks0KhCAUhW/Sz6pFSc1AD9HL+OBFbdsVOKWLajH9EE7GFBEjOMxcUNHD8dxPBCEE/DKyLGMqraoqcd4j0ChpUmlBEGCFRBzH2dbj5JycJAn90CEpy1J2SK4apVSM4yiKonhePYwxMU2TaJrm8BpykpWmKQ3D8FbX9SOO4/tOhDEG0zRhGAZo2xaiKDLyPGeSyPM8sCxr868+WC/mvu9j13XBtm1ACME8z7AsC/R9r0fGOf+arOu6jUwS7l6tT/B+xo+aDFRo5BykHfav3/gSYAAtIdQ1IT0puAAAAABJRU5ErkJggg==';

  return Sparkle;
}

})(angular);
