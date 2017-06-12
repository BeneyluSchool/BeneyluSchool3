(function (angular) {
'use strict';

angular.module('bns.calendar.backFormControllers', [
  'bns.calendar.agendaEventType',
  'bns.calendar.calendars',
])

  .controller('CalendarBackFormActionbar', CalendarBackFormActionbarController)
  .controller('CalendarBackFormSidebar', CalendarBackFormSidebarController)
  .controller('CalendarBackFormContent', CalendarBackFormContentController)
  .service('calendarBackFormState', CalendarBackFormStateFactory)

;

function CalendarBackFormActionbarController ($scope, $state, dialog, toast, Calendars, calendarBackFormState, event) {

  $scope.shared = calendarBackFormState;
  var ctrl = this;
  ctrl.event = event;
  ctrl.deleteEvent = deleteEvent;

  function deleteEvent($event) {
    if (!event.id) {
      return;
    }

    return dialog.confirm({
      title: 'CALENDAR.TITLE_DELETE_EVENT',
      content: 'CALENDAR.DESCRIPTION_DELETE_EVENT',
      cancel: 'CALENDAR.LINK_CANCEL',
      ok: 'CALENDAR.LINK_DELETE_EVENT',
      intent: 'warn',
      targetEvent: $event,
    })
      .then(function doDelete() {
        return Calendars.one('events', event.id).remove()
          .then(function success () {
            toast.success('CALENDAR.FLASH_DELETE_EVENT_SUCCESS');

            return $state.go('^.week');
          })
          .catch(function error (response) {
            toast.error('CALENDAR.FLASH_DELETE_EVENT_ERROR');

            throw response;
          })
        ;
      })
    ;
  }

}

function CalendarBackFormSidebarController ($rootScope, $scope, $timeout, calendarBackFormState) {

  $scope.shared = calendarBackFormState;

  init();

  function init () {
    $scope.$watch('shared.type.form.isAllDay', function (value) {
      if (value !== undefined) {
        $timeout(function(){
          // can't use scope because of form proxy :'(
          $rootScope.$broadcast('track.height');
        }, 10);
      }
    });
  }

}

function CalendarBackFormContentController (_, $scope, $state, $translate, calendarBackFormState, toast, navbar, Calendars, agendas, event) {

  var shared = $scope.shared = calendarBackFormState;
  var ctrl = this;
  ctrl.submit = submit;
  ctrl.agendas = agendas;
  ctrl.error = '';

  init();

  function init () {
    // wait for complete form initialization before altering it
    var unwatch = $scope.$watch('shared.type.form.description', function (v) {
      if (!v) {
        return;
      }

      if (!event.agenda_id) {
        navbar.getOrRefreshGroup().then(function (group) {
          var defaultAgenda = _.find(agendas, {group_id: group.id});
          if (defaultAgenda) {
            shared.type.form.agenda_id = defaultAgenda.id;
          }
        });
      }

      shared.type.setData(event);

      unwatch();
    });
  }

  function submit () {
    var promise;
    if (event.id) {
      promise = Calendars.one('events', event.id).patch(shared.type.getData());
    } else {
      promise = Calendars.all('events').post(shared.type.getData());
    }

    return promise
      .then(function success (response) {
        toast.success('CALENDAR.FLASH_EVENT_SAVE_SUCCESS');
        $state.go('^.week', { defaultDate: response.date_start });

        return response;
      })
      .catch(function error (response) {
        console.error('[POST] events', response);
        toast.error('CALENDAR.FLASH_EVENT_SAVE_ERROR');

        if (response.data.errors.errors) {
          ctrl.error = $translate.instant('CALENDAR.'+ response.data.errors.errors[0]);
        }

        throw 'CALENDAR.EVENT_SAVE_ERROR';
      })
    ;
  }

}

function CalendarBackFormStateFactory (AgendaEventType) {

  return {
    type: new AgendaEventType(),
  };

}

})(angular);
