$(document).ready(function ()
{
    $('.content-school-subject a.btn').click(function (event)
    {
        event.preventDefault();
        var $this = $(this);
        var hdId = $(this).attr('data-task-id');
        var taskDone = $(this).attr('data-task-done');
        
        if(taskDone == 0)
        {
            $.ajax(
            {
                url: Routing.generate('BNSAppHomeworkBundle_frontajax_task_status', { hdId: hdId }),
                type: 'POST',
                success: function (data)
                {
                    $this.removeClass('not-validate').addClass('btn-success');
                    $this.attr('data-task-done', 1);
                    $this.html('<span class="icons-validated"></span>Travail validé');
                },
                error: function (data)
                {
                    console.log("TODO : AJAX error when changing task status");
                }
            });
        }
    });
});