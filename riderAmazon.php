<?php
/*
  Plugin Name: RiderAmazon
  Plugin URI: http://retujyou.com/rideramazon/
  Description: 投稿画面でASINの検索。本文中に[amazon]ASIN[/amazon]を記入でAmazon.co.jpから情報を取得。
  Author: rui_mashita
  Version: 0.2.0
  Author URI: http://retujyou.com
  Special Thanks: Tomokame (http://tomokame.moo.jp/)
  Special Thanks: Keith Devens.com (http://keithdevens.com/software/phpxml)
  Special Thanks: websitepublisher.net (http://www.websitepublisher.net/article/aws-php/)
  Special Thanks: hiromasa.zone :o) (http://hiromasa.zone.ne.jp/)
  Special Thanks: PEAR :: Package :: Cache_Lite (http://pear.php.net/package/Cache_Lite)
  Special Thanks: よしとも (http://wppluginsj.sourceforge.jp/amazonlink/)
  Special Thanks: leva (http://note.openvista.jp/187/(
*/

/* * ******** 使い方 *********
  1.プラグインディレクトリにアップロード
  2.管理画面で有効化
  3.投稿画面で、ASINの検索が出来る。
  4.本文中に[amazon]ASIN[/amazon]を記入。

 * ************************* */

/* * ******** Notes *********
  ECS4.0 に対応しています
  PHP5 で動作します
  LGPL で提供されている Lite.php を同梱
  PEAR::HTTP_Client が必要
  GD が必要
 * ************************* */

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




if (!class_exists('RiderAmazon')) {

    class RiderAmazon {

        var $width;
        var $height;
        //詳細を表示ボタンの画像
        var $showDetailButtonImg = "showdetail119.png";
        //カートに入れるボタンの画像
        var $addCartButtonImg = "gocart119.png";
        // 国際化リソースドメイン
        var $i18nDomain = 'riderAmazon';
        // RiderAmazon
        var $pluginDirName;
        // /path/to/RiderAmazon
        var $pluginDir;
        // http://sample.com/path/to/RiderAmazon
        var $pluginDirURL;
        // wp_opions table option_name column
        var $optionName = "riderAmazonOption";
        var $options;
        var $defaultOptions;

        /*
         * コンストラクタ
         *
         * @param void
         *
        */
        function __construct() {

// plugin path
            $dirs = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
// Rider Amaozn
            $this->pluginDirName = array_pop($dirs);
// /path/to/RiderAmazon
            $this->pluginDir = WP_PLUGIN_DIR . '/' . $this->pluginDirName;
// http://sample.com/path/to/RiderAmazon
            $this->pluginDirURL = WP_PLUGIN_URL . '/' . $this->pluginDirName;

            //Localization
            $locale = get_locale();
            load_plugin_textdomain($this->i18nDomain, $this->pluginDir. '/locales/', $this->pluginDirName. '/locales/' );


            $this->defaultOptions = array(
                    'associateTag' => __('retujyou-22', $this->i18nDomain),
                    'accessKeyId' => '',
                    'secretAccessKey' => '',
                    'associateEmail' => '',
                    'associatePassword' => '',
                    'xmlCache' => '',
                    'imageCache' => '',
                    'maxSize' => '160'
            );
// load $this->optinos from DB
            $this->_loadOptions();

// add action link
            add_filter( 'plugin_action_links_'. plugin_basename(__FILE__), array(&$this, '_addPluginActionLinks'));
// admin option page hooks
            add_action('admin_menu', array(&$this, '_addAdminOptionPage'));
// admin post/page hooks
            add_action('admin_menu', array(&$this, '_addMetaBox'));
            add_action('wp_ajax_riderAmazon_ItemSearch', array(&$this, '_ajaxItemSearch'));

// post hooks
            add_shortcode('amazon', array(&$this, 'replaceCode'));
            add_action('wp_head', array(&$this, '_addWpHead'));


            register_activation_hook(__FILE__, array(&$this, '_rideramazonRegisterHook'));

            register_deactivation_hook(__FILE__, array(&$this, '_rideramazonNotRegisterHook'));



            //    echo wp_get_schedule('_rideramazonDailyEvent');
        }

        /**
         * Retrieves the plugin's options from the database.
         *
         *
         * @return boolean
         */
        function _loadOptions() {
            if (false === ( $options = get_option($this->optionName) )) {
                $this->options = $this->defaultOptions;
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
         * Save the options value to the WordPress database
         *
         * @return boolean true if the save was successful.
         */
        function _saveOptions() {
            return update_option($this->optionName, $this->options);
        }

        /**
         * get option
         *
         * @param string $key
         * @return mixed
         *
         */
        function _getOption($key) {
            return $this->options[$key];
        }

        /**
         * set option
         *
         * @param string $key
         * @param mixed $value
         * @return void
         *
         */
        function _setOption($key, $value) {
            $this->options[$key] = $value;
        }

        function _addPluginActionLinks($actions) {
            $link = '<a href="'.get_bloginfo( 'wpurl' ).'/wp-admin/plugins.php?page='.basename(__FILE__).'" >'. __('Settings') .'</a>';
            array_unshift($actions, $link);
            return $actions;
        }

        /**
         * Add admin option page
         *
         * @return void
         */
        function _addAdminOptionPage() {

            $optionPage = add_plugins_page(
                    __('Rider Amazon Option', $this->i18nDomain),
                    __('Rider Amazon', $this->i18nDomain),
                    'activate_plugins',
                    basename(__FILE__),
                    array(&$this, '_adminOptionPage')
            );


            add_action('admin_print_styles-' . $optionPage, array(&$this, '_addAdminOptionPrintStyles'));
            add_action('admin_print_scripts-' . $optionPage, array(&$this, '_addAdminOptionPrintScripts'));
            add_action('admin_notices', array(&$this, "_addAdminNotices"));
        }

        /**
         * 管理画面メニューのオプションページ
         *
         * @return void
         */
        function _adminOptionPage() {
            ?>
<div class="wrap" id="riderAmazonAdminOptionPage">
    <h2><?php _e('Rider Amazon Options', $this->i18nDomain); ?></h2>

    <div style="width: 100%;" class="postbox-container">
        <div class="metabox-holder" >

            <div class="meta-box-sortables ui-sortable">

                <div class="postbox" id="present">
                    <div title="Click to toggle" class="handlediv"><br></div>
                    <h3 class="hndle"><span><?php _e('Present for Plugin Editor', $this->i18nDomain); ?></span></h3>
                    <div class="inside">
                        <div><label><?php _e('Let the monitor out of your sight ,and close your eyes for a moment. If you can imagine the real existence of the plugin editor, two things you can do right now.', $this->i18nDomain); ?></label>
                        </div>

                        <div class="alignleft inside" style="width: 45%;">
                            <p>1. <?php _e('you can send present for me.', $this->i18nDomain); ?> </p>
                            <div class="inside">
                                <a href="<?php _e('http://amzn.com/w/37VZ4S64TVJ87', $this->i18nDomain); ?>">
                                    <img src="<?php echo $this->pluginDirURL . '/images/amazon.gif'; ?>" width="122" title="amazon.co.jp wish list" />
                                </a>
                            </div>
                        </div>
                        <div class="alignright inside" style="width: 45%;">
                            <p>2. <?php _e('you can donate to me.', $this->i18nDomain); ?></p>
                            <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                                <input type="hidden" name="cmd" value="_donations" />
                                <input type="hidden" name="business" value="BAT75LYEU6B3L" />
                                <input type="hidden" name="item_name" value="All in One SEO Multibyte Descriptions" />
                                <input type="hidden" name="item_number" value="1" />
                                <input type="hidden" name="item_number" value="1" />
                                <input type="hidden" name="currency_code" value="<?php _e('USD', $this->i18nDomain); ?>" />
                                <input type="hidden" name="country" value="<?php _e('US', $this->i18nDomain); ?>" />
                                <input type="hidden" name="first_name" value="<?php _e('Thank you', $this->i18nDomain); ?>" />
                                <input type="hidden" name="last_name" value="<?php _e('for donating', $this->i18nDomain); ?>" />
                                <input type="hidden" name="lc" value="<?php _e('US', $this->i18nDomain); ?>JP" />
                                <input type="hidden" name="charset" value="UTF-8" />
                                <input
                                    type="image"
                                    src="<?php _e('https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif', $this->i18nDomain); ?>"
                                    border="0"
                                    name="submit"
                                    alt="PayPal"
                                    />
                                <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                            </form>
                        </div>
                        <div class="clear"></div>
                    </div>
                </div>

                <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
                    <div class="postbox" id="setting">
                        <div title="Click to toggle" class="handlediv"><br></div>
                        <h3 class="hndle"><span><?php _e('Required Setting', $this->i18nDomain); ?></span></h3>

                        <div class="inside">


                            <fieldset>
                                <legend><?php _e('Product Advertising API Setting', $this->i18nDomain); ?></legend>
                                <p>
                                                <?php _e('If you dont set this option , you cant display item in post view, cant use ItemSearch in post custom box.', $this->i18nDomain); ?><br />
                                                <?php _e('Reference：<a href="https://affiliate-program.amazon.com/gp/advertising/api/detail/main.html">Amazon Product Advertising API</a>', $this->i18nDomain); ?>
                                </p>

                                <table class="form-table">

                                    <tr>
                                        <th><label for="accessKeyId"><?php _e('Access Key ID', $this->i18nDomain); ?></label></th>
                                        <td><input type="text" name="accessKeyId" id="accessKeyId" value="<?php echo htmlspecialchars($this->_getOption('accessKeyId')); ?>" />
                                            <span class="description"><?php _e('Access Key ID.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><label for="secretAccessKey"><?php _e('secretAccessKey', $this->i18nDomain); ?></label></th>
                                        <td><input type="text" name="secretAccessKey" id="secretAccessKey" value="<?php echo htmlspecialchars($this->_getOption('secretAccessKey')); ?>" />
                                            <span class="description"><?php _e('secretAccessKey.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>

                                </table>
                            </fieldset>

                            <fieldset>
                                <legend><?php _e('Amazon Associates Setting', $this->i18nDomain); ?></legend>
                                <p>
                                                <?php _e('<a href="https://affiliate-program.amazon.com/" >Amazon.com Associates</a>', $this->i18nDomain); ?>
                                </p>

                                <table class="form-table">
                                    <tr>
                                        <th><label for="associateTag"><?php _e('AssociateTag', $this->i18nDomain); ?></label></th>
                                        <td><input type="text" name="associateTag" id="associateTag" value="<?php echo htmlspecialchars($this->_getOption('associateTag')); ?>" />
                                            <span class="description"><?php _e('Amazon Associates Tracking ID.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><label for="associateEmail"><?php _e('Associate Email', $this->i18nDomain); ?></label></th>
                                        <td><input type="text" name="associateEmail" id="associateEmail" value="<?php echo htmlspecialchars($this->_getOption('associateEmail')); ?>" />
                                            <span class="description"><?php _e('Amazon Associates Login Email.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>


                                    <tr>
                                        <th><label for="associatePassword"><?php _e('Associate Password', $this->i18nDomain); ?></label></th>
                                        <td><input type="password" name="associatePassword" id="associatePassword" value="<?php echo htmlspecialchars($this->_getOption('associatePassword')); ?>" />
                                            <span class="description"><?php _e('Amazon Associates Login Password.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>

                                </table>
                            </fieldset>

                            <div class="alignright">
                                <input class="button-primary" id="saveOption" type="submit" value="<?php _e('Update Options &raquo;', $this->i18nDomain); ?>" />
                            </div>
                            <input type="hidden" name="optionMethod" value="saveOption" />


                            <div class="clear"></div>
                        </div>
                    </div>

                    <div class="postbox" id="setting">
                        <div title="Click to toggle" class="handlediv"><br></div>
                        <h3 class="hndle"><span><?php _e('Custom Setting', $this->i18nDomain); ?></span></h3>

                        <div class="inside">


                            <fieldset>
                                <legend><?php _e('Cache Setting', $this->i18nDomain); ?></legend>

                                <table class="form-table">

                                    <tr>
                                        <th><label for="cache"><?php _e('cache', $this->i18nDomain); ?></label></th>
                                        <td><input type="checkbox" name="cache" id="cache" value="1" <?php checked('1', $this->_getOption('cache')); ?> />
                                            <span class="description"><?php _e('Image cache.', $this->i18nDomain); ?></span>
                                        </td>
                                    </tr>

                                </table>
                            </fieldset>

                            <div class="alignright">
                                <input class="button-primary" id="saveOption" type="submit" value="<?php _e('Update Options &raquo;', $this->i18nDomain); ?>" />
                            </div>
                            <input type="hidden" name="optionMethod" value="saveOption" />


                            <div class="clear"></div>
                        </div>
                    </div>
                </form>

                <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
                    <p class="submit">
                        <input class="button" id="resetOption" type="submit" value="<?php _e('Reset Options &raquo;', $this->i18nDomain); ?>" />
                    </p>
                    <input type="hidden" name="optionMethod" value="resetOption" />
                </form>
            </div>
        </div>
    </div>
    <div class="clear"></div>
</div>

            <?php

        }

        function _addAdminOptionPrintStyles() {
            wp_enqueue_style('riderAmazonAdminOption', $this->pluginDirURL . '/css/riderAmazonAdminOption.css');
            wp_enqueue_style('dashboard');
        }

        function _addAdminOptionPrintScripts() {
            wp_enqueue_script('dashboard');
            wp_enqueue_script('riderAmazonAdminOption', $this->pluginDirURL . '/js/riderAmazonAdminOption.js', array('jquery'), false);
        }

        /**
         * 管理画面のエラー表示を追加する
         *
         * @return void
         */
        function _addAdminNotices() {

            if ("saveOption" == @$_POST['optionMethod']):

                $this->_setOption('associateTag', @$_POST['associateTag']);
                $this->_setOption('accessKeyId', @$_POST['accessKeyId']);
                $this->_setOption('secretAccessKey', @$_POST['secretAccessKey']);
                $this->_setOption('associateEmail', @$_POST['associateEmail']);
                $this->_setOption('associatePassword', @$_POST['associatePassword']);
                $this->_setOption('cache', @$_POST['cache']);

                $this->_saveOptions();
                ?>
<div id="riderAmazonAdminOptionUpdated" class="updated fade" >
    <p><?php _e('Updated Rider Amazon Options' , $this->i18nDomain); ?></p>
</div>
            <?php
            endif;

            if ("resetOption" == @$_POST['optionMethod']) {

                $this->_deleteOptions();
            }

            $this->_loadOptions();


            if ($this->_loadOptions() == false):
                ?>

<div id="riderAmaoznAdminOptionError" class="error fade" >
    <p><?php _e('You haven\'t set Rider Amazon Options yet. Set', $this->i18nDomain); ?>
        <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/plugins.php?page=riderAmazon.php'; ?>">Rider Amazon Options</a></p>
</div>


            <?php

            endif;
        }

        /*
                                 * 記事作成画面のボックスを追加するフック
                                 *
                                 * @return none
        */
        function _addMetaBox() {
            add_meta_box('rideramazondiv', __('RiderAmazon ItemSearch', $this->i18nDomain), array(&$this, '_metaBox'), 'post', 'normal');
            add_meta_box('rideramazondiv', __('RiderAmazon ItemSearch', $this->i18nDomain), array(&$this, '_metaBox'), 'page', 'normal');

            add_action('admin_print_styles', array(&$this, '_addAdminPrintStyles'));
            add_action('admin_print_scripts', array(&$this, '_addAdminPrintScripts'));

        }

        /*
* 記事作成画面のドッキングボックス
*
* @return none
        */
        function _metaBox() {
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
<input type="hidden" name="riderAmazon_dir" id="riderAmazon_dir" value="<?php echo ABSPATH . '/wp-includes/'; ?>" />
<input type="hidden" name="riderAmazon_totalPages" id="riderAmazon_totalPages" value="0" />
<input type="hidden" name="riderAmazon_currentPage" id="riderAmazon_currentPage" value="1" />
<input type="hidden" name="riderAmazon_lastSearchIndex" id="riderAmazon_lastSearchIndex" value="" />
<input type="hidden" name="riderAmazon_lastKeyword" id="riderAmazon_lastKeyword" value="" />
<select name="riderAmazon_searchIndex" id="riderAmazon_searchIndex" >
                <?php
                foreach ($categories as $key => $value) {
                    print "\t" . '<option value="' . $value . '">' . $key . "</option>\n";
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
         * 管理画面用スクリプトの登録をする
         *
         *
         * @return none
         */
        function _addAdminPrintScripts() {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-effects-core', $this->pluginDirURL . '/js/jquery/effects.core.js', array('jquery', 'jquery-ui-core'), false);
            wp_enqueue_script('jquery-effects-highlight', $this->pluginDirURL . '/js/jquery/effects.highlight.js', array('jquery', 'jquery-ui-core', 'jquery-effects-core'), false);
            wp_enqueue_script('riderAmazonJs', $this->pluginDirURL . '/js/riderAmazon.js', array('jquery', 'jquery-ui-core', 'jquery-effects-core', 'jquery-effects-highlight'), false);
        }

        /**
         * 管理画面のヘッダにCSSを登録
         *
         *
         * @return void
         */
        function _addAdminPrintStyles() {
            wp_enqueue_style('riderAmazonAdmin', $this->pluginDirURL . '/css/riderAmazonAdmin.css');
        }

        /**
         * 管理画面にてamazonに問い合わせ検索
         *
         *
         * @return
         */
        function _ajaxItemSearch() {
            // Amazon Product Advertising API 用パラメータ作成 jsから受けとった情報を格納
            $amazonParam = array(
                    'operation' => 'ItemSearch',
                    'keyword' => $_POST['keyword'],
                    'searchIndex' => $_POST['searchIndex'],
                    'currentPage' => $_POST['currentPage'],
                    'responseGroup' => 'Request,ItemAttributes,Small,Images'
            );


// Amazon Product Advertising API にアクセスして XML を返してもらう
            $amazonXml = $this->_fetchAmazonXml($amazonParam);

            $parsedAmazonXml = simplexml_load_string($amazonXml);

            if ($parsedAmazonXml === false) {
                die("alert('XML parse error')");
            }

// エラー表示用
            $isValid = $parsedAmazonXml->Items->Request->IsValid;
            if (isset($isValid)) {
                $error = $parsedAmazonXml->Items->Request->Errors->Error;
            } else {
                $error = $parsedAmazonXml->Error;
            }
            $totalPages = (string) $parsedAmazonXml->Items->TotalPages;
            $itemTableHTML = $this->_buildItemTableHTML($parsedAmazonXml);

// js に返すパラメータを作成
            $resultArray = array(
                    'itemTableHTML' => $itemTableHTML,
                    'totalPages' => $totalPages,
                    'error' => $error
            );

            $resultJson = json_encode($resultArray);

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
            $params['AssociateTag'] = $this->options['associateTag'];
            $params['AWSAccessKeyId'] = $this->options['accessKeyId'];
            $params['Version'] = '2009-07-01';

            switch ($amazonParam['operation']) {
                case 'ItemSearch':
                    $params['Operation'] = $amazonParam['operation'];
                    $params['ResponseGroup'] = $amazonParam['responseGroup'];
                    $params['SearchIndex'] = $amazonParam['searchIndex'];
                    $params['Keywords'] = $amazonParam['keyword'];
                    $params['ItemPage'] = $amazonParam['currentPage'];
                    if ($amazonParam['searchIndex'] != 'All') {
                        $params['Sort'] = 'salesrank';
                    }
                    break;
                case 'ItemLookup':
                    $params['Operation'] = $amazonParam['operation'];
                    $params['ItemId'] = $amazonParam['itemId'];
                    $params['ResponseGroup'] = $amazonParam['responseGroup'];
                    break;
            }

            $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
            ksort($params);

// canonical string を作成します
            $canonical_string = '';
            foreach ($params as $k => $v) {
                $canonical_string .= '&' . $this->_rfc3986Urlencode($k) . '=' . $this->_rfc3986Urlencode($v);
            }
            $canonical_string = substr($canonical_string, 1);

// 署名の作成
            $parsed_url = parse_url($api_interface);
            $string_to_sign = 'GET' . "\n" . $parsed_url['host'] . "\n" . $parsed_url['path'] . "\n" . $canonical_string;
            $signature = base64_encode(hash_hmac('sha256', $string_to_sign, $secret_access_key, true));

            $url = $api_interface . '?' . $canonical_string . '&Signature=' . $this->_rfc3986Urlencode($signature);

            // wp-includes/http.php
            $response = wp_remote_request($url);
            if(is_wp_error($response)) {
                $error = 'Amazon conection error. '. 'message: '.$response['response']['message'].'. code: '.$response['response']['code'];
                die('alert("'.$error.'")');
            }
            $amazonXml = $response['body'];
            return $amazonXml;
        }


        /**
         * RFC3986に合わせ、チルダを除外したURLエンコードをする
         * Product Advertising API のURLエンコードは RFC3986 準拠
         *
         * @param $string
         * @return
         */
        function _rfc3986Urlencode($string) {
            $string = str_replace('%7E', '~', rawurlencode($string));
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
        function _buildItemTableHTML($parsedAmazonXml) {

            $items = $parsedAmazonXml->Items->Item;
            $itemTableHTML = '';

            if (isset($items)):

                $itemPage = (int) $parsedAmazonXml->Items->Request->ItemSearchRequest->ItemPage;
                $number = ( ($itemPage - 1) * 10) + 1;
                $class = '';

                $itemTableHTML = <<< EOF
                        <div id="riderAmazon_resultTable">
                                       <table  class="widefat">
                                         <thead>
                                           <tr>
                                             <th>No.</th>
                                             <th>Image</th>
                                             <th class="titleAndCode">Title & code</th>
                                           </tr>
                                         </thead>
                                         <tbody>
EOF;

                foreach ($items as $item) {
                    $class = ($number % 2 == 0 ) ? 'alt' : '';
                    $itemTableHTML .= <<< EOF
                                                              <tr class="{$class}">
                                                                <th class="number" >
                            {$number}.
                                                                </th>
                                                                <td class="image" >
                                                                  <a href="{$item->DetailPageURL}"><img src="{$item->SmallImage->URL}" alt="{$item->ASIN}" /></a>
                                                                </td>
                                                                <td class="titleAndCode" >
                                                                  <a href="{$item->DetailPageURL}"> {$item->ItemAttributes->Title}</a>
                                                                <br /><br />
                                                                  <code title='AmazonLink コード'>[amazon]{$item->ASIN}[/amazon]</code>
                                                                </td>
                                                              </tr>

EOF;
                    $number++;
                }

                $itemTableHTML .= <<< EOF
                                  </tbody>
                                 </table>
                                </div>
EOF;

            endif;

            return $itemTableHTML;
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
<link rel="stylesheet" type="text/css" href="<?php echo $this->pluginDirURL; ?>/css/riderAmazon.css" />
            <?php
        }

        /**
         * ASINをhtmlに置換する
         *
         * @param $attr,$asinCode
         * @return $htmlCode
         */
        function replaceCode($attr, $asin='') {

            if (!($asin == '')) {
                // 前準備
                $item = $this->_getItemfromAmazon($asin);
                $htmlCode = $this->_buildHTML($item);

                return $htmlCode;
            }

        }


        /**
         *
         * ASIN から item object を取得
         *
         * @param string $asin
         * @return object(SimpleXMLElement) $item
         *
         */
        function _getItemfromAmazon($asin) {

// ASINをチェック
            $this->_checkAsin($asin);

// Amazon Product Advertising API 用パラメータ作成
            $amazonParam = array(
                    'operation' => 'ItemLookup',
                    'itemId' => $asin,
                    'responseGroup' => 'Medium'
            );

            // キャッシュ
            if( $this->options['xmlChache'] == '1') {
                $amazonXml = $this->_cacheAmazonXml($amazonParam);
            }else {
                $amazonXml = $this->_fetchAmazonXml($amazonParam);
            }

            $parsedAmazonXml = simplexml_load_string($amazonXml);

            // AmazonECSか自分がネットワークから離脱している場合
            if (false === $parsedAmazonXml) {
                $this->errors[] = __("An error occured. Please reaload this page.", "$this->i18nDomain");
            }

            // 正常な処理ができなかった場合
            if ("True" === $parsedAmazonXml->Items->Request->IsValid) {
                $this->errors[] = __("Returned data is not valid.", "$this->i18nDomain");
            }

            // 変数の設定
            $item = $parsedAmazonXml->Items->Item;

            return $item;

        }



        /**
         * 入力されたASINをチェック
         *
         * @param <type> $asin
         *
         */
        function _checkAsin($asin) {

            if (empty($asin)) {
                $this->errors[] = __("Please set ASIN for Amazon-Linkage Plugin.", "$this->i18nDomain");
            }

            $asin = preg_replace("/[\- ]/", "", $asin);
            $length = strlen($asin);

            if (($length != 9) && ($length != 10) && ($length != 13)) {
                $this->errors[] = __("Please check the length of ASIN (accept only 9, 10, 13 letters one).", "$this->i18nDomain");
            }

            // ASIN(ここではISBN)を10桁に変換
            switch ($length) {
                case "13": $this->ISBN_13to10($asin);
                    break;
                case "9" : $this->ISBN_9to10($asin);
                    break;
                case "12": $this->ISBN_12to10($asin);
                    break;
            }
        }

        /**
         * 9桁ISBNにチェックデジットを足す
         *
         * @param <type> $asin
         *
         */
        function ISBN_9to10($asin) {

            // 総和を求める
            for ($digit = 0, $i = 0; $i < 9; $i++) {
                $digit += $asin {
                        $i} * (10 - $i);
            }

            // 11から総和を11で割った余りを引く（10の場合はX, 11の場合は0に）
            $digit = (11 - ($digit % 11)) % 11;
            if ($digit == 10) {
                $digit = "X";
            }

            $asin .= $digit;
        }

        /**
         *  13桁新ISBNを10桁旧ISBNにする
         *
         * @param <type> $asin
         *
         */
        function ISBN_13to10($asin) {

            $asin = substr($asin, 3, 9); // 978+チェックデジット除去
            return $this->ISBN_9to10($asin);
        }

        /**
         *  12桁新ISBNを10桁旧ISBNにする
         *
         * @param <type> $asin
         *
         */
        function ISBN_12to10($asin) {

            $asin = substr($asin, 3, 9); // 978除去
            return $this->ISBN_9to10($asin);
        }

        /**
         *
         * AmazonXMLをキャッシュしてパースしたxmlを返す
         *
         *
         * @param <type> $amazonParam
         * @return <type> $parsedAmazonXm
         *
         */
        function _cacheAmazonXml($amazonParam) {

            $asin = $amazonParam['itemId'];

            $parsedAmazonXml = '';
            // キャッシュ作成パッケージを読み込み

            /**
             * キャッシュの設定
             * "lifeTime"は秒単位です、ここでは3日に設定しています
             */
            $options = array(
                    "cacheDir" => $this->pluginDir . "/cache/xml/",
                    "lifeTime" => 3 * 60 * 60 * 24,
                    "automaticCleaningFactor" => 0
            );

            require_once("cache/Lite.php");
            $Cache = new Cache_Lite($options);

            // キャッシュがあるかチェック
            $xmlCache = $Cache->get($asin);
            if ($xmlCache) { // あればそれを利用
                return $xmlCache;
            } else {

                // Amazon Product Advertising API にアクセスして XML を返してもらう
                $amazonXml = $this->_fetchAmazonXml($amazonParam);

                // Amazon Webサービスが落ちているなどしてXMLが返ってこない場合
                if ($amazonXml === false) {
                    return false;
                } else {
                    // XMLが返ってきたら利用
                    // XMLをキャッシュ
                    $Cache->save($amazonXml, $asin);

                    return $amazonXml;
                }
            }
        }


        /*** データを取得 ***/
        function _buildHTML($item) {

            $asin = $item->ASIN;

            $URL = "http://www.amazon.co.jp/o/ASIN/" . $asin . "/" . $this->options['associateTag'];
            $KeywordURL = "http://www.amazon.co.jp/gp/search?ie=UTF8&index=blended&tag=" . $this->options['associateTag'] . "&keywords=";

            $coverImage = (object) array(
                            'URL' => '',
                            'Width' => '',
                            'Height' => ''
            );
echo $this->options['imageCache'] ;
            $this->options['maxSize'] = '180';
            if( $this->options['imageCache'] == '1') {
                $coverImage = $this->_cacheCoverImage($item);
            }else {
                $coverImage = $this->_setCoverImage($item);
            }

            // タイプ別処理
            if ($item->ItemAttributes->ProductGroup == "Book") {
                $str_creator = __("Author", "$this->i18nDomain");
                $pubDate = split("-", $item->ItemAttributes->PublicationDate);
            } else {
                $str_creator = __("Player", "$this->i18nDomain");
                $pubDate = split('-', $item->ItemAttributes->ReleaseDate);
            }

            list( $pubDate['year'], $pubDate['month'], $pubDate['day'] ) = $pubDate;

            if (($item->ItemAttributes->ProductGroup == "DVD") || ($item->ItemAttributes->ProductGroup == "Music")) {
                $NumOfDiscs = $item->ItemAttributes->NumberOfDiscs;
                $RunningTime = $item->ItemAttributes->RunningTime;
                if ($RunningTime > 60) {
                    $hour = round($RunningTime / 60);
                    $min = round($RunningTime % 60);
                    $RunningTime = $hour . __("hour", $this->i18nDomain) . " {$min} " . __("min", $this->i18nDomain);
                } else {
                    $RunningTime += " " . __("min", "$this->i18nDomain");
                }
            } else {
                $NumOfDiscs = "";
                $RunningTime = "";
            }

            // HTMLコードを生成

            $htmlCode = $this->_makeHtmlCode($item, $asin, $URL, $NumOfDiscs, $coverImage, $str_creator, $KeywordURL, $pubDate, $RunningTime);

            return $htmlCode;
        }



        function _setCoverImage($item) {

// 入手可能なできるだけ大きい画像を取得
            $coverImage = $this->_selectMaxImage($item);
            if(empty($coverImage->URL)) {
                $coverImage = $this->_setNoImage();
            }
            $aspect = $coverImage->Width / $coverImage->Height;
            if($coverImage->Width > $coverImage->Height) {
                $coverImage->Width = $this->options['maxSize'];
                $coverImage->Height = round( 1/$aspect * $this->options['maxSize']);
            }else {
                $coverImage->Height = $this->options['maxSize'];
                $coverImage->Width = round( $aspect * $this->options['maxSize']);
            }
            return $coverImage;
        }


        function  _selectMaxImage($item) {
            unset($image);
            $Limg = $item->LargeImage;
            $Mimg = $item->MediumImage;
            $Simg = $item->SmallImage;

            if (empty($image) && !empty($Limg)) {
                $image = $item->LargeImage;
                return $image;
            }
            if ( empty($image) && !empty($Mimg) ) {
                $image = $item->MediumImage;
                return $image;
            }
            if (empty($image) && !empty($Simg)) {
                $image = $item->SmallImage;
                return $image;
            }

        }


        function  _setNoImage() {

            $coverImage = (object) array(
                            'URL' => $this->pluginDirURL . '/images/printing.png',
                            'Width' => '100',
                            'Height' => '100'
            );
            return $coverImage;
        }



        /**
         * cache image
         *
         * @param <type> $item
         * @param <type> $asin
         * @return <type>
         *
         */
        function _cacheCoverImage($item) {
            $asin = $item['ASIN'];

            // 1フォルダにキャッシュする容量が大きくなりすぎないように3つのフォルダに分ける
            switch (substr($asin, 0, 1)) {
                case "0": $path = "0";
                    break;
                case "4": $path = "4";
                    break;
                case "B": $path = "B";
                    break;
                default : $path = "unknown";
                    break;
            }


            // カバー画像のパス
            $cacheImagePath = $this->pluginDir . "/cache/img/" . $path . "/" . $asin . ".jpg";
            $cacheImageURL = $this->pluginDirURL . "/cache/img/" . $path . "/" . $asin . ".jpg";

            // キャッシュされた画像の設定
            if (file_exists($cacheImageURL)) {
                list($coverImage->Width, $coverImage->Height) = getImageSize($cacheImageURL);
                $coverImage->URL = $cacheImageURL;

                // キャッシュがない場合、画像を取得して設定
            } else {
                unset($source);
                // 入手可能なできるだけ大きい画像を取得
                $source = $this->_selectMaxImage($item);
                list($width, $height, $fileTypes) = getimagesize($source);

                // 画像の読み込み
                switch ($fileTypes) {
                    case "2": $bg = ImageCreateFromJPEG($source);
                        break;
                    case "3": $bg = ImageCreateFromPNG($source);
                        break;
                    case "1": $bg = ImageCreateFromGIF($source);
                        break;
                    default : return $this->_setNoImage();
                }

                $aspect = $source->Width / $source->Height;
                if($source->Width > $source->Height) {
                    $source->Width = $this->options['maxSize'];
                    $source->Height = round( 1/$aspect * $this->options['maxSize']);
                }else {
                    $source->Height = $this->options['maxSize'];
                    $source->Width = round( $aspect * $this->options['maxSize']);
                }

                // 画像のリサイズ
                $im = ImageCreateTrueColor($width, $height) or die("Cannot create image");
                ImageCopyResampled($im, $bg, 0, 0, 0, 0, $source->Width, $source->Height, $width, $height);

                // ファイルのキャッシュとメモリキャッシュの廃棄
                ImageJPEG($im, $cacheImagePath, 80);
                ImageDestroy($bg);
                ImageDestroy($im);
            }

            return $coverImage;
        }

        /*                                 * * Amazonコードを生成 ** */
        function _makeHtmlCode($item, $asin, $URL, $NumOfDiscs, $coverImage, $str_creator, $KeywordURL, $pubDate, $RunningTime) {

            $htmlCode = "\n";

            $htmlCode .= '<div class="riderAmazon">';
            $htmlCode .= '<div class="hreview">';
            $htmlCode .= '<div class="item item-' . $asin . '">';

            // タイトル
            $htmlCode .= '<div class="fn">';
            $htmlCode .= '<a href="' . $URL . '" class="url">';
            $htmlCode .= $item->ItemAttributes->Title;
            $htmlCode .= ( ($NumOfDiscs >= "2") ? " ( {
                            $NumOfDiscs
            }" . __("discs", "$i18nDomain") . ")" : "");
            $htmlCode .= "</a>";
            $htmlCode .= "</div>";


            // カバー画像
            $htmlCode .= '<div class="image">';
            $htmlCode .= '<a href="' . $URL . '" class="url" title="Amazon.co.jp: ">';
            $htmlCode .= '<img src="' . $coverImage->URL . '" width="' . $coverImage->Width . '" height="' . $coverImage->Height . '" class="photo" alt="' . $item->ItemAttributes->Title .'" />';
            $htmlCode .= "</a>";
            $htmlCode .= "</div>";

            // 購入者情報
            $htmlCode .= '<div class="reports">' . $this->getActionData($asin) . "</div>";


            $htmlCode .= '<div class="buttons">';
            // 詳細情報を見るボタン
            $htmlCode .= '<a href="' . $URL . '" title="Amazon.co.jp:' . $item->ItemAttributes->Title . '" class="showdetailbutton" >';
            $htmlCode .= '<img src="' . $this->pluginDirURL . '/images/' . $this->showDetailButtonImg . '" title="amazon.co.jpで詳細情報を見る" alt="amazon.co.jpで詳細情報を見る"/></a>';

            // カートに入れるボタン
            $htmlCode .= $this->showAddCartButton($asin);
            $htmlCode .= '</div>';

            $htmlCode .= '<table >';
            // summary="'. __(" \"", "$this->i18nDomain") . $this->Title;
            //		$htmlCode .= __("\" ", "$this->i18nDomain"). __(" information", "$this->i18nDomain"). '"

            $htmlCode .= "<tbody>";

            // 製作者
            if (!empty($item->ItemAttributes->Creator) || !empty($item->ItemAttributes->Author)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>" . $str_creator . "</th>";
                $htmlCode .= "<td>";
                $htmlCode .= $this->getCreators($item, $KeywordURL);
                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }//製作者情報がなければ

            elseif (!empty($item->ItemAttributes->Artist)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>Artist</th>";
                $htmlCode .= '<td><a href="' . $KeywordURL . urlencode($item->ItemAttributes->Artist) . '">' . $item->ItemAttributes->Artist . '</a></td>';
                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }

            // 製造元
            if (!empty($item->ItemAttributes->Manufacturer)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>" . __("Manufacturer", "$this->i18nDomain") . "</th>";
                $htmlCode .= '<td><a href="' . $KeywordURL . urlencode($item->ItemAttributes->Manufacturer) . '">' .
                        $item->ItemAttributes->Manufacturer . '</a></td>';
                $htmlCode .= "</tr>";
            }

            // 発売日
            if (!empty($pubDate["year"])) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>" . __("Release Date", "$this->i18nDomain") . "</th>";
                $htmlCode .= "<td>";
                $htmlCode .= $pubDate["year"] . "年";
                $htmlCode .= ( (!empty($pubDate["month"])) ? $pubDate["month"] . "月" : "");
                $htmlCode .= ( (!empty($pubDate["day"])) ? $pubDate["day"] . "日" : "");
                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }

            // 価格（定価と割引後の価格）
            if (!empty($item->ItemAttributes->ListPrice->Amount)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>" . __("Price", "$this->i18nDomain") . "</th>";
                $htmlCode .= "<td>";

                $htmlCode .= $this->getPrice($item);

                $htmlCode .= "</td>";
                $htmlCode .= "</tr>";
            }

            // 再生時間
            if (!empty($RunningTime)) {
                $htmlCode .= "<tr>";
                $htmlCode .= "<th>" . __("Running Time", "$this->i18nDomain") . "</th>";
                $htmlCode .= "<td>" . $RunningTime . "</td>";
                $htmlCode .= "</tr>";
            }

            $htmlCode .= "</tbody>";
            $htmlCode .= '</table><br clear="both" />';
            $htmlCode .= "</div>";
            $htmlCode .= "</div></div>";
            $htmlCode .= '<br clear="both" />';


            $htmlCode = preg_replace("/<\/(div|p|table|tbody|tr|dl|dt|dd|li)>/", "</$1>\n", $htmlCode);
            $htmlCode = str_replace('&', '&amp;', $htmlCode);

            return $htmlCode;
        }


        /**
         *
         * @param <type> $asin
         * @return <type>
         *
         */
        function getActionData($asin) {

            //   if (true === $this->show_iteminfo) {

            $actionData = $this->_loadAmazonReports($asin);
            //     }

            return $actionData;
        }



        /**
         * カートに入れるボタンを表示するコードを返す
         *
         * @param <type> $asin
         * @return <type> $tmpCode
         *
         */
        function showAddCartButton($asin) {

            $tmpCode = '<form class="showaddcartbutton" action="http://www.amazon.co.jp/gp/aws/cart/add.html" method="post">';
            $tmpCode .= '<input name="ASIN.1" value="' . $asin . '" type="hidden" />';
            $tmpCode .= '<input name="Quantity.1" value="1" type="hidden" />';
            $tmpCode .= '<input name="AssociateTag" value="' . $this->options['associateTag'] . '" type="hidden" />';
            $tmpCode .= '<input name="SubscriptionId" value="' . $this->options['accessKeyId'] . '" type="hidden" />';

            $tmpCode .= '<input name="submit.add-to-cart" type="image" ';
            $tmpCode .= 'src="' . $this->pluginDirURL . '/images/' . $this->addCartButtonImg . '" ';
            $tmpCode .= 'alt="' . __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain") . '" ';
            $tmpCode .= 'title="' . __("Take this item into your cart in amazon.co.jp", "$this->i18nDomain") . '" />';
            $tmpCode .= '</form>';

            return $tmpCode;
        }

        /**
         *
         *
         * @param <type> $item
         * @return <type> $tmpCode
         *
         */
        function getPrice($item) {

            // Price $item->ItemAttributes->ListPrice->Amount
            // discountPrice $item->OfferSummary->LowestNewPrice->Amount
            if ((empty($item->ItemAttributes->ListPrice->Amount)) && (!empty($item->OfferSummary->LowestNewPrice->Amount))) {
                $item->ItemAttributes->ListPrice->Amount = $item->OfferSummary->LowestNewPrice->Amount;
            }
            if ((!empty($item->OfferSummary->LowestNewPrice->Amount)) && (((int) $item->ItemAttributes->ListPrice->Amount) > ((int) $item->OfferSummary->LowestNewPrice->Amount))) {
                $discounted = true;
            }

            $tmpCode .= ( ($discounted == true) ? "<del>" : "");
//var_dump($item->ItemAttributes->ListPrice->Amount);
            // 定価
            $tmpCode .= ( $item->ItemAttributes->ListPrice->CurrencyCode == "USD") ?
                    "$ " . number_format((double)$item->ItemAttributes->ListPrice->Amount) : number_format((double)$item->ItemAttributes->ListPrice->Amount) . __("yen", "$this->i18nDomain");

            // 割引していればその価格を表示
            if ($discounted == true) {
                $tmpCode .= "</del> ";
                $tmpCode .= ( $item->ItemAttributes->ListPrice->CurrencyCode == "USD") ?
                        "$ " . number_format((double)$item->OfferSummary->LowestNewPrice->Amount) : number_format((double)$item->OfferSummary->LowestNewPrice->Amount) . __("yen", "$this->i18nDomain");
                $CutRate = round(( 1 - ( $item->OfferSummary->LowestNewPrice->Amount / $item->ItemAttributes->ListPrice->Amount )) * 100);
                $tmpCode .= ( ($CutRate > 0) ? " (<em> {
                        $CutRate}%</em> OFF)" : "");
            }

            return $tmpCode;
        }

        /**
         * 製作者情報を5人まで返す
         *
         * @param <type> $item
         * @param <type> $keywordURL
         * @return <type> $tmpCode
         *
         *
         */
        function getCreators($item, $KeywordURL) {

            unset($tmpCode);

            if ($item->ItemAttributes->ProductGroup == "Book") {
                $Creator = $item->ItemAttributes->Author;

            } else {

                $Creator = $item->ItemAttributes->Creator;
            }


            if (isset($Creator[1])) {

                for ($q = 0; $q < 5; $q++) {
                    if (isset($Creator[$q])) {
                        $tmpCode .= '<a href="' . $KeywordURL . urlencode($Creator[$q]) . '">';
                        $tmpCode .= $Creator[$q] . "</a>";
                        $tmpCode .= "<br />";
                    }
                }
            } else {
                $tmpCode .= '<a href="' . $KeywordURL . urlencode($Creator) . '">';
                $tmpCode .= $Creator . "</a>";
            }

            return $tmpCode;
        }


        /*
                                 * プラグイン有効化時に毎日発生するイベントを、一度行ってから登録
                                 *
                                 *
                                 *
        */
        function _rideramazonRegisterHook() {


            $this->_rideramazonDailyEvent();
            //      wp_schedule_event(time(), 'daily', '_rideramazonDailyEvent');
            //  テスト用 毎時
         //   wp_schedule_event(time(), 'hourly', '_rideramazonDailyEvent');
        }

        /*
                                 * 毎日発生するイベント
                                 *
                                 *
        */
        function _rideramazonDailyEvent() {

           $this->_getReport();
        //    $this->_saveReport();
        //    $this->_makeTable();
        }

        /*
                                 * プラグイン無効化時にイベントを消去する
                                 *
                                 *
        */
        function _rideramazonNotRegisterHook() {
            wp_clear_scheduled_hook('_rideramazonDailyEvent');
        }

        // レポートをゲット
        function _getReport() {


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
                    'endMonth' => (string) (date("m", $yesterday) - 1),
                    'endDay' => date("d", $yesterday),
                    $request => "",
            );

            $loginQueries = http_build_query($loginParams);
            $reportQueries = http_build_query($reportParams);

            $client = new HTTP_Client();

            // ログイン画面
            $client->get("{$host}/associates/login/login.html");
            $response = $client->currentResponse();
            var_dump($response);
            // ログイン
            $client->post("{$host}/flex/sign-in/select.html", $loginQueries, true);
            $response = $client->currentResponse();
            // レポート
            $client->post("{$host}/associates/network/reports/report.html", $reportQueries, true);
            $response = $client->currentResponse();

            if (!empty($response['body'])) {
           //     $this->report = $response['body'];
                return true;
            } else {

                return false;
            }
        }

        // ゲットしたレポートを出力
        function _saveReport() {

            $reportFileName = "report" . date('Y-m-d');

            // レポートを捕捉
            ob_start();
            print_r($this->report);
            $buffer = ob_get_contents();
            ob_end_clean();

            // 出力
            $fn = "{$this->pluginDir}/report/{$reportFileName}.xml";
            file_put_contents($fn, $buffer);
            chmod($fn, 0666);

            return true;
        }

        function _makeTable() {

            // あらかじめ作成したXMLを取得
            $this->report = simplexml_load_file($this->pluginDir . "/report/report.xml");
            // DBを更新
            $this->_updateTable();
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

            $sql = "SELECT orders,clicks,asin FROM " . $table_name . " WHERE asin = '" . $asin . "'";
            $request = $wpdb->get_row($sql);


            if (!empty($request->orders)) {

                $code .= '<div class="orders" ><img src="' . $this->pluginDirURL . '/images/buys.png" width="16" height="16" alt="購入数" /> ' .
                        "<strong>{$request->orders}人</strong>が購入しました</div>";
            }

            if (!empty($request->asin)) {

                $code .= '<div class="clicks"><img src="' . $this->pluginDirURL . '/images/clicks.png" width="16" height="16" alt="クリック数" /> ' .
                        "このサイトで<em>{$request->clicks}人</em>がクリック</div> ";
            }

            return $code;
        }

        function _updateTable() {

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

                //require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
                //dbDelta($sql);
                $wpdb->query($sql);
            }

            // クリックのみの商品を配列に格納
            foreach ($this->report->ItemsNoOrders->Item as $item) {
                $asin = (string) $item["ASIN"];
                $items[$asin] = array(
                        "Title" => str_replace(",", "", (string) $item["Title"]),
                        "Clicks" => (int) $item["Clicks"]
                );
            }
            $commands[] = "INSERT INTO " . $table_name . " (asin, title, clicks, orders, price) " .
                    "VALUES ('" . $asin . "','" . $item["Title"] . "','" . $item["Clicks"] . "','" . $item["Orders"] . "','" . $item["Price"] . "')";
            // 購入された商品を配列に格納
            foreach ($this->report->Items->Item as $item) {
                $asin = (string) $item["ASIN"];
                $items[$asin] = array(
                        "Title" => str_replace("'", "", (string) $item["Title"]),
                        "Price" => str_replace(",", "", (string) $item["Price"]),
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
                $sql = "SELECT * FROM	" . $table_name . " WHERE asin = '" . $asin . "'";
                $request = $wpdb->query($sql);

                unset($commands);

                $item["Title"] = htmlspecialchars($item["Title"]);

                if ($wpdb->get_row($sql)) {
                    // データがある場合は更新
                    $commands[] = "UPDATE " . $table_name . " SET clicks = '" . $item["Clicks"] . "' WHERE asin = '" . $asin . "'";
                    $commands[] = "UPDATE " . $table_name . " SET orders = '" . $item["Orders"] . "' WHERE asin = '" . $asin . "'";
                } else {
                    // データがない場合は作成
                    $commands[] = "INSERT INTO " . $table_name . " (asin, title, clicks, orders, price) " .
                            "VALUES ('" . $asin . "','" . $item["Title"] . "','" . $item["Clicks"] . "','" . $item["Orders"] . "','" . $item["Price"] . "')";
                }

                // クエリを投げて更新
                foreach ($commands as $command) {
                    $request = $wpdb->query($command);
                }
            }
        }

        function _isError() {

            if (count($this->errors) > 0) {

                $msg = '<ul class="error">';

                foreach ($this->errors as $error) {
                    $msg .= "<li>エラー：{$error}</li>";
                }

                $msg .= "</ul>";

                return $msg;
            } else {

                return false;
            }
        }

    }

    //end of class
} //end of class exists

if (class_exists('RiderAmazon')) {
    $rideramazon
            = new RiderAmazon();
}
?>
