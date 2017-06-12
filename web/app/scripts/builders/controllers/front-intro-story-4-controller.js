(function (angular) {
'use strict';

angular.module('bns.builders.frontIntroStory4Controller', [])

  .controller('BuildersFrontIntroStory4', BuildersFrontIntroStory4Controller)

;

function BuildersFrontIntroStory4Controller (Restangular, toast) {

  var intro = this;
  intro.sendContact = sendContact;
  intro.contact = {};
  intro.busy = false;

  function sendContact () {
    if (intro.busy) {
      return;
    }
    intro.busy = true;

    return Restangular.all('builders').all('contact').post(intro.contact)
      .then(function success () {
        toast.success('BUILDERS.FLASH_SEND_CONTACT_SUCCESS');
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_SEND_CONTACT_ERROR');
        throw response;
      })
      .finally(function end () {
        intro.busy = false;
      })
    ;
  }

}

})(angular);
