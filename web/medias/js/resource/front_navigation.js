$(function ()
{
	// TODO a checker
	//Bouton d'édition d'une ressource
	$('.resource-edit').live('click',function(){
		var id = $(this).attr('id');
		var params = id.split('-');
		var resource_id = params[2];
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_navigation_content_resource_edit', { resource_id: resource_id }),
			success: function(data)
			{
				$('#resource-current').html(data);
			}
		});
	});

	$('.cancel-edit').live('click',function(){
		$.post(
			Routing.generate('resource_navigate_file'),
			{resource_id: $(this).attr('data-resource-id') },
			function complete(data)
			{
				$("#resource-navigation").hide();
				$("#resource-current").html(data);
				$(".container-current-file").show();
			}
		)
	});

	//Suppression d'un label à une resource
	$('.label.delete').live('click',function(){
		var params = $(this).parent('.label-text').attr('id').split('_');
		var type = params[0];
		var label_id = params[2];
		$.post(
			Routing.generate('BNSAppResourceBundle_front_navigation_content_resource_delete_label', {}),
			{ 'resource_id' : $('#resource_id').val() , 'label_id' : label_id, 'type': type },
			function complete(data)
			{
				$(".labels-list").html(data);
			}
		);
	});

	//////////////////    Ajout de label dans la sidebar   \\\\\\\\\\\\\\\\\\\\\\\\\

	//Clic sur le submit
	$('#resourceAddLabel').on('click',"#resource-sidebar-new-label-submit",function(){
		$('#resource-sidebar-new-label-submit').addClass('disabled');
		var value = $('#resource-sidebar-new-label-input').val();
		$('#resourceAddLabel').find('.add-label-form').hide();
		$('#resourceAddLabel').find('.loader').show();
		$.post(
			Routing.generate('BNSAppResourceBundle_label_add_front_submit', {}),
			{value: value},
			function complete(data)
			{
				$('#resource-sidebar-new-label-input').val('');
				$("#resource-sidebar").replaceWith(data);
				$('#resourceAddLabel').modal('hide');
				$('#resourceAddLabel').find('.add-label-form').show();
				$('#resourceAddLabel').find('.loader').hide();
				$('#resource-sidebar-new-label-submit').removeClass('disabled');
				adjustHeight();
			}
		);
	});


	/////////////////////////    Suppression \\\\\\\\\\\\\\\\\\\\\\\\\\\

	$('.resource-selection-delete').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_delete_alert', {}),
			success: function(data)
			{
				$('#resource-selection-delete-content').html(data);
			}
		});
	});

	$('#resource-delete-preview-cancel').live('click',function(){
		$('#resourceSelectionDelete').modal('hide');
	});


	$('#resource-garbage-empty-confirm').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_garbage_empty', {}),
			success: function(data)
			{
				$('#resourceGarbageEmpty').modal('hide');
				$("#resource-navigation").html(data);
			}
		});
	});
});