$(function(){

	$('#input-destination-choice').val($('.destination-choice').attr('id'));

	//Soumission du formulaire si bouton actif
	$('#load-button').click(function(event) {
		event.preventDefault();
		$('.layer.submit').show();
		$('#results').hide();
		$('#validation-buttons').hide();
		$('#urls-form').submit();
	});

	//Ajout d'un lien / vidéo
	$('#add-url-form').submit(function(){
		
		$('#informations').hide();
		$('.layer.loading').show();

		$.post(Routing.generate('BNSAppResourceBundle_add_url_submit'),
		{
			url: $('#add-url-form-url').val()
		},			
		function complete(data)
		{
						
		})
	});
	//au clic sur Annuler au supprimer l'élement du dom
	$('#resource-navigation').on('click', '.reset-url', function() {
		$(this).parent().parent().hide('blind', function() {
			$(this).remove();
			checkLoadButton();
		});

		return false;
	});
	
	//Dans la modal
	
	$('#completed-continue').click(function() {
		$('#add-links').trigger('click');
	});
	
	$('#resource-add-url-first-step-form').ajaxForm({
		beforeSubmit: function(arr, $form, options){ 
			$('#informations').hide();
			$('.layer.loading').show();
			$("#resourceAddUrl").modal('hide'); 
		},
		success: function(data){
			$('#results').html(data + $('#results').html());
			$('#results').show();
			$('.add-url-input').each(function(index) {
				$(this).next('.add-url-less').remove();
				if(index > 0){	
					$(this).remove();
				}else{
					$(this).val('');
					$(this).removeClass('refused');
					$(this).removeClass('validated');
				}
			});
			
			$('.layer.loading').hide();
			checkLoadButton();
		}
	});
	
	$('#urls-form').ajaxForm({
		target: '#resource-navigation'
	});
	
	$('#add-url-add').click(function(){
		$(this).before($('#add-url-less').clone().show());
		$('#add-url-add').before($('#add-url-input').clone().removeClass('refused').removeClass('validated').val(''));
		if($('.add-url-input').length > 4 && parseFloat($('#resourceAddUrl').css('top')) > 40){
			$('#resourceAddUrl').css('top',parseFloat($('#resourceAddUrl').css('top')) - '3' + '%');
		}
	});
	
	$('.add-url-less').click(function() {
		$(this).prev('input').remove();
		$(this).remove();
	});
	
	$('.add-url-input').change(function() {
		if(ValidateWebAddress($(this).val())){
			$(this).removeClass('refused');
			$(this).addClass('validated');
		}else{
			$(this).removeClass('validated');
			$(this).addClass('refused');
		}
		if($(this).val() == ""){
			$(this).removeClass('refused');
			$(this).removeClass('validated');
		}
		
	});
	
	$('#resource-add-url-first-step-submit').click(function(){
		$('#resource-add-url-first-step-form').submit();
	});
	
	//Fin modal
});

function ValidateWebAddress(url) {
	var webSiteUrlExp = /^(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?$/;
	if (webSiteUrlExp.test(url)) {
		return true;
	}
	else {
		return false;
	}
}