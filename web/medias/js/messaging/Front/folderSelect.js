$(document).ready(function ()
{
    
    $('.mailFolder').on('click', function() {
        
        //Droit d'écrire 
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');

        $('.content-message').hide('slow');

        //Génération du lien
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_list_emails', {'folderFunctionalName': $(this).attr('id') , 'page' : 1});

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


        $('.mailFolder').removeClass('actif');
        $('.mailFolder').addClass('no-actif');
        $(this).removeClass('no-actif');
        $(this).addClass('actif');
        
        return false;
        
    });
    
    //Chargement au tout départ sur la page
    baseBtn = $('#SF_INBOX');
    baseBtn.click();
    
    
});

 