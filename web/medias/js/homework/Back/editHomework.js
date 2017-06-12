$(document).ready(function ()
{   
    $('.btn.btn-info.btn-new-article').hide();
    
    jQuery(function($){$.datepicker.setDefaults($.datepicker.regional['fr']);});
    
    $('#recurrence_container').hide();
    
    if($("#homework_form_recurrence_type").val() == 'ONCE') {
        $("#recurence-end").hide();
    }
    

    $("#homework_form_recurrence_type").change(function(){
        if($(this).val() == 'ONCE') {
            $("#recurence-end").hide('fast');
        } else {
            $("#recurence-end").show('fast');
        }
    });
    
    if ($('.container-content .alert.alert-success').length > 0) {
        setTimeout(function ()
        {
                $('.bns-alert div').slideUp('fast', function () { var $this = $(this); $this.parent().slideUp('fast', function () { $this.show() }) });
        }, 8000); // 8 seconds
    }
    
    $("#homework_form_date").change(function(){
        $("#homework_form_recurrence_days").find(':checkbox').removeAttr("checked");
         var day = getDateFromFormat($(this).val(), "dd/MM/y");
         var date = new Date(day);
         var nb = date.getDay();
         $("#homework_form_recurrence_days_"+nb).attr('checked', 'checked');
    });

    
    $(".submit-save-homework").click(function(){$("#add-homework-form").submit();})
    $(".submit-savecontinue-homework").click(function(){
        $("#homework_form_createAnother").val('true');
        $("#add-homework-form").submit();
    })

    if($("#homework_form_recurrence_days").find('input:checked').length == 0)
    {
        //Cocher le jour courant
        $("#homework_form_date").change();
    }

    $('#is-recurrence').live('click',function(){
        $('#toggle-recurrence').toggle();
    });

    
});