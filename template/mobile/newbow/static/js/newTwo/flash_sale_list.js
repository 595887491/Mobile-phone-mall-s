function GetRTime1(time){
    var end_time  = time;
    var NowTime = new Date();
    var t = (end_time*1000) - NowTime.getTime();
    var d=Math.floor(t/1000/60/60/24);
    var h=Math.floor(t/1000/60/60%24);
    var m=Math.floor(t/1000/60%60);
    var s=Math.floor(t/1000%60);
    if(s >= 0) {
        if (h == 0) {
            $('.hms').css('background-color','transparent');
            d * 24 + h<10? $('#hours').text('0'+(d * 24 + h)):$('#hours').text(d * 24 + h);
            m<10 ? $('#minte').text('0'+m) :$('#minte').text(m) ;
            s<10 ? $('#second').text('0'+s) :$('#second').text(s);
        } else {
            $('.hms').css('background-color','transparent');
            d * 24 + h<10? $('#hours').text('0'+(d * 24 + h)):$('#hours').text(d * 24 + h);
            m<10 ? $('#minte').text('0'+m) :$('#minte').text(m) ;
            s<10 ? $('#second').text('0'+s) :$('#second').text(s);
        }
    }
}
// 延迟执行
$(function(){
    setTimeout(function () {
        $('.Time').show()
    },1000);
    ajax_sourch_submit()
});

function GetRTime(end_time){
    var NowTime = new Date();
    var t = (end_time*1000) - NowTime.getTime();
    var d=Math.floor(t/1000/60/60/24);
    var h=Math.floor(t/1000/60/60%24);
    var m=Math.floor(t/1000/60%60);
    var s=Math.floor(t/1000%60);
    if(s >= 0){
        return (d * 24 + h) + '时' + m + '分' +s+'秒';
    }
}

function formatterDate (date) {
    var result = new Date(date);
    return result;
}
function GetRTime2(){
    var start_time_data = $('.content30').find('.red').attr('data-start-time');
    var timestamp = Date.parse(new Date()).toString().substring(0,10) ;
    var start_time = $('.content30').find('.red').attr('data-start-time');
    var end_time = $('.content30').find('.red').attr('data-end-time');
    if(start_time_data > timestamp){
        $('.nowkill').find(".fl").text("秒杀活动即将开场~");
        $('.surplus').text('距开场');
        GetRTime1(start_time)
    }else{
        $('.nowkill').find(".fl").text("好价! 好货! 限量抢!");
        $('.surplus').text('距结束');
        GetRTime1(end_time)
    }
}
setInterval(GetRTime2,1000);

var page = 0;//页数
if ($('.content30').find('.red').length == 0) {
    $('.content30 li').eq(0).addClass('red');
}
var start_time = $('.content30').find('.red').attr('data-start-time');
var end_time = $('.content30').find('.red').attr('data-end-time');
function reload_flash_sale_list(obj)
{
    page = 0;
    hasMoreData = 1;
    $(obj).parent().children().removeClass('red');
    $(obj).addClass('red');
    start_time = $(obj).attr('data-start-time');
    end_time = $(obj).attr('data-end-time');
    setInterval(GetRTime2,1000);
    $("#flash_sale_list").empty();
    ajax_sourch_submit();
}

/**
 * ajax加载更多商品
 */
var ajaxHasreturn = 1;
var hasMoreData = 1;
function ajax_sourch_submit() {
    if (ajaxHasreturn == 0 || hasMoreData== 0) return;
    ajaxHasreturn = 0;
    ++page;
    $.ajax({
        type : "GET",
        url: "/index.php?m=Mobile&c=Activity&a=ajax_flash_sale&p=" + page + "&start_time=" + start_time + "&end_time=" + end_time,
        success: function(data){
            if ($.trim(data)) {
                $(".shopkill ul").append(data);
            } else {
                hasMoreData = 0;
            }
            ajaxHasreturn = 1;
        }
    });
}
//滚动加载更多
$(window).scroll(
    function() {
        var scrollTop = $(this).scrollTop();
        var scrollHeight = $(document).height();
        var windowHeight = $(this).height();
        if (scrollTop + windowHeight == scrollHeight) {
            ajax_sourch_submit();//调用加载更多
        }
    }
);

// 秒杀提醒
function remindMe(goods_id,obj) {
    event.stopPropagation();
    if ("{$Think.session.user.user_id}" == "") {  //判断登录
        $('.isLogin_box').show();
        $('.isLogin-wrapper .btn .fl').on('click',function () {
            $('.isLogin_box').hide();
        });
        return ;
    }
    if($(obj).hasClass('remenber')){
        $.ajax({
            type : "post",
            url: '/Api/Activity/flashRemindUser',
            data:{
                goods_id:goods_id,
                flash_start_time:start_time,
                status:2
            },
            success: function(data){
                if(data.code == 1){
                    $('.flash_sale').css('display','none');
                    $(obj).html('<img src="/template/mobile/newbow/static/images/clock.png" style="width: .5rem;height: .5rem" alt="">'
                        +'提醒我');
                    layer.open({content:"已取消提醒",time:2,skin:"msg"});
                }
            }
        });
        $(obj).removeClass('remenber');
    }
    else {
        $.ajax({
            type : "post",
            url: '/Api/Activity/flashRemindUser',
            data:{
                goods_id:goods_id,
                flash_start_time:start_time,
                status:0
            },
            success: function(data){
                if(data.code == 1){
                    $('.flash_sale').css('display','block');
                    setTimeout(function () {
                        $('.flash_sale').css('display','none');
                    },2000);
                    $(obj).html('取消提醒');
                }
            }
        });
        $(obj).addClass('remenber');
    }
}