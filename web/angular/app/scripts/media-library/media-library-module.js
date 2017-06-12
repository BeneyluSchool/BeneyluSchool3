'use strict';

angular.module('bns.mediaLibrary', [
  'bns.core',
  'bns.core.treeUtils',
  'bns.core.collectionMap',
  'bns.main.statistic',
  'bns.viewer.bnsViewerMedia',
  'bns.mediaLibrary.api',
  'bns.mediaLibrary.dev',   // TODO: remove this
  'bns.mediaLibrary.restangular',
  'bns.mediaLibrary.scene',
  'bns.mediaLibrary.uploader',
  'bns.mediaLibrary.navigationTree',
  'bns.mediaLibrary.manager',
  'bns.mediaLibrary.selectionManager',
  'bns.mediaLibrary.config',
  'bns.mediaLibrary.viewer',
  'bns.mediaLibrary.foldersFilter',
  'bns.mediaLibrary.filesFilter',
  'bns.mediaLibrary.isFileFilter',
  'bns.mediaLibrary.mediaTypeFilters',
  'bns.mediaLibrary.mediaPreview',
  'bns.mediaLibrary.mediaIcon',
  'bns.mediaLibrary.favoriteFlag',
  'bns.mediaLibrary.privateFlag',
  'bns.mediaLibrary.share',
  'angularFileUpload',
  'ui.router',
  'ct.ui.router.extras.sticky',
  'ct.ui.router.extras.dsr',
  'bns.main.navbar',
])

  .config(function ($stateProvider) {
    var addNamespace = ['navbar', function (navbar) {
      angular.element('body').addClass('media-library');
      navbar.setApp('MEDIA_LIBRARY');
    }];

    var removeNamespace = function () {
      angular.element('body').removeClass('media-library');
    };

    $stateProvider
      // Boot state: redirects to the correct state based on global config
      // at runtime.
      .state('app.mediaLibraryBoot', {
        url: '/media-library/boot',
        template: '<div ui-view></div>',
        controller: function MediaLibraryBootCtrl ($stateParams, mediaLibraryConfig, $state, statistic) {
          if ('view' === mediaLibraryConfig.mode) {
            $state.go('app.mediaLibrary.base.media', { id: mediaLibraryConfig.mediaId });
            return;
          }
          if (angular.isUndefined(mediaLibraryConfig.mode)) {
            statistic.visit('media_library');
          }

          $state.go('app.mediaLibrary.base.folders.details');
        },
      })

      .state('app.mediaLibrary', {
        url: '/media-library',
        template: '<bns-starter-kit-progress></bns-starter-kit-progress><div ui-view="root" class="media-library-root"></div>',
        sticky: false,
        dsr: true,
        onEnter: addNamespace,
        onExit: removeNamespace,
        onInactivate: removeNamespace,
        onReactivate: addNamespace,
      })

      .state('app.mediaLibrary.base', {
        abstract: true,
        resolve: {
          starterKit: ['starterKit', function (starterKit) {
            return starterKit.boot('MEDIA_LIBRARY');
          }],
        },
        views: {
          root: {
            templateUrl: '/ent/angular/app/views/media-library/base.html',
            controller: 'MediaLibraryBaseCtrl',
          }
        }
      })

      .state('app.mediaLibrary.base.folders', {
        abstract: true,
        views: {
          'topbar': {
            templateUrl: '/ent/angular/app/views/media-library/topbar/topbar.html',
            controller: 'MediaLibraryTopbarCtrl',
          },
          'navigation': {
            templateUrl: '/ent/angular/app/views/media-library/navigation/navigation.html',
            controller: 'MediaLibraryNavigationCtrl',
          },
          'selection': {
            templateUrl: '/ent/angular/app/views/media-library/selection/selection.html',
            controller: 'MediaLibrarySelectionCtrl',
          },
          'upload': {
            templateUrl: '/ent/angular/app/views/media-library/upload/upload.html',
            controller: 'MediaLibraryUploadCtrl',
          },
        }
      })

      .state('app.mediaLibrary.base.folders.help', {
        url: '/help',
        views: {
          'scene@app.mediaLibrary.base': {
            templateUrl: '/ent/angular/app/views/media-library/scene/help.html',
            controller: 'MediaLibraryHelpSceneCtrl',
          },
        }
      })

      .state('app.mediaLibrary.base.folders.details', {
        url: '/dossiers/{slug}',
        views: {
          'scene@app.mediaLibrary.base': {
            templateUrl: '/ent/angular/app/views/media-library/scene/scene.html',
            controller: 'MediaLibrarySceneCtrl',
          },
        }
      })

      .state('app.mediaLibrary.base.media', {
        url: '/medias/{id}',
        views: {
          viewer: {
            templateUrl: '/ent/angular/app/views/media-library/viewer/base.html',
            controller: 'MediaLibraryViewerCtrl'
          }
        }
      })
    ;

  })

;
