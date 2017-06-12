$(function ()
{
    /**
     * Grid theme for Highcharts JS
     * @author Torstein Hønsi
     */
    Highcharts.setOptions({                                            // This is for all plots, change Date axis to local timezone
        global : {
            useUTC : false
        }
    });
    Highcharts.theme = {
       colors: ['#058DC7', '#50B432', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],
       chart: {
          backgroundColor: {
             linearGradient: [0, 0, 500, 500],
             stops: [
                [0, 'rgb(255, 255, 255)'],
                [1, 'rgb(240, 240, 255)']
             ]
          },
          borderWidth: 2,
          plotBackgroundColor: 'rgba(255, 255, 255, .9)',
          plotShadow: true,
          plotBorderWidth: 1
       },
       title: {
          style: {
             color: '#000',
             font: 'bold 16px "Trebuchet MS", Verdana, sans-serif'
          }
       },
       subtitle: {
          style: {
             color: '#666666',
             font: 'bold 12px "Trebuchet MS", Verdana, sans-serif'
          }
       },
       xAxis: {
          gridLineWidth: 1,
          lineColor: '#000',
          tickColor: '#000',
          labels: {
             style: {
                color: '#000',
                font: '11px Trebuchet MS, Verdana, sans-serif'
             }
          },
          title: {
             style: {
                color: '#333',
                fontWeight: 'bold',
                fontSize: '12px',
                fontFamily: 'Trebuchet MS, Verdana, sans-serif'

             }
          }
       },
       yAxis: {
          minorTickInterval: 'auto',
          lineColor: '#000',
          lineWidth: 1,
          tickWidth: 1,
          tickColor: '#000',
          labels: {
             style: {
                color: '#000',
                font: '11px Trebuchet MS, Verdana, sans-serif'
             }
          },
          title: {
             style: {
                color: '#333',
                fontWeight: 'bold',
                fontSize: '12px',
                fontFamily: 'Trebuchet MS, Verdana, sans-serif'
             }
          }
       },
       legend: {
          itemStyle: {
             font: '9pt Trebuchet MS, Verdana, sans-serif',
             color: 'black'

          },
          itemHoverStyle: {
             color: '#039'
          },
          itemHiddenStyle: {
             color: 'gray'
          }
       },
       labels: {
          style: {
             color: '#99b'
          }
       }
    };

    // Apply the theme
    var highchartsOptions = Highcharts.setOptions(Highcharts.theme);

    var chart = new Highcharts.Chart({
        chart: {
            renderTo: 'connexion-container',
            type: 'spline',
            marginRight: 35,
            marginBottom: 70
        },
        title: {
            text: $('#titleGraph').val(),
            x: -20 //center
        },
        xAxis: {
            type: 'datetime',
            tickInterval: 7 * 24 * 3600 * 1000, // one week
            tickWidth: 0,
            gridLineWidth: 1,
            labels: {
                align: 'left',
                x: 3,
                y: -3
            }
        },
        yAxis: {
            title: {
                text: 'Action'
            }
        },
        tooltip: {
            formatter: function() {
                if( $('#periodGraph').attr("value") == 'DAY') {
                    return translate_date(Highcharts.dateFormat('%A %e %B', this.x)) + ': '+ this.y;
                }
                else if( $('#periodGraph').attr("value") == 'MONTH') {
                    return translate_date(Highcharts.dateFormat('%B', this.x)) + ': '+ this.y;
                }
                else {
                    return translate_date(Highcharts.dateFormat('%A %e %B %H', this.x)) + 'h : '+ this.y;
                }
            }
        },
        series: [{
            name: $('#data').data('name'),
            data: $('#data').data('code')
        }]
        ,
        plotOptions: {
            spline: {
                lineWidth: 4,
                states: {
                    hover: {
                        lineWidth: 5
                    }
                },
                marker: {
                    enabled: true
                },
                pointInterval: 3600000 * 24, // one hour
                pointStart: Date.UTC($('#range').data('year'), $('#range').data('month') - 1, $('#range').data('day') + 1, 0, 0, 0)
            }
        }
    });

    if ($('#filters').css('display') == 'none') {
        $('#show-filters>strong').html($('#show-filters').data('show'));
        //Valeur pa défaut choisi sur le select lors du premier affichage de la page
        $("#stats_filter_marker").val('MAIN_CONNECT_PLATFORM');
    }
    else {
        $('#show-filters>strong').html($('#show-filters').data('hide'));
    }

    // Affichage des filtres
    $("#show-filters").click(function () {
        if (!$(this).hasClass("disabled")) {
            if ($('#filters').css('display') == 'none') {
                $('#filters').slideDown('fast');
                $('#show-filters>strong').html($('#show-filters').data('hide'));
            }
            else {
                $('#filters').slideUp('fast');
                $('#show-filters>strong').html($('#show-filters').data('show'));
            }
        }

        return false;
    });

    // Export
    $('#export-button').click(function (e) {
        var $this = $(e.currentTarget);

        $('.stats-list form').attr('action', $this.attr('href')).submit().removeAttr('action');

        return false;
    });


    //chart.series[0].setName('Série0');

    //chart.addSeries({name: '' + "nom2" + '', data: [[1384105600000,1],[1377784000000,3],[1379562400000,11]] });
    //chart.series[1].setName('Série1');
    //chart.series[1].setData([[1384105600000,1],[1377784000000,3],[1379562400000,11]]);


    $("input").click(function(){
        $('#stats_filter_graph_pro').prop('checked', false);
        form.submit();
    });

    $("form input.date").datepicker({
        dateFormat: 'dd/mm/yy',
        firstDay:1
    }).attr("readonly","readonly");


        $("#stats_filter_date_start").datepicker("setDate", new Date($("#stats_filter_date_start").val()));
        $("#stats_filter_date_start").trigger('change');

        $("#stats_filter_date_end").datepicker("setDate", new Date($("#stats_filter_date_end").val()));
        $("#stats_filter_date_end").trigger('change');


    $("#stats_filter_date_start").change(function() {
        var dateStart = $("#stats_filter_date_start").datepicker("getDate");
        $("#stats_filter_date_end").datepicker('option', 'minDate', dateStart);
    });

    $("#stats_filter_date_end").change(function() {
        var dateEnd = $("#stats_filter_date_end").datepicker("getDate");
        $("#stats_filter_date_start").datepicker('option', 'maxDate', dateEnd);
    });


    $(".finish:not(.disabled)").click(function(e)
    {
        if (!$(this).hasClass("disabled")) {
            if ($('#filters').css('display') != 'none') {
                $('#filters').slideUp(100);
                $('#show-filters>strong').html($('#show-filters').data('show'));
            }

            $('.btn-stats').addClass("disabled", "disabled");

            $("#stats_filter_date_start").val($("#stats_filter_date_start").val().replace(new RegExp("/","g"),"-"));
            $("#stats_filter_date_end").val($("#stats_filter_date_end").val().replace(new RegExp("/","g"),"-"));
            //date par défaut, pour prendre en compte la date créer un nouveau champ et concaténer
            $("#stats_filter_date_start").val($("#stats_filter_date_start").val()+"T00:00:00Z");
            $("#stats_filter_date_end").val($("#stats_filter_date_end").val()+"T23:59:59Z");
            $("#form_stats").submit();
        }
    });
});
