(function (angular) {
'use strict';

/*
 * @ngdoc module
 * @name bns.material
 * @description Material app config and overrides
 */
angular.module('bns.material', [
  'ngMaterial',
])

  .config(themeConfig)
  .config(augmentMediaConfig)
  .config(mdPanelCreateDecorator)
  .run(setBreakpointsRun)

;

function themeConfig ($mdThemingProvider) {
  $mdThemingProvider.definePalette('bns-light-green', $mdThemingProvider.extendPalette('light-green', {
    contrastDefaultColor: 'dark',
    contrastLightColors: ['500', '600', '700', '800', '900'],
  }));

  $mdThemingProvider.definePalette('bns-grey', $mdThemingProvider.extendPalette('grey', {
    '50': '#ffffff', // override gray-50 by pure white
  }));

  $mdThemingProvider.theme('default')
    .primaryPalette('light-blue', {
      'default': '600',
    })
    .accentPalette('bns-light-green', {
      'default': '500',
      'hue-1': '300',
      'hue-2': '800',
      'hue-3': 'A200',
    })
    .backgroundPalette('bns-grey')
  ;
}

function augmentMediaConfig ($provide) {
  // monkey-patch $mdMedia to add a mobile check
  $provide.decorator('$mdMedia', mdMediaDecorator);

  function mdMediaDecorator ($delegate, $sniffer) {
    $delegate.hasTouch = $sniffer.hasEvent('touchstart');

    return $delegate;
  }
}

function setBreakpointsRun ($mdConstant) {
  // override layout breakpoints
  // /!\ Must set the same values in the scss files
  $mdConstant.MEDIA.xs = '(max-width: 0px)';
  $mdConstant.MEDIA['gt-xs'] = '(min-width: 0px)';
  $mdConstant.MEDIA.sm = '(max-width: 600px)';
  $mdConstant.MEDIA['gt-sm'] = '(min-width: 600px)';
  $mdConstant.MEDIA.md = '(min-width: 600px) and (max-width: 1000px)';
  $mdConstant.MEDIA['gt-md'] = '(min-width: 1000px)';
  $mdConstant.MEDIA.lg = '(min-width: 1000px) and (max-width: 1400px)';
  $mdConstant.MEDIA['gt-lg'] = '(min-width: 1400px)';
}

function mdPanelCreateDecorator ($provide) {

  $provide.decorator('$mdPanel', function ($delegate) {
    var originalCreate = $delegate.create;
    $delegate.create = bnsCreate;

    return $delegate;

    // override $mdPanel.create() to get a hold on the MdPanelRef
    function bnsCreate () {
      var panelRef = originalCreate.apply($delegate, arguments);
      var originalUpdatePosition = panelRef._updatePosition;
      panelRef._updatePosition = bnsUpdatePosition;

      // override MdPanelRef._updatePosition() to add position classes
      function bnsUpdatePosition () {
        var position = panelRef.config.position;
        if (position && position._relativeToEl) {
          panelRef.panelEl.addClass('panel-position-relative');
        }

        var ret = originalUpdatePosition.apply(panelRef, arguments);
        if (position && position._relativeToEl) {
          panelRef.panelEl.addClass('panel-position-x-'+position._actualPosition.x);
          panelRef.panelEl.addClass('panel-position-y-'+position._actualPosition.y);
        } else {
          panelRef.panelEl.removeClass(function (index, css) {
            return (css.match(/(^|\s)panel-position-\S+/g) || []).join(' ');
          });
        }

        return ret;
      }

      return panelRef;
    }
  });

}

}) (angular);
