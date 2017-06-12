'use strict';

angular.module('bns.core.collectionHelpers', [])

  /**
   * @ngdoc service
   * @name bns.core.collectionHelpers
   * @kind function
   *
   * @description
   * Collection of helper functions for collections manipulations
   *
   * ** Methods **
   * - `aggregate(collection, what, how)`: Parses the collection and aggregates
   *        values, recognized by the `what` property path.
   *        Any two values are compared by using the `how` property path. It can
   *        be omitted if values retrieved by `what` are scalars.
   *        Examples:
   *        - `aggregate(myCollection, 'path.to.related.object', 'path.to.related.object.id')`
   *          will produce a collection of 'related object' indexed by their id.
   *        - `aggregate(myPersons, 'age')` will collect the different 'age'
   *          values for the myPersons collection.
   */
  .factory('collectionHelpers', function ($parse) {

    // dash-case to camelCase
    function aggregate (collection, what, how) {
      var aggregated = {};
      var objGetter = $parse(what);
      var idGetter = how ? $parse(how) : objGetter;

      angular.forEach(collection, function (item) {
        // get identifier for current item's group
        var groupId = idGetter(item);

        // if not already known: store it
        if (!aggregated[groupId]) {
          aggregated[groupId] = objGetter(item);
        }
      });

      return aggregated;
    }


    // public API
    return {
      aggregate: aggregate,
    };
  });
