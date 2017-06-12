'use strict';

angular.module('bns.core.apiCodes', [])

  .constant('ApiCodes', {
    ERROR_NOT_ENOUGH_SPACE_USER: 'ERROR_NOT_ENOUGH_SPACE_USER',
    ERROR_NOT_ENOUGH_SPACE_GROUP: 'ERROR_NOT_ENOUGH_SPACE_GROUP',
    ERROR_FILE_IS_TOO_LARGE: 'ERROR_FILE_IS_TOO_LARGE',
    ERROR_NO_ALLOWED_SPACE: 'ERROR_NO_ALLOWED_SPACE',
  });
