'use strict';

angular.module('bns.realtime.socket', [
  'btford.socket-io',
  'bns.core.parameters',
  'bns.realtime.tokens',
])

  .factory('socket', function ($timeout, io, _, socketFactory, parameters, Tokens) {
    var socket = socketFactory({
      ioSocket: io.connect(parameters.realtime_socket_addr, {
        secure: true,
      }),
    });

    // make socket errors available across the app
    // see https://github.com/socketio/socket.io-client#events
    socket.forward([
      'error',
      'connect_error',
      'reconnect_error',
      'reconnect',
    ]);

    socket.join = join;
    socket.leave = leave;
    socket.introduce = introduce;

    return socket;


    /* ---------------------------------------------------------------------- *\
     *    API
    \* ---------------------------------------------------------------------- */

    /**
     * Joins the given channel, via an access token
     *
     * @param {String} channel
     * @param {Function} callback an optional callback, on success
     */
    function join (channel, callback) {
      callback = callback || angular.noop;

      // ask a fresh token from the API
      Tokens.one(channel).get()
        .then(function success (data) {
          // try to join with this token
          joinWithToken(channel, data.token, callback);
        })
        .catch(function error (response) {
          console.error(response);
        })
      ;
    }

    /**
     * Leaves the given channel.
     *
     * @param {String} channel
     * @param {Function} callback an optional callback, on success
     */
    function leave (channel, callback) {
      callback = callback || angular.noop;

      socket.emit('leave_request', { room: channel }, serverCallback);

      function serverCallback (error, data) {
        if (error) {
          console.error('WebSocket error:', error);
        } else {
          console.info('Left channel:', channel, data);
          callback();
        }
      }
    }

    /**
     * Presents the given user to the socket.
     *
     * @param {Object} user
     * @param {Function} callback an optional callback, on success
     */
    function introduce (user, callback) {
      callback = callback || angular.noop;

      socket.emit('introduction', user.id, serverCallback);

      function serverCallback (error) {
        if (error) {
          console.error('WebSocket error:', error);
        } else {
          console.info('Introduced user to socket');
          callback();
        }
      }
    }


    /* ---------------------------------------------------------------------- *\
     *    Internals
    \* ---------------------------------------------------------------------- */

    /**
     * Asks for joining a channel, corresponding to the given token
     *
     * @param  {String} token A JWS string
     */
    function joinWithToken (channel, token, callback) {
      // initial request
      askForJoin();

      function askForJoin () {
        socket.emit('token_join', { token: token }, serverCallback);

        function serverCallback (error, data) {
          if (error) {
            console.error('WebSocket error:', error);

            // try again, from start
            $timeout(function () {
              socket.join(channel);
            }, 2000);
          } else {
            console.info('Joined channel:', channel, data);
            callback();

            // handle reconnect
            if (!socket.hasJoinReconnect) {
              socket.hasJoinReconnect = true;
              socket.on('reconnect', function () {
                askForJoin();
              });
            }
          }
        }
      }
    }
  })

;
