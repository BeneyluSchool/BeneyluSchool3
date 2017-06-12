(function (angular) {
'use strict';

angular.module('bns.calendar.agendaEventType', [])

  .factory('AgendaEventType', AgendaEventTypeFactory)

;

/**
 * @ngdoc service
 * @name AgendaEventType
 * @module bns.calendar.agendaEventType
 *
 * @description
 * Manages an AgendaEvent form and all idiosyncrasis between API formats and
 * view/model formats.
 *
 * @requires _
 * @requires moment
 */
function AgendaEventTypeFactory (_, moment) {

  var MIN_HOUR = 8;
  var MAX_HOUR = 22;

  function AgendaEventType () {
    this.form = {};
    this.attachments = [];

    this.recurrences = {
      DAILY: 'CALENDAR.LABEL_DAILY',
      WEEKLY: 'CALENDAR.LABEL_WEEKLY',
      MONTHLY: 'CALENDAR.LABEL_MONTHLY',
      YEARLY: 'CALENDAR.LABEL_YEARLY',
    };

    // populate valid hours
    this.times = [];
    for (var h = MIN_HOUR; h < MAX_HOUR; h++) {
      var hour = h < 10 ? '0' : '';
      hour += h;
      this.times.push(hour+':00');
      this.times.push(hour+':30');
    }
    this.times.push(MAX_HOUR+':00');
  }

  /**
   * @returns {Object} A map of API-compliant data
   */
  AgendaEventType.prototype.getData = function () {
    var dateStart = moment(this.form.date_start.value);
    var dateEnd = moment(this.form.date_end.value);
    var timeStart = moment(this.form.time_start.value);
    var timeEnd = moment(this.form.time_end.value);

    dateStart.hours(this.form.isAllDay ? 0 : timeStart.hours());
    dateStart.minutes(this.form.isAllDay ? 0 : timeStart.minutes());
    dateStart.seconds(0);
    dateStart.milliseconds(0);

    dateEnd.hours(this.form.isAllDay ? 0 : timeEnd.hours());
    dateEnd.minutes(this.form.isAllDay ? 0 : timeEnd.minutes());
    dateEnd.seconds(0);
    dateEnd.milliseconds(0);

    // force same day for recurring events
    if (this.form.isRecurring) {
      dateEnd.year(dateStart.year());
      dateEnd.month(dateStart.month());
      dateEnd.day(dateStart.day());
    }

    var data = {
      agendaId: this.form.agenda_id,
      title: this.form.title.value,
      description: this.form.description.value,
      start: dateStart.format(),
      end: dateEnd.format(),
      isAllDay: this.form.isAllDay,
      isRecurring: this.form.isRecurring,
      location: this.form.location.value,
      'resource-joined': _.map(this.attachments, 'id'),
    };

    if (this.form.isRecurring) {
      data.recurringType = this.form.recurring_type.value;
      data.recurringEndDate = this.form.recurring_end_date.value ?
        moment(this.form.recurring_end_date.value).format() :
        null ;
      data.recurringCount = this.form.recurring_count.value;
    }

    return data;
  };

  /**
   * Populates the form with the given data
   *
   * @param {Object} data
   */
  AgendaEventType.prototype.setData = function (data) {
    var defaults = getDefaultDates(data);
    var start = defaults.start;
    var end = defaults.end;

    this.form.agenda_id = data.agenda_id;
    this.form.title.value = data.title;
    this.form.description.value = data.description;
    this.form.location.value = data.location;
    this.form.date_start.value = start.clone().startOf('day').toDate();
    this.form.date_end.value = end.clone().startOf('day').toDate();
    this.form.time_start.value = start.clone().toDate();
    this.form.time_end.value = end.clone().toDate();
    this.form.isAllDay = !!data.is_all_day;
    this.form.isRecurring = !!data.is_recurring;
    this.form.recurring_type.value = data.recurring_type;

    if (data.recurring_end_date) {
      this.form.recurring_end_date.value = moment(data.recurring_end_date).toDate();
    } else if (data.recurring_count) {
      this.form.recurring_count.value = data.recurring_count;
    }

    if (data._embedded && data._embedded.attachments) {
      this.setAttachments(data._embedded.attachments);
    } else {
      // reset attachments
      this.attachments.splice(0, this.attachments.length);
    }
  };

  AgendaEventType.prototype.setAttachments = function (attachments) {
    this.attachments.splice(0, this.attachments.length);
    Array.prototype.push.apply(this.attachments, attachments);
  };

  return AgendaEventType;

  function getDefaultDates (data) {
    var start, end;

    if (data.start) {
      start = moment(data.start);
    } else {
      start = moment();
      start.hours(Math.min(Math.max(MIN_HOUR, start.hours()), MAX_HOUR - 1)); // ensure valid hour
      start.add(30 - (start.minutes() % 30), 'minutes');                      // round to next 30 minutes
      start.seconds(0);
      start.milliseconds(0); // weird rounding
    }

    if (data.end) {
      end = moment(data.end);
    } else {
      end = start.clone().add(30, 'minutes');
    }

    return {
      start: start,
      end: end,
    };
  }

}

})(angular);
