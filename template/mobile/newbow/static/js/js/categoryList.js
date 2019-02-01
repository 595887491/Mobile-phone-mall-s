$(function () {
    var $c_index = localStorage.getItem('c_index');
    var $branchList_position = localStorage.getItem('branchList_position');
    $branchList_position ? $('.category2').animate({scrollTop:$branchList_position},300): $('.category2').animate({scrollTop:0})

    $c_index?  $(".category1 li").eq($c_index).addClass('red'): $(".category1 li:first").addClass('red');


    var li_hei = $(".category1 li:first").height(); // .category1 li 的高度
    var category2_hei = '';
    var c_index = 0;
    var invalid_scroll_event = false;
    var branchList_position = new Array(); // .branchList 的距离 .category2 顶部的位置
    $(".category2 .branchList").each(function(k,v){
        branchList_position.push($(v).position().top);
    })

    $(".category1").on('click','li',function(e){
        // $(".category2").unbind('scroll');
        // $("img.lazy").lazyload({effect: "fadeIn"});
        // $("img.lazy").lazyload({ threshold :180});
        invalid_scroll_event = true;
        var c_index =e.currentTarget.dataset.index;
        $('.category2').animate({scrollTop:branchList_position[c_index]},400);
        // 判断该项到上边缘距离和下边缘距离
        if (c_index < 4) {
            $(".category1").animate({scrollTop:0},400);
        } else if (c_index >= 4) {
            $(".category1").animate({scrollTop:(c_index-3)*li_hei},400);
        }
        // console.log($(".category1 ul").position())
        setTimeout(function(){invalid_scroll_event = false},500);

        //
        $('.classlist .fr .branchList .tp-class-list ul li').on('click',function () {
            localStorage.setItem('c_index',c_index)
            localStorage.setItem('branchList_position',branchList_position[c_index]+$(this).offset().top-$('.classreturn').height())
        })
    });

    function category2_on_scroll() {
        $(".category2").bind('scroll', function(){
            lazyload(); //图片懒加载
            if (invalid_scroll_event) {
                return;
            }
            // setTimeout(function () {
            //     $("img.lazy").lazyload({effect: "fadeIn"});
            //     $("img.lazy").lazyload({ threshold :180});
            // },200);
            var scroll_height = $(".category2").scrollTop();
            for(var i=0 ; i<=branchList_position.length; i++){
                if (scroll_height < branchList_position[i]) {
                    break;
                }else if (scroll_height > branchList_position[i] && scroll_height <= branchList_position[i+1]) {
                    break;
                }
            }
            if (c_index != i) {
                c_index = i;
                if (c_index < 4) {
                    $(".category1").animate({scrollTop:0},400);
                } else if (c_index >= 4) {
                    $(".category1").animate({scrollTop:(c_index-3)*li_hei},400);
                }
                $(".category1 li").eq(i).addClass('red').siblings().removeClass('red');
                if (i > branchList_position.length) {
                    $(".category1 li:last").addClass('red').siblings().removeClass('red');
                }
            }
        });
    }

    //点击存储 滚动位置
    $('.classlist .fr .branchList .tp-class-list ul li').on('click',function () {
        // console.log(c_index)
        // console.log( )
        localStorage.setItem('c_index',c_index)
        localStorage.setItem('branchList_position',branchList_position[c_index]+$(this).offset().top-$('.classreturn').height())

    })
    category2_on_scroll();

    // $("img.lazy").lazyload({effect: "fadeIn"});
    //
    // $("img.lazy").lazyload({ threshold :180});
    // $("img.lazy").lazyload({
    //     placeholder : "/template/mobile/newbow/static/images/a.png", //用图片提前占位
    //     // placeholder,值为某一图片路径.此图片用来占据将要加载的图片的位置,待图片加载时,占位图则会隐藏
    //     effect: "show", // 载入使用何种效果
    //     // effect(特效),值有show(直接显示),fadeIn(淡入),slideDown(下拉)等,常用fadeIn
    //     threshold: 150, // 提前开始加载
    //     // threshold,值为数字,代表页面高度.如设置为200,表示滚动条在离目标位置还有200的高度时就开始加载图片,可以做到不让用户察觉
    //     event: 'click',  // 事件触发时才加载
    //     // event,值有click(点击),mouseover(鼠标划过),sporty(运动的),foobar(…).可以实现鼠标莫过或点击图片才开始加载,后两个值未测试…
    //     container: $(".category2"),  // 对某容器中的图片实现效果
    //     // container,值为某容器.lazyload默认在拉动浏览器滚动条时生效,这个参数可以让你在拉动某DIV的滚动条时依次加载其中的图片
    //     failurelimit : 10 // 图片排序混乱时
    //     // failurelimit,值为数字.lazyload默认在找到第一张不在可见区域里的图片时则不再继续加载,但当HTML容器混乱的时候可能出现可见区域内图片并没加载出来的情况,failurelimit意在加载N张可见区域外的图片,以避免出现这个问题.
    // });

    var viewHeight = document.documentElement.clientHeight // 可视区域的高度

    function lazyload () {
        // 获取所有要进行懒加载的图片
        var eles = document.querySelectorAll('img[data-original][lazyload]');
        Array.prototype.forEach.call(eles, function (item, index) {
            var rect
            if (item.dataset.original === '')
               return
            rect = item.getBoundingClientRect()
            // 图片一进入可视区，动态加载
            if (rect.bottom >= 0 && rect.top < viewHeight) {
                !function () {
                    var img = new Image()
                    img.src = item.dataset.original;
                    img.onload = function () {
                        item.src = img.src
                    }
                    item.removeAttribute('data-original');
                    item.removeAttribute('lazyload');
                }()
            }
        })
    }
// 首屏要人为的调用，否则刚进入页面不显示图片
    lazyload()

    document.addEventListener('scroll', lazyload)
});