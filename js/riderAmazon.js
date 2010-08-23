// riderAmaozn
// 投稿画面 Amazon.co.jp 検索 JavaScript

var RiderAmazon = function() {
    this.initialize.apply(this, arguments);
}



RiderAmazon.prototype = {
    // メンバ変数
    version : 20090903,


    // コンストラクタ
    initialize : function() {

    //       $ = jQuery;
         
    //        jQuery(document).ready(function(){
    //
    //            jQuery("#riderAmazon_search").click(function(){
    //                this.firstSearch();
    //            });
    //            jQuery("#riderAmazon_toNextPage").click(function(){
    //                this.toNextPage();
    //            });
    //            jQuery("#riderAmazon_toPreviousPage").click(function(){
    //                this.toPreviousPage();
    //            });
    //        });
    },

    searchAmazon : function(){

        jQuery("#riderAmazon_result").html('<br /><img src="../wp-includes/js/thickbox/loadingAnimation.gif" id="riderAmazonLoadingImage" /><br />');
        jQuery.post(
            jQuery("#riderAmazon_url").attr("value") + "/wp-admin/admin-ajax.php",
            {
                action: "riderAmazonAjax",
                'cookie': encodeURIComponent(document.cookie),
                'keyword': jQuery("#riderAmazon_keyword").attr("value"),
                'searchIndex': jQuery("#riderAmazon_searchIndex option:selected").attr("value"),
                'currentPage': jQuery("#riderAmazon_currentPage").attr("value")
            },
            function(data , status) {
                if (status == "success"){

                    var amazonResult = eval("("+data+")");
                    //                    $resultArray = array(
                    //                'htmlResult' => $htmlResult,
                    //                'totalPages' => $totalPages,
                    //                     'error' => $error
                    //            );
                    if ( amazonResult.error == null ){

                        currentPage = jQuery("#riderAmazon_currentPage").attr("value");
                        page = currentPage + "/" + amazonResult.totalPages;

                    }
                    else if ( amazonResult.error.Code == 'AWS.ECommerceService.NoExactMatches' ){
                        amazonResult.htmlResult = '<div class ="error" >該当する商品が見つかりませんでした。カテゴリーやキーワードをご確認ください。</div>';
                        page = "0/0";
                    }
                    else if ( amazonResult.error.Code == 'AWS.MinimumParameterRequirement' ){
                         jQuery("#riderAmazon_keyword").effect("highlight", {color: "#c00"}, 2000);

                        amazonResult.htmlResult = '<div class ="error" >検索キーワードをご入力ください。</div>';
                        page = "0/0";
                    }
                    else if ( amazonResult.error.Code == 'AWS.ParameterOutOfRange' ){
                        amazonResult.htmlResult = '<div class ="error" >全商品から検索した場合、5ページまでしか表示できません。</div>';
                        page = "0/0";
                    }
                    else{
                        amazonResult.htmlResult =  '<div class ="error" >' + amazonResult.error.Message + '</div>';
                        page = "0/0";
                    }
                    //   console.log(amazonResult.totalPages);
                    jQuery("#riderAmazon_result").html(amazonResult.htmlResult);
                    jQuery("#riderAmazon_resultTable").slideDown(1000);
                    jQuery("#riderAmazon_totalPages").attr("value", amazonResult.totalPages);
                  
                    jQuery("#riderAmazon_page").html(page);
                    jQuery("#riderAmazon_result .error").fadeIn("slow")

                }
            });
    },

 
    selectSearchType : function(searchType){
        var currentPage = eval(jQuery("#riderAmazon_currentPage").attr("value")) ;
        var totalPage = eval(jQuery("#riderAmazon_totalPages").attr("value")) ;


        switch (searchType){

            case 'first':
                var changedPage =  1;
                break;
            case 'next':
                if ( currentPage == totalPage ){
                    changedPage = 1
                }else{
                    changedPage = currentPage + 1;
                }
                break;
            case 'previous':
                if (currentPage == 1 ){
                    changedPage = 1
                }else{
                    changedPage = currentPage - 1;
                    break;
                }
        }
     
        jQuery("#riderAmazon_currentPage").attr("value", changedPage );
        this.searchAmazon();

    },


    fadeInMessage: function(){
        jQuery("#riderAmazonAdminOptionUpdated").fadeIn("slow")
    }




};


var rideramazon = new RiderAmazon();
 

jQuery(document).ready(function(){
    //$(function(){
    var rideramazon = new RiderAmazon();

    jQuery("#riderAmazon_search").click(function(){
        rideramazon.selectSearchType('first');
    });
    jQuery("#riderAmazon_toNextPage").click(function(){
        rideramazon.selectSearchType('next');
    });
    jQuery("#riderAmazon_toPreviousPage").click(function(){
        rideramazon.selectSearchType('previous');
    });
    jQuery("#riderAmazonAdminOptionPage #saveOption").click(function(){
        rideramazon.fadeInMessage();
    });
});




