(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.calendar.colorPicker
 */
angular.module('bns.calendar.colorPicker', [])

  .constant('BNS_CALENDAR_COLOR_CLASSES', {
    'cal-green': '#A7C736',
    'cal-red': '#E8452E',
    'cal-orange': '#F8B93C',
    'cal-pink': '#C97378',
    'cal-blue': '#63B4BB',
    'cal-light-blue': '#9BD3D5',
    'cal-brown': '#FAC53E',
  })
  .directive('bnsCalendarColorPicker', BNSCalendarColorPickerDirective)
  .controller('BNSCalendarColorPicker', BNSCalendarColorPickerController)

;

/**
 * @ngdoc directive
 * @name bnsCalendarColorPicker
 * @module bns.calendar.colorPicker
 *
 * @description
 * Allows to pick color of an agenda
 *
 * ** Attributes **
 * - agenda: the agenda object. Should have an 'id' and a 'color' properties.
 * - onPick: a callback to be executed when a color is picked.
 */
function BNSCalendarColorPickerDirective () {

  return {
    templateUrl: 'views/calendar/directives/bns-calendar-color-picker.html',
    controller: 'BNSCalendarColorPicker',
    controllerAs: 'ctrl',
    bindToController: true,
    scope: {
      agenda: '=',
      onPick: '&',
    },
  };

}

function BNSCalendarColorPickerController (BNS_CALENDAR_COLOR_CLASSES, $mdMenu, Restangular) {

  var ctrl = this;
  ctrl.busy = false;
  ctrl.colors = angular.copy(BNS_CALENDAR_COLOR_CLASSES);
  ctrl.setColor = setColor;

  function setColor (color, className) {
    ctrl.busy = true;

    return Restangular.one('calendar').one('agendas', ctrl.agenda.id).patch({
      color_class: className,
    })
      .then(function success () {
        ctrl.agenda.color = color;
        $mdMenu.hide().then(function () {
          ctrl.busy = false;
        });

        if (angular.isFunction(ctrl.onPick)) {
          ctrl.onPick();
        }
      })
    ;
  }

}

})(angular);
