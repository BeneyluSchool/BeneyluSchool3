$(document).ready(function ()
{
    $('.content-school-subject a.btn.sign').click(function (event)
    {
        event.preventDefault();
        var $this = $(this);
        var hdId = $(this).attr('data-task-id');
        var taskDone = $(this).attr('data-task-done');
        
        if(taskDone == 0)
        {
	    $this.removeClass('not-validate').addClass('validate');
                    $this.attr('data-task-done', 1);
                    $this.html('<span class="icons-validated"></span>' + $(this).attr('data-label-validate'));
            $.ajax(
            {
                url: Routing.generate('BNSAppHomeworkBundle_frontajax_task_status', { hdId: hdId }),
                type: 'POST',
                success: function (data)
                {
                },
                error: function (data)
                {

                }
            });
        }
    });
});