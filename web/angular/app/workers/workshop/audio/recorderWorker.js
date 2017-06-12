this.onmessage = function( e ){
  var worker = this;
  switch( e.data.command ){

    case 'recordBuffers':
      worker.recorder.recordBuffers( e.data.buffers );
      break;

    case 'requestData':
      if ( worker.recorder.encodeFinalFrame ) {
        worker.recorder.encodeFinalFrame();
      }
      if ( !worker.recordOpus.stream ) {
        var data = worker.recorder.requestData();
        var wav;
        if (worker.recorder.keepWav && worker.recorder.wavepcm) {
          wav = worker.recorder.wavepcm.requestData();
        }
        worker.postMessage( {
          format: worker.recordOpus ? 'ogg' : 'wav',
          data: data,
          wav: wav,
        }, [data.buffer] );
      }
      break;

    case 'stop':
      worker.close();
      break;

    case 'start':
      worker.recordOpus = e.data.recordOpus;
      if ( worker.recordOpus ) {
        importScripts( 'oggopus.js' );
        if ( worker.recordOpus.stream ) {
          e.data.onPageComplete = function( page ){ worker.postMessage( {
            format: 'ogg',
            data: page,
          }, [page.buffer] ); };
        }
        worker.recorder = new OggOpus( e.data );
      }
      else {
        importScripts( 'wavepcm.js' );
        worker.recorder = new WavePCM( e.data );
      }
      break;
  }
};
