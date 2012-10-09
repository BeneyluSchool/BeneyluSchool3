$(document).ready(function ()
{
    $('.ajaxLoad').css('display', 'block');
    $(this).css('cursor','wait');
    
    //Clic sur le dossier correspondant à la boite de reception
    $('.btn.write-new-msg.btn-messaging.inbox').on('click', function() {
            $('.mailFolder.actif').click();
            return false;
    });
 
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
    
    //Formulaire nouveau message
    $('.btn.write-new-msg.btn-messaging.write').on('click', function() {
        
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.inbox').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');
        
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_new_message', {});
        
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
    
        //Clic sur le dossier correspondant à l'envoie du message
    $('.btn.write-new-msg.btn-messaging.send').on('click', function() {
        
        //Doit être un/des email(s) valide (y compris ("jk" <fdjfd@fdfd.com>, toto@toto.com) )
//        var toField = $('#email_form_to').val();
//        var regSplitContact = new RegExp("[,]+", "g");
//        var toEmails = toField.split(regSplitContact);
//
//        for (var i=0; i< toEmails.length; i++) {
//            var currentEmailToCheck = $.trim(toEmails[i]);
//            console.log(currentEmailToCheck);
//        }       

        $('.content-message').hide('slow');
        $.ajax({
            type: 'POST',
            url: $('form').attr('action'),	
            data: $('form').serialize(),
            success:function(response){	
                //Recharge dossier en cours
                $('.mailFolder.actif').click();
            },
            error:function (xhr, ajaxOptions, thrownError){
                //Alert en cas d'erreur
                $('.content-message').show('slow');
                alert('Une erreur est survenue lors de la création d\'un nouveau message !');
            } 	
        });
        return false;
    });
    
    
       //Clic sur le bouton suppression
    $('.btn.write-new-msg.btn-messaging.delete').on('click', function() {
        
        if(confirm('Voulez-vous réellement supprimer cet email ?'))
        {
            var folder = $('.mailFolder.actif').attr('id');
            var msgId = $('.header-message').attr('id');

            var link = Routing.generate('BNSAppMessagingBundle_frontajax_delete_message', {'messageId' : msgId, 'folderFunctionalName' : folder});

            //Appel de la suppression de l'email
            $.ajax({
                url: link,	
                success:function(response){	
                    //On recharge le dossier courant
                    $('.mailFolder.actif').click();
                },
                error:function (xhr, ajaxOptions, thrownError){
                    //Alert en cas d'erreur
                    $('.content-message').show('slow');
                    alert('Une erreur est survenue lors de la création d\'un nouveau message !');
                } 	
            });
        }
        return false;
    });
    
        
     $('.btn.write-new-msg.btn-messaging.answer').on('click', function() {
        to = $('a.from').text();
        subject = $('p.subject').text();
        
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.inbox').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.send').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');
        
        var link = Routing.generate('BNSAppMessagingBundle_frontajax_new_message', {'to' : to, 'subject' : subject});
        
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
     
     $('.btn.write-new-msg.btn-messaging.search').on('click', function() {
        
        if($('#inputSearch').val().length > 2)
        {
            //Droit d'écrire 
            $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
            $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
            $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');

            $('.content-message').hide('slow');

            query = $('#inputSearch').val();

            //Génération du lien
            var link = Routing.generate('BNSAppMessagingBundle_frontajax_search_emails', {'query': query , 'page' : 1});

            //Appel de la génération de template
            $.ajax({
                url: link,	
                success:function(response){	
                    //On charge le résultat dans la div de présentation si OK	
                    $('.content-message').html(response);
                    $('#inputSearch').val('');
                    $('.content-message').show('slow');
                },
                error:function (xhr, ajaxOptions, thrownError){
                    //Alert en cas d'erreur
                    $('.content-message').show('slow');
                    alert('Une erreur est survenue lors du chargement des messages !');
                } 	
            });
            return false;
        }
    });
    
});

 