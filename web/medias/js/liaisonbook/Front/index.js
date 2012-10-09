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
            var link = Routing.generate('BNSAppLiaisonBookBundle_front_sign', { 'liaisonBookId': idNew });

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
    $('.liaisonbook-messages-min-height').height($(window).height() - $('.liaisonbook-messages-min-height').offset().top - parseInt($('.liaisonbook-messages-min-height').css('paddingBottom')) - parseInt($('.liaisonbook-messages-min-height').css('marginBottom')) - parseInt($('.liaisonbook-messages-min-height').css('paddingTop')) - 150 + 'px');
}