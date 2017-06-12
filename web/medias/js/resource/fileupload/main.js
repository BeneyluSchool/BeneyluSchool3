var timeoutSuccess = null;

$(function () {
    'use strict';


    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload();

    // Enable iframe cross-domain access via redirect option:
    $('#fileupload').fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );

	$('#fileupload').fileupload('option', {
		url : Routing.generate('BNSAppResourceBundle_add_files_submit'),
		maxFileSize: 50 * 1024 * 1024, //50Mo
		process: [
			{
				action: 'load',
				maxFileSize: 50 * 1024 * 1024 // 50MB
			},
			/*{
				action: 'resize',
				maxWidth: 1440,
				maxHeight: 900
			},*/
			{
				action: 'save'
			}
		]
	});
	// Upload server status check for browsers with CORS support:
	/*if ($.support.cors) {
		$.ajax({
			url : Routing.generate('BNSAppResourceBundle_add_files_submit'),
			type: 'HEAD'
		}).fail(function () {
			$('<span class="alert alert-error"/>')
				.text('Le serveur est temporairement innaccessible' +
						new Date())
				.appendTo('#fileupload');
		});
	}*/
	
	$('#fileupload').bind('fileuploadsubmit', function (e, data) {
		//On cache la barre de submit
		$('.fileupload-buttonbar').hide();
		//$('.cancel-upload:not(.error)').hide();
		
		//On assigne la destination à tous les fichiers
		$(".resource-label").val($('.destination-choice').attr('id'));
		
		//On met à jour les liens de redirection
		var params = $(".resource-label").val().split('_');
		var label_type = params[0];
		var label_id = params[2];
		$('.uploaded-files').css('margin-bottom','0px');
		var inputs = data.context.find(':input');
		if (inputs.filter('[required][value=""]').first().focus().length) {
			return false;
		}
		data.formData = inputs.serializeArray();
	});

	$('#fileupload').bind('fileuploaddone', function (e, data) {
		//$('.temp-file:not(.error)').remove();
		clearTimeout(timeoutSuccess);
		timeoutSuccess = setTimeout(function () {
			var still = $('.uploaded-files').find(".template-upload");
			if(still.length == 0){
				$('.cancel-upload').show();
				$('#upload-completed').find('#completed-return').attr('href', Routing.generate('resource_navigate', {'slug': data.result.label_slug}));
				$('#upload-completed').show();
				//$('#fileupload').hide();
			}
		}, 500);
	});

	$('#completed-continue').on("click", function(event){
		//On raffiche la barre de submit
		$('.fileupload-buttonbar').show();
		$('#upload-completed').hide();
		$('#fileupload').show();
		$('.temp-file').remove();
		checkLoadButton();
	});
	
	//On revérifie si on annule des fichiers
	$(".cancel-upload").live("click", function(event){
		setTimeout("checkLoadButton()",250); 
	});
	//ON vérifie à l'ajout de fichiers			
	$('#fileupload').bind('fileuploadadded', function (e, data) {
		$('.uploaded-files').css('margin-bottom','200px');
		setTimeout(function(){checkLoadButton();},250); 
		$('#informations').hide();
	});
});

//Gère l'activation / la désactivation du bouton "Charger les fichiers"
function checkLoadButton()
{ 
	if(	$('.template-upload').length > 0 && 
		$('.destination-choice').attr('id') != null && 
		$('.destination-choice').attr('id') != "" && 
		$('.error-upload').length == 0
	){
		$('#load-button-disabled').hide();
		$('#load-button').show();
	}else{
		$('#load-button-disabled').show();
		$('#load-button').hide();
	}
	if($('.template-upload').length == 0){
		$('.fileupload-buttonbar').show();
	}
}