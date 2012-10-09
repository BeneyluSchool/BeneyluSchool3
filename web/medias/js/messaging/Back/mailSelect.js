$(document).ready(function ()
{
    
    $('.btn.read-mail').on('click', function() {
        
        msgId = $(this).attr('id');
        
        $('.content-message').hide('slow');
        
        link = Routing.generate('BNSAppMessagingBundle_backajax_message', {'messageId' : msgId});
        
        //Appel de la génération de template
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
                $('.btn.write-new-msg.btn-messaging').removeAttr('disabled');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement du message !');
            } 	
        });
        
        return false;
    });
    
    $('.next-page').on('click', function() {
        
        $('.content-message').hide('slow');

        var page = parseInt($('#currentPage').html());

        //Génération du lien
        var link = Routing.generate('BNSAppMessagingBundle_backajax_list_emails', {'page' : (page + 1)});

        //Appel de la génération de template
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement des messages !');
            } 	
        });
        return false;
    });
    
    $('.previous-page').on('click', function() {
        
        $('.content-message').hide('slow');

        var page = parseInt($('#currentPage').html());

        //Génération du lien
        var link = Routing.generate('BNSAppMessagingBundle_backajax_list_emails', {'page' : (page - 1)});

        //Appel de la génération de template
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement des messages !');
            } 	
        });
        return false;
    });

});

 