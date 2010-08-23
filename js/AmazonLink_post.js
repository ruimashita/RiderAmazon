// AmazonLink
// 投稿画面 Amazon.co.jp 検索 JavaScript

var AmazonLink = Class.create();
AmazonLink.prototype = {
	version : 20071101,									// JavaScript コードのバージョン
	isDebugMode : 0,									// デバッグモード
	xmlTreeObject : null,								// XML パーサー
//	itemListTemplate : null,
	dumper : null,										// デバッグ用ダンパー

	// コンストラクタ
	initialize : function()
	{
		this.xmlTreeObject = new XML.ObjTree();
		this.xmlTreeObject.force_array = ["Argument", "ResponseGroup", "Item", "ImageSet", "Error"];
		this.xmlTreeObject.attr_prefix = '@';

//		this.itemListTemplate = new Template(
//			"<tr><th class='number'>#{index}</th>" +
//			"<td class='itemImage'><a href='#{detailUrl}' target='_blank'><img src='#{imageUrl}' width='#{imageWidth}' height='#{imageHeight}' alt='' /></a></td>" +
//			"<td class='infomation'><a href='#{detailUrl}' target='_blank' title='Amazon.co.jp の商品ページへ'>#{title}</a><br />" +
//			"<code title='AmazonLink コード'>[amazon]#{asin}[/amazon]</code></td></tr>"
//		);

		// デバッグモード時、ダンプ用オブジェクトを生成する
		if ( this.isDebugMode == 1 )
		{
			this.dumper = new JKL.Dumper();
		}
	},

	// 商品検索を実行
	searchItem : function()
	{
		var url = $F('yo_amazonLink_url')+'/awsAjax.php';
		var param = {
						class_path: $F('yo_amazonLink_dir'),
						AssociateTag: $F('yo_amazonLink_trackingId'),
						Keywords: $F('yo_amazonLink_lastKeyword'),
						SearchIndex: $F('yo_amazonLink_lastType'),
						ItemPage: $F('yo_amazonLink_currentPage')
					};
		var paramStr  = $H(param).toQueryString();

		if ( $F('yo_amazonLink_keyword') != '' )
		{
			var ajax = new Ajax.Request(
				url,
				{
					method: 'get',
					parameters: paramStr,
					onLoading: function() { $('yo_amazonLink_result').innerHTML = '<img src="'+$F('yo_amazonLink_url')+'/images/loading.gif" width="16" height="16" title="Loading.." />'; },
					onFailure: function() { $('yo_amazonLink_result').innerHTML = 'Search Failed.'; },
					onComplete: this.displayResult.bind(this)
				}
			);
		}
		else
		{
			$('yo_amazonLink_result').innerHTML = '検索キーワードをご入力ください。';
			new Effect.Highlight('yo_amazonLink_keyword');
			Field.focus('yo_amazonLink_keyword');
		}
	},

	// 検索結果を表示
	displayResult : function(originalRequest)
	{
		var jasonTree = this.xmlTreeObject.parseXML(originalRequest.responseText);

		if ( this.isDebugMode == 1 )
		{
			$('content').value = this.dumper.dump(jasonTree);
		}

		var amazonItems = jasonTree.ItemSearchResponse.Items;
		if ( amazonItems.Request.IsValid == 'True' )
		{
			if ( amazonItems.Request.Errors == null )
			{
				var totalResults = amazonItems.TotalResults;
				var totalPages = amazonItems.TotalPages;
				if ( totalPages > 3200 )				// AWS の上限に合わせる
				{
					totalPages = 3200;
				}
				var itemPage = $F('yo_amazonLink_currentPage');

				$('yo_amazonLink_totalPages').value = totalPages;

				var itemTemplate = new Template(
					"<tr><th class='number'>#{index}</th>" +
					"<td class='itemImage'><a href='#{detailUrl}' target='_blank'><img src='#{imageUrl}' width='#{imageWidth}' height='#{imageHeight}' alt='商品イメージ' /></a></td>" +
					"<td class='infomation'><a href='#{detailUrl}' target='_blank' title='Amazon.co.jp の商品ページへ'>#{title}</a><br />" +
					"<code title='AmazonLink コード'>[amazon]#{asin}[/amazon]</code><!-- <input type='button' value='挿入' name='yo_amazonLink_insertCodeButton#{index}' id='yo_amazonLink_insertCodeButton#{index}' title='AmazonLink コードを挿入する' /> --></td></tr>"
				);

				var data = "<table id='yo_amazonLink_list'><tbody>";
				amazonItems.Item.each( function(amazonItem, index)
				{
					if ( amazonItem )
					{
						// 商品写真タイプの選択
						if ( amazonItem.ImageSets )
						{
							var amazonImageSet = amazonItem.ImageSets.ImageSet[0];
							if ( amazonImageSet.SmallImage )
							{
								image = amazonImageSet.SmallImage;
							}
							else if ( amazonImageSet.ThumbnailImage )
							{
								image = amazonImageSet.ThumbnailImage;
							}
							else if ( amazonImageSet.SwatchImage )
							{
								image = amazonImageSet.SwatchImage;
							}
						}
						// 商品写真情報
						if ( image )
						{
							imageUrl = image.URL;
							imageWidth = image.Width["#text"];
							imageHeight = image.Height["#text"];
						}
						else							// 商品画像が1つもない場合
						{
							imageUrl = $F('yo_amazonLink_url')+"/images/noImage.png";
							imageWidth = "59";
							imageHeight = "75";
						}
						var itemInfo = {
							index: (itemPage-1) * 10 + index + 1 + '.',
							title: amazonItem.ItemAttributes.Title,
							asin: amazonItem.ASIN,
							detailUrl: amazonItem.DetailPageURL,
							imageUrl: imageUrl,
							imageHeight: imageHeight,
							imageWidth: imageWidth
						};

						data += itemTemplate.evaluate(itemInfo);
//						Event.observe(
//							'yo_amazonLink_insertCodeButton'+itemInfo.index,
//							'click',
//							function()
//							{
//								alert('[amazon]'+itemInfo.asin+'[/amazon]');
//							},
//							false
//						);
//						data += this.itemListTemplate.evaluate(itemInfo);
					}
				});
				data += "</tbody></table>";
				$('yo_amazonLink_page').innerHTML = $F('yo_amazonLink_currentPage') + "/" + totalPages;
			}
			else
			{
				var errors = amazonItems.Request.Errors.Error;
				if ( errors[0].Code == 'AWS.ECommerceService.NoExactMatches' )
				{
					$('yo_amazonLink_currentPage').value = 1;
					$('yo_amazonLink_totalPages').value = 0;
					$('yo_amazonLink_page').innerHTML = '0/0';
					data = '該当する商品が見つかりませんでした。カテゴリーやキーワードをご確認ください。';
				}
			}
		}
		else
		{
			data = 'Amazon.co.jp への問い合わせにエラーがあったため、商品情報を得ることができませんでした。';
		}
		$('yo_amazonLink_result').innerHTML = data;
	},

	// ページ切り替え
	changePage : function(changeTo)
	{
		var totalPages = parseInt($F('yo_amazonLink_totalPages'));
		if ( totalPages > 0 )
		{
			var currentPage = parseInt($F('yo_amazonLink_currentPage'));
			var changeToPage = 0;
			if ( changeTo == 'previous' )
			{
				if ( currentPage > 1 )
				{
					changeToPage = currentPage - 1;
				}
				else if ( currentPage == 1 )
				{
					changeToPage = totalPages;
				}
			}
			else if ( changeTo == 'next' )
			{
				if ( currentPage < totalPages )
				{
					changeToPage = currentPage + 1;
				}
				else if ( currentPage == totalPages )
				{
					changeToPage = 1;
				}
			}
			$('yo_amazonLink_currentPage').value = changeToPage;
			this.searchItem();
		}
	},

	// AmazonLink コード挿入
	insertCode : function(code)
	{
		alert('[amazon]'+code+'[/amazon]');
	}

};

// イベント処理登録
Event.observe(
	window,
	'load',
	function()
	{
		var amazonLink = new AmazonLink();

		Event.observe(
			'yo_amazonLink_search',
			'click',
			function()
			{
				$('yo_amazonLink_lastType').value = $F('yo_amazonLink_type');
				$('yo_amazonLink_lastKeyword').value = $F('yo_amazonLink_keyword');
				$('yo_amazonLink_currentPage').value = 1;
				amazonLink.searchItem();
			},
			false
		);

		Event.observe(
			'yo_amazonLink_toPreviousPage',
			'click',
			function()
			{
				amazonLink.changePage('previous');
			},
			false
		);

		Event.observe(
			'yo_amazonLink_toNextPage',
			'click',
			function()
			{
				amazonLink.changePage('next');
			},
			false
		);

		Event.observe(
			'yo_amazonLink_keyword',
			'keypress',
			function(event)
			{
				if ( event.keyCode == Event.KEY_RETURN )
				{
					Event.stop(event);
					$('yo_amazonLink_lastType').value = $F('yo_amazonLink_type');
					$('yo_amazonLink_lastKeyword').value = $F('yo_amazonLink_keyword');
					$('yo_amazonLink_currentPage').value = 1;
					amazonLink.searchItem();
				}
			},
			false
		);
	},
	false
);

