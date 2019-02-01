// 下滑继续加载
var before_request = 1; // 上一次请求是否已经有返回来, 有才可以进行下一次请求
var page = 1;
function ajax_sourch_submit(){
    if(before_request == 0)// 上一次请求没回来 不进行下一次请求
        return false;
    before_request = 0;
    page++;
    $.ajax({
        type : "get",
        url:"/index.php?m=Mobile&c=article&a=cfFrontPageList&p="+page,
        success: function(data)
        {
            if(data){
                $("#cf_content_list").append(data);
                before_request = 1;
            }else{
                $('.get_more').hide();
            }
        }
    });

}
$(function(){
    $(document).scroll(function(){
        if($(document).height() - $(document).scrollTop() - $(window).height() <400 )
        {
            ajax_sourch_submit()
        }
    });

})