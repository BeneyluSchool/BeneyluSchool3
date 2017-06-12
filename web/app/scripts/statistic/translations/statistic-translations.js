'use strict';
// TODO remove migrate files to xliff
angular.module('bns.statistic.translations', [
  'pascalprecht.translate',
])

  .config(function (/* $translateProvider */) {
  //  $translateProvider.translations('en', {
  //    STATISTIC: {
  //      ADD: 'Add',
  //      SAVE: 'Save',
  //      REQUIRED: 'Required',
  //      MY_WORKSPACES: 'My workspaces',
  //      MY_SELECTION: 'My selection',
  //      WORKSPACES: 'Workspaces',
  //      PUPILS: 'Pupils',
  //      PARENTS: 'Parents',
  //      TEACHERS: 'Teachers',
  //      DIRECTORS: 'Directors',
  //      PERIOD_LAST_30_DAY: 'Last 30 days',
  //      PERIOD_LAST_MONTH: 'Last month',
  //      PERIOD_CURRENT_WEEK: 'Current week',
  //      PERIOD_CURRENT_SCHOOL_YEAR: 'Current school year',
  //      STATISTICS: 'My Statistics',
  //      ACTIVATED_SCHOOL: 'Activated School',
  //      ACTIVATED_CLASSROOM: 'Activated classrooms',
  //      CLASSROOM_NUMBER: 'Number of classrooms',
  //      PUPILS_NUMBER: 'Number of pupils',
  //    },
  //  });
  //
  //  $translateProvider.translations('fr', {
  //    STATISTIC: {
  //      ADD: 'Ajouter',
  //      SAVE: 'Sauvegarder',
  //      REQUIRED: 'Obligatoire',
  //      MY_WORKSPACES: 'Mes espaces de travail',
  //      MY_SELECTION: 'Ma sélection',
  //      WORKSPACES: 'Espaces de travail',
  //      PUPILS: 'Elèves',
  //      PARENTS: 'Parents',
  //      TEACHERS: 'Enseignants',
  //      DIRECTORS: 'Directeurs',
  //      PERIOD_LAST_30_DAY: 'Les 30 derniers jours',
  //      PERIOD_LAST_MONTH: 'Le mois dernier',
  //      PERIOD_CURRENT_WEEK: 'La semaine en cours',
  //      PERIOD_CURRENT_SCHOOL_YEAR: 'L\'année scolaire en cours',
  //      STATISTICS: 'Mes Statistiques',
  //      ACTIVATED_SCHOOL: 'Ecoles inscrites',
  //      ACTIVATED_CLASSROOM: 'Classes inscrites',
  //      CLASSROOM_NUMBER: 'Classes potentielles',
  //      PUPILS_NUMBER: 'Elèves inscrits',
  //    },
  //  });

    // Highcharts translations
    //Highcharts.setOptions({
    //  lang: {
    //    loading: 'Chargement...',
    //    months: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
    //    weekdays: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
    //    shortMonths: ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'aoû', 'sep', 'oct', 'nov', 'déc'],
    //    exportButtonTitle: 'Exporter',
    //    printButtonTitle: 'Imprimer',
    //    rangeSelectorFrom: 'Du',
    //    rangeSelectorTo: 'au',
    //    rangeSelectorZoom: 'Période',
    //    downloadPNG: 'Télécharger en PNG',
    //    downloadJPEG: 'Télécharger en JPEG',
    //    downloadPDF: 'Télécharger en PDF',
    //    downloadSVG: 'Télécharger en SVG',
    //    resetZoom: 'Réinitialiser le zoom',
    //    resetZoomTitle: 'Réinitialiser le zoom',
    //    thousandsSep: ' ',
    //    decimalPoint: ','
    //  }
    //});
  });
