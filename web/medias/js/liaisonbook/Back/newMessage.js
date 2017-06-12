var $form;
$(document).ready(function ()
{
    jQuery(function($){$.datepicker.setDefaults($.datepicker.regional['fr']);});
    $( ".jq-date" ).datepicker({});
    
	$('.btn.return').show();
	$('.btn.finish').show();
	//Submit
	$('.btn.finish').click(function(){
		$newMessageForm = $('#form_new_message');
		$newMessageForm.submit();
		return false;
	});

	$form = $('.content-form-scroll');
	onResize();
	$(window).resize(function() { onResize(); });

});

function onResize(){
	$form.css('height', window.innerHeight - 267 + 'px');
}