(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.minisite.scroll
   */
  angular.module('bns.minisite.scroll', [])

    .directive('bnsMinisiteScroll', BNSMinisiteScrollDirective)

  ;

  /**
   * @ngdoc directive
   * @name bnsMinisiteScroll
   * @module bns.minisite.scroll
   *
   * @description
   * Scroll magic
   */
  function BNSMinisiteScrollDirective ($window, $timeout) {

     return function(scope, element) {
      /*global document: false */
      /* header DOM element with md-page-header attribute */
      var header         = document.querySelector('[md-page-header]');

      var head = angular.element(document.querySelector('[scroll]'));
      /* Store header dimensions to initialize header styling */
      var baseDimensions = header.getBoundingClientRect();
      /* DOM element with md-header-title attribute (title in toolbar) */
      var title          = angular.element(document.querySelector('[md-header-title]'));
      /* DOM element with md-header-picture attribute (picture in header) */
      var picture        = angular.element(document.querySelector('[md-header-picture]'));
      /* The height of a toolbar by default in Angular Material */
      var legacyToolbarH = 64;
      /* The primary color palette used by Angular Material */
      var primaryColor   = [255,255,255];

      var sidebar = element.find('.bns-sidebar');
      var sidebarContent = sidebar.find('> md-content');

      function styleInit () {
        title.css('padding-left','16px');
        title.css('position','relative');
        title.css('transform-origin', '24px');
      }

      function handleStyle(dim) {
        if ((dim.bottom) > legacyToolbarH) {
          title.css('top', ((dim.bottom)-legacyToolbarH)+'px');
          head.css('height', (dim.bottom)+'px');
          if (sidebar.hasClass('md-locked-open')) {
            sidebarContent.css('margin-top', 0); // normal sidebar, in the flow
          } else {
            sidebarContent.css('margin-top',(dim.bottom)+'px'); // fixed sidebar, keep content below
          }
          title.removeClass('darken');

        } else {
          title.css('top', '0px');
          head.css('height', legacyToolbarH+'px');
          if (sidebar.hasClass('md-locked-open')) {
            sidebarContent.css('margin-top', (-1*dim.bottom + legacyToolbarH)+'px');
          } else {
            sidebarContent.css('margin-top',(legacyToolbarH)+'px'); // fixed sidebar, keep content below
          }
          title.css('transform','scale(1,1)');
          title.addClass('darken');
        }
        head.css('background-color','rgba('+primaryColor[0]+','+primaryColor[1]+','+primaryColor[2]+','+(1-ratio(dim))+')');
        picture.css('background-position','50% '+(ratio(dim)*50)+'%');
      }

      function ratio(dim) {
        var r = (dim.bottom)/dim.height;
        if(r<0) {
          return 0;
        }
        if(r>1) {
          return 1;
        }
        return Number(r.toString().match(/^\d+(?:\.\d{0,2})?/));
      }

      styleInit();
      handleStyle(baseDimensions);

      // hacky way to solve wrong sidebar init happening sometimes (because of
      // css transition?)
      $timeout(function () {
        styleInit();
        handleStyle(baseDimensions);
      }, 500);

        /* Scroll event listener */
      angular.element($window).bind('scroll', function() {
        var dimensions = header.getBoundingClientRect();
        handleStyle(dimensions);
        scope.$apply();
      });

      /* Resize event listener */
      angular.element($window).bind('resize',function () {
        baseDimensions = header.getBoundingClientRect();
        var dimensions = header.getBoundingClientRect();
        handleStyle(dimensions);
        scope.$apply();
      });

    };
  }

})(angular);
