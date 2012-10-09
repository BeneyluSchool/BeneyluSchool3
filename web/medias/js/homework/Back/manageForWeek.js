$(document).ready(function ()
{   
     $(".submit-save-homework").hide();
     $(".submit-savecontinue-homework").hide();
     $('.btn.btn-info.btn-return').hide();
    
    jQuery(function($){$.datepicker.setDefaults($.datepicker.regional['fr']);});

    $( ".jq-date" ).datepicker({
    });
    
    $('.bns-user').live('click', function(){
        return false;
    });

    $('.btn.delete-homeworkdue').live('click', function(){
        
        return false;
//        var $this = $(this);
//        var id = $this.val();
//        
//        //Hide
//        $this.parents('.hwdue-parent').hide('fast');
//        
//        $.ajax(
//        {
//            url: Routing.generate('BNSAppHomeworkBundle_backajax_homeworkdue_delete', {dueId: id}),
//            success: function ()
//            {
//                
//            },
//            error: function ()
//            {
//                $this.parents('.hwdue-parent').show('fast');
//                alert('Une erreur est survenue');
//            }
//        });
//        
//        return false;
    });
    
    $('.valid-delete').live('click', function(){
        var id = $(this).siblings('.homework-id').val();
        $('[data-hd-id="'+id+'"]').parents('.hwdue-parent').hide('fast');
        
        $.ajax(
        {
            url: Routing.generate('BNSAppHomeworkBundle_backajax_homeworkdue_delete', {dueId: id}),
            success: function ()
            {
                
            },
            error: function ()
            {
                $this.parents('.hwdue-parent').show('fast');
                alert('Une erreur est survenue');
            }
        });
        
        return false;
    });
    
    
    
    $('.btn-change-week').live('click', function()
    {
        var link = $(this).attr('href');
        $('.manage-content').hide('fast');
        
        $.ajax(
        {
            url: link,
            success: function (data)
            {
                $('.manage-content').html(data);
                $('.manage-content').show('fast');
            },
            error: function ()
            {
                alert('Une erreur est survenue');
                $('.manage-content').show('fast');
            }
        });
        
        return false;
    });
    
    
    // Creation et envoi du formulaire de creation rapide de devoir
    var quickcreateoptions = {
        success: refreshweek 
    };
    
    function refreshweek(responseText, statusText, xhr, $form)  { 
        $('#currentDay').click();
    }
    
    $('.btn-quick-create').live('click', function()
    {
        $('.btn-quick-create-cancel').hide('fast');
        var cancel = $(this).siblings('.btn-quick-create-cancel');
        var container = $(this).next('.quick-add-form');
        
        $.ajax(
        {
            url: Routing.generate('BNSAppHomeworkBundle_backajax_quick_form', {day: $(this).attr('data-date')}),
            success: function (data)
            {   
                //clear all other form containers
                $('.quick-add-form').html('');
                container.html(data);
                
                if(container.find("#homework_form_recurrence_type").val() == 'ONCE') {
                    container.find(".recurrence_container").hide();
                }

                container.show('fast');
                
                container.find("#homework_form_recurrence_type").change(function(){
                    if($(this).val() == 'ONCE') {
                        $(".recurrence_container").hide('fast');
                    } else {
                        $(".recurrence_container").show('fast');
                    }
                });
                
                container.find('form').ajaxForm(quickcreateoptions); 
                cancel.show('fast');
                
            },
            error: function ()
            {
                alert('Une erreur est survenue');
            }
        });
        
        return false;
    });    
    
    $('.btn-quick-create-cancel').live('click', function()
    {
        $(this).siblings('.quick-add-form').html('');
        $(this).hide();
        return false;
    });  
});