if ( LI == undefined )
  var LI = {};

$(document).ready(function(){
  // a trick to activate correctly the graphs
  if ( $('#sf_fieldset_stocks').attr('aria-expanded') == 'true' )
    LI.posStocks();
  $('[href="#sf_fieldset_stocks"]').click(function(){
    LI.posStocks();
  });
});

LI.posPrepareSalesData = function(obj)
{
  var r = [];
  for ( var key in obj )
    r.push([key, obj[key]]);
  return r;
}

LI.posStocks = function(){
  // sales
  if ( $('#sales_chart > *').length == 0 )
  {
    LI.csvData.sales = [
      [($('.jqplot.sales h2').prop('title') ? $('.jqplot.sales h2').prop('title')+': ' : '')+$('.jqplot.sales h2').text()],
    ]; 
    
    var sales;
    $('#sales_chart').dblclick(function(){
      sales.resetZoom();
    });
    $.get($('#sales_chart').attr('data-json-url'), function(json){
      $.each(json, function(date, nb){
        LI.csvData.sales.push([date, nb]);
      });
      sales = $.jqplot('sales_chart', [LI.posPrepareSalesData(json)], {
        seriesDefaults: {
          showMarker: false
        },
        axes: { 
          xaxis: {
            renderer: $.jqplot.DateAxisRenderer,
            tickOptions: { formatString:'%d/%m/%Y' } 
          },
          yaxis: {
            min: 0,
            tickInterval: 1,
            tickOptions: {
              formatString: '%d'
            }
          }
        },
        cursor: {
          show: true,
          zoom: true
        },
        captureRightClick: true
      });
    });
  }
  
  // pure stocks
  var cpt = 0;
  var ticks = [];
  LI.series.stocks = [[], [], []];
  LI.csvData.stocks = [
    [($('.jqplot.stocks h2').prop('title') ? $('.jqplot.stocks h2').prop('title')+': ' : '')+$('.jqplot.stocks h2').text()],
    [
      $('#sf_fieldset_declinations .sf_admin_form_field_declinations > label').text(),
      $('#sf_fieldset_declinations .stock-current:first') .closest('tr').find('label').text(),
      $('#sf_fieldset_declinations .stock-critical:first').closest('tr').find('label').text(),
      $('#sf_fieldset_declinations .stock-perfect:first') .closest('tr').find('label').text()
    ]
  ]; 
  
  $('.sf_admin_form_field_declinations .widget > table > tbody > tr').each(function(){
    var stocks = {
      critical: parseInt($(this).find('[name="product[declinations]['+cpt+'][stock_critical]"]').val()),
      current:  parseInt($(this).find('[name="product[declinations]['+cpt+'][stock]"]').val()),
      perfect:  parseInt($(this).find('[name="product[declinations]['+cpt+'][stock_perfect]"]').val())
    };
    
    // the name of the declination
    LI.csvData.stocks.push([
      ticks[cpt] = $(this).find('[name="product[declinations]['+cpt+'][code]"]').val(),
      stocks.current,
      stocks.critical,
      stocks.perfect
    ]);
    
    if ( stocks.current <= stocks.critical )
    {
      LI.series.stocks[0][cpt] = stocks.current;
      LI.series.stocks[1][cpt] = 0;
      LI.series.stocks[2][cpt] = 0;
    }
    else if ( stocks.current > stocks.critical && stocks.current <= stocks.perfect )
    {
      LI.series.stocks[0][cpt] = 0;
      LI.series.stocks[1][cpt] = stocks.current;
      LI.series.stocks[2][cpt] = 0;
    }
    else
    {
      LI.series.stocks[0][cpt] = 0;
      LI.series.stocks[1][cpt] = 0;
      LI.series.stocks[2][cpt] = stocks.current;
    }
    cpt++;
  });
  
  $('#stocks_chart > *').remove();
  
  $.jqplot(
    'stocks_chart', LI.series.stocks, {
      series: [
        { label: 'Critical' },
        { label: 'Correct' },
        { label: 'Good' }
      ],
      stackSeries: true,
      seriesColors: [
        'rgba(255,0,0,0.7)',
        'rgba(255,165,0,0.7)',
        'rgba(0,128,0,0.7)'
      ],
      seriesDefaults: {
        renderer: $.jqplot.BarRenderer,
        rendererOptions: { barMargin: 30 },
        pointLabels: {
          stackedValue: true,
          location: 's',
          show: true
        }
      },
      legend: {
        show: true,
        location: 'e',
        placement: 'outside'
      },
      axes: {
        xaxis: {
          ticks: ticks,
          renderer: $.jqplot.CategoryAxisRenderer
        }
      },
      captureRightClick: true
    }
  );
}