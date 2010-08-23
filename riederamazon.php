<?php
/*
Plugin Name: RiderAmazon
Plugin URI: http://retujyou.com/rideramazon/
Description: 投稿画面でASINの検索。本文中に[amazon]ASIN[/amazon]を記入でAmazon.co.jpから情報を取得。
Author: rui_mashita
Version: 0.0.3
Author URI: http://retujyou.com
Special Thanks: Tomokame (http://tomokame.moo.jp/)
Special Thanks: Keith Devens.com (http://keithdevens.com/software/phpxml)
Special Thanks: websitepublisher.net (http://www.websitepublisher.net/article/aws-php/)
Special Thanks: hiromasa.zone :o) (http://hiromasa.zone.ne.jp/)
Special Thanks: PEAR :: Package :: Cache_Lite (http://pear.php.net/package/Cache_Lite)
Special Thanks: よしとも (http://wppluginsj.sourceforge.jp/amazonlink/)
Special Thanks: leva (http://note.openvista.jp/187/(
*/

/********** 使い方 *********
1.プラグインディレクトリにアップロード
2.管理画面で有効化
3.投稿画面で、ASINの検索が出来る。
4.本文中に[amazon]ASIN[/amazon]を記入。

***************************/

/********** Notes *********
 ECS4.0 に対応しています
 PHP5 で動作します
 LGPL で提供されている Lite.php を同梱
 PEAR::HTTP_Client が必要
 GD が必要



***************************/

$rideramazon = new RiderAmazon();
// Hooks
add_shortcode('amazon', array(&$rideramazon, 'replaceCode'));
add_action('wp_head', array(&$rideramazon, '_addWpHead'));
add_action('admin_head', array(&$rideramazon, '_addAdminHead'));
add_action('admin_print_scripts', array(&$rideramazon, '_admin_print_scripts'));
add_action('dbx_post_advanced', array(&$rideramazon, '_dbxPost'));

register_activation_hook( __FILE__,array (&$rideramazon, '_rideramazonRegisterHook'));
register_deactivation_hook( __FILE__,array (&$rideramazon, '_rideramazonNotRegisterHook'));

class RiderAmazon{

	//各種設定、変更して下さい。

	// Amazon.co.jp アソシエイトID
	var $AssociatesID = "retujyou-22";
	// Amazon.co.jp サブスクリプションID
	var $SubscriptionID = "1P1KJSTVRDMR2FA0ZGG2";
	// サムネイル変換の際、リサイズ後の長辺の最大値を記入
	var $resize = 240;
	// 購入数・クリック数情報を表示するか（上級者向け）
	// （trueの場合は PEAR::HTTP_Client が必要）
	var $show_iteminfo = true;

	//(Amazon.co.jp アソシエイトのアカウント情報を入力してください）
	// アカウントのEメールアドレス
	var $email = "";
	// アカウントのパスワード
	var $password = "";
	//詳細を表示ボタンの画像
	var $showDetailButtonImg ="showdetail119.png";
	//カートに入れるボタンの画像
	var $addCartButtonImg ="gocart119.png";


	// 以下は内部で使用する規定値です
	var $Version = "2007-10-29";
	var $JPendpoint = "http://webservices.amazon.co.jp/onca/xml?Service=AWSECommerceService";
	var $Operation = "ItemLookup";
	var $ResponseGroup = "Medium";
	var $PageNum = 1;
	// 国際化リソースドメイン
	var $i18nDomain = 'rideramazon';	
	

	/*
	 * コンストラクタ
	 *
	 * @param void
	 *
	 */
	function __construct(){

		/*** Localization ***/
		$wp_mofile = dirname(__FILE__);
		load_plugin_textdomain($this->i18nDomain, 'wp-content/plugins/RiderAmazon' );
					
		// プラグインパス
		$dirs = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
		$this->pluginDir = '/wp-content/plugins/' . array_pop($dirs);
		$this->pluginDirUrl = get_bloginfo('wpurl') . $this->pluginDir;

		
	}

	/*
	 * プラグイン有効化時に毎日発生するイベントを、一度行ってから登録
	 * 
	 *
	 *
	 */
	function _rideramazonRegisterHook(){
		
		$this->_rideramazonDailyEvent();

//		wp_schedule_event(time(), 'daily', '_rideramazonDailyEvent');
		wp_schedule_event(time(), 'hourly', '_rideramazonDailyEvent');

	}

	/*
	 * 毎日発生するイベント
	 *
	 *
	 */
	function _rideramazonDailyEvent() {
		

		$this->getReport();
		$this->saveReport();

		$amazonDB = new AmazonDB($this->pluginDir,$this->pluginDirUrl);
		$amazonDB->makeDB();
	}

	/*
	 * プラグイン無効化時にイベントを消去する
	 *
	 *
	 */
	function _rideramazonNotRegisterHook(){
		
		wp_clear_scheduled_hook('_rideramazonDailyEvent');

	}

	function _addWpHead(){

?>
<!-- Added By RiderAmazon Plugin  -->
<link rel="stylesheet" type="text/css" href="<?php echo $this->pluginDirUrl; ?>/css/amazon.css" />
<?php
	}



	// 管理画面用スクリプトの登録をする
	function _admin_print_scripts(){

		wp_enqueue_script('prototype');
		wp_enqueue_script('scriptaculous-effects');
		wp_enqueue_script('ObjTree', $this->pluginDirUrl.'/js/ObjTree.js', array('prototype'), '0.24');
		wp_enqueue_script('jkl-dumper', $this->pluginDirUrl.'/js/jkl-dumper.js', array('ObjTree'), false);
		wp_enqueue_script('AmazonLink-post', $this->pluginDirUrl.'/js/AmazonLink_post.js', array('prototype','ObjTree','jkl-dumper'), false);

	}


	/**
	 * 管理画面のヘッダ
	 */
	function _addAdminHead()
	{
?>
<link rel="stylesheet" type="text/css" href="<?php echo $this->pluginDirUrl; ?>/css/admin.css" />
<?php
		if ( !function_exists('wp_enqueue_script') )
		{

?>
<script type="text/javascript" src="<?php echo $this->pluginDirUrl; ?>/js/prototype.js"></script>
<script type="text/javascript" src="<?php echo $this->pluginDirUrl; ?>/js/scriptaculous/scriptaculous.js?load=effects"></script>
<script type="text/javascript" src="<?php echo $this->pluginDirUrl; ?>/js/ObjTree.js"></script>
<script type="text/javascript" src="<?php echo $this->pluginDirUrl; ?>/js/jkl-dumper.js"></script>
<script type="text/javascript" src="<?php echo $this->pluginDirUrl; ?>/js/AmazonLink_post.js"></script>
<?php

		}
	}




	// 記事作成画面のドッキングボックス
	/*
	 * @return none
	 */
	function _dbxPost()
	{
		global $post;

		$categories = array(
			__('全商品', $this->i18nDomain) => 'Blended',
			__('本', $this->i18nDomain) => 'Books',
			__('洋書', $this->i18nDomain) => 'ForeignBooks',
			__('エレクトロニクス', $this->i18nDomain) => 'Electronics',
			__('ホーム＆キッチン', $this->i18nDomain) => 'Kitchen',
			__('ミュージック', $this->i18nDomain) => 'Music',
			__('ビデオ', $this->i18nDomain) => 'Video',
			__('ソフトウェア', $this->i18nDomain) => 'Software',
			__('ゲーム', $this->i18nDomain) => 'VideoGames',
			__('おもちゃ＆ホビー', $this->i18nDomain) => 'Toys',
			__('スポーツ＆アウトドア', $this->i18nDomain) => 'SportingGoods',
			__('ヘルス＆ビューティー', $this->i18nDomain) => 'HealthPersonalCare',
			__('時計', $this->i18nDomain) => 'Watches',
			__('ベビー＆マタニティー', $this->i18nDomain) => 'Baby',
			__('アパレル＆シューズ', $this->i18nDomain) => 'Apparel',
					);
?>
<div id="yo_amazonLink_dbx" class="postbox open">
<h3 class="dbx-handle">Amazon.co.jp 商品検索</h3>
<div class="inside">
<form action="#" method="GET" onsubmit="return false;">
<input type="hidden" name="yo_amazonLink_trackingId" id="yo_amazonLink_trackingId" value="<?php echo $this->AssociatesID; ?>" />
<input type="hidden" name="yo_amazonLink_url" id="yo_amazonLink_url" value="<?php echo $this->pluginDirUrl; ?>" />
<input type="hidden" name="yo_amazonLink_totalPages" id="yo_amazonLink_totalPages" value="0" />
<input type="hidden" name="yo_amazonLink_currentPage" id="yo_amazonLink_currentPage" value="1" />
<input type="hidden" name="yo_amazonLink_dir" id="yo_amazonLink_dir" value="<?php echo ABSPATH.'/wp-includes/'; ?>" />
<input type="hidden" name="yo_amazonLink_lastType" id="yo_amazonLink_lastType" value="" />
<input type="hidden" name="yo_amazonLink_lastKeyword" id="yo_amazonLink_lastKeyword" value="" />
<select name="yo_amazonLink_type" id="yo_amazonLink_type">
<?php
foreach ( $categories as $key => $value )
{
	print "\t".'<option value="'.$value.'">'.$key."</option>\n";
}
?>
</select>
<input type="text" name="yo_amazonLink_keyword" id="yo_amazonLink_keyword" value="" title="検索するキーワードを入力します" />
<input type="button" name="yo_amazonLink_search" id="yo_amazonLink_search" value="検索" title="検索を実行します" />
<input type="button" name="yo_amazonLink_toPreviousPage" id="yo_amazonLink_toPreviousPage" value="前のページへ" />
<span name="yo_amazonLink_page" id="yo_amazonLink_page">0/0</span>
<input type="button" name="yo_amazonLink_toNextPage" id="yo_amazonLink_toNextPage" value="次のページへ" />
</form>
<div name="yo_amazonLink_result" id="yo_amazonLink_result"></div>
</div>
</div>
<?php
	}



	/**
	 * ASINをhtmlに置換する
	 *
	 * @param $attr,$asinCode
	 * @return $htmlCode
	 */
	
	function replaceCode($atts, $asinCode=''){
			
		if( !($asinCode=='') ){
			$this->asin_ = $asinCode ;

			
			// 前準備
			$this->getData();
			// HTMLコードを生成
			$htmlCode = $this->makeCode();
	
			return $htmlCode;
		}
		
				
		
	}
	



	/*** 下ごしらえ：データを取得 ***/
	
	function getData(){
		
		// ASINをチェック
		$this->checkASIN();
		
		$AmazonXML = $this->connectECS($this->asin_);
		
		// AmazonECSか自分がネットワークから離脱している場合
		if (false === $AmazonXML){ 
			$this->errors[] = __("An error occured. Please reaload this page.", "$this->i18nDomain");
		}
		
		// 正常な処理ができなかった場合
		if ("True" === $AmazonXML->Items->Request->IsValid){ 
			$this->errors[] = __("Returned data is not valid.", "$this->i18nDomain");
		}
		
		// 変数の設定
		
		$this->item = $AmazonXML->Items->Item;
		$this->ASIN = $this->item->ASIN;
		$this->Type = $this->item->ItemAttributes->ProductGroup;
		$this->Title = $this->item->ItemAttributes->Title;
		$this->Creator = $this->item->ItemAttributes->Creator;
		$this->Manufacturer = $this->item->ItemAttributes->Manufacturer;
		$this->Currency = $this->item->ItemAttributes->ListPrice->CurrencyCode;
		$this->Binding = $this->item->ItemAttributes->Binding;
		$this->Price = $this->item->ItemAttributes->ListPrice->Amount;
		$this->CutPrice = $this->item->OfferSummary->LowestNewPrice->Amount;
		$this->Stock = $this->item->OfferSummary->TotalNew;
		$this->URL = "http://www.amazon.co.jp/o/ASIN/" . $this->ASIN . "/" . $this->AssociatesID;
		$this->URLMobile = "http://www.amazon.co.jp/gp/aw/rd.html?url=/gp/aw/d.html&lc=msn&dl=1&a=".
			$this->ASIN.'&uid=NULLGWDOCOMO&at='. $this->AssociatesID;
		$this->KeywordURL = "http://www.amazon.co.jp/gp/search?ie=UTF8&index=blended&tag=" . $this->AssociatesID . "&keywords=";
		$this->Artist = $this->item->ItemAttributes->Artist;

		// 価格を設定
		if ((empty($this->Price)) && (!empty($this->CutPrice))){
			$this->Price = $this->CutPrice;
		}
		if ((!empty($this->CutPrice)) && (((int)$this->Price) > ((int)$this->CutPrice))){
			$this->discounted = true;
		}
		
		// タイプ別処理
		if ($this->Type == "Book"){
			$this->str_creator = __("Author", "$this->i18nDomain");
			$this->pubDate = split("-", $this->item->ItemAttributes->PublicationDate);
			
		} else{
			$this->str_creator = __("Player", "$this->i18nDomain");
			$this->pubDate = split("-", $this->item->ItemAttributes->ReleaseDate);
			
		}
		
		list($this->pubDate["year"], $this->pubDate["month"], $this->pubDate["day"]) = $this->pubDate;
		
		if (($this->Type == "DVD") || ($this->Type == "Music")){
			$this->NumOfDiscs = $this->item->ItemAttributes->NumberOfDiscs;
			$this->RunningTime = $this->item->ItemAttributes->RunningTime;
			if ($this->RunningTime > 60){
				$hour = round($this->RunningTime / 60);
				$min = round($this->RunningTime % 60);
				$this->RunningTime = "{$hour} ". __("hour", "$this->i18nDomain") . " {$min} " . __("min", "$this->i18nDomain");
			} else{
				$this->RunningTime += " ". __("min", "$this->i18nDomain");
			}
			
		}else{
			$this->NumOfDiscs = "";
			$this->RunningTime = "";

		}
		
		//カバー画像を取得
					
		$this->Image = $this->getCover();
			
		return true;
		
	}
	
	/*** Amazonコードを生成 ***/
	
	function makeCode(){
		
		$htmlCode = "\n";
		
	$htmlCode .= $this->createGraphicalCode();
		
		$htmlCode = preg_replace("/<\/(div|p|table|tbody|tr|dl|dt|dd|li)>/", "</$1>\n", $htmlCode);
		$htmlCode = str_replace('&', '&amp;', $htmlCode);
		
		return $htmlCode;
		
	}
	
	/*** 入力されたASINをチェック ***/
	
	function checkASIN(){
		
		if (empty($this->asin_)){
			$this->errors[] = __("Please set ASIN for Amazon-Linkage Plugin.", "$this->i18nDomain");
		}
		
		$this->asin_ = preg_replace("/[\- ]/", "", $this->asin_);
		$length = strlen($this->asin_);
		
		if (($length != 9) && ($length != 10) && ($length != 13)){
			$this->errors[] = __("Please check the length of ASIN (accept only 9, 10, 13 letters one).", "$this->i18nDomain");
		}
		
		
		// ASIN(ここではISBN)を10桁に変換
		switch ($length){
			case "13": $this->ISBN_13to10(); break;
			case "9" : $this->ISBN_9to10(); break;
			case "12": $this->ISBN_12to10(); break;
		}
		
		
	}
	
	
	/*** コードを生成 ***/
	
	function createGraphicalCode(){
		
		$htmlCode .= '<div class="hreview">';
		
		$htmlCode .= "\n".'<div class="item item-'. $this->ASIN .'">';
		
		// カバー画像
		$htmlCode .= '<div class="cover">';
		$htmlCode .= '<a href="'.$this->URL.'" class="url" ';
		$htmlCode .= 'title="Amazon.co.jp: ">'. $this->Image . "</a>";
		$htmlCode .= "</div>";
		
		$htmlCode .= '<div class="">'."\n";
		
		// タイトル
		$htmlCode .= '<p class="fn">';
		$htmlCode .= '<a href="' .$this->URL .'" class="url">';
		$htmlCode .= $this->Title;
	 $htmlCode .= (($this->NumOfDiscs >= "2") ? " ({$this->NumOfDiscs}".__("discs", "$this->i18nDomain").")" : "");
		$htmlCode .= "</a>";
		$htmlCode .= "</p>";
		
		// 購入者情報
		$htmlCode .= '<p class="clicks">'.$this->getActionData()."</p>";
		
		
		$htmlCode .= '<div class="buttons">';
		// 詳細情報を見るボタン
		$htmlCode .=$this->showDetailButton();
		// カートに入れるボタン
		$htmlCode .= $this->showAddCartButton();
		$htmlCode .= '</div>';
		
		$htmlCode .= '<table >';
// summary="'. __(" \"", "$this->i18nDomain") . $this->Title;
//		$htmlCode .= __("\" ", "$this->i18nDomain"). __(" information", "$this->i18nDomain"). '"

		$htmlCode .= "\n<tbody>\n";
		
		// 製作者
		if (!empty($this->Creator)){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>". $this->str_creator ."</th>";
			$htmlCode .= "<td>";
			$htmlCode .= $this->getCreators();
			$htmlCode .= "</td>";
			$htmlCode .= "</tr>";
		}//製作者情報がなければ
		elseif(!empty($this->Artist)){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>Artist</th>";
			$htmlCode .= '<td><a href="' . $this->KeywordURL . urlencode($this->Artist) .'">'. $this->Artist .'</a></td>';
			$htmlCode .= "</td>";
			$htmlCode .= "</tr>";
		}
		
		// 製造元
		if (!empty($this->Manufacturer)){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>". __("Manufacturer", "$this->i18nDomain") ."</th>";
			$htmlCode .= '<td><a href="' . $this->KeywordURL . urlencode($this->Manufacturer) .'">'.
									 $this->Manufacturer .'</a></td>';
			$htmlCode .= "</tr>";
		}
		
		// 発売日
		if (!empty($this->pubDate["year"])){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>". __("Release Date", "$this->i18nDomain") ."</th>";
			$htmlCode .= "<td>";
			$htmlCode .= $this->pubDate["year"]. "年";
			$htmlCode .= ((!empty($this->pubDate["month"])) ? $this->pubDate["month"]. "月" : "");
			$htmlCode .= ((!empty($this->pubDate["day"])) ? $this->pubDate["day"]. "日" : "");
			$htmlCode .= "</td>";
			$htmlCode .= "</tr>";
		}
		
		// 価格（定価と割引後の価格）
		if (!empty($this->Price)){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>". __("Price", "$this->i18nDomain") ."</th>";
			$htmlCode .= "<td>";
			
			$htmlCode .= $this->getPrice();
			
			$htmlCode .= "</td>";
			$htmlCode .= "</tr>";
		}
		
		// 再生時間
		if (!empty($this->RunningTime)){
			$htmlCode .= "<tr>";
			$htmlCode .= "<th>". __("Running Time", "$this->i18nDomain") ."</th>";
			$htmlCode .= "<td>". $this->RunningTime ."</td>";
			$htmlCode .= "</tr>";
		}
		
		$htmlCode .= "</tbody>";
		$htmlCode .= '</table><br clear="both" />';
		
		$htmlCode .= "</div>";
//		$htmlCode .= $this->getCodeGenerator();
		
		$htmlCode .= "</div></div>";
		
		if ($this->type_ == "clear"){
			$htmlCode .= '<br clear="both" />';
		}
		
		return $htmlCode;
		
	}
	


	

	
	/*** 購入者情報を返す ***/
	
	function getActionData(){
		
		if (true === $this->show_iteminfo){
			$amazonDB = new AmazonDB($this->pluginDir,$this->pluginDirUrl);
			$actionData = $amazonDB->getDB($this->ASIN);
		}
		
		return $actionData;
		
	}
	
	/**
	 *詳細を見るボタン
	 *
	 *
	 *@return $htmlCode
	 */
	function showDetailButton(){

		$tmpCode = '<a href="'.$this->URL.'" title="Amazon.co.jp:' .$this->Title. '" class="showdetailbutton" >';
		$tmpCode .='<img src="'.$this->pluginDirUrl. '/images/' . $this->showDetailButtonImg .'" title="amazon.co.jpで詳細情報を見る" alt="amazon.co.jpで詳細情報を見る"/></a>';

		return $tmpCode;
	}

	/*** カートに入れるボタンを表示するコードを返す ***/
	
	function showAddCartButton(){
		



		$tmpCode = '<form class="showaddcartbutton" action="http://www.amazon.co.jp/gp/aws/cart/add.html" method="post">';
		$tmpCode .= '<input name="ASIN.1" value="'. $this->ASIN .'" type="hidden" />';
		$tmpCode .= '<input name="Quantity.1" value="1" type="hidden" />';
		$tmpCode .= '<input name="AssociateTag" value="'. $this->AssociatesID .'" type="hidden" />';
		$tmpCode .= '<input name="SubscriptionId" value="'. $this->SubscriptionID .'" type="hidden" />';
		
		$tmpCode .= '<input name="submit.add-to-cart" type="image" ';
		$tmpCode .= 'src="'. $this->pluginDirUrl. '/images/' . $this->addCartButtonImg . '" ';
		$tmpCode .= 'alt="'. __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain"). '" ';
		$tmpCode .= 'title="'. __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain"). '" />';
		$tmpCode .= '</form>';
		
		return $tmpCode;
		
	}
	
	function getPrice(){
		
		$tmpCode .= (($this->discounted == true) ? "<del>" : "");
		
		// 定価
		$tmpCode .= ($this->Currency == "USD") ?
		"$ ". number_format($this->Price) : number_format($this->Price) . __("yen", "$this->i18nDomain");
		
		// 割引していればその価格を表示
		if ($this->discounted == true){
			$tmpCode .= "</del> ";
			$tmpCode .= ($this->Currency == "USD") ?
			"$ ".number_format($this->CutPrice) : number_format($this->CutPrice). __("yen", "$this->i18nDomain");
			$CutRate	= round(( 1 - ( $this->CutPrice / $this->Price )) * 100);
			$tmpCode .= (($CutRate > 0) ? " (<em>{$CutRate}%</em> OFF)" : "");
		}
		
		return $tmpCode;
		
	}
	
	/*** 製作者情報を5人まで返す	***/
	
	function getCreators(){
		
		unset($tmpCode);
		
		if (isset($this->Creator[1])){

			for ($q=0; $q<5; $q++){
				if (isset($this->Creator[$q])){
					$tmpCode .= '<a href="' . $this->KeywordURL . urlencode($this->Creator[$q]) .'">';
					$tmpCode .= $this->Creator[$q]. "</a>";
					$tmpCode .= "<br />";
				}			
			}
		
		} else{
			$tmpCode .= '<a href="' . $this->KeywordURL . urlencode($this->Creator) .'">';
			$tmpCode .= $this->Creator. "</a>";
		}
		
		return $tmpCode;
		
	}
	
	/*** レビューを書いた人を示すmicroformaticなXHTMLを返す 
	
	function getCodeGenerator(){
		
		$tmpCode =	'<dl class="hidden">'."\n";
		$tmpCode .= '<dt>version</dt><dd><span class="version">0.1</span></dd>';
		$tmpCode .= '<dt>type</dt><dd><span class="type">product</span></dd>';
		$tmpCode .= '<dt>reviewer</dt><dd><a href="http://www.openvista.jp/person/leva" class="reviewer">leva</a></dd>';
		$tmpCode .= '</dl>';
		
		return $tmpCode;
		
	}***/
	
	/*** カバー画像を表示するコードを生成 ***/
	
	function getCover(){
		
		// 1フォルダにキャッシュする容量が大きくなりすぎないように3つのフォルダに分ける
		switch (substr($this->ASIN, 0, 1)){
			case "0": $path = "0"; break;
			case "4": $path = "4"; break;
			case "B": $path = "B"; break;
			default : $path = "unknown"; break;
		}

		
		// カバー画像のパス
		$img_path = "." . $this->pluginDir . "/cache/img/" .$path."/".$this->ASIN. ".jpg";
		$img_url = $this->pluginDirUrl . "/cache/img/" .$path."/".$this->ASIN. ".jpg";
		
		unset($source);
		
		// キャッシュされた画像の設定
		if (file_exists($img_url)){
			
			list($this->width_ , $this->height_) = getImageSize($img_url);
			
		// キャッシュがない場合、画像を取得して設定
		} else{
			
			
			
			// 入手可能なできるだけ大きい画像を取得
			
				
				if (!empty($this->item->LargeImage->URL)){
					$source = $this->item->LargeImage->URL;
				} elseif (!empty($this->item->MediumImage->URL)){
					$source = $this->item->MediumImage->URL;
				} elseif (!empty($this->item->SmallImage->URL)){
					$source = $this->item->SmallImage->URL;
				}
				
			
			
			// 外部に画像がなかった場合
			if (empty($source)){
				
				return $this->setLackedCover();
				
			// 外部に画像があった場合
			} else{
				
				list($width, $height, $fileTypes) = getImageSize($source);
				$longest = ($width > $height) ? $width : $height;
				
				// この場合も画像はないと判定
				if (($width == 1) || ($width == 0)){
					
					return $this->setLackedCover();
					
				}
				
				// リサイズ値より画像の長辺の方が長い場合はリサイズ
				if ( $longest > $this->resize ){
					$percent = round($this->resize / $longest, 2);
					$this->width_ = round($width * $percent);
					$this->height_ = round($height * $percent);
				} else{
					$this->width_ = $width;
					$this->height_ = $height;
				}
				
			}
			
			// 画像の読み込み
			switch($fileTypes){
				case "2": $bg = ImageCreateFromJPEG($source); break;
				case "3": $bg = ImageCreateFromPNG($source);	break;
				case "1": $bg = ImageCreateFromGIF($source);	break;
				default : return $this->setLackedCover();
			}
			
			// 画像のリサイズ
			$im = ImageCreateTrueColor($this->width_, $this->height_) or die ("Cannot create image");
			ImageCopyResampled($im, $bg, 0, 0, 0, 0, $this->width_ , $this->height_ , $width, $height);
			
			// ファイルのキャッシュとメモリキャッシュの廃棄
			ImageJPEG($im, $img_path, 80);
			ImageDestroy($bg);
			ImageDestroy($im);
			
		}
			
		$Image	= '<img src="'. $img_url .'" ';
		$Image .= 'width="'. $this->width_ . '" height="'. $this->height_ .'"';
		$Image .= 'class="photo reflect rheight20 ropacity40" ';
		$Image .= 'alt="' . $this->Title . __(" - cover art", "$this->i18nDomain") .'" />';

		return $Image;
		
	}
	
	/*** カバー画像がない場合、その旨のカバー画像を設定する ***/
	
	function setLackedCover(){
		
		list($this->width_ , $this->height_) = array(160, 260);
		$Image = '<img src="'. $this->pluginDirUrl .'/images/printing.png" ';
		$Image .= 'width="160" height="260" class="photo" ';
		$Image .= 'alt="'. __("Cover image is not found", "$this->i18nDomain") .'" />';

		
		return $Image;
		
	}
	
	/*** 9桁ISBNにチェックデジットを足す ***/
	
	function ISBN_9to10(){
		
		// 総和を求める
		for ($digit=0, $i=0; $i<9; $i++){
			$digit += $this->asin_{$i} * (10 - $i);
		}
		
		// 11から総和を11で割った余りを引く（10の場合はX, 11の場合は0に）
		$digit = (11 - ($digit % 11)) % 11;
		if ($digit == 10){
			$digit = "X";
		}
		
		$this->asin_ .= $digit;
		
	}
	
	/*** 13桁新ISBNを10桁旧ISBNにする ***/
	
	function ISBN_13to10(){
		
		$this->asin_ = substr($this->asin_ , 3, 9); // 978+チェックデジット除去
		return $this->ISBN_9to10();
		
	}
	
	/*** 12桁新ISBNを10桁旧ISBNにする ***/
	
	function ISBN_12to10(){
		
		$this->asin_ = substr($this->asin_ , 3, 9); // 978除去
		return $this->ISBN_9to10();
		
	}
	
	/*** ECSからXMLをゲット ***/
	
	function connectECS($SearchString){
		
		// XMLのURLを作成
		
		$xmlFeed = $this->JPendpoint.
			'&SubscriptionId=' .$this->SubscriptionID.
			'&AssociateTag=' .$this->AssociatesID.
			'&Operation=' .$this->Operation.
			'&ItemId=' .$SearchString.
			'&Version=' .$this->Version.
			'&ResponseGroup=' .$this->ResponseGroup.
			'&ItemPage=1&f=text/xml';
		
		// キャッシュ作成パッケージを読み込み
		
		// デバグ用
		// echo $xmlFeed;
		
		$id = $xmlFeed;
		
		/**
		* キャッシュの設定
		* "lifeTime"は秒単位です、ここでは3日(72時間)に設定しています
		*/
		$options = array(
			"cacheDir" => "." . $this->pluginDir ."/cache/xml/",
			"lifeTime" => 60*60*72,
			"automaticCleaningFactor" => 0
		);

		require_once("cache/Lite.php");
		$Cache = new Cache_Lite($options);
		
		// キャッシュがあるかチェック
		
		if ($xmlCache = $Cache->get($id)) {	// あればそれを利用
		
			$parseData = simplexml_load_string($xmlCache);
			return $parseData;
		
		} else{
			
			// Amazonからデータを読み込み
			$data = @implode("", file($xmlFeed));
			
			// Amazon Webサービスが落ちているなどしてXMLが返ってこない場合
			if (!strpos($data, 'xml')){
				
				return false;
				
			} else{ // XMLが返ってきたら利用
				
				$parseData = simplexml_load_string($data);
				
				// XMLをキャッシュ
				$Cache->save($data, $id);
				
				return $parseData;
				
			}
			
		}
		
	}
	
	/*** エラーがあるかどうか調べる ***/
	
	// レポートをゲット
	function getReport(){
		
		$this->docType = "report";
		require_once("HTTP/Client.php");

		$request = "submit.download_XML";
			
		$reportType = "ordersReport";
		
		$yesterday = time() - ( 60 * 60 * 24 );
		$host = "https://affiliate.amazon.co.jp/gp";
	
		$loginParams = array(
			'ie' => "UTF-8",
			'protocol' => "https",
			'__mk_ja_JP' => urlencode("カタカナ"),
			'path' => "/gp/associates/login/login.html",
			'useRedirectOnSuccess' => "0",
			'query' => "",
			'mode' => "1",
			'redirectProtocol' => "",
			'pageAction' => "/gp/associates/login/login.html",
			'disableCorpSignUp' => "",
			'email' => $this->email,
			'password' => $this->password,
			'action' => "sign-in",
		);
		
		$reportParams = array(
			'__mk_ja_JP' => urlencode("カタカナ"),
			'tag' => "",
			'reportType' => $reportType,
			'preSelectedPeriod' => "yesterday",
			'periodType' => "exact",
			'startYear' => "2003",
			'startMonth' => "0",
			'startDay' => "1",
			'endYear' => date("Y", $yesterday),
			'endMonth' => (string)(date("m", $yesterday) - 1),
			'endDay' => date("d", $yesterday),
			$request => "",
		);
		
		$loginQueries = http_build_query($loginParams);
		$reportQueries = http_build_query($reportParams);
		
		$client = new HTTP_Client();

		// ログイン画面
		$client->get("{$host}/associates/login/login.html");
		$response = $client->currentResponse();
		// ログイン
		$client->post("{$host}/flex/sign-in/select.html", $loginQueries, true);
		$response = $client->currentResponse();
		// レポート
		$client->post("{$host}/associates/network/reports/report.html", $reportQueries, true);
		$response = $client->currentResponse();
		
		if (!empty($response['body'])){
		  
			$this->report = $response['body'];
			return true;
		  
		} else{
		  
			return false;
		  
		}
		

	}
	
	// ゲットしたレポートを出力
	function saveReport(){
		
		// レポートを捕捉
		ob_start();
		print_r($this->report);
		$buffer = ob_get_contents();
		ob_end_clean();
		
		// 出力
		$fn = ABSPATH."{$this->pluginDir}/report/{$this->docType}.xml";
		file_put_contents($fn,$buffer);
		chmod($fn,0666);
		
		return true;
		
	}


	function isError(){
		
		if (count($this->errors) > 0){
			
			$msg =	'<p class="error">';
				
			foreach($this->errors as $error){
				$msg .= "<li>エラー：{$error}</li>";
			}
			
			$msg .= "</ul>";
			
			return $msg;
			
		} else{
			
			return false;
			
		}
		
	}


}


class AmazonDB{

	function __construct($pluginDir,$pluginDirUrl){
		
		$this->pluginDir = $pluginDir;
		$this->pluginDirUrl = $pluginDirUrl;
	}


	function makeDB(){
		
		// あらかじめ作成したXMLを取得
		$this->report = simplexml_load_file(ABSPATH.$this->pluginDir."/report/report.xml");
		// DBを更新
		$this->updateDB();
						
	}
	

	/**
	 * データベースからよみこむ
	 *
	 * @param asin
	 * @return code 
	 */
	function getDB($asin){
		
		global $wpdb;
		$table_name = $wpdb->prefix . "amazonreport";

		$sql = "SELECT orders,clicks,asin FROM " . $table_name . " WHERE asin = '". $asin ."'";
		$request = $wpdb->get_row($sql);
 		
		
		if (!empty($request->orders)){
			
			$code .= '<img src="'.$this->pluginDirUrl.'/images/buys.png" width="16" height="16" alt="購入数" /> '.
								 	"<strong>{$request->orders}人</strong>が購入しました ";
			
		}
		
		if (!empty($request->asin)){
			
			$code .= '<img src="'.$this->pluginDirUrl.'/images/clicks.png" width="16" height="16" alt="クリック数" /> '.
			"サイト内で<em>{$request->clicks}人</em>がクリック";
			
				}
		
				return $code;

	}
	
	function updateDB(){
			
		global $wpdb;
		$table_name = $wpdb->prefix . "amazonreport";

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
			$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
				asin VARCHAR(20) NOT NULL,
				title VARCHAR(255) NOT NULL,
				clicks INT DEFAULT 0,
				orders INT DEFAULT 0,
				price INT DEFAULT 0,
				PRIMARY KEY (asin)
				);";

			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			dbDelta($sql);
			$wpdb->query($sql);
		}

		// クリックのみの商品を配列に格納
		foreach ($this->report->ItemsNoOrders->Item as $item){
			$asin = (string) $item["ASIN"];
			$items[$asin] = array(
				"Title"	=> str_replace(",", "", (string) $item["Title"]),
				"Clicks" => (int) $item["Clicks"]
				);
		}

		// 購入された商品を配列に格納
		foreach ($this->report->Items->Item as $item){
			$asin = (string) $item["ASIN"];
			$items[$asin] = array(
				"Title"	=> str_replace("'", "", (string) $item["Title"]),
				"Price"	=> str_replace(",", "", (string) $item["Price"]),
				"Clicks" => (int) $item["Clicks"],
				"Orders" => (int) $item["Qty"]
				);
		}
		
		// 購入された商品のクリック数を修正
		foreach ($this->report->ItemsOrderedDifferentDay->Item as $item){
			$asin = (string) $item["ASIN"];
			$items[$asin]["Clicks"] = (int) $item["Clicks"];
		}

		foreach ($items as $asin => $item){
			
			// データを作成 or 更新
			$sql = "SELECT * FROM	". $table_name . " WHERE asin = '".$asin."'";
			$request = $wpdb->query($sql);
			
			unset($commands);
			
			$item["Title"] = htmlspecialchars($item["Title"]);
			
			if ($wpdb->get_row($sql)){
				// データがある場合は更新
				$commands[] =	"UPDATE ". $table_name . " SET clicks = '".$item["Clicks"]."' WHERE asin = '".$asin."'";
				$commands[] =	"UPDATE ". $table_name . " SET orders = '".$item["Orders"]."' WHERE asin = '".$asin."'";
			} else{
				// データがない場合は作成
				$commands[] = "INSERT INTO ". $table_name . " (asin, title, clicks, orders, price) ".
				"VALUES ('".$asin."','".$item["Title"]."','".$item["Clicks"]."','".$item["Orders"]."','".$item["Price"]."')";
			}
			
			// クエリを投げて更新
			foreach ($commands as $command){
				$request = $wpdb->query($command);
			}
			
		}

	}

	
 
	
}

?>
