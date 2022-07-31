$(document).ready(function(){
    $('.candleChart').each(function(index){
        const $parent = $(this).closest('.analysis_list_wrapper')
        const data = $parent.data('data')
        const group = $parent.data('group')
        const candles = data.map(d => {
            return d.c.slice(0,5)
        })
        // console.log('candles',candles)
        const markData = data.map(d => {
            if(d.m === 'buy'){
                return {
                    x: d.c[0],
                    borderColor: 'green',
                    label: {
                        borderColor: 'green',
                        orientation: 'horizontal',
                        text: 'Buy'
                    }
                }
            }else if(d.m === 'sell'){
                return {
                    x: d.c[0],
                    borderColor: 'red',
                    label: {
                        borderColor: 'red',
                        orientation: 'horizontal',
                        text: 'Sell'
                    }
                }
            }else if(d.m){
                return {
                    x: d.c[0],
                    borderColor: 'yellow',
                    label: {
                        borderColor: 'yellow',
                        orientation: 'horizontal',
                        text: d.m
                    }
                }
            }
        }).filter(d => d !== undefined)
        var options = {
            chart: {
                id: group+'_candlestick',
                type: 'candlestick',
                group: group,
                height: '400px',
                width: '100%',
                animations: {
                    enabled: false
                },
                zoom: {
                    autoScaleYaxis: true
                }
            },
            series: [{
                name: 'candles',
                data: candles
            }],
            xaxis: {
                type: 'datetime',
            },
            yaxis: {
                forceNiceScale: true,
                labels: {
                    minWidth: 40
                }
            },
            annotations: {
                xaxis: markData
            },
            markers: {
                size: 0
            },
            dataLabels: {
                enabled: false,
            }
        }

        var chart = new ApexCharts(this, options);
        chart.render();
    })

    $('.rsiChart').each(function(index){
        const $parent = $(this).closest('.analysis_list_wrapper')
        const data = $parent.data('data')
        const rsi = $parent.data('rsi')
        const min = $parent.data('min')
        const max = $parent.data('max')
        const group = $parent.data('group')
        let lineData = []
        for(var i=0;i<data.length;i++){
            lineData.push([data[i]['c'][6],(rsi.hasOwnProperty(i) ? Math.round(rsi[i]*100)/100 : 0)])
        }

        var options = {
            chart: {
                id: group+'_rsi',
                type: 'line',
                group: group,
                height: '150px',
                width: '100%',
                animations: {
                    enabled: false
                },
                toolbar: {
                    show: false
                },
                zoom: {
                    autoScaleYaxis: true
                }
            },
            stroke: {
                width: 1,
            },
            series: [{
                name: 'RSI',
                data: lineData
            }],
            xaxis: {
                type: 'datetime',
            },
            yaxis: {
                forceNiceScale: true,
                labels: {
                    minWidth: 40
                },
                min: 0,
                max: 90
            },
            annotations: {
                yaxis: [
                    {
                        y: min,
                        borderColor: 'blue',
                    },
                    {
                        y: max,
                        borderColor: 'blue',
                    }
                ]
            },
            markers: {
                size: 0
            }
        }

        var chart = new ApexCharts(this, options);
        chart.render();
    })
    $('.stochRsiChart').each(function(index){
        const $parent = $(this).closest('.analysis_list_wrapper')
        const data = $parent.data('data')
        const stochRsi = $parent.data('stochrsi')
        const smaStochRsi = $parent.data('smastochrsi')
        const group = $parent.data('group')
        let lineStoch = []
        let lineSma = []
        for(var i=0;i<data.length;i++){
            lineStoch.push([data[i]['c'][6],(stochRsi.hasOwnProperty(i) ? Math.round(stochRsi[i]*100)/100 : 0)])
            lineSma.push([data[i]['c'][6],(smaStochRsi.hasOwnProperty(i) ? Math.round(smaStochRsi[i]*100)/100 : 0)])
        }

        var options = {
            chart: {
                id: group+'_stoch_rsi',
                type: 'line',
                group: group,
                height: '150px',
                width: '100%',
                animations: {
                    enabled: false
                },
                toolbar: {
                    show: false
                },
                zoom: {
                    autoScaleYaxis: true
                }
            },
            stroke: {
                width: 1,
            },
            colors: ['#283fd0','#faff2f'],
            series: [
                {
                    name: 'Stoch RSI',
                    data: lineStoch
                },
                {
                    name: 'SMA Stoch RSI',
                    data: lineSma
                }
            ],
            xaxis: {
                type: 'datetime',
            },
            yaxis: {
                forceNiceScale: true,
                labels: {
                    minWidth: 40
                },
                min: 0,
                max: 90
            },
            annotations: {
                yaxis: [
                    {
                        y: 20,
                        borderColor: 'blue',
                    },
                    {
                        y: 80,
                        borderColor: 'blue',
                    }
                ]
            },
            markers: {
                size: 0
            }
        }

        var chart = new ApexCharts(this, options);
        chart.render();
    })
})
