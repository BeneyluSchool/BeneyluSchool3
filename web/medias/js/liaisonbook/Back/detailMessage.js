$(document).ready(function ()
{
    //Submit
    $('.valid-delete').click(function(){
        var url = $('.btn.bns-danger.btn-24.medium-return.delete-item').attr('href');
        window.location.href = url;
    });
    
    if ($('.container-content .alert.alert-success').length > 0) {
        setTimeout(function ()
        {
                $('.bns-alert div').slideUp('fast', function () { var $this = $(this); $this.parent().slideUp('fast', function () { $this.show() }) });
        }, 8000); // 8 seconds
    }
});
