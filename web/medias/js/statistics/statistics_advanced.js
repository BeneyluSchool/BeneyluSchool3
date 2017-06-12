$(function () {
   var chart = new Highcharts.Chart({
        chart: {
            renderTo: 'connexion-container',
            type: 'spline',
            marginRight: 35,
            marginBottom: 70,
            zoomType: 'x',
            spacingRight: 20
        },
        title: {
            text: $('#titleGraph').attr("value")
        },
        subtitle: {
            text: document.ontouchstart === undefined ?
                'Cliquer et sélectionner une zone pour zoomer dessus' :
                'Passer la souris au dessus d\'un point pour avoir des informations' 
        },
        xAxis: {
            type: 'datetime',
            maxZoom: 14 * 24 * 3600000, // fourteen days
            title: {
                text: null
            }
        },
        yAxis: {
            title: {
                text: 'Action'
            }
        },
        tooltip: {
            shared: true
        },
        legend: {
            enabled: false
        },
        plotOptions: {
            area: {
                fillColor: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1},
                    stops: [
                        [0, Highcharts.getOptions().colors[0]],
                        [1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
                    ]
                },
                lineWidth: 1,
                marker: {
                    enabled: false
                },
                shadow: false,
                states: {
                    hover: {
                        lineWidth: 1
                    }
                },
                threshold: null
            }
        },

        series: []
    });

    if ($('#filters').css('display') == 'none') {
        $('#show-filters').html("Afficher les filtres");
        //Valeur pa défaut choisi sur le select lors du premier affichage de la page
        $("#stats_filter_marker").val('MAIN_CONNECT_PLATFORM');
    }
    else {
        $('#show-filters').html("Cacher les filtres");
    }

    // Affichage des filtres
    $('#show-filters').click(function () {
        if ($('#filters').css('display') == 'none') {
            $('#filters').slideDown('fast');
        }
        else {
            $('#filters').slideUp('fast');
        }

        return false;
    });

    var size = $('#size').attr('value');

    for(i = 0; i <= size ; ++i) {
        chart.addSeries({type: 'area',
            name: $('#data'+i).data('name'),
            pointInterval: 24 * 3600 * 1000,
            pointStart: Date.UTC(2006, 0, 01),
            data: $('#data'+i).data('code')});
    }
});