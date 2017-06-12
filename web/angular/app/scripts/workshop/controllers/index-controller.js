'use strict';

angular.module('bns.workshop.indexController', [
  'ui.router',
  'bns.core.url',
  'bns.core.message',
  'bns.workshop.manager',
  'bns.user.users',
  'bns.workshop.restangular',
  'bns.main.statistic',
])

.controller('WorkshopIndexController', function ($q, $rootScope, $state, url, message, workshopManager, Users, WorkshopRestangular, statistic) {
  var ctrl = this;
  var documents = WorkshopRestangular.all('documents');
  var contents = WorkshopRestangular.all('contents');

  ctrl.url = url;
  ctrl.busy = false;
  ctrl.canEditDocument = canEditDocument;
  ctrl.createDocument = createDocument;
  ctrl.createAudio = createAudio;
  ctrl.createQuestionnaire = createQuestionnaire;
  ctrl.range = range;
  ctrl.isDocument = workshopManager.isDocument;
  ctrl.isAudio = workshopManager.isAudio;
  ctrl.contributorsList = contributorsList;

  init();

  function init () {
    $rootScope.hideDockBar = false;

    getContents();

    statistic.visit('workshop');
  }

  function getContents () {
    ctrl.busy = true;

    $q.all({
      me: Users.me(),                                 // get current user
      contents: contents.getList({ limit: 10, }),     // load all contents
    })
      .then(function success (data) {
        ctrl.contents = data.contents;
        ctrl.me = data.me;
      })
      .catch(function error (result) {
        message.error('WORKSHOP.GET_DOCUMENTS_ERROR');
        console.error('GET contents', result);
      })
      .finally(function () {
        ctrl.busy = false;
      })
    ;
  }

  function contributorsList (document) {
    var list = [];
    angular.forEach(document._embedded.contributor_groups, function (group) {
      list.push(group.label);
    });
    angular.forEach(document._embedded.contributor_users, function (user) {
      list.push(user.full_name);
    });

    return list.join(', ');
  }

  function canEditDocument (document) {
    return !document.is_locked || ctrl.me.rights.workshop_document_manage_lock;
  }

  /**
   * Creates a new Workshop document
   */
  function createDocument () {
    return doCreateDocument({});
  }

  function createQuestionnaire () {
    return doCreateDocument({questionnaire: true});
  }

  function doCreateDocument (params) {
    return documents.post(params).then(success, error);

    function success (response) {
      // extract the document id from the response Location header, that
      // contains the API url for the newly-created document
      var location = response.headers.location;
      var match = location.match(/\/documents\/(\d+)/);
      var documentId = match[1];

      if (documentId) {
        message.success('WORKSHOP.CREATE_DOCUMENT_SUCCESS');
        $state.go('app.workshop.document.base.index', { documentId: documentId, pagePosition: 1 });
      }
    }

    function error (result) {
      message.error('WORKSHOP.CREATE_DOCUMENT_ERROR');
      console.error('POST documents', result);
    }
  }

  function createAudio () {
    $state.go('app.workshop.audio.create');
  }

  function range (n) {
    return new Array(n);
  }
});
