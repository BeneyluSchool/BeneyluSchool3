

$(document).ready(function ()
{
    
    $('.btn.save-draft').on('click', function() {
        
        $('.btn.write-new-msg.btn-messaging').attr('disabled', 'disabled');
        $('.btn.write-new-msg.btn-messaging.write').removeAttr('disabled');
        $('.btn.write-new-msg.btn-messaging.search').removeAttr('disabled');
        
        $('.content-message').hide('slow');
        $('#email_form_mustSave').val(true);
        
        $.ajax({
            type: 'POST',
            url: $('form').attr('action'),	
            data: $('form').serialize(),
            success:function(response){	
                //Recharger la boite de reception ??
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
    
    $('.btn.write-new-msg.add-user.add-attachment').on('click', function() {
        //Gérer les PJ
        return false;
    });
    

    function onResize()
    {
        $form.css('height', window.innerHeight - 267 + 'px');
    }
    
    $('#user-id-to-manage').change(function()
    {
            //Send id
            var link = Routing.generate('BNSAppMessagingBundle_frontajax_get_emails', {'user_ids' : $(this).val()});
            var userPickerModalDiv = $('#userpicker-modal');
            
            //Get result
            $.ajax({
                url: link,	
                success:function(response){	
                    //On ajoute les emails de la réponse dans le champs 'to'
                    var emails = response.split(','); 
                    
                    for (var i in emails)
                    {
                        email = emails[i];
                        $('.jq_tags_editor_input').val(email);
                        $('.jq_tags_editor_input').focus();
                        var press = jQuery.Event("keypress");
                        press.ctrlKey = false;
                        press.which = 13;
                        $('.jq_tags_editor_input').trigger(press);
                    }
                    
                    //Reset user_picker
                    $(this).val('');

                    //Close
                    userPickerModalDiv.modal('hide');
                },
                error:function (xhr, ajaxOptions, thrownError){
                    alert('Une erreur est survenue lors de l\'ajout de contacts !')
                    //Close
                    userPickerModalDiv.modal('hide');
                } 	
            });
            return false;
    });
    
});


$(function(){
        $('#email_form_to').tags({
                'separator':',',
                'add':function(added_tag, tags){
                        
                },
                'remove':function(removed_tag, tags){
                        
                }
        });
});