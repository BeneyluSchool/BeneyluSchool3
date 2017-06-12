(function (angular) {
'use strict';

angular.module('bns.twoDegrees.wordsControllers', [
  'bns.twoDegrees.state',
])

  .controller('TwoDegreesWords', TwoDegreesWordsController)
  .controller('TwoDegreesWord', TwoDegreesWordController)

;

function TwoDegreesWordsController (_, $stateParams, $state, unaccentFilter, words) {

  var ctrl = this;
  ctrl.words = words;

  init();

  function init () {
    // group words by their first letter. Letters order is not guaranteed
    var unordered = _.groupBy(ctrl.words, function (word) {
      return unaccentFilter(word.title[0]);
    });

    // sort keys
    var ordered = {};
    Object.keys(unordered).sort().forEach(function(key) {
      // inside same key (ie. letter), sort words
      ordered[key] = _.sortBy(unordered[key], function (word) {
        return unaccentFilter(word.title);
      });
    });

    ctrl.sortedWords = ordered;

    // redirect to first available word, on first load
    if ($state.current.name.indexOf('detail') === -1) {
      var firstLetter = _.first(Object.keys(ctrl.sortedWords));
      if (firstLetter) {
        var firstWord = _.first(ctrl.sortedWords[firstLetter]);
        $state.go('.detail', {code: firstWord.code});
      }
    }
  }

}

function TwoDegreesWordController (arrayUtils, twoDegreesState, word) {

  var ctrl = this;
  ctrl.word = word;

  init();

  function init () {
    // remove word from list of unread
    arrayUtils.remove(twoDegreesState.unreadWords, word.code);
  }

}

})(angular);
