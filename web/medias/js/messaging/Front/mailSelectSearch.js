$(document).ready(function ()
{
    
    $('.btn.read-mail').on('click', function() {
        
        //Droit de revenir sur la boite de reception, d'écrire'
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.inbox').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');
        
        keys = $(this).attr('id').split('-');
        
        msgId = $.trim(keys[0]);
        folderFunctionalName = $.trim(keys[1]);
        $('.mailFolder').removeClass('actif');
        $('.mailFolder').addClass('no-actif');
        
        $('#'+folderFunctionalName+'').removeClass('no-actif');
        $('#'+folderFunctionalName+'').addClass('actif');
        
        //Changer le dossier actif
        $('.content-message').hide('slow');
        
        link = Routing.generate('BNSAppMessagingBundle_frontajax_message', {'messageId' : msgId, 'folderFunctionalName': folderFunctionalName});
        
        //Appel de la génération de template
        $.ajax({
            url: link,	
            success:function(response){	
                //On charge le résultat dans la div de présentation si OK	
                $('.content-message').html(response);
                $('.content-message').show('slow');
                //Droit de répondre si tout est ok
                $('.btn.write-new-msg.btn-messaging.delete').removeAttr('disabled');
                //Droit de répondre si c'est un message reçu
                if($('.mailFolder.actif').attr('id') != 'SF_DRAFT' 
                    && $('.mailFolder.actif').attr('id') != 'SF_TRASH' 
                        && $('.mailFolder.actif').attr('id') != 'SF_OUTBOX')
                {
                    $('.btn.write-new-msg.btn-messaging.answer').removeAttr('disabled');
                }
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors du chargement des messages !');
            } 	
        });
        
        return false;
    });
    
    
    $('.next-page').on('click', function() {
        
        //Droit d'écrire 
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');

        $('.content-message').hide('slow');

        var page = parseInt($('#currentPage').html());
        
        $query = $(this).attr('id');

        //Génération du lien
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_search_emails', {'query': query , 'page' : (page + 1)});

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
        
        //Droit d'écrire 
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');

        $('.content-message').hide('slow');

        var page = parseInt($('#currentPage').html());

        $query = $(this).attr('id');

        //Génération du lien
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_search_emails', {'query': query , 'page' : (page - 1)});

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

 