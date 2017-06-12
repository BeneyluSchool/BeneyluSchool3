(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.lunch.lunchWeekEditor
 */
angular.module('bns.lunch.lunchWeekEditor', [])

  .service('LunchWeekEditor', LunchWeekEditorService)

;

/**
 * @ngdoc service
 * @name LunchWeekEditor
 * @module bns.lunch.lunchWeekEditor
 *
 * @description
 *
 *
 * @returns {Object} The LunchWeekEditor service
 */
function LunchWeekEditorService (toast, LunchWeek, $window) {

  var SECTIONS = [
    { value: 'full_menu', label: 'LUNCH.VALUE_FREE_ENTRY' },
    { value: 'starter', label: 'LUNCH.VALUE_STARTER' },
    { value: 'main_course', label: 'LUNCH.VALUE_MAIN_COURSE' },
    { value: 'accompaniment', label: 'LUNCH.VALUE_ACCOMPANIMENT' },
    { value: 'dairy', label: 'LUNCH.VALUE_DAIRY' },
    { value: 'dessert', label: 'LUNCH.VALUE_DESSERT' },
    { value: 'afternoon_snack', label: 'LUNCH.VALUE_AFTERNOON_SNACK' },
  ];

  var STATUSES = [
    { value: '1', label: 'LUNCH.STATUS_NORMAL' },
    { value: '2', label: 'LUNCH.STATUS_SPECIAL' },
    { value: '3', label: 'LUNCH.STATUS_NO_LUNCH' },
  ];

  function LunchWeekEditor () {
    this._model = null;
    this._form = null;

    this.sections = SECTIONS;
    this.statuses = STATUSES;

    this.busy = false;
    this.ready = false;

    this._form = buildForm();
  }

  LunchWeekEditor.sections = SECTIONS;
  LunchWeekEditor.statuses = STATUSES;

  LunchWeekEditor.prototype.setModel = function (model) {
    this._model = model;
    this.populate(model);
    this.ready = true;
  };

  LunchWeekEditor.prototype.getForm = function () {
    return this._form;
  };

  LunchWeekEditor.prototype.populate = function (data) {
    var self = this;
    var weekProps = ['label', 'description', 'sections'];
    var dayProps = ['status'];
    self.sections.forEach(function (section) {
      dayProps.push(section.value);
    });

    weekProps.forEach(function (prop) {
      if (data[prop] !== undefined) {
        if (angular.isArray(self._form[prop]) && angular.isArray(data[prop])) {
          self._form[prop].splice(0, self._form[prop].length);        // empty 1st array
          self._form[prop].push.apply(self._form[prop], data[prop]);  // merge 2nd array into 1st
        } else {
          self._form[prop] = data[prop];
        }
      }
    });

    if (data._embedded && data._embedded.days) {
      self._form.lunch_days.forEach(function (day, i) {
        dayProps.forEach(function (prop) {
          if (data._embedded.days[i][prop] !== undefined) {
            day[prop] = data._embedded.days[i][prop];
          }
        });
      });
    }
  };

  LunchWeekEditor.prototype.save = function () {
    var self = this;

    if (!this._model) {
      throw 'Cannot save without a model';
    }

    this.busy = true;

    var formData = this._form;
    var date = this._model.date_start;
    var promise = LunchWeek.one(this._model.date_start);

    if (this._model.id) {
      promise = promise.patch(formData)
        .then(function success () {
          toast.success('LUNCH.FLASH_EDIT_MENU_SUCCESS');
        })
        .catch(function error (response) {
          console.error('[PATCH] lunch/week', response);
          toast.error('LUNCH.FLASH_EDIT_MENU_ERROR');
        })
      ;
    } else {
      promise = promise.post('', formData)
        .then(function success () {
          toast.success('LUNCH.FLASH_CREATE_MENU_SUCCESS');
        })
        .catch(function error (response) {
          console.error('[POST] lunch/week', response);
          toast.error('LUNCH.FLASH_CREATE_MENU_ERROR');
        })
      ;
    }

    promise.finally(function end () {
      self.busy = false;
      $window.location.href = '#/lunch/manage/' + date;
    });
    return promise;
  };

  LunchWeekEditor.prototype.remove = function () {
    var self = this;

    if (!(this._model && this._model.id)) {
      throw 'Cannot delete an undefined model';
    }

    this.busy = true;

    return LunchWeek.one(this._model.date_start).remove()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      toast.success('LUNCH.FLASH_DELETE_MENU_SUCCESS');
    }
    function error (response) {
      console.error(response);
      toast.error('LUNCH.FLASH_DELETE_MENU_ERROR');
    }
    function end () {
      self.busy = false;
    }
  };

  return LunchWeekEditor;

  function buildForm () {
    var form = {
      label: '',
      description: '',
      sections: ['full_menu'],
      lunch_days: [],
    };

    for (var i = 0; i < 5; i++) {
      form.lunch_days.push({
        status: '1',
      });
    }

    return form;
  }

}

})(angular);
