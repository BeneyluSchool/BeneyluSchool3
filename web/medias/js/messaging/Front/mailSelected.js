$(document).ready(function ()
{
  
    //Clic sur le bouton d'édition de brouillon
    $('.btn.edit-draft').on('click', function() {
        msgId = $(this).attr('id');
        
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.inbox').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.send').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');
        
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_edit_message', {'msgId' : msgId});
        
        //Appel de la génération de formulaire
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
                $('.btn.write-new-msg.btn-messaging.send').removeAttr('disabled');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors de la création d\'un nouveau message !');
            } 	
        });
        return false;
    });
    
    //Clic sur le bouton d'édition de brouillon
    $('.btn.write-new-msg.add-user.get-attachment').on('click', function() {
        
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_get_attachment');
        msgId = $(this).parent().children('form').children('.msgId').attr('value');
        folder = $(this).parent().children('form').children('.folder').attr('value');
        
        $('.content-message').hide('slow');
        
        //Appel de la génération de formulaire
        $.ajax({
            type: 'POST',
            url: link,	
            data: $(this).parent().children('form').serialize(),
            success:function(response){	
                //On recharge le template pour afficher la resource téléchargée
                var redirectLink = Routing.generate('BNSAppMessagingBundle_frontajax_message', {'messageId' : msgId, 'folderFunctionalName': folder});
                redirectAjax(redirectLink);
                
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement de la pièce jointe ! (Peut être ce type de fichier n\'est pas pris en compte ?');
            } 	
        });
        return false;
    });
    
    function redirectAjax(redirectLink)
    {
        //Recharger la div ajax avec le lien passé
        $.ajax({
            url: redirectLink,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du rechargement de la page !');
            } 	
        });
        return false;
    }
    
    
});

 