$(document).ready(function ()
{
    //Disabled on ajax
    $("body").on({
        ajaxStart: function() { 
            $('.ajaxLoad').css('display', 'block');
            $(this).css('cursor','wait');
            return false;
        },
        ajaxStop: function() { 
            $('.ajaxLoad').css('display', 'none');
            $(this).css('cursor','auto');
            return false;
        }  
    });
    
    //Click bouton valider
    $('.btn.write-new-msg.btn-messaging.validate').on('click', function() {
        if(confirm('Voulez-vous réelement valider cet email et l\'envoyer ?'))
        {
            var link = Routing.generate('BNSAppMessagingBundle_backajax_validate_message', {'messageId' : $('.header-message').attr('id') });

            //Appel de la génération de formulaire
            $.ajax({
                url: link,	
                success:function(response){	
                    //On charge le résultat dans la div de présentation si OK	
                    $('.mailFolder.btn.actif').click();
                },
                error:function (xhr, ajaxOptions, thrownError){
                    //Alert en cas d'erreur
                    $('.content-message').show('slow');
                    alert('Une erreur est survenue lors de la validation de l\'email !');
                } 	
            });
        }
        return false;
    });
    
    //Click bouton refuser
    $('.btn.write-new-msg.btn-messaging.refuse').on('click', function() {
        
        if(confirm('Voulez-vous réelement refuser cet email et le supprimer ?'))
        {
            var link = Routing.generate('BNSAppMessagingBundle_backajax_refuse_message', {'messageId' : $('.header-message').attr('id') });   

            //Appel de la génération de formulaire
            $.ajax({
                url: link,	
                success:function(response){	
                    //On charge le résultat dans la div de présentation si OK	
                    $('.mailFolder.btn.actif').click();
                },
                error:function (xhr, ajaxOptions, thrownError){
                    //Alert en cas d'erreur
                    $('.content-message').show('slow');
                    alert('Une erreur est survenue lors du refus de validation de l\'email !');
                } 	
            });
        }
        return false;
    });
    
    //Click bouton menu
    $('.mailFolder.btn.actif').on('click', function() {
        var link = Routing.generate('BNSAppMessagingBundle_backajax_list_emails', {});
        
        //Appel de la génération de formulaire
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
                $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement de la liste des messages en attente de modération !');
            } 	
        });
        return false;
    });
    
    $('.mailFolder.btn.actif').click();
    
});

 