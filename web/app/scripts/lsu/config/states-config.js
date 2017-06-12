(function (angular) {
'use strict'  ;

angular.module('bns.lsu.config.states', [
  'ui.router',
  'bns.core.appStateProvider',

  'bns.lsu.frontRecordsControllers',
  'bns.lsu.frontRecordControllers',
  'bns.lsu.backConfigControllers',
  'bns.lsu.backTemplatesControllers',
  'bns.lsu.backTemplateEditControllers',
  'bns.lsu.backRecordsControllers',
  'bns.lsu.printController',
])

  .config(LsuStatesConfig)

;

function LsuStatesConfig ($stateProvider, $urlRouterProvider, appStateProvider) {

  /* ------------------------------------------------------------------------ *\
   *    States
  \* ------------------------------------------------------------------------ */

  var rootState = appStateProvider.createRootState('lsu');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.lsu', rootState)

    // Front
    // ------------------------------

    .state('app.lsu.front', {
      abstract: true,
      templateUrl: 'views/lsu/front.html',
      onEnter: ['navbar', function (navbar) {
        angular.element('body').attr('data-mode', 'front');
        navbar.mode = 'front';
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
    })

    .state('app.lsu.front.records', {
      url: '', // default child state
      views: {
        content: {
          templateUrl: 'views/lsu/front/records-content.html',
          controller: 'LsuFrontRecordsContent',
          controllerAs: 'ctrl',
        },
      },
      onEnter: function () {console.log('enter records'); },
      onExit: function () {console.log('exit records'); },
    })

    .state('app.lsu.front.records.view', {
      url: '/records/{id:int}',
      resolve: {
        record: ['$stateParams', 'Restangular', function ($stateParams, Restangular) {
          return Restangular.one('lsu', $stateParams.id).get();
        }],
      },
      views: {
        'actionbar@app.lsu.front': {
          templateUrl: 'views/lsu/front/record-actionbar.html',
          controller: 'LsuFrontRecordActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/lsu/front/record-content.html',
          controller: 'LsuFrontRecordContent',
          controllerAs: 'ctrl',
        },
      },
    })

    // Back
    // ------------------------------

    .state('app.lsu.back', angular.extend(backState, {
      templateUrl: 'views/lsu/back.html',
    }))

    .state('app.lsu.back.templates', {
      url: '', // default back state
      views: {
        sidebar_templates: {
          templateUrl: 'views/lsu/back/templates-sidebar.html',
          controller: 'LsuBackTemplatesSidebar',
          controllerAs: 'ctrl',
        },
        actionbar: {
          templateUrl: 'views/lsu/back/templates-actionbar.html',
        },
        content: {
          templateUrl: 'views/lsu/back/templates-content.html',
          controller: 'LsuBackTemplatesContent',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.lsu.back.templates.edit', {
      url: '/templates/edit/:id',
      views: {
        'actionbar@app.lsu.back': {
          templateUrl: 'views/lsu/back/template-edit-actionbar.html',
          controller: 'LsuBackTemplateEditActionbar',
          controllerAs: 'ctrl'
        },
        'sidebar@app.lsu.back': {
          templateUrl: 'views/lsu/back/template-edit-sidebar.html',
        },
        'content@app.lsu.back': {
          templateUrl: 'views/lsu/back/template-edit-content.html',
          controller: 'LsuBackTemplateEditContent',
          controllerAs: 'ctrl'
        },
      },
    })

    .state('app.lsu.back.records', {
      url: '/records/{templateId:int}',
      views: {
        actionbar: {
          templateUrl: 'views/lsu/back/records-actionbar.html',
          controller: 'LsuBackRecordsActionbar',
          controllerAs: 'ctrl',
        },
        sidebar: {
          templateUrl: 'views/lsu/back/records-sidebar.html',
          controller: 'LsuBackRecordsSidebar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/lsu/back/records-content.html',
          controller: 'LsuBackRecordsContent',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.lsu.back.records.edit', {
      url: '/edit/{userId:int}',
      views: {
        content_edit: {
          templateUrl: 'views/lsu/back/record-edit-content.html',
          controller: 'LsuBackRecordEditContent',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.lsu.back.config', {
      url: '/config',
      views: {
        sidebar_config: {
          templateUrl: 'views/lsu/back/config-sidebar.html',
        },
        content: {
          templateUrl: 'views/lsu/back/config-content.html',
          controller: 'LsuBackConfigContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.lsu.print', {
      url: '/print?templateId&userIds&ids',
      controller: 'LsuPrint',
      controllerAs: 'ctrl',
      templateUrl: 'views/lsu/print.html',
    })

  ;

}

})(angular);
