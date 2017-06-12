var $form;
$(document).ready(function ()
{
    $('.btn.btn-danger.btn-delete-article').hide();
    $('.btn.btn-info.btn-edit-article').hide();
    $('.btn.return').hide();
    $('.btn.btn-info.btn-new-article').hide();
    $('.btn.finish').hide();
    
    $('.archive_link').click(function(){
        var link_info = $(this).attr('id');
        var infos = link_info.split('_');
        var month = infos[0];
        var year = infos[1];
        
        var url = Routing.generate('BNSAppLiaisonBookBundle_back', { 'month': month, 'year': year });
        window.location.href = url;
        
        return false;
    });
 
});

