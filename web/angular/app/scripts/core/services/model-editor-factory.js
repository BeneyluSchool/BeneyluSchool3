'use strict';

angular.module('bns.core.modelEditor', [
  'bns.core.objectHelpers',
])

  /**
   * @ngdoc service
   * @name bns.core.modelEditor.ModelEditor
   * @kind function
   *
   * @description An abstract model editor, capable of autosave and rollback.
   * Actual API dialogs must be implemented by subclassing this editor.
   *
   * @example
   * var editor = new ModelEditor()
   * editor.init(myModelObject)
   * editor.autosave(2) // enable autosave 2sec after changes
   * // ...
   * editor.commit().then()
   * editor.rollback().then()
   *
   * @requires $q
   * @requires $timeout
   * @requires $rootScope
   * @requires objectHelpers
   *
   * @returns {Object} the ModelEditor constructor.
   */
  .factory('ModelEditor', function ($q, $timeout, $rootScope, objectHelpers) {

    /**
     * Initializes for the given model.
     *
     * @param {Object} model
     */
    function ModelEditor (model) {
      this._model = null;   // object being edited
      this._source = null;  // source of data for rollback
      this._mask = null;
      this._timer = null;   // js timeout
      this._watch = null;   // scope watcher
      this.init(model);
    }

    /**
     * Initializes the editor with the given model, and an optional mask.
     *
     * The mask is a (nested) set of properties to restrict save/rollback to. If
     * given, properties that are not in the set will be left untouched by the
     * editor.
     *
     * @param {Object} model
     * @param {Object} mask
     */
    ModelEditor.prototype.init = function (model, mask) {
      this.teardownAutosave();
      this._model = model;

      if (mask) {
        this._mask = mask;
      }
      this.refreshSource();
    };

    /**
     * Refreshes the model source, used for rollbacks
     */
    ModelEditor.prototype.refreshSource = function () {
      if (this._mask) {
        this._source = maskCopy(this._model, this._mask);
      } else {
        this._source = angular.copy(this._model);
      }

      // recursively copies the given object following the given mask, ie
      // ignoring model properties that are not in the mask.
      function maskCopy (model, mask) {
        var source = {};
        for (var prop in mask) {
          if (angular.isArray(model[prop])) {
            source[prop] = angular.copy(model[prop]);
          } else if (angular.isObject(model[prop])) {
            source[prop] = maskCopy(model[prop], mask[prop]);
          } else {
            source[prop] = model[prop];
          }
        }

        return source;
      }
    };

    /**
     * Enables (or disables) autosave.
     *
     * @param {Integer} delay The number of seconds to wait after a detected
     *                        change before triggering autosave.
     *                        `false` to disable the feature
     * @param {Function} callback An optional callback to be executed upon
     *                            autosave. It receives the API promise as
     *                            parameter.
     */
    ModelEditor.prototype.autosave = function (delay, callback) {
      var editor = this;
      if (!delay) {
        return editor.teardownAutosave();
      }

      if (!editor._model) {
        throw 'Cannot enable autosave without model';
      }

      callback = callback || angular.noop();
      delay = parseInt(delay, 10);

      editor._watch = $rootScope.$watch(function () {
        return editor._model;
      }, watchHandler, true);

      function watchHandler (newValue, oldValue) {
        if (newValue === oldValue) {
          return;
        }

        if (editor._timer) {
          $timeout.cancel(editor._timer);
        }

        editor._timer = $timeout(function () {
          var promise = editor.doAutosave(editor._model);
          callback(promise);
          editor._timer = null;
        }, delay * 1000, false);
      }
    };

    /**
     * Actually performs autosave to the API. Must be implemented.
     *
     * @param {Object} model
     * @return {Object} A promise
     */
    ModelEditor.prototype.doAutosave = function (model) {
      console.warn('This ModelEditor does not know how to actually autosave model', model);

      return console.warn('You must implement its \'doAutosave\' method');
    };

    /**
     * Removes internal timers/watchers related to the autosave.
     */
    ModelEditor.prototype.teardownAutosave = function () {
      var editor = this;
      if (editor._timer) {
        $timeout.cancel(editor._timer);
        editor._timer = null;
      }
      if (editor._watch) {
        editor._watch();
        editor._watch = null;
      }
    };

    /**
     * Cancels all edits made to the model
     *
     * @returns {Object} A promise that is given the rollbacked model
     */
    ModelEditor.prototype.rollback = function () {
      var editor = this;
      editor.teardownAutosave();

      var deferred = $q.defer();

      // simply rollback from local copy
      //to-do: réparer
      objectHelpers.softMerge(editor._model, editor._source, 'id', false, false, true);
      editor.refreshSource();

      deferred.resolve(editor._model);

      return deferred.promise;
    };

    /**
     * Persists changes made to the model.
     *
     * @param {Function} transform An optional function to transform model
     * before commit.
     *
     * @returns {Object} A promise that is given the edited model
     */
    ModelEditor.prototype.commit = function (transform) {
      var editor = this;
      editor.teardownAutosave();
      (transform || angular.noop)(editor._model);

      return editor.doCommit(editor._model);
    };

    /**
     * Actually persist changes to the API. Must be implemented.
     *
     * @param {Object} model
     * @returns {Object} A promise that is given the edited model.
     */
    ModelEditor.prototype.doCommit = function (model) {
      console.warn('This ModelEditor does not know how to actually save model', model);

      return console.warn('You must implement its \'doCommit\' method');
    };

    return ModelEditor;
  })

;
