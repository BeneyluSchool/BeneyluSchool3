$(document).ready(function ()
{
        //Ajax signature
        $('.btn-sign').click(function ()
        {
            //Balise en cours
            var current = $(this);
            var idNew = $(this).attr('id');

            //On désactive le bouton pour le moment
            current.addClass('disabled');

            //Généré avec fosJSRouting
            var link = Routing.generate('BNSAppNoteBookBundle_front_sign', { 'noteBookId': idNew });

            $.ajax({
                url: link,
				dataType: 'json',
                success:function(response) {
                    if (response == true) {
						current.hide();
						current.parent().find('button.validate').show();
					}
                },
                error:function (xhr, ajaxOptions, thrownError) {
                    //On affiche à nouveau le bouton en cas d'erreur
                    current.show();
                } 
            });

            return false;
        });

        //Ajustement de la hauteur
        adjustHeight();

        $(window).resize(function() {
            adjustHeight();
        });

});
//Ajustement de la hauteur
function adjustHeight()
{
	var adhe = $(window).height() - $('.notebook-messages-min-height').offset().top;
	
	var pb = parseInt($('.notebook-messages-min-height').css('paddingBottom'));
	if(!isNaN(pb)){
		adhe = adhe - pb;
	}
	
	var mb = parseInt($('.notebook-messages-min-height').css('marginBottom'));
	if(!isNaN(mb)){
		adhe = adhe - mb;
	}
	
	var pt = parseInt($('.notebook-messages-min-height').css('paddingTop'));
	if(!isNaN(pt)){
		adhe = adhe - pt;
	}
	
	adhe = adhe - 150;
	
    $('.notebook-messages-min-height').height(adhe + 'px');
}