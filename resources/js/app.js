require('./bootstrap');
require('./spot_charts');

$(document).ready(function(){
    $('.startMarketAnalysis').click(function(){
        $(this).attr('disabled','disabled')
        $self = $(this)
        axios.post('/market_analysis/'+$(this).attr('data-id'))
            .then(function (response) {
                $self.removeAttr('disabled')
                // console.log('response',response)
                location.reload()
            })
            .catch(function (error) {
                console.log(error)
            })
    })
    $('.delete').click(function(){
        let $row = $(this).closest('.market_row')
        axios.delete('/market/'+$(this).attr('data-id'))
            .then(function (response) {
                $row.remove()
            })
            .catch(function (error) {
                console.log(error)
            })
    })
    $('.delete_market').click(function(){
        axios.delete('/market/'+$(this).attr('data-id'))
            .then(function (response) {
                window.close()
            })
            .catch(function (error) {
                console.log(error)
            })
    })
    $('.toggleAnalysis').click(function(){
        let $list = $('.analysis_list_wrapper')
        if($list.hasClass('opened')){
            $list.removeClass('opened')
        }else{
            $list.addClass('opened')
        }
    })
    $('.upload_db').click(function(){
        axios.get('/uploadCSVFromBinance/'+$(this).data('market'))
            .then(function (response) {
                location.reload()
            })
            .catch(function (error) {
                console.log(error);
            })
    })


})
