<?php
/*
Plugin Name: RiderAmazon
Plugin URI: http://retujyou.com/rideramazon/
Description: 投稿画面でASINの検索。本文中に[amazon]ASIN[/amazon]を記入でAmazon.co.jpから情報を取得。
Author: rui_mashita
Version: 0.1.1
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

/*
Copyright (C) 2008  rui mashita

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/




if ( !class_exists( 'RiderAmazon' ) ) {
    class RiderAmazon {

    //各種設定、変更して下さい。



    // サムネイル変換の際、リサイズ後の長辺の最大値を記入
        var $resize = 240;


        //詳細を表示ボタンの画像
        var $showDetailButtonImg ="showdetail119.png";
        //カートに入れるボタンの画像
        var $addCartButtonImg ="gocart119.png";

        // 国際化リソースドメイン
        var $i18nDomain = 'riderAmazonDomain';

        // RiderAmazon
        var $pluginDirName;
        // /path/to/RiderAmazon
        var $pluginDir ;
        // http://sample.com/path/to/RiderAmazon
        var $pluginDirUrl ;

        // wp_opions table option_name column
        var $optionName = "riderAmazonOption" ;
        // optionの値
        //        array(
        //        'associateTag' => ,
        //        'accessKeyId' => ,
        //        'secretAccessKey'=> ,
        //        'associateEmail'=> ,
        //        'associatePassword'=>
        //        )
        var $options ;
        var $defaultOptions = array(
        'associateTag' => 'retujyou-22',
        'accessKeyId' => '',
        'secretAccessKey'=> '',
        'associateEmail'=> '',
        'associatePassword'=> ''
        );

	/*
	 * コンストラクタ
	 *
	 * @param void
	 *
	 */
        function __construct() {

		/*** Localization ***/
            $wp_mofile = dirname(__FILE__);
            load_plugin_textdomain($this->i18nDomain, 'wp-content/plugins/RiderAmazon' );

            // DB から $this->optinos に ロード
            $this->_loadOptions();

            // Hooks
            add_shortcode('amazon', array(&$this, 'replaceCode'));
            add_action('wp_head', array(&$this, '_addWpHead'));
            add_action('admin_head', array(&$this, '_addAdminHead'));
            add_action('admin_print_scripts', array(&$this, '_adminPrintScripts'));
            add_action('admin_menu', array(&$this, '_addCustomBox'));
            // ajax hook
            add_action('wp_ajax_riderAmazonAjax' ,array (&$this,'_riderAmazonAjax') );
            // 管理オプションページ hook
            add_action('admin_menu', array(&$this, '_addAdminOptionPage'));
            // 管理オプションページ エラー表示
            add_action('admin_notices', array (&$this,"_addAdminNotices"));

            register_activation_hook( __FILE__,array (&$this, '_rideramazonRegisterHook'));
            register_deactivation_hook( __FILE__,array (&$this, '_rideramazonNotRegisterHook'));


            // プラグインパス
            $dirs = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
            // Rider Amaozn
            $this->pluginDirName = array_pop($dirs);
            // /path/to/RiderAmazon
            $this->pluginDir = WP_PLUGIN_DIR .'/'.  $this->pluginDirName;
            // http://sample.com/path/to/RiderAmazon
            $this->pluginDirUrl = WP_PLUGIN_URL .'/'.  $this->pluginDirName;


        }



	/*
	 * プラグイン有効化時に毎日発生するイベントを、一度行ってから登録
	 * 
	 *
	 *
	 */
        function _rideramazonRegisterHook() {

            $this->_rideramazonDailyEvent();
            wp_schedule_event(time(), 'daily', '_rideramazonDailyEvent');

        //  テスト用 毎時
        //  wp_schedule_event(time(), 'hourly', '_rideramazonDailyEvent');

        }

	/*
	 * 毎日発生するイベント
	 *
	 *
	 */
        function _rideramazonDailyEvent() {

            $this->getReport();
            $this->saveReport();
            $this->makeDB();

        }

	/*
	 * プラグイン無効化時にイベントを消去する
	 *
	 *
	 */
        function _rideramazonNotRegisterHook() {
            wp_clear_scheduled_hook('_rideramazonDailyEvent');

        }

	/*
	 * 記事作成画面のボックスを追加するフック
	 * 旧と新で場合わけ
	 * 
	 * @return none
	 */
        function _addCustomBox() {

            add_meta_box( 'rideramazondiv', __( 'RiderAmazon 商品検索'), array(&$this, '_dbxPost'), 'post', 'normal');
            add_meta_box( 'rideramazondiv', __( 'RiderAmazon 商品検索'), array(&$this, '_dbxPost'), 'page', 'normal');

        }


        /**
         * ヘッダーにCSSを登録
         *
         *
         * @return none
         */
        function _addWpHead() {

            ?>
<!-- Added By RiderAmazon Plugin  -->
<link rel="stylesheet" type="text/css" href="<?php echo $this->pluginDirUrl; ?>/css/riderAmazon.css" />
        <?php
        }



        /**
         * 管理画面用スクリプトの登録をする
         *
         *
         * @return none
         */
        function _adminPrintScripts() {

            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-effects-core', $this->pluginDirUrl.'/js/effects.core.js', array('jquery','jquery-ui-core'), false);
            wp_enqueue_script('jquery-effects-highlight', $this->pluginDirUrl.'/js/effects.highlight.js', array('jquery','jquery-ui-core','jquery-effects-core'), false);
            wp_enqueue_script('riderAmazonJs', $this->pluginDirUrl.'/js/riderAmazon.js', array('jquery','jquery-ui-core','jquery-effects-core','jquery-effects-highlight'), false);

        }


        /**
         * 管理画面のヘッダにCSSを登録
         *
         *
         * @return void
         */
        function _addAdminHead() {
            ?>
<link rel="stylesheet" type="text/css" href="<?php echo $this->pluginDirUrl; ?>/css/riderAmazonAdmin.css" />
        <?php
        }


        /**
         * 管理画面にてamazonに問い合わせ検索
         *
         *
         * @return
         */
        function _riderAmazonAjax() {

        // Amazon Product Advertising API 用パラメータ作成 jsから受けとった情報を格納
            $amazonParam = array(
                'operation' => 'ItemSearch',
                'keyword' => $_POST['keyword'] ,
                'searchIndex' => $_POST['searchIndex'],
                'currentPage' => $_POST['currentPage'],
                'responseGroup' => 'Request,ItemAttributes,Small,Images'
            );


            // Amazon Product Advertising API にアクセスして XML を返してもらう
            $amazonXml = $this->_fetchAmazonXml($amazonParam);

            $parsedAmazonXml = simplexml_load_string($amazonXml);

            if ($parsedAmazonXml === false) {
                die( "alert('XMLパースエラーです')");
            }

            // エラー表示用
            $isValid = $parsedAmazonXml->Items->Request->IsValid;
            if (isset($isValid)) {
                $error = $parsedAmazonXml->Items->Request->Errors->Error;
            }
            else {
                $error = $parsedAmazonXml->Error ;
            }
            $totalPages =  (string)$parsedAmazonXml->Items->TotalPages;
            $htmlResult = $this->_getHtmlResult($parsedAmazonXml) ;

            // js に返すパラメータを作成
            $resultArray = array(
                'htmlResult' => $htmlResult,
                'totalPages' => $totalPages,
                'error' => $error
            );

            $resultJson = json_encode($resultArray);
            //$json = json_encode($parsedAmazonXml);
            //      var_dump($totalPages);
            //            var_dump( $currentPage);

            //    var_dump($isValid);
            //
            //        echo $currentPage;
            echo $resultJson;

            die();

        }



        /**
         * Amazon Product Advertising API から XML を fetch
         *
         * @param $amazonParam = array(
         *       'operation' =>
         *      'keyword' =>
         *      'searchIndex' =>
         *      'currentPage' =>
         *      'responseGroup' =>
         *      'itemId' =>
         *  );
         * @return $amazonXml
         */
        function _fetchAmazonXml($amazonParam) {
        // 送信された情報を格納

            $api_interface = 'http://webservices.amazon.co.jp/onca/xml';
            $secret_access_key = $this->options['secretAccessKey'];

            // パラメーターの設定
            $params = array();
            $params['Service'] = 'AWSECommerceService';
            $params['AssociateTag'] =  $this->options['associateTag'];
            $params['AWSAccessKeyId'] = $this->options['accessKeyId'];
            $params['Version'] = '2009-07-01';

            switch ($amazonParam['operation']) {
                case 'ItemSearch':
                    $params['Operation'] = $amazonParam['operation'];
                    $params['ResponseGroup'] = $amazonParam['responseGroup'];
                    $params['SearchIndex'] = $amazonParam['searchIndex'];
                    $params['Keywords'] =  $amazonParam['keyword'];
                    $params['ItemPage'] = $amazonParam['currentPage'];
                    if ( $amazonParam['searchIndex'] != 'All' ) {
                        $params['Sort'] = 'salesrank';
                    }
                    break;
                case 'ItemLookup':
                    $params['Operation']      = $amazonParam['operation'];
                    $params['ItemId']       = $amazonParam['itemId'];
                    $params['ResponseGroup']       = $amazonParam['responseGroup'];
                    break;
            }

            $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
            ksort($params);

            // canonical string を作成します
            $canonical_string = '';
            foreach ($params as $k => $v) {
                $canonical_string .= '&'.$this->rfc3986_urlencode($k).'='.$this->rfc3986_urlencode($v);
            }
            $canonical_string = substr($canonical_string, 1);

            // 署名の作成
            $parsed_url = parse_url($api_interface);
            $string_to_sign = 'GET' . "\n" . $parsed_url['host'] . "\n" . $parsed_url['path'] . "\n" . $canonical_string;
            $signature = base64_encode(hash_hmac('sha256', $string_to_sign, $secret_access_key, true));

            $url = $api_interface . '?' . $canonical_string . '&Signature=' . $this->rfc3986_urlencode($signature);

            // SnoopyによるURLリクエストを生成
            require_once( ABSPATH . WPINC . '/class-snoopy.php' );
            $Snoopy = new Snoopy();
            $Snoopy->agent = 'WordPress/' . $wp_version;
            $Snoopy->read_timeout = 2;

            // リクエスト
            if( !$Snoopy->fetch($url)) {
                die( "alert('amazonに接続できませんでした')" );
            }

            $amazonXml = $Snoopy->results;
            return $amazonXml;

        }


        /**
         *
         * AmazonXMLをキャッシュしてパースしたxmlを返す
         *
         *
         * @param <type> $asin
         * @return <type> $parsedAmazonXm
         *
         */
        function _CacheAmazonXml($asin) {

        // キャッシュ作成パッケージを読み込み

        /**
         * キャッシュの設定
         * "lifeTime"は秒単位です、ここでは3日に設定しています
         */
            $options = array(
                "cacheDir" =>  $this->pluginDir ."/cache/xml/",
                "lifeTime" => 3*60*60*24,
                "automaticCleaningFactor" => 0
            );

            require_once("cache/Lite.php");
            $Cache = new Cache_Lite($options);

            // キャッシュがあるかチェック
            $xmlCache = $Cache->get($asin);
            if ( $xmlCache ) {	// あればそれを利用

                $parsedAmazonXml = simplexml_load_string($xmlCache);
                return $parsedAmazonXml;

            } else {

            // Amazon Product Advertising API 用パラメータ作成
                $amazonParam = array(
                    'operation' => 'ItemLookup',
                    'itemId' => $asin,
                    'responseGroup' => 'Medium'
                );

                // Amazon Product Advertising API にアクセスして XML を返してもらう
                $amazonXml = $this->_fetchAmazonXml($amazonParam);

                // Amazon Webサービスが落ちているなどしてXMLが返ってこない場合
                if ($parsedAmazonXml === false) {
                    return false;
                } else {
                // XMLが返ってきたら利用
                // XMLをキャッシュ
                    $Cache->save($amazonXml, $asin);

                    $parsedAmazonXml = simplexml_load_string($amazonXml);
                    return $parsedAmazonXml;
                }
            }
        }




        /**
         * RFC3986に合わせ、チルダを除外したURLエンコードをする
         * Product Advertising API のURLエンコードは RFC3986 準拠
         *
         * @param $string
         * @return
         */
        function rfc3986_urlencode($string) {
            $string =  str_replace('%7E', '~', rawurlencode($string));
            return $string;
        }


        /**
         * パースしたXMLから表示HTMLを作成
         *
         * @param  $parsedAmazonXml
         * @return
         *
         *
         */
        function _getHtmlResult($parsedAmazonXml) {

            $items =  $parsedAmazonXml->Items->Item;

            if (isset($items) ) {

                $itemPage = (int)$parsedAmazonXml->Items->Request->ItemSearchRequest->ItemPage;
                $number = ( ($itemPage - 1) * 10) + 1;

                $HTMLResult = <<< EOF

                    <div id="riderAmazon_resultTable">
                    <table >
    <thead>
        <tr>
            <th>No.</th>
            <th>Image</th>
            <th class="titleAndCode">Title & code</th>
        </tr>
    </thead>
    <tbody>
EOF;


                foreach ( $items as $item ) {

                    $HTMLResult .= <<< EOF
        <tr>
            <th class="number" >
                        $number.
            </th>
            <td class="image" >
                <a href="{$item->DetailPageURL}"><img src="{$item->SmallImage->URL}" alt="{$item->ASIN}" /></a>
            </td>
            <td class="titleAndCode" >
                <a href="{$item->DetailPageURL}"> {$item->ItemAttributes->Title}</a>
                <br /><br /><code title='AmazonLink コード'>[amazon]{$item->ASIN}[/amazon]</code>
            </td>
        </tr>
EOF;

                    $number++;
                }

                $HTMLResult .=  <<< EOF
                    </tbody>
</table>
</div>
EOF;
            }
            return $HTMLResult;
        }



        /**
         * 管理画面にページを追加する
         *
         * @return void
         */
        function _addAdminOptionPage() {
        // オプションページの追加
            if ( function_exists('add_options_page') ) {
                add_options_page(__('Rider Amazon Option', $this->i18nDomain), __('RiderAmazon', $this->i18nDomain), 8, basename(__FILE__), array(&$this, '_adminOptionPage'));
            }
        }


        /**
         * 管理画面のエラー表示を追加する
         *
         * @return void
         */
        function _addAdminNotices() {

            if( "saveOption" == $_POST['optionMethod'] ) {

                $this->_setOption('associateTag', $_POST['associateTag']);
                $this->_setOption('accessKeyId', $_POST['accessKeyId']);
                $this->_setOption('secretAccessKey', $_POST['secretAccessKey']);
                $this->_setOption('associateEmail', $_POST['associateEmail']);
                $this->_setOption('associatePassword', $_POST['associatePassword']);

                $this->_saveOptions();


            }

            if( "resetOption" == $_POST['optionMethod'] ) {

                $this->_deleteOptions();
            }

            $this->_loadOptions() ;

            if( "saveOption" == $_POST['optionMethod'] ) { ?>

<div id="riderAmazonAdminOptionUpdated" class="updated fade" >
    <p>Rider Amazon の Option が設定されました。</p>
</div>

            <?php }

            if (  $this->_loadOptions() == false ) {  ?>
<div id="riderAmaoznAdminOptionError" class="error" >
    <p>Rider Amazon の Option が未設定です。<a href="<?php  echo get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=riderAmazon.php'  ;?>">こちらからRider Amazon の設定を行って下さい</a></p>
</div>
            <?php }

        }


        /**
         * 管理画面メニューのオプションページ
         *
         * @return void
         */

        function _adminOptionPage() {

        // プラグインオプション画面のコード出力
            ?>
<div class="wrap" id="riderAmazonAdminOptionPage">
    <h2><?php _e('RiderAmazon Setting', $this->i18nDomain); ?></h2>
    <h3><?php _e('共通の設定', $this->i18nDomain); ?></h3>
    <form action="<?php  echo $_SERVER['REQUEST_URI']; ?>" method="post">


        <fieldset>


            <legend><?php _e('Product Advertising API アカウント', $this->i18nDomain); ?></legend>
            <p>
                            <?php _e('Product Advertising API を利用するために必要なアカウント情報です。<br />この項目を設定しない場合は、ブログ記事での商品表示、及び、投稿画面での商品検索も利用することができません。', $this->i18nDomain); ?><br />
                            <?php _e('<strong>重要</strong>：他人のアカウントを利用することは、Amazon によって禁止されています。', $this->i18nDomain); ?>
                            <?php _e('参考：<a href="http://affiliate.amazon.co.jp/gp/associates/network/help/t126/a3/ref=amb_link_84042136_3?pf_rd_m=AN1VRQENFRJN5&pf_rd_s=center-1&pf_rd_r=&pf_rd_t=501&pf_rd_p=&pf_rd_i=assoc_help_t126_a1" target="_blank">Amazon.co.jp による参考訳：Product Advertising API アカウント</a>', $this->i18nDomain); ?>
            </p>

            <table class="form-table">

                <tr>
                    <th><label for="accessKeyId"><?php _e('アクセスキー ID', $this->i18nDomain); ?></label></th>
                    <td><input type="text" name="accessKeyId" id="accessKeyId" value="<?php echo htmlspecialchars($this->_getOption('accessKeyId')); ?>" />
                        <span class="description"><?php _e('Amazon.co.jp Access Key ID.', $this->i18nDomain); ?></span>
                    </td>
                </tr>

                <tr>
                    <th><label for="secretAccessKey"><?php _e('秘密キー', $this->i18nDomain); ?></label></th>
                    <td><input type="text" name="secretAccessKey" id="secretAccessKey" value="<?php echo htmlspecialchars($this->_getOption('secretAccessKey')); ?>" />
                        <span class="description"><?php _e('secretAccessKey', $this->i18nDomain); ?></span>
                    </td>
                </tr>

            </table>

        </fieldset>

        <fieldset>
            <legend><?php _e('Amazon Associates', $this->i18nDomain); ?></legend>

            <table class="form-table">
                <tr>
                    <th><label for="associateTag"><?php _e('AssociateTag', $this->i18nDomain); ?></label></th>
                    <td><input type="text" name="associateTag" id="associateTag" value="<?php echo htmlspecialchars($this->_getOption('associateTag')); ?>" />
                        <span class="description"><?php _e('Amazon アソシエイトのリンクで使用するトラッキング ID です。空欄の場合は作者のものが使用されます。', $this->i18nDomain); ?></span>
                    </td>
                </tr>

                <tr>
                    <th><label for="associateEmail"><?php _e('Associate Email', $this->i18nDomain); ?></label></th>
                    <td><input type="text" name="associateEmail" id="associateEmail" value="<?php echo htmlspecialchars($this->_getOption('associateEmail')); ?>" />
                        <span class="description"><?php _e('Amazon アソシエイト、アカウントへのログインEmail。', $this->i18nDomain); ?></span>
                    </td>
                </tr>


                <tr>
                    <th><label for="associatePassword"><?php _e('Associate Password', $this->i18nDomain); ?></label></th>
                    <td><input type="password" name="associatePassword" id="associatePassword" value="<?php echo htmlspecialchars($this->_getOption('associatePassword')); ?>" />
                        <span class="description"><?php _e('Amazon アソシエイト、アカウントへのログインパスワード', $this->i18nDomain); ?></span>
                    </td>
                </tr>

       <!--     <tr>
                <th><label for=""><?php _e('秘密キー', $this->i18nDomain); ?></label></th>
                <td><input type="text" name="" id="" value="<?php echo htmlspecialchars($this->_getOption('void')); ?>" />
                    <span class="description"><?php _e('secretAccessKey', $this->i18nDomain); ?></span>
                </td>
            </tr>
                -->

            </table>
        </fieldset>

        <p class="submit">
            <input class="button-primary" id="saveOption" type="submit" value="<?php _e('設定を更新する &raquo;', $this->i18nDomain); ?>" />
        </p>
        <input type="hidden" name="optionMethod" value="saveOption" />
    </form>

    <form action="<?php  echo $_SERVER['REQUEST_URI']; ?>" method="post">
        <p class="submit">
            <input class="button" id="resetOption" type="submit" value="<?php _e('設定をリセットする &raquo;', $this->i18nDomain); ?>" />
        </p>
        <input type="hidden" name="optionMethod" value="resetOption" />
    </form>
</div>


        <?php
        //    var_dump($this->options);
        }

        /**
         * Retrieves the plugin's options from the database.
         *
         *
         * @return boolean
         */
        function _loadOptions() {
            if( false === ( $options = get_option( $this->optionName) ) ) {
                $this->options = $this ->defaultOptions;
                return false;
            } else {
                $this->options = $options;
                return true;
            }
            return false;
        }



        /**
         * delete the plugin's options from the database.
         *
         */
        function _deleteOptions() {
            return delete_option($this->optionName);
        }


        /**
         * Provides an easy mechanism to save the options value to the WordPress database
         * for persistence.
         *
         * @return boolean true if the save was successful.
         */
        function  _saveOptions( ) {
            return update_option( $this->optionName, $this->options );
        }


        /**
         * 自分自身のプラグインオプション値を得る
         *
         * @param string $key オプションkey
         * @return mixed オプション値
         *
         */
        function _getOption($key) {
            return $this->options[$key];
        }

        /**
         * 自分自身のプラグインオプション値を設定する
         *
         * @param string $key オプションkey
         * @param mixed $value 値
         * @return void
         *
         */
        function _setOption($key, $value) {
            $this->options[$key] = $value;
        }


	/*
	 * 記事作成画面のドッキングボックス
	 * 
	 * @return none
	 */
        public function _dbxPost() {
            global $post;

            $categories = array(
                __('全商品', $this->i18nDomain) => 'All',
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

<input type="hidden" name="riderAmazon_url" id="riderAmazon_url" value="<?php echo get_bloginfo('wpurl'); ?>" />
<input type="hidden" name="riderAmazon_dir" id="riderAmazon_dir" value="<?php echo ABSPATH.'/wp-includes/'; ?>" />
<input type="hidden" name="riderAmazon_totalPages" id="riderAmazon_totalPages" value="0" />
<input type="hidden" name="riderAmazon_currentPage" id="riderAmazon_currentPage" value="1" />
<input type="hidden" name="riderAmazon_lastSearchIndex" id="riderAmazon_lastSearchIndex" value="" />
<input type="hidden" name="riderAmazon_lastKeyword" id="riderAmazon_lastKeyword" value="" />
<select name="riderAmazon_searchIndex" id="riderAmazon_searchIndex" >
                <?php
                foreach ( $categories as $key => $value ) {
                    print "\t".'<option value="'.$value.'">'.$key."</option>\n";
                }
                ?>
</select>
<input type="text" name="riderAmazon_keyword" id="riderAmazon_keyword" value="" title="検索するキーワードを入力します" />
<input type="button" name="riderAmazon_search" id="riderAmazon_search" class="button" value="検索" title="検索を実行します" />
<input type="button" name="riderAmazon_toPreviousPage" id="riderAmazon_toPreviousPage" class="button" value="前のページへ" />
<span name="riderAmazon_page" id="riderAmazon_page">0/0</span>
<input type="button" name="riderAmazon_toNextPage" id="riderAmazon_toNextPage" class="button" value="次のページへ" />

<div name="riderAmazon_result" id="riderAmazon_result"></div>

        <?php


        }



        /**
         * ASINをhtmlに置換する
         *
         * @param $attr,$asinCode
         * @return $htmlCode
         */

        function replaceCode($atts, $asinCode='') {

            if( !($asinCode=='') ) {
                $this->asin_ = $asinCode ;

                // 前準備
                $this->getData();
                // HTMLコードを生成
                $htmlCode = $this->makeCode();

                return $htmlCode;
            }

        }

	/*** 下ごしらえ：データを取得 ***/

        function getData() {

        // ASINをチェック
            $this->checkASIN();

            $parsedAmazonXml = $this->_CacheAmazonXml($this->asin_);


            // AmazonECSか自分がネットワークから離脱している場合
            if (false === $parsedAmazonXml) {
                $this->errors[] = __("An error occured. Please reaload this page.", "$this->i18nDomain");
            }

            // 正常な処理ができなかった場合
            if ("True" === $parsedAmazonXml->Items->Request->IsValid) {
                $this->errors[] = __("Returned data is not valid.", "$this->i18nDomain");
            }

            // 変数の設定

            $this->item = $parsedAmazonXml->Items->Item;
            $this->ASIN = $this->item->ASIN;
            $this->Type = $this->item->ItemAttributes->ProductGroup;
            $this->Title = $this->item->ItemAttributes->Title;
            $this->Creator = $this->item->ItemAttributes->Creator;
            $this->Author = $this->item->ItemAttributes->Author;
            $this->Manufacturer = $this->item->ItemAttributes->Manufacturer;
            $this->Currency = $this->item->ItemAttributes->ListPrice->CurrencyCode;
            $this->Binding = $this->item->ItemAttributes->Binding;
            $this->Price = $this->item->ItemAttributes->ListPrice->Amount;
            $this->CutPrice = $this->item->OfferSummary->LowestNewPrice->Amount;
            $this->Stock = $this->item->OfferSummary->TotalNew;
            $this->URL = "http://www.amazon.co.jp/o/ASIN/" . $this->ASIN . "/" . $this->options['associateTag'];
            $this->URLMobile = "http://www.amazon.co.jp/gp/aw/rd.html?url=/gp/aw/d.html&lc=msn&dl=1&a=".
                $this->ASIN.'&uid=NULLGWDOCOMO&at='. $this->options['associateTag'];
            $this->KeywordURL = "http://www.amazon.co.jp/gp/search?ie=UTF8&index=blended&tag=" . $this->options['associateTag'] . "&keywords=";
            $this->Artist = $this->item->ItemAttributes->Artist;

            // 価格を設定
            if ((empty($this->Price)) && (!empty($this->CutPrice))) {
                $this->Price = $this->CutPrice;
            }
            if ((!empty($this->CutPrice)) && (((int)$this->Price) > ((int)$this->CutPrice))) {
                $this->discounted = true;
            }

            // タイプ別処理
            if ($this->Type == "Book") {
                $this->str_creator = __("Author", "$this->i18nDomain");
                $this->pubDate = split("-", $this->item->ItemAttributes->PublicationDate);

            } else {
                $this->str_creator = __("Player", "$this->i18nDomain");
                $this->pubDate = split("-", $this->item->ItemAttributes->ReleaseDate);

            }

            list($this->pubDate["year"], $this->pubDate["month"], $this->pubDate["day"]) = $this->pubDate;

            if (($this->Type == "DVD") || ($this->Type == "Music")) {
                $this->NumOfDiscs = $this->item->ItemAttributes->NumberOfDiscs;
                $this->RunningTime = $this->item->ItemAttributes->RunningTime;
                if ($this->RunningTime > 60) {
                    $hour = round($this->RunningTime / 60);
                    $min = round($this->RunningTime % 60);
                    $this->RunningTime = "{$hour} ". __("hour", "$this->i18nDomain") . " {$min} " . __("min", "$this->i18nDomain");
                } else {
                    $this->RunningTime += " ". __("min", "$this->i18nDomain");
                }

            }else {
                $this->NumOfDiscs = "";
                $this->RunningTime = "";

            }

            //カバー画像を取得

            $this->Image = $this->getCover();

            return true;

        }

	/*** Amazonコードを生成 ***/

        function makeCode() {

            $htmlCode = "\n";

            $htmlCode .= $this->createGraphicalCode();

            $htmlCode = preg_replace("/<\/(div|p|table|tbody|tr|dl|dt|dd|li)>/", "</$1>\n", $htmlCode);
            $htmlCode = str_replace('&', '&amp;', $htmlCode);

            return $htmlCode;

        }

	/*** 入力されたASINをチェック ***/

        function checkASIN() {

            if (empty($this->asin_)) {
                $this->errors[] = __("Please set ASIN for Amazon-Linkage Plugin.", "$this->i18nDomain");
            }

            $this->asin_ = preg_replace("/[\- ]/", "", $this->asin_);
            $length = strlen($this->asin_);

            if (($length != 9) && ($length != 10) && ($length != 13)) {
                $this->errors[] = __("Please check the length of ASIN (accept only 9, 10, 13 letters one).", "$this->i18nDomain");
            }


            // ASIN(ここではISBN)を10桁に変換
            switch ($length) {
                case "13": $this->ISBN_13to10(); break;
                case "9" : $this->ISBN_9to10(); break;
                case "12": $this->ISBN_12to10(); break;
            }

        }


	/*** コードを生成 ***/

        function createGraphicalCode() {

            $htmlCode .= '<div class="riderAmazon">';
            $htmlCode .= '<div class="hreview">';
            $htmlCode .= "\n".'<div class="item item-'. $this->ASIN .'">';

            // タイトル
            $htmlCode .= '<div class="fn">';
            $htmlCode .= '<a href="' .$this->URL .'" class="url">';
            $htmlCode .= $this->Title;
            $htmlCode .= (($this->NumOfDiscs >= "2") ? " ({$this->NumOfDiscs}".__("discs", "$this->i18nDomain").")" : "");
            $htmlCode .= "</a>";
            $htmlCode .= "</div>";


            // カバー画像
            $htmlCode .= '<div class="image">';
            $htmlCode .= '<a href="'.$this->URL.'" class="url" ';
            $htmlCode .= 'title="Amazon.co.jp: ">'. $this->Image . "</a>";
            $htmlCode .= "</div>";

            // 購入者情報
            $htmlCode .= '<div class="reports">'.$this->getActionData()."</div>";


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
            if (!empty($this->Creator) || !empty($this->Author)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>". $this->str_creator ."</th>";
                $htmlCode .= "<td>";
                $htmlCode .= $this->getCreators();
                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }//製作者情報がなければ
            elseif(!empty($this->Artist)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>Artist</th>";
                $htmlCode .= '<td><a href="' . $this->KeywordURL . urlencode($this->Artist) .'">'. $this->Artist .'</a></td>';
                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }

            // 製造元
            if (!empty($this->Manufacturer)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>". __("Manufacturer", "$this->i18nDomain") ."</th>";
                $htmlCode .= '<td><a href="' . $this->KeywordURL . urlencode($this->Manufacturer) .'">'.
                    $this->Manufacturer .'</a></td>';
                $htmlCode .= "</tr>";
            }

            // 発売日
            if (!empty($this->pubDate["year"])) {
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
            if (!empty($this->Price)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>". __("Price", "$this->i18nDomain") ."</th>";
                $htmlCode .= "<td>";

                $htmlCode .= $this->getPrice();

                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }

            // 再生時間
            if (!empty($this->RunningTime)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>". __("Running Time", "$this->i18nDomain") ."</th>";
                $htmlCode .= "<td>". $this->RunningTime ."</td>";
                $htmlCode .= "</tr>";
            }

            $htmlCode .= "</tbody>";
            $htmlCode .= '</table><br clear="both" />';

            $htmlCode .= "</div>";


            $htmlCode .= "</div></div>";

            $htmlCode .= '<br clear="both" />';


            return $htmlCode;

        }


	/*** 購入者情報を返す ***/

        function getActionData() {

        //   if (true === $this->show_iteminfo) {

            $actionData = $this->_loadAmazonReports($this->ASIN);
            //     }

            return $actionData;

        }

        /**
         *詳細を見るボタン
         *
         *
         *@return $htmlCode
         */
        function showDetailButton() {

            $tmpCode = '<a href="'.$this->URL.'" title="Amazon.co.jp:' .$this->Title. '" class="showdetailbutton" >';
            $tmpCode .='<img src="'.$this->pluginDirUrl. '/images/' . $this->showDetailButtonImg .'" title="amazon.co.jpで詳細情報を見る" alt="amazon.co.jpで詳細情報を見る"/></a>';

            return $tmpCode;
        }

	/*** カートに入れるボタンを表示するコードを返す ***/

        function showAddCartButton() {

            $tmpCode = '<form class="showaddcartbutton" action="http://www.amazon.co.jp/gp/aws/cart/add.html" method="post">';
            $tmpCode .= '<input name="ASIN.1" value="'. $this->ASIN .'" type="hidden" />';
            $tmpCode .= '<input name="Quantity.1" value="1" type="hidden" />';
            $tmpCode .= '<input name="AssociateTag" value="'. $this->options['associateTag'] .'" type="hidden" />';
            $tmpCode .= '<input name="SubscriptionId" value="'. $this->options['accessKeyId'] .'" type="hidden" />';

            $tmpCode .= '<input name="submit.add-to-cart" type="image" ';
            $tmpCode .= 'src="'. $this->pluginDirUrl. '/images/' . $this->addCartButtonImg . '" ';
            $tmpCode .= 'alt="'. __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain"). '" ';
            $tmpCode .= 'title="'. __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain"). '" />';
            $tmpCode .= '</form>';

            return $tmpCode;

        }

        function getPrice() {

            $tmpCode .= (($this->discounted == true) ? "<del>" : "");

            // 定価
            $tmpCode .= ($this->Currency == "USD") ?
                "$ ". number_format($this->Price) : number_format($this->Price) . __("yen", "$this->i18nDomain");

            // 割引していればその価格を表示
            if ($this->discounted == true) {
                $tmpCode .= "</del> ";
                $tmpCode .= ($this->Currency == "USD") ?
                    "$ ".number_format($this->CutPrice) : number_format($this->CutPrice). __("yen", "$this->i18nDomain");
                $CutRate	= round(( 1 - ( $this->CutPrice / $this->Price )) * 100);
                $tmpCode .= (($CutRate > 0) ? " (<em>{$CutRate}%</em> OFF)" : "");
            }

            return $tmpCode;

        }

	/*** 製作者情報を5人まで返す	***/

        function getCreators() {

            unset($tmpCode);



            if($this->Type == "Book") {
                $this->Creator = $this->Author;
            // var_dump($this->Author);
            // var_dump($this->Creator);
            }


            if (isset($this->Creator[1])) {

                for ($q=0; $q<5; $q++) {
                    if (isset($this->Creator[$q])) {
                        $tmpCode .= '<a href="' . $this->KeywordURL . urlencode($this->Creator[$q]) .'">';
                        $tmpCode .= $this->Creator[$q]. "</a>";
                        $tmpCode .= "<br />";
                    }
                }

            } else {
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

        function getCover() {

        // 1フォルダにキャッシュする容量が大きくなりすぎないように3つのフォルダに分ける
            switch (substr($this->ASIN, 0, 1)) {
                case "0": $path = "0"; break;
                case "4": $path = "4"; break;
                case "B": $path = "B"; break;
                default : $path = "unknown"; break;
            }


            // カバー画像のパス
            $img_path =  $this->pluginDir . "/cache/img/" .$path."/".$this->ASIN. ".jpg";
            $img_url = $this->pluginDirUrl . "/cache/img/" .$path."/".$this->ASIN. ".jpg";

            unset($source);

            // キャッシュされた画像の設定
            if (file_exists($img_url)) {

                list($this->width_ , $this->height_) = getImageSize($img_url);

            // キャッシュがない場合、画像を取得して設定
            } else {

            // 入手可能なできるだけ大きい画像を取得


                if (!empty($this->item->LargeImage->URL)) {
                    $source = $this->item->LargeImage->URL;
                } elseif (!empty($this->item->MediumImage->URL)) {
                    $source = $this->item->MediumImage->URL;
                } elseif (!empty($this->item->SmallImage->URL)) {
                    $source = $this->item->SmallImage->URL;
                }

                // 外部に画像がなかった場合
                if (empty($source)) {

                    return $this->setLackedCover();

                // 外部に画像があった場合
                } else {

                    list($width, $height, $fileTypes) = getImageSize($source);
                    $longest = ($width > $height) ? $width : $height;

                    // この場合も画像はないと判定
                    if (($width == 1) || ($width == 0)) {

                        return $this->setLackedCover();

                    }

                    // リサイズ値より画像の長辺の方が長い場合はリサイズ
                    if ( $longest > $this->resize ) {
                        $percent = round($this->resize / $longest, 2);
                        $this->width_ = round($width * $percent);
                        $this->height_ = round($height * $percent);
                    } else {
                        $this->width_ = $width;
                        $this->height_ = $height;
                    }

                }

                // 画像の読み込み
                switch($fileTypes) {
                    case "2": $bg = ImageCreateFromJPEG($source);
                        break;
                    case "3": $bg = ImageCreateFromPNG($source);
                        break;
                    case "1": $bg = ImageCreateFromGIF($source);
                        break;
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
            $Image .= 'class="photo" ';
            //            $Image .= 'class="photo reflect rheight20 ropacity40" ';
            $Image .= 'alt="' . $this->Title . __(" - cover art", "$this->i18nDomain") .'" />';

            return $Image;

        }

	/*** カバー画像がない場合、その旨のカバー画像を設定する ***/

        function setLackedCover() {

            list($this->width_ , $this->height_) = array(160, 260);
            $Image = '<img src="'. $this->pluginDirUrl .'/images/printing.png" ';
            $Image .= 'width="160" height="260" class="photo" ';
            $Image .= 'alt="'. __("Cover image is not found", "$this->i18nDomain") .'" />';


            return $Image;

        }

	/*** 9桁ISBNにチェックデジットを足す ***/

        function ISBN_9to10() {

        // 総和を求める
            for ($digit=0, $i=0; $i<9; $i++) {
                $digit += $this->asin_{$i} * (10 - $i);
            }

            // 11から総和を11で割った余りを引く（10の場合はX, 11の場合は0に）
            $digit = (11 - ($digit % 11)) % 11;
            if ($digit == 10) {
                $digit = "X";
            }

            $this->asin_ .= $digit;

        }

	/*** 13桁新ISBNを10桁旧ISBNにする ***/

        function ISBN_13to10() {

            $this->asin_ = substr($this->asin_ , 3, 9); // 978+チェックデジット除去
            return $this->ISBN_9to10();

        }

	/*** 12桁新ISBNを10桁旧ISBNにする ***/

        function ISBN_12to10() {

            $this->asin_ = substr($this->asin_ , 3, 9); // 978除去
            return $this->ISBN_9to10();

        }




	/*** エラーがあるかどうか調べる ***/

        // レポートをゲット
        function getReport() {

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
                'email' => $this->options['associateEmail'],
                'password' => $this->options['associatePassword'],
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

            if (!empty($response['body'])) {

                $this->report = $response['body'];
                return true;

            } else {

                return false;

            }


        }

        // ゲットしたレポートを出力
        function saveReport() {

        // レポートを捕捉
            ob_start();
            print_r($this->report);
            $buffer = ob_get_contents();
            ob_end_clean();

            // 出力
            $fn = "{$this->pluginDir}/report/{$this->docType}.xml";
            file_put_contents($fn,$buffer);
            chmod($fn,0666);

            return true;

        }


        function isError() {

            if (count($this->errors) > 0) {

                $msg =	'<p class="error">';

                foreach($this->errors as $error) {
                    $msg .= "<li>エラー：{$error}</li>";
                }

                $msg .= "</ul>";

                return $msg;

            } else {

                return false;

            }

        }


        function makeDB() {

        // あらかじめ作成したXMLを取得
            $this->report = simplexml_load_file($this->pluginDir."/report/report.xml");
            // DBを更新
            $this->updateDB();

        }


        /**
         * データベース wp_amazonreport から clicks orders をよみこむ
         *
         * @param asin
         * @return code
         */
        function _loadAmazonReports($asin) {

            global $wpdb;
            $table_name = $wpdb->prefix . "amazonreport";

            $sql = "SELECT orders,clicks,asin FROM " . $table_name . " WHERE asin = '". $asin ."'";
            $request = $wpdb->get_row($sql);


            if (!empty($request->orders)) {

                $code .= '<div class="orders" ><img src="'.$this->pluginDirUrl.'/images/buys.png" width="16" height="16" alt="購入数" /> '.
                    "<strong>{$request->orders}人</strong>が購入しました</div>";

            }

            if (!empty($request->asin)) {

                $code .= '<div class="clicks"><img src="'.$this->pluginDirUrl.'/images/clicks.png" width="16" height="16" alt="クリック数" /> '.
                    "このサイトで<em>{$request->clicks}人</em>がクリック</div> ";

            }

            return $code;

        }

        function updateDB() {

            global $wpdb;
            $table_name = $wpdb->prefix . "amazonreport";

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
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
            foreach ($this->report->ItemsNoOrders->Item as $item) {
                $asin = (string) $item["ASIN"];
                $items[$asin] = array(
                    "Title"	=> str_replace(",", "", (string) $item["Title"]),
                    "Clicks" => (int) $item["Clicks"]
                );
            }

            // 購入された商品を配列に格納
            foreach ($this->report->Items->Item as $item) {
                $asin = (string) $item["ASIN"];
                $items[$asin] = array(
                    "Title"	=> str_replace("'", "", (string) $item["Title"]),
                    "Price"	=> str_replace(",", "", (string) $item["Price"]),
                    "Clicks" => (int) $item["Clicks"],
                    "Orders" => (int) $item["Qty"]
                );
            }

            // 購入された商品のクリック数を修正
            foreach ($this->report->ItemsOrderedDifferentDay->Item as $item) {
                $asin = (string) $item["ASIN"];
                $items[$asin]["Clicks"] = (int) $item["Clicks"];
            }

            foreach ($items as $asin => $item) {

            // データを作成 or 更新
                $sql = "SELECT * FROM	". $table_name . " WHERE asin = '".$asin."'";
                $request = $wpdb->query($sql);

                unset($commands);

                $item["Title"] = htmlspecialchars($item["Title"]);

                if ($wpdb->get_row($sql)) {
                // データがある場合は更新
                    $commands[] =	"UPDATE ". $table_name . " SET clicks = '".$item["Clicks"]."' WHERE asin = '".$asin."'";
                    $commands[] =	"UPDATE ". $table_name . " SET orders = '".$item["Orders"]."' WHERE asin = '".$asin."'";
                } else {
                // データがない場合は作成
                    $commands[] = "INSERT INTO ". $table_name . " (asin, title, clicks, orders, price) ".
                        "VALUES ('".$asin."','".$item["Title"]."','".$item["Clicks"]."','".$item["Orders"]."','".$item["Price"]."')";
                }

                // クエリを投げて更新
                foreach ($commands as $command) {
                    $request = $wpdb->query($command);
                }

            }

        }

    } //end of class

} //end of class exists

if ( class_exists( 'RiderAmazon' ) ) {
    $rideramazon = new RiderAmazon();
}

?>
