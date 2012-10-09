
$(function(){ 

	//Fermeture des ressources
	$('.close-resource-iframe').live('click',function(){
		$('#resource-iframe',window.parent.document).hide(1000, function () {
			$('#resource-iframe',window.parent.document).remove();
		});
	});
});