'use strict';

module.exports = function (grunt) {

  // Time how long tasks take. Can help when optimizing build times
  require('time-grunt')(grunt);

  // Load grunt tasks automatically
  require('load-grunt-tasks')(grunt);

  // keep in sync with those in config_app.yml
  // format is symfony-like: 'en', 'en_US', 'fr_FR', ...
  var locales = [
    'fr',
    'en',
    'en_GB',
    'en_US',
    'es',
    'es_AR',
  ];

  var APP_VERSION = grunt.option('APP_VERSION') || Date.now();
  grunt.option('APP_VERSION', APP_VERSION);

  var assetsFolder = 'web/assets-version';
  // get all previous asset folders and ignore last 3
  var previousAssets = grunt.file.expand(assetsFolder+'/*').sort();
  var i = Math.min(previousAssets.length, 3);
  while (i > 0) {
    previousAssets.pop();
    i--;
  }
  var APP_ASSETS_DIST = grunt.option('APP_ASSETS_DIST') || 'web/assets';

  // angular old app
  var oldAppAssetsFolder = 'web/angular/assets-version';
  // get all previous asset folders and ignore last 3
  var oldAppPreviousAssets = grunt.file.expand(oldAppAssetsFolder+'/*').sort();
  i = Math.min(oldAppPreviousAssets.length, 3);
  while (i > 0) {
    oldAppPreviousAssets.pop();
    i--;
  }



  var OLD_APP_ASSETS_DIST = grunt.option('OLD_APP_ASSETS_DIST') || 'web/angular/assets';

  var config = {
    bower: 'web/bower_components',
    node_modules: 'node_modules',

    httpPath: '/ent',

    // Sf and Angular
    shared: {
      dist: 'web',
      build: '.tmp/shared'
    },

    // Angular only
    angular: {
      app: 'web/angular/app',
      dist: OLD_APP_ASSETS_DIST,
      final: 'web/angular/assets',
      build: '.tmp/angular'
    },

    // New app
    material: {
      app: 'web/app',
      dist: APP_ASSETS_DIST,
      final: 'web/assets',
      build: '.tmp/material'
    },

    patterns: {
      less: '!(_)*.less',
      js: '*.js'
    },
    files: {
      less: {
        // -------- Styles Symfony --------
        // Workshop
        // 'web/css/workshop.css': ['web/medias/less/workshop/**/<%= config.patterns.less %>'],

        // -------- Styles Angular --------
        // Main (all non _ files)
        '<%= config.angular.dist %>/styles/main.css': [
          '<%= config.angular.app %>/styles/**/<%= config.patterns.less %>'
        ],
        // Workshop
        '<%= config.angular.dist %>/styles/workshop.css': [
          '<%= config.angular.app %>/styles/_workshop.less'
        ],
        // Media library
        '<%= config.angular.dist %>/styles/media-library.css': [
          '<%= config.angular.app %>/styles/_media-library.less'
        ],
        // Viewer
        '<%= config.angular.dist %>/styles/viewer.css': [
          '<%= config.angular.app %>/styles/_viewer.less'
            ],
      },
      css: {
        '<%= config.material.dist %>/styles/vendors.css': [
          '<%= config.bower %>/mediaelement/build/mediaelementplayer.css',
          '<%= config.bower %>/angular-tileview/dist/tileview.css',
          '<%= config.bower %>/fullcalendar/dist/fullcalendar.css',
          '<%= config.node_modules %>/mdPickers/dist/mdPickers.css',
          '<%= config.bower %>/slick-carousel/slick/slick.css',
          '<%= config.bower %>/slick-carousel/slick/slick-theme.css',
        ],
      },
      js: {
        // -------- Scripts Symfony --------
        // Workshop
        // '<%= config.shared.build %>/scripts/workshop/script.js': [
        //   '<%= config.bower %>/nanoscroller/bin/javascripts/jquery.nanoscroller.min.js',
        //   'web/medias/js/workshop/**/<%= config.patterns.js %>',
        // ],

        // Packed
        '<%= config.shared.build %>/scripts/scripts.js': [
          '<%= config.shared.build %>/scripts/parameters.js', // conf from sf parsed by grunt
          'web/medias/js/angular-bootstrap.js',
          'web/medias/js/angular-compat.js',
          'web/medias/js/angularizer.js',
          'web/medias/js/core-auth-catch.js',
          // Media library
          'web/medias/js/media-library/**/<%= config.patterns.js %>',
        ],
        '<%= config.shared.build %>/scripts/scripts-light.js': [
          '<%= config.shared.build %>/scripts/parameters.js', // conf from sf parsed by grunt
          'web/medias/js/angular-compat.js',
        ],

        // -------- Scripts Angular --------
        // Workshop
        '<%= config.angular.build %>/scripts/scripts.js': [
          '<%= config.angular.app %>/scripts/**/*-module.js',   // load modules before anything else
          '<%= config.angular.app %>/scripts/**/!(_)*.js'       // load other js that don't start with _
        ],

        // Compatibilities
        '<%= config.angular.build %>/scripts/ie8.js': [
          '<%= config.angular.app %>/scripts/_ie8.js'
        ],

        // -------- Scripts Material --------
        '<%= config.material.build %>/scripts/scripts.js': [
          '<%= config.material.app %>/scripts/**/*-module.js',   // load modules before anything else
          '<%= config.material.app %>/scripts/**/!(_)*.js'       // load other js that don't start with _
        ],
      },
      jsVendors: {
        // -------- Vendors de base --------
        '<%= config.shared.build %>/scripts/vendors.js': [
          // TODO: migrer tous les vendors du core ICI
          '<%= config.bower %>/es5-shim/es5-shim.js',
          '<%= config.bower %>/es5-shim/es5-sham.js',
          '<%= config.bower %>/jquery/jquery.js',
          '<%= config.bower %>/flipclock/compiled/flipclock.js',

          // TODO: suppr les composants inutiles
          '<%= config.bower %>/jquery-ui/ui/core.js',
          '<%= config.bower %>/jquery-ui/ui/widget.js',
          '<%= config.bower %>/jquery-ui/ui/mouse.js',
          '<%= config.bower %>/jquery-ui/ui/position.js',
          '<%= config.bower %>/jquery-ui/ui/draggable.js',
          '<%= config.bower %>/jquery-ui/ui/droppable.js',
          '<%= config.bower %>/jquery-ui/ui/resizable.js',
          '<%= config.bower %>/jquery-ui/ui/selectable.js',
          '<%= config.bower %>/jquery-ui/ui/sortable.js',
          '<%= config.bower %>/jquery-ui/ui/accordion.js',
          '<%= config.bower %>/jquery-ui/ui/autocomplete.js',
          '<%= config.bower %>/jquery-ui/ui/button.js',
          '<%= config.bower %>/jquery-ui/ui/datepicker.js',
          '<%= config.bower %>/jquery-ui/ui/dialog.js',
          '<%= config.bower %>/jquery-ui/ui/menu.js',
          '<%= config.bower %>/jquery-ui/ui/progressbar.js',
          '<%= config.bower %>/jquery-ui/ui/selectmenu.js',
          '<%= config.bower %>/jquery-ui/ui/slider.js',
          '<%= config.bower %>/jquery-ui/ui/spinner.js',
          '<%= config.bower %>/jquery-ui/ui/tabs.js',
          '<%= config.bower %>/jquery-ui/ui/tooltip.js',
          '<%= config.bower %>/jquery-ui/ui/effect.js',
          '<%= config.bower %>/jquery-ui/ui/effect-blind.js',
          '<%= config.bower %>/jquery-ui/ui/effect-bounce.js',
          '<%= config.bower %>/jquery-ui/ui/effect-clip.js',
          '<%= config.bower %>/jquery-ui/ui/effect-drop.js',
          '<%= config.bower %>/jquery-ui/ui/effect-explode.js',
          '<%= config.bower %>/jquery-ui/ui/effect-fade.js',
          '<%= config.bower %>/jquery-ui/ui/effect-fold.js',
          '<%= config.bower %>/jquery-ui/ui/effect-highlight.js',
          '<%= config.bower %>/jquery-ui/ui/effect-puff.js',
          '<%= config.bower %>/jquery-ui/ui/effect-pulsate.js',
          '<%= config.bower %>/jquery-ui/ui/effect-scale.js',
          '<%= config.bower %>/jquery-ui/ui/effect-shake.js',
          '<%= config.bower %>/jquery-ui/ui/effect-size.js',
          '<%= config.bower %>/jquery-ui/ui/effect-slide.js',
          '<%= config.bower %>/jquery-ui/ui/effect-transfer.js',

          '<%= config.bower %>/nanoscroller/bin/javascripts/jquery.nanoscroller.js',

          '<%= config.bower %>/sms-counter/sms_counter.js',

          // mediaelement
          '<%= config.bower %>/mediaelement/build/mediaelement-and-player.js'
        ],
        // -------- Vendors IE8 --------
        '<%= config.shared.build %>/scripts/vendors-ie8.js': [
          '<%= config.bower %>/html5shiv/dist/html5shiv.js',
        ],
        // -------- Scripts Angular --------
        '<%= config.angular.build %>/scripts/vendors.js': [
          // js libs used only by angular app
          '<%= config.bower %>/nanoscroller/bin/javascripts/jquery.nanoscroller.js',
          '<%= config.bower %>/socket.io-client/socket.io.js',
          '<%= config.bower %>/wavesurfer.js/src/wavesurfer.js',
          '<%= config.bower %>/wavesurfer.js/src/util.js',
          '<%= config.bower %>/wavesurfer.js/src/webaudio.js',
          '<%= config.bower %>/wavesurfer.js/src/mediaelement.js',
          '<%= config.bower %>/wavesurfer.js/src/drawer.js',
          '<%= config.bower %>/wavesurfer.js/src/drawer.*.js',
          // '<%= config.bower %>/jqueryui-touch-punch/jquery.ui.touch-punch.js',

          // actual angular vendors
          '<%= config.bower %>/angular-ui-sortable/sortable.js',
          '<%= config.bower %>/angular-nanoscroller/scrollable.js',
          '<%= config.bower %>/angular-dragdrop/src/angular-dragdrop.js',
          '<%= config.bower %>/angular-drag-and-drop-lists/angular-drag-and-drop-lists.js',
          '<%= config.bower %>/angular-loading-bar/build/loading-bar.js',
          '<%= config.bower %>/angular-notifier/dist/angular-notifier.js',
          '<%= config.bower %>/angular-modal/modal.js',
          '<%= config.bower %>/angular-file-upload/dist/angular-file-upload.min.js',
          '<%= config.bower %>/angular-socket-io/socket.js',
          '<%= config.bower %>/angular-tileview/dist/tileview.js',
          '<%= config.bower %>/dotjem-angular-tree/dotjem-angular-tree.js',
          '<%= config.bower %>/angular-awesome-slider/dist/angular-awesome-slider.min.js',
          '<%= config.bower %>/checklist-model/checklist-model.js',
          // TODO : lasy load lib
        ],
        // -------- Scripts Material light --------
        '<%= config.material.build %>/scripts/vendors-light.js': [
          '<%= config.bower %>/lodash/lodash.js',

          '<%= config.node_modules %>/angular/angular.js',
          '<%= config.node_modules %>/angular-animate/angular-animate.js',
          '<%= config.node_modules %>/angular-aria/angular-aria.js',
          '<%= config.node_modules %>/angular-cookies/angular-cookies.js',
          '<%= config.node_modules %>/angular-sanitize/angular-sanitize.js',

          // material
          '<%= config.node_modules %>/angular-material/angular-material.js',
          '<%= config.node_modules %>/angular-messages/angular-messages.js',

          // // translations
          '<%= config.node_modules %>/angular-translate/dist/angular-translate.js',
          '<%= config.node_modules %>/messageformat/messageformat.js',
          '<%= config.node_modules %>/angular-translate-interpolation-messageformat/angular-translate-interpolation-messageformat.js',
          '<%= config.node_modules %>/angular-translate-loader-static-files/angular-translate-loader-static-files.js',
        ],
        // -------- Scripts Material --------
        '<%= config.material.build %>/scripts/vendors.js': [
          '<%= config.bower %>/lodash/lodash.js',

          '<%= config.node_modules %>/angular/angular.js',
          '<%= config.node_modules %>/angular-animate/angular-animate.js',
          '<%= config.node_modules %>/angular-aria/angular-aria.js',
          '<%= config.node_modules %>/angular-cookies/angular-cookies.js',
          '<%= config.node_modules %>/angular-sanitize/angular-sanitize.js',
          '<%= config.bower %>/angular-filter/dist/angular-filter.js',

          // material
          '<%= config.node_modules %>/angular-material/angular-material.js',
          '<%= config.node_modules %>/angular-messages/angular-messages.js',
          '<%= config.node_modules %>/mdPickers/dist/mdPickers.js',

          // routing
          '<%= config.node_modules %>/@uirouter/core/_bundles/ui-router-core.js',
          '<%= config.node_modules %>/@uirouter/sticky-states/_bundles/ui-router-sticky-states.js',
          '<%= config.node_modules %>/@uirouter/angularjs/release/ui-router-angularjs.js',
          '<%= config.node_modules %>/@uirouter/angularjs/release/stateEvents.js',

          // translations
          '<%= config.node_modules %>/angular-translate/dist/angular-translate.js',
          '<%= config.node_modules %>/messageformat/messageformat.js',
          '<%= config.node_modules %>/angular-translate-interpolation-messageformat/angular-translate-interpolation-messageformat.js',
          '<%= config.node_modules %>/angular-translate-loader-static-files/angular-translate-loader-static-files.js',

          // dates
          '<%= config.bower %>/moment/moment.js',
          '<%= config.bower %>/angular-moment/angular-moment.js',

          // editor
          '<%= config.bower %>/angular-ui-tinymce/src/tinymce.js',

          // drag&drop
          '<%= config.bower %>/Sortable/Sortable.js',
          // ng-sortable is copied in app core

          // misc
          '<%= config.node_modules %>/oclazyload/dist/ocLazyLoad.js', // We use a fork because of issue with reloading NG
          '<%= config.bower %>/angular-scroll/angular-scroll.js',
          '<%= config.bower %>/restangular/dist/restangular.js',
          '<%= config.bower %>/angular-uuids/angular-uuid.js',
          '<%= config.bower %>/angularLocalStorage/src/angularLocalStorage.js',
          '<%= config.bower %>/ngInfiniteScroll/build/ng-infinite-scroll.js',
          '<%= config.node_modules %>/libphonenumber-js/bundle/libphonenumber-js.min.js',

          // highchart Lazyloaded in statistic vendors
          //'<%= config.bower %>/highcharts-ng/dist/highcharts-ng.js',
          // Lazyloaded in bns-highchart directive
          //'<%= config.bower %>/highcharts/highcharts.src.js',
          //'<%= config.bower %>/highcharts/modules/exporting.src.js',
          //'<%= config.bower %>/highcharts/modules/offline-exporting.src.js',

          // Lazyloaded in statistic module with oc.LazyLoad
          //'<%= config.bower %>/angular-ui-grid/ui-grid.js',
          '<%= config.bower %>/angular-timer/dist/angular-timer.js',
          '<%= config.bower %>/humanize-duration/humanize-duration.js',
          '<%= config.bower %>/angular-uuids/angular-uuid.js',
          '<%= config.bower %>/slick-carousel/slick/slick.js',
          '<%= config.bower %>/angular-slick/dist/slick.js',
        ],
        // -------- Scripts Material Shell --------
        '<%= config.material.build %>/scripts/vendors-ng1.js': [
          '<%= config.bower %>/lodash/lodash.js',

          '<%= config.bower %>/angular-filter/dist/angular-filter.js',

          // dates
          '<%= config.bower %>/moment/moment.js',
          '<%= config.bower %>/angular-moment/angular-moment.js',

          // editor
          '<%= config.bower %>/angular-ui-tinymce/src/tinymce.js',

          // drag&drop
          '<%= config.bower %>/Sortable/Sortable.js',
          // ng-sortable is copied in app core

          // misc
          // '<%= config.bower %>/oclazyload/dist/ocLazyLoad.js', // We use a fork because of issue with reloading NG
          '<%= config.bower %>/angular-scroll/angular-scroll.js',
          '<%= config.bower %>/restangular/dist/restangular.js',
          '<%= config.bower %>/angular-uuids/angular-uuid.js',
          '<%= config.bower %>/angularLocalStorage/src/angularLocalStorage.js',
          '<%= config.bower %>/ngInfiniteScroll/build/ng-infinite-scroll.js',
          '<%= config.node_modules %>/libphonenumber-js/bundle/libphonenumber-js.min.js',

          '<%= config.bower %>/angular-timer/dist/angular-timer.js',
          '<%= config.bower %>/humanize-duration/humanize-duration.js',
          '<%= config.bower %>/angular-uuids/angular-uuid.js',
          '<%= config.bower %>/slick-carousel/slick/slick.js',
          '<%= config.bower %>/angular-slick/dist/slick.js',
        ],
        // -------- Scripts Calendar --------
        '<%= config.material.build %>/modules/calendar.vendors.js': [
          '<%= config.bower %>/angular-ui-calendar/src/calendar.js',
          '<%= config.bower %>/fullcalendar/dist/fullcalendar.js',
        ],
        // -------- Scripts Statistic --------
        '<%= config.material.build %>/modules/statistic.vendors.js': [
          '<%= config.bower %>/highcharts-ng/dist/highcharts-ng.js',
        ],
      }
    }
  };

  var localePlaceholder = '__LOCALE__';
  var localVendors = {
    '<%= config.material.build %>/scripts/vendors-__LOCALE__.js' : [
      '<%= config.node_modules %>/angular-i18n/angular-locale_'+localePlaceholder+'.js',
      '<%= config.bower %>/moment/locale/'+localePlaceholder+'.js',
      '<%= config.bower %>/jquery-ui/ui/i18n/datepicker-'+localePlaceholder+'.js',
      '<%= config.bower %>/mediaelement/build/lang/'+localePlaceholder+'.js',
    ],
    '<%= config.material.build %>/modules/calendar.vendors-__LOCALE__.js' : [
      '<%= config.bower %>/fullcalendar/dist/lang/'+localePlaceholder+'.js',
    ],
  };

  locales.forEach(function (locale) {
    var cases = [
      locale,                                   // en_US
      locale.replace('_', '-'),                 // en-US
      locale.toLowerCase().replace('_', '-'),   // en-us
      locale.split('_')[0],                     // en
    ];

    Object.keys(localVendors).forEach(function (key) {
      var vendors = [];

      localVendors[key].forEach(function (tpl) {
        var template = grunt.template.process(tpl, {data: {config: config}});
        // try each locale case variant, use the first found
        for (var i = 0; i < cases.length; i++) {
          var path = template.replace(localePlaceholder, cases[i]);
          if (grunt.file.exists(path)) {
            vendors.push(path);
            break;
          //} else if (i === cases.length - 1) {
          //  // no match found :(
          //  grunt.fail.warn('Locale "'+locale+'" has no vendor file "'+template+'"');
          }
        }
      });

      config.files.jsVendors[key.replace(localePlaceholder, locale)] = vendors;
    });
  });

  grunt.initConfig({

    // Project settings
    config: config,

    less: {
      server: {
        options: {
          strictUnits: true,
          sourceMapRootpath: '',
          sourceMap: true
        },
        files: '<%= config.files.less %>'
      },
      dist: {
        options: {
          strictUnits: true,
          sourceMap: false,
          cleancss: true,
          compress: true
        },
        files: '<%= config.files.less %>'
      }
    },

    // Empties folders to start fresh
    clean: {
      dist: {
        files: [{
          dot: true,
          src: [
            '.tmp',
            '<%= config.angular.dist %>/*',
            '!<%= config.angular.dist %>/.git*',
            '<%= config.material.dist %>/*',
            '!<%= config.material.dist %>/.git*'
          ]
        }]
      },
      previous: {
        files: [{
          src: previousAssets,
        }, {
          src: oldAppPreviousAssets,
        }]
      },
      server: {
        files: [{
          src: [
            '.tmp',
            '<%= config.angular.dist %>',
            '<%= config.material.dist %>',
          ],
        }]
      }
    },

    // compile grunt templates
    'template': {
      // compile config file from symfony dir and move it to build folder
      parameters: {
        files: {
          '<%= config.shared.build %>/scripts/parameters.js': 'app/config/parameters.js',
        },
      },
    },

    // Compiles Sass to CSS and generates necessary files if requested
    sass: {
      files: [{
        expand: true,
        cwd: '<%= config.material.app %>/styles',
        src: ['*.scss'],
        dest: '<%= config.material.build %>/styles',
        ext: '.css',
      }],
      options: {
        includePaths: ['<%= config.bower %>'],
      },
      dist: {
        files: '<%= sass.files %>'
      },
      server: {
        options: {
          sourceMap: true,
        },
        files: '<%= sass.files %>'
      },
    },

    // Add vendor prefixed styles
    autoprefixer: {
      options: {
        browsers: ['last 2 version', '> 1%']
      },
      server: {
        options: {
          map: true,
        },
        files: [{
          expand: true,
          cwd: '<%= config.material.build %>/styles/',
          src: '{,*/}*.css',
          dest: '<%= config.material.build %>/styles/'
        }, {
          expand: true,
          cwd: '<%= config.angular.dist %>/styles/',
          src: '{,*/}*.css',
          dest: '<%= config.angular.dist %>/styles/'
        }]
      },
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.material.build %>/styles/',
          src: '{,*/}*.css',
          dest: '<%= config.material.build %>/styles/'
        }, {
          expand: true,
          cwd: '<%= config.angular.dist %>/styles/',
          src: '{,*/}*.css',
          dest: '<%= config.angular.dist %>/styles/'
        }]
      }
    },

    asset_cachebuster: {
      options: {
        buster: APP_VERSION,
      },
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.material.build %>/styles/',
          src: '{,*/}*.css',
          dest: '<%= config.material.build %>/styles/'
        }]
      }
    },

    cssmin: {
      dist: {
        options: {
          advanced: false,
          aggressiveMerging: false,
        },
        files: [
          // target files already in dest manually
          {
            '<%= config.material.dist %>/styles/vendors.css': [
              '<%= config.material.dist %>/styles/vendors.css',
            ],
          },
          // compile any css in build
          {
            expand: true,
            cwd: '<%= config.material.build %>/styles',
            src: '*.css',
            dest: '<%= config.material.dist %>/styles',
          }
        ]
      }
    },

    imagemin: {
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.material.app %>/images',
          src: '**/*.{png,jpg,jpeg,gif}',
          dest: '<%= config.material.dist %>/images'
        }]
      }
    },

    svgmin: {
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.material.app %>/images',
          src: '**/*.svg',
          dest: '<%= config.material.dist %>/images'
        }]
      }
    },

    sprite: {
      apps40: {
        src: '<%= config.material.app %>/images/apps/*/icon-40*.png',
        retinaSrcFilter: ['<%= config.material.app %>/images/apps/*/icon-40@2x.png'],
        dest: '<%= config.material.dist %>/images/apps/sprite-40.png',
        retinaDest: '<%= config.material.dist %>/images/apps/sprite-40@2x.png',
        destCss: '<%= config.material.app %>/styles/sprites/_apps-40.scss',
        imgPath: '../images/apps/sprite-40.png', // in css
        retinaImgPath: '../images/apps/sprite-40@2x.png', // in css retina
        cssOpts: {
          functions: true,
        },
        cssVarMap: function (sprite) {
          // capture app name from folder
          var match = /\/apps\/(.*)\//.exec(sprite.source_image);
          sprite.name = 'sprite-' + match[1] + sprite.name.replace('icon-40', '');
        },
      },
      icons32: {
        src: '<%= config.material.app %>/images/icons/*-{32,64}.png',
        retinaSrcFilter: ['<%= config.material.app %>/images/icons/*-64.png'],
        dest: '<%= config.material.dist %>/images/icons/sprite-32.png',
        retinaDest: '<%= config.material.dist %>/images/icons/sprite-64.png',
        destCss: '<%= config.material.app %>/styles/sprites/_icons-32.scss',
        imgPath: '../images/icons/sprite-32.png', // in css
        retinaImgPath: '../images/icons/sprite-64.png', // in css retina
        cssOpts: {
          functions: false,
        },
        cssVarMap: function (sprite) {
          sprite.name = sprite.name.replace('-32', '');
        },
      },
      icons64: {
        src: '<%= config.material.app %>/images/icons/*-{64,128}.png',
        retinaSrcFilter: ['<%= config.material.app %>/images/icons/*-128.png'],
        dest: '<%= config.material.dist %>/images/icons/sprite-64.png',
        retinaDest: '<%= config.material.dist %>/images/icons/sprite-128.png',
        destCss: '<%= config.material.app %>/styles/sprites/_icons-64.scss',
        imgPath: '../images/icons/sprite-64.png', // in css
        retinaImgPath: '../images/icons/sprite-128.png', // in css retina
        cssOpts: {
          functions: false,
        },
        cssVarMap: function (sprite) {
          sprite.name = sprite.name.replace('-64', '');
        },
      },
      messaging: {
        src: '<%= config.material.app %>/images/apps/messaging/icons/*.png',
        retinaSrcFilter: ['<%= config.material.app %>/images/apps/messaging/icons/*@2x.png'],
        dest: '<%= config.material.dist %>/images/apps/messaging/sprite-icons.png',
        retinaDest: '<%= config.material.dist %>/images/apps/messaging/sprite-icons@2x.png',
        destCss: '<%= config.material.app %>/styles/sprites/_messaging.scss',
        imgPath: '../images/apps/messaging/sprite-icons.png', // in css
        retinaImgPath: '../images/apps/messaging/sprite-icons@2x.png', // in css retina
        cssOpts: {
          functions: false,
        },
        cssVarMap: function (sprite) {
          sprite.name = 'messaging-' + sprite.name;
        },
      },
      food: {
        src: '<%= config.material.app %>/images/apps/breakfast-tour/food/*.png',
        retinaSrcFilter: ['<%= config.material.app %>/images/apps/breakfast-tour/food/*@2x.png'],
        dest: '<%= config.material.dist %>/images/apps/breakfast-tour/sprite-food.png',
        retinaDest: '<%= config.material.dist %>/images/apps/breakfast-tour/sprite-food@2x.png',
        destCss: '<%= config.material.app %>/styles/sprites/_food.scss',
        imgPath: '../images/apps/breakfast-tour/sprite-food.png', // in css
        retinaImgPath: '../images/apps/breakfast-tour/sprite-food@2x.png', // in css
        cssOpts: {
          functions: false,
        },
      },
    },

    // Make sure code styles are up to par and there are no obvious mistakes
    jshint: {
      options: {
        jshintrc: '.jshintrc',
        reporter: require('jshint-stylish')
      },
      all: {
        src: [
          'Gruntfile.js',
          '<%= config.material.app %>/scripts/**/*.js'
        ]
      },
      // test: {
      //   options: {
      //     jshintrc: 'test/.jshintrc'
      //   },
      //   src: ['test/spec/{,*/}*.js']
      // }
    },

    // Concat src files
    concat: {
      options: {
        separator: ';\n',
      },
      distcss: {
        options: {
          separator: '\n',
        },
        files: [
          '<%= config.files.css%>',
        ]
      },
      dist: {
        files: [
          '<%= config.files.js %>',
          '<%= config.files.jsVendors %>'
        ]
      },
      modules: {
        files: [], // populated by 'modules' task
      },
      serverScripts: {
        files: '<%= config.files.js %>'
      },
      serverVendors: {
        files: '<%= config.files.jsVendors %>'
      }
    },

    ngtemplates: {
      options: {
        htmlmin: {
          collapseBooleanAttributes:      false,
          collapseWhitespace:             true,
          // removeAttributeQuotes:          true,
          removeComments:                 true,
          removeEmptyAttributes:          true,
          removeRedundantAttributes:      true,
          removeScriptTypeAttributes:     true,
          removeStyleLinkTypeAttributes:  true
        },
      },
      material: {
        cwd: '<%= config.material.app %>',
        src: 'views/**/*.html',
        dest: '<%= config.material.build %>/scripts/views.js',
        options: {
          module: 'bns.core.views',
          // prefix: '/ent/app/',
        },
      },
      'material-light': {
        cwd: '<%= config.material.app %>',
        src: [
          'views/components/dialog/bns-dialog.html',
          'views/components/toast/bns-toast.html',
          'views/main/directives/bns-auto-login-box.html',
          'views/components/keyboard/keyboard.html',
          'views/components/keyboard/keyboard-inputs.html'
        ],
        dest: '<%= config.material.build %>/scripts/views-light.js',
        options: {
          module: 'bns.core.viewsLight',
        },
      }
      // module_* subtasks populated by 'modules' task
    },

    // Allow the use of non-minsafe AngularJS files. Automatically makes it
    // minsafe compatible so Uglify does not destroy the ng references
    ngAnnotate: {
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.angular.build %>/scripts',
          src: '*.js',
          dest: '<%= config.angular.build %>/scripts'
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/scripts',
          src: '*.js',
          dest: '<%= config.material.build %>/scripts'
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/modules',
          src: '*.js',
          dest: '<%= config.material.build %>/modules'
        }]
      }
    },

    // Minifies files from build to dist.
    uglify: {
      dist: {
        files: [{
          expand: true,
          cwd: '<%= config.shared.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.shared.dist %>/js'
        }, {
          expand: true,
          cwd: '<%= config.angular.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.angular.dist %>/scripts'
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.material.dist %>/scripts',
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/modules',
          src: '**/*.js',
          dest: '<%= config.material.dist %>/modules'
        }]
      }
    },

    // Simply copies files from build to dist.
    copy: {
      scripts: {
        files: [{
          expand: true,
          cwd: '<%= config.shared.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.shared.dist %>/js'
        }, {
          expand: true,
          cwd: '<%= config.angular.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.angular.dist %>/scripts'
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/scripts',
          src: '**/*.js',
          dest: '<%= config.material.dist %>/scripts'
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/modules',
          src: '**/*.js',
          dest: '<%= config.material.dist %>/modules'
        }]
      },
      styles: {
        files: [{
          expand: true,
          cwd: '<%= config.material.build %>/styles',
          src: '**/*.css',
          dest: '<%= config.material.dist %>/styles'
        },{
          expand: true,
          cwd: '<%= config.bower %>/mediaelement/build',
          src: 'mejs-controls.{svg,png}',
          dest: '<%= config.material.dist %>/styles'
        }]
      },
      images: {
        files: [{
          expand: true,
          cwd: '<%= config.material.app %>/images',
          dest: '<%= config.material.dist %>/images',
          src: '**/*.{png,jpg,jpeg,gif,svg,webp}'
        }]
      },
      dist: {
        files: [{
          expand: true,
          dot: true,
          cwd: '<%= config.material.app %>',
          dest: '<%= config.material.dist %>',
          src: [
            '*.{ico,png,txt}',
            // 'views/{,*/}*.html',
            'images/{,*/}*.{webp}',
            'fonts/{,*/}*.*'
          ]
        }, {
          expand: true,
          cwd: '<%= config.material.build %>/images',
          dest: '<%= config.material.dist %>/images',
          src: ['generated/*']
        },{
          expand: true,
          cwd: '<%= config.bower %>/mediaelement/build',
          src: 'mejs-controls.{svg,png}',
          dest: '<%= config.material.dist %>/styles'
        }]
      },
    },

    exec: {
      starter_kit: 'php app/console bns:starter-kit:generate',
    },

    watch: {
      less: {
        files: [
          'web/medias/less/**/*.less',
          '<%= config.angular.app %>/styles/**/*.less'
        ],
        tasks: ['less:server', 'autoprefixer']
      },
      sass: {
        files: ['<%= config.material.app %>/styles/**/*.{scss,sass}'],
        tasks: ['sass:server', 'autoprefixer', 'copy:styles']
      },
      images: {
        files: ['<%= config.material.app %>/images/**/*.{png,jpg,jpeg,gif,svg}'],
        tasks: ['copy:images']
      },
      parameters: {
        files: [
          'app/config/parameters.js',
        ],
        tasks: ['template:parameters', 'concat:serverScripts', 'copy:scripts'],
      },
      javascript: {
        files: [
          'web/medias/js/angular-bootstrap.js',
          '<%= config.angular.app %>/scripts/**/*.js',
          '<%= config.material.app %>/scripts/**/*.js',
          'web/medias/js/*/src/{,*/}*.js'
        ],
        tasks: ['concat:serverScripts', 'copy:scripts']
      },
      javascriptmodules: {
        files: ['<%= config.material.app %>/modules/**/*.js'],
        tasks: ['modules', 'concat:modules', 'copy:scripts'],
      },
      ngtemplates: {
        files: ['<%= config.material.app %>/views/**/*.html'],
        tasks: ['ngtemplates', 'copy:scripts']
      },
      ngtemplatesmodules: {
        files: ['<%= config.material.app %>/modules/**/*.html'],
        tasks: ['modules', 'ngtemplates', 'copy:scripts'],
      },
      gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['concurrent:server']
      },
      livereload: {
        options: {
          livereload: '<%= connect.options.livereload %>'
        },
        files: [
          '<%= config.angular.app %>/views/**/*.html',
          '<%= config.angular.dist %>/scripts/*.js',
        ]
      },
      livereloadStyles: {
        options: {
          livereload: '<%= connect.options.livereload %>'
        },
        files: [
          '<%= config.angular.dist %>/styles/*.css',
          '<%= config.material.dist %>/styles/*.css',
          // Désactive livereload sur le dossier entier = gros gain de perf'
          // 'web/medias/css/{,*/}*.css',
          // 'web/medias/images/{,*/}*.{png,jpg,jpeg,gif,webp,svg}'
        ]
      },
      starter_kit: {
        files: ['src/BNS/App/*/Resources/starter_kit/*.yml'],
        tasks: ['exec:starter_kit']
      }
    },

    connect: {
      options: {
        port: 9000,
        // open: true,
        hostname: 'localhost',
        livereload: 35729
      },
      livereload: {
        options: {
          base: ['assets', 'app']
        }
      }
    },

    // Run some tasks in parallel to speed up the build process
    concurrent: {
      server: [
        'less:server',
        'sass:server',
      ],
      dist: [
        'less:dist',
        'sass:dist',
        'svgmin'
      ]
    },

    // symlink dist with version number to final dist folder
    symlink: {
      options: {
        overwrite: true,
        force: true
      },
      expanded: {
        files: [
          {
            src: ['<%= config.material.dist %>'],
            dest: '<%= config.material.final %>'
          },
          {
            src: ['<%= config.angular.dist %>'],
            dest: '<%= config.angular.final %>'
          }
        ]
      },
    }

  });

  var noImagemin = grunt.option('no-imagemin');
  if (!noImagemin) {
    // imagemin is not disabled, add it to the concurrent tasks
    var tasks = grunt.config.get('concurrent.dist');
    tasks.push('imagemin');
    grunt.config.set('concurrent.dist', tasks);
  }

  grunt.registerTask('modules', 'Prepares AngularJS BNS apps', function () {
    grunt.file.expand(
      grunt.template.process('<%= config.material.app %>/modules/*')
    ).forEach(function (dir) {
      // get the module name from the directory name
      var name = dir.substr(dir.lastIndexOf('/')+1);
      var camelName = name.replace(/(\-\w)/g, function (match) {
        return match[1].toUpperCase();
      });
      var taskName = 'module_' + name;

      var ngtemplates = grunt.config.get('ngtemplates') || {};
      ngtemplates[taskName] = {
        cwd: dir,
        src: '**/*.html',
        dest: '<%= config.material.build %>/modules/' + name + '.views.js',
        options: {
          module: 'bns.' + camelName,
          prefix: name + '/',
        },
      };

      grunt.config.set('ngtemplates', ngtemplates);

      // get the current concat object from initConfig
      var concat = grunt.config.get('concat') || {};

      // create a subtask for each module, find all src files
      // and combine into a single js file per module
      concat.modules.files.push({
        expand: true,
        cwd: dir,
        src: [
          '**/*module.js',
          '**/*.js',
        ],
        dest: '<%= config.material.build %>/modules/' + name + '.scripts.js',

        // hack to make cwd + expand work in grunt-contrib-concat
        // https://github.com/gruntjs/grunt-contrib-concat/issues/31
        rename: function (dest) { return dest; },
      });

      // add module subtasks to the concat task in initConfig
      grunt.config.set('concat', concat);

      console.log('Configured module', name);
    });
  });

  grunt.registerTask('serve', function (target) {
    if (target === 'dist') {
      return grunt.task.run(['build']);
    }

    grunt.task.run([
      'clean:server',
      'modules',
      'template',
      'newer:jshint:all',
      'sprite',
      'concurrent:server',
      'ngtemplates',
      'concat',
      'autoprefixer:server',
      'asset_cachebuster',
      'copy',
      'connect:livereload',
      'watch'
    ]);
  });

  var buildTasks = [
    'clean:dist',
    'modules',
    'template',
    'sprite'
  ];

  var noConcurrent = grunt.option('no-concurrent');
  if (!noConcurrent) {
    buildTasks.push('concurrent:dist');
  } else {
    var concurrentTasks = grunt.config.get('concurrent.dist');
    for (var t in concurrentTasks) {
      buildTasks.push(concurrentTasks[t]);
    }
  }
  buildTasks.push('ngtemplates');
  buildTasks.push('concat');
  buildTasks.push('ngAnnotate');
  buildTasks.push('autoprefixer');
  buildTasks.push('asset_cachebuster');
  buildTasks.push('cssmin');
  buildTasks.push('copy:dist');
  buildTasks.push('uglify');

  if (noImagemin) {
    // imagemin is disabled, simply copy images
    buildTasks.push('copy:images');
  }
  buildTasks.push('symlink');
  buildTasks.push('clean:previous');

  grunt.registerTask('build', function () {
    // build assets into a folder with version number
    APP_ASSETS_DIST = assetsFolder+'/'+APP_VERSION;
    grunt.config.set('config.material.dist', APP_ASSETS_DIST);
    grunt.option('APP_ASSETS_DIST', APP_ASSETS_DIST);

    // Old angular app : build assets into a folder with version number
    OLD_APP_ASSETS_DIST = oldAppAssetsFolder+'/'+APP_VERSION;
    grunt.config.set('config.angular.dist', OLD_APP_ASSETS_DIST);
    grunt.option('OLD_APP_ASSETS_DIST', OLD_APP_ASSETS_DIST);

    grunt.task.run(buildTasks);
  });

  grunt.registerTask('default', [
    'newer:jshint',
    'build'
  ]);
};
