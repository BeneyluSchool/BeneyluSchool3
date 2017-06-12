$(function(){ 

	//Ajout de libellé
	$('#add-label-submit').live('click',function(){
		var parent_id = $('#label_input_parent_id').val();
		$("#add-label-form").hide('blind');
		$.post(Routing.generate('BNSAppResourceBundle_label_save', {}),
			{
				label: $('#label-input-label').val(), 
				parent_id: parent_id
			},			
			function complete(data)
			{
				$('#label-input-label').val("");
				$("#add-label-button").removeClass('disabled');
				params = parent_id.split('_');
				var type = params[0];
				var entity_id = params[1];
				$('#' + type + '_' + entity_id + '_list').html(data);
			}
		)
	});
	
	$("#add-label-button").click(function(){
		$('.notice-message').remove();
		$("#add-label-button").addClass('disabled');
		$("#add-label-form").show('blind');
		$('#add-label-cancel').show();
		$('#choice').hide();
		$('#add-label-submit').hide();
	});
	
	$("#add-label-cancel").click(function(){
		$("#add-label-form").hide('blind');
		$("#add-label-button").removeClass('disabled');
		$('#add-label-cancel').hide();
		$('#add-label-submit').hide();
		$('#choice').hide();
		$('#label_input_label').val("");
		$('#label_input_parent_id').val("");
	});



	//Affichage du formulaire d'édition des libellés
	$('.resource-category-row > .bordered > .crud > .edit-button').live("click", function(event){
		$('.resource-category-row > .bordered > .form').hide();
		$('.resource-category-row > .bordered > .title').show();
		
		var id = $(this).attr('id');
		var params = id.split('-');
		
		var type = params[0];
		var label_id = params[1];
	
		$('#' + type + '-' + label_id + '-form').show();
		$('#' + type + '-' + label_id + '-label').hide();
	});
	
	//Annulation de l'édition
	$('.resource-category-row > .bordered > .form > .cancel').live("click", function(event){
		$('.resource-category-row > .bordered > .form').hide();
		$('.resource-category-row > .bordered > .title').show();
	});
	
	//Envoi de l'édition
	$('.resource-category-row > .bordered > .form > .submit').live("click", function(event){
		var id = $(this).attr('id');
		var params = id.split('-');
		var type = params[0];
		var label_id = params[1];
		var label = $('#' + type + '-' + label_id + '-input').val();
		
		$.post(
			Routing.generate('BNSAppResourceBundle_label_edit', {}),
			{ 'label_id': label_id , 'type': type , 'label': label}, 
			function complete(data)
			{
				$('#' + type + '-' + label_id + '-label').html(data);
				$('#' + type + '-' + label_id + '-input').html(data);
				$('.resource-category-row > .bordered > .form').hide();
				$('.resource-category-row > .bordered > .title').show();
				
			}
		);
	});
	
	//Affichage de la notice de suppression
	$('.resource-category-row > .bordered > .crud > .delete-button').live("click", function(event){
		
		var id = $(this).attr('id');
		var params = id.split('-');
		
		var type = params[0];
		var label_id = params[1];
	
		$('#' + type + '-' + label_id + '-delete').show();
		$('#' + type + '-' + label_id + '-label').hide();
	});
	
	//Annulation de la suppression
	$('.resource-category-row > .bordered > .delete > .cancel').live("click", function(event){
		$('.resource-category-row > .bordered > .delete').hide();
		$('.resource-category-row > .bordered > .title').show();
	});
	
	//Envoi de la suppression
	$('.resource-category-row > .bordered > .delete > .submit').live("click", function(event){
		var id = $(this).attr('id');
		var params = id.split('-');
		var type = params[0];
		var label_id = params[1];
		
		$.post(
			Routing.generate('BNSAppResourceBundle_label_delete', {}),
			{ 'label_id': label_id , 'type': type}, 
			function complete(data)
			{
				$('#' + type + '-' + label_id).remove();
			}
		);
	});
	

	
});