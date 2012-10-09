$(function(){ 
	
	//Fiche groupe
	$('.group-attribute').on('click','.edit-btn',function(){
		var id = $(this).parent().attr('id');
		var params = id.split('-');
		var group_id = params[1];
		var attribute_unique_name = params[2];
		
		$.post(
			Routing.generate('BNSAppAdminBundle_group_attribute_form', {}),
			{ 'attribute_unique_name': attribute_unique_name , 'group_id': group_id}, 
			function complete(data)
			{
				$('#' + id).html(data);
			}
		);		
		
	});
	
	//Fiche groupe
	$('.group-attribute').on('click','.group-attribute-form-submit',function(){
		var id = $(this).parent().attr('id');
		var params = id.split('-');
		var group_id = params[1];
		var attribute_unique_name = params[2];
		var value = $('#group-attribute-form-' + group_id + '-' + attribute_unique_name).val();
		
		$.post(
			Routing.generate('BNSAppAdminBundle_group_attribute_form', {}),
			{ 'attribute_unique_name': attribute_unique_name , 'group_id': group_id , 'value': value}, 
			function complete(data)
			{
				$('#' + id).html(data);
			}
		);		
	});
	
	$('#admin_group_list_select').change(function(){
		var route = Routing.generate('BNSAppAdminBundle_group', { "type": $(this).val() });
		window.location = route;
	});
	
	$('.rule-toggle').live('click',function(event){
		event.preventDefault();
		$(this).find('img').attr('src',"/medias/images/icons/loading.gif");
		var id = $(this).attr('id');
		$.ajax(
		{
			url: $(this).attr('href'),
			success:function(data){
				$('#' + id).replaceWith(data);
			}
		});
	});
	
	$('.group-parent-edit').live('click',function(event){
		event.preventDefault();
		$('.group-parent-form').toggle('blink');
	});
	
	$('#rank-add-permission').change(function(){
		if($(this).val() != "")
			$('#rank-add-permission-button').show();
		else
			$('#rank-add-permission-button').hide();
	});
	
});