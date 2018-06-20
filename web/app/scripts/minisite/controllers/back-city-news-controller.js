(function (angular) {
'use strict';

angular.module('bns.minisite.back.cityNewsController', [])

  .controller('MinisiteBackCityNews', MinisiteBackCityNewsController)

;

function MinisiteBackCityNewsController (_, moment, $scope, $translate) {

  var formName = 'mini_site_page_city_news_form'; // hardcoded value from twig
  var publishedStatusChoice;
  var now = moment();

  // wait for form to be ready and get a hold on the published status choice
  $scope.$watchCollection('app._choices["'+formName+'[status]"]', function (choices) {
    if (!publishedStatusChoice) {
      publishedStatusChoice = _.find(choices, {value: 'PUBLISHED'});
    }
  });

  // update choice label when publication dates change
  $scope.$watch(formName + '.published_at', updatePublishedStatusName);
  $scope.$watch(formName + '.published_end_at', updatePublishedStatusName);

  function updatePublishedStatusName () {
    if (!(publishedStatusChoice && $scope[formName])) {
      return;
    }

    var label;
    var status;

    if (now.isBefore($scope[formName].published_at, 'day')) {
      label = 'PUBLISHED_FUTURE';
      status = 'FINISHED';
    } else if (now.isAfter($scope[formName].published_end_at, 'day')) {
      label = 'PUBLISHED_PAST';
      status = 'SCHEDULED';
    } else {
      label = 'PUBLISHED';
      status = 'PUBLISHED';
    }

    publishedStatusChoice.label = $translate.instant('MINISITE.' + label);
    publishedStatusChoice.status = status;
  }

}

})(angular);
