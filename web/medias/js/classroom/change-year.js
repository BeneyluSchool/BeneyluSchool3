$(function () {
    var isValided = false,
        canSubmit = false,
        ajaxCount = 0;

    $('form').submit(function (e) {
        return canSubmit;
    });

    $('#reset-change-year').click(function (e) {
        $('#reset-modal').modal('hide');

        canSubmit = true;

        // Validation process
        if (!isValided) {
            var results = [],
                formsCount = $('.data-reset-container form:not(#pdf-form)').length;

            $('#loader').fadeIn('fast');
            $('.data-reset-container form:not(#pdf-form)').ajaxForm({
                type: 'POST',
                dataType: 'json',
                success: function (data) {
                    if (!data.success) {
                        $('.' + data.name + ' .alert-danger').text(data.error).slideDown('fast');
                    }
                    else {
                        results.push(true);
                        $('.' + data.name + ' .alert-danger').slideUp('fast');
                    }

                     ++ajaxCount;
                }
            });

            // Feedbacks
            $(document).ajaxStop(function () {
                if (!isValided) {
                    if (formsCount == results.length) {
                        results = [];
                        $('#errors-alert div').slideUp('fast', function () { var $this = $(this); $this.parent().slideUp('fast', function () { $this.show() }) });

                        if (!confirm($('#confirm').data('confirm'))) {
                            canSubmit = false;
                            isValided = false;
                            $(document).off('ajaxStop');
                            location.reload();
                            return false;
                        }

                        isValided = true;

                        $('.data-reset-container form').attr('action', Routing.generate('data_reset_changeyear_dostep'));
                        $('#reset-change-year').click();
                        $('#loader').fadeOut('fast');
                        ajaxCount = 0;
                    }
                    else if (formsCount == ajaxCount) {
                        canSubmit = false;
                        ajaxCount = 0;
                        $('#loader').fadeOut('fast');
                        $('#errors-alert').slideDown('fast');
                    }
                }
            });

            var doSubmit = function (i, timer) {
                setTimeout(function () {
                    $($('.data-reset-container form:not(#pdf-form)').get(i)).submit();

                    if (i < formsCount - 1) {
                        doSubmit(i+1, timer + 500);
                    }
                }, timer);
            };

            doSubmit(0, 0);
            // $('.data-reset-container form').submit();
        }
        else {
            $('form').ajaxForm({
                type: 'POST',
                dataType: 'json',
                success: function (data) {
                    if (!data.success) {
                        $('#expose').fadeOut('fast');
                        $('.' + data.name + ' .alert-danger').text(data.error).slideDown('fast');
                    }
                    else {
                        var $dataReset = $('.data-reset').first();
                        if ($dataReset.length > 0) {
                            $dataReset.slideUp('fast', function () {
                                $(this).remove();

                                $('form').first().submit();
                            });
                        }
                        else {
                            // End process
                            $('#expose').fadeOut('fast');
                            $('#success-alert').slideDown('fast');
                            $('.header-buttons a.bns-danger').fadeOut('fast');
                        }
                    }
                }
            });

            $('#expose').fadeIn('fast');
            $('form').first().submit();
        }

        return false;
    });

    $('#submit-pdf-form').click(function(e){
        canSubmit = true;
        e.preventDefault();
        $('#generate-blog-pdf').modal('hide');
        $('#pdf-form').submit();
    });
});
