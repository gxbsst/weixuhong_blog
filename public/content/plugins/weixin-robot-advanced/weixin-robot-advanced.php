<?php
/*
Plugin Name: 微信机器人高级版
Plugin URI: http://wpjam.net/item/weixin-robot-advanced/
Description: 微信机器人的主要功能就是能够将你的公众账号和你的 WordPress 博客联系起来，搜索和用户发送信息匹配的日志，并自动回复用户，让你使用微信进行营销事半功倍。
Version: 3.9.2
Author: Denis
Author URI: http://blog.wpjam.com/
*/

define('WEIXIN_ROBOT_PLUGIN_URL', plugins_url('', __FILE__));
define('WEIXIN_ROBOT_PLUGIN_DIR', WP_PLUGIN_DIR.'/'. dirname(plugin_basename(__FILE__)));
define('WEIXIN_ROBOT_PLUGIN_FILE',  __FILE__);

add_action('init', 'wpjam_weixin_robot_redirect', 11);
function wpjam_weixin_robot_redirect($wp){
	if(isset($_GET['weixin']) || isset($_GET['signature'])){
		global $wechatObj;
		if(!isset($wechatObj)){
			$wechatObj = new wechatCallback();
			$wechatObj->valid();
			exit;
		}
	}
}

class wechatCallback {
	private $postObj		= '';
	private $fromUsername	= '';
	private $toUsername		= '';
	private $response		= '';

	public function valid(){

		//file_put_contents(WP_CONTENT_DIR.'/uploads/weixin.log',var_export($_GET,true));
		if(isset($_GET['debug'])){
			$this->checkSignature();
			$this->responseMsg();
		}else{
			if($this->checkSignature() || isset($_GET['yixin'])){
				if(isset($_GET["echostr"])){
					$echoStr = $_GET["echostr"];
					echo $echoStr;					
				}
				$this->responseMsg();
				exit;
			}
		}
	}

	public function responseMsg(){
		$postStr = (isset($GLOBALS["HTTP_RAW_POST_DATA"]))?$GLOBALS["HTTP_RAW_POST_DATA"]:'';
		//file_put_contents(WP_CONTENT_DIR.'/uploads/test.html',var_export($postStr,true));

		if (isset($_GET['debug']) || !empty($postStr)){	
			if(isset($_GET['debug'])){
				$this->fromUsername = $this->toUsername = '';
				$keyword = strtolower(trim($_GET['t']));
			}else{
				$postObj		= simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

				$this->postObj		= $postObj;

				$this->fromUsername	= (string)$postObj->FromUserName;
				$this->toUsername	= (string)$postObj->ToUserName;

				$msgType = strtolower(trim($postObj->MsgType));

				if($msgType == 'text'){ 			// 文本消息
					$keyword = strtolower(trim($postObj->Content));
				}elseif($msgType == 'event'){		// 事件消息
					$event = strtolower(trim($postObj->Event));

					if(in_array($event, array('subscribe', 'unsubscribe'))) { // 订阅和取消订阅事件
						$keyword = $event;
					}elseif($event == 'click'){		//点击事件
						$keyword = strtolower(trim($postObj->EventKey));
					}elseif($event == 'view'){		//查看网页事件，估计也进不来。
						exit;
					}elseif($event == 'location'){	// 高级接口，用户自动提交地理位置事件。
						$keyword = '[event-location]';
					}
				}else{	
					if(isset($postObj->Recognition) && trim($postObj->Recognition)){ // 如果已经识别了语言，识别之后的文字作为关键字
						$keyword = strtolower(trim($postObj->Recognition));
					}else{	// 其他消息，统一处理成关键字为 [{消息类}] ，后面再做处理。
						$keyword = '['.$msgType.']';
					}
				}
			}

			$pre = apply_filters('weixin_custom_keyword', false, $keyword);

			if($pre == false){ // 如果不是自定义的关键字，就直接搜索回复。
				$this->query($keyword);
			}

			do_action('weixin_robot',$this);	// 已经执行了一次完整的信息自动回复
		}else {
			echo "";
		}
		exit;
	}

	public function query($keyword=''){

		$weixin_count = weixin_robot_get_setting('weixin_count');

		// 获取除 page 和 attachmet 之外的所有日志类型
		$post_types = get_post_types( array('exclude_from_search' => false) );
		unset($post_types['page']);
		unset($post_types['attachment']);

		$weixin_query_array = array(
			's'						=> $keyword, 
			'ignore_sticky_posts'	=> true,
			'posts_per_page'		=> $weixin_count , 
			'post_status'			=> 'publish',
			'post_type'				=> $post_types
		);

		$weixin_query_array = apply_filters('weixin_query',$weixin_query_array); 

		if(empty($this->response)){
			if(isset($weixin_query_array['s'])){
				$this->response = 'query';
			}elseif(isset($weixin_query_array['cat'])){
				$this->response = 'cat';
			}elseif(isset($weixin_query_array['tag_id'])){
				$this->response = 'tag';
			}
		}

		global $wp_the_query;
		$wp_the_query->query($weixin_query_array);

		$items = '';

		$counter = 0;

		if($wp_the_query->have_posts()){
			while ($wp_the_query->have_posts()) {
				$wp_the_query->the_post();

				global $post;

				$title	= apply_filters('weixin_title', get_the_title()); 
				$excerpt= apply_filters('weixin_description', get_post_excerpt( $post,apply_filters( 'weixin_description_length', 150 ) ) );
				$url	= apply_filters('weixin_url', get_permalink());

				if($counter == 0){
					$thumb = get_post_weixin_thumb($post, array(640,320));
				}else{
					$thumb = get_post_weixin_thumb($post, array(80,80));
				}

				$items = $items . $this->get_item($title, $excerpt, $thumb, $url);
				$counter ++;
			}
		}

		$articleCount = count($wp_the_query->posts);
		if($articleCount > $weixin_count) $articleCount = $weixin_count;

		if($articleCount){
			echo sprintf($this->get_picTpl(),$articleCount,$items);
		}else{
			if(weixin_robot_get_setting('weixin_3rd_search')){
				weixin_robot_3rd_reply();
			}else{
				weixin_robot_not_found_reply($keyword);
			}
		}
	}

	public function get_item($title, $description, $picUrl, $url){
		if(!$description) $description = $title;

		return
		'
		<item>
			<Title><![CDATA['.html_entity_decode($title, ENT_QUOTES, "utf-8" ).']]></Title>
			<Description><![CDATA['.html_entity_decode($description, ENT_QUOTES, "utf-8" ).']]></Description>
			<PicUrl><![CDATA['.$picUrl.']]></PicUrl>
			<Url><![CDATA['.$url.']]></Url>
		</item>
		';
	}

	public function get_fromUsername(){ // 微信的 USER OpenID
		return $this->fromUsername;
	}

	public function get_response(){
		return $this->response;
	}

	private function get_basicTpl(){
		return "
				<ToUserName><![CDATA[".$this->fromUsername."]]></ToUserName>
				<FromUserName><![CDATA[".$this->toUsername."]]></FromUserName>
				<CreateTime>".time()."</CreateTime>
		";
	}
	public function get_textTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[text]]></MsgType>
				<Content><![CDATA[%s]]></Content>
			</xml>
		";
	}

	public function get_picTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[news]]></MsgType>
				<Content><![CDATA[]]></Content>
				<ArticleCount>%d</ArticleCount>
				<Articles>
				%s
				</Articles>
			</xml>
		";
	}

	public function get_imageTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[image]]></MsgType>
				<Image>
				<MediaId><![CDATA[%s]]></MediaId>
				</Image>
			</xml>
		";
	}

	public function get_voiceTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[voice]]></MsgType>
				<Voice>
				<MediaId><![CDATA[%s]]></MediaId>
				</Voice>
			</xml>
		";
	}

	public function get_videoTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[video]]></MsgType>
				<Video>
				<MediaId><![CDATA[%s]]></MediaId>
				<Title><![CDATA[%s]]></Title>
				<Description><![CDATA[%s]]></Description>
				</Video>
			</xml>
		";
	}

	public function get_musicTpl(){
		return "
			<xml>".$this->get_basicTpl()."
				<MsgType><![CDATA[music]]></MsgType>
				<Music>
				<Title><![CDATA[%s]]></Title>
				<Description><![CDATA[%s]]></Description>
				<MusicUrl><![CDATA[%s]]></MusicUrl>
				<HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
				<ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
			</Music>
			</xml>
		";
	}	

	public function get_msgType(){
		return $this->msgType;
	}

	public function get_postObj(){
		return $this->postObj;
	}

	public function set_response($response){
		$this->response = $response;
	}

	private function checkSignature(){
		$signature	= isset($_GET["signature"])?$_GET["signature"]:'';
		$timestamp	= isset($_GET["timestamp"])?$_GET["timestamp"]:'';
		$nonce 		= isset($_GET["nonce"])?$_GET["nonce"]:'';	
				
		$weixin_token = weixin_robot_get_setting('weixin_token');
		if(isset($_GET['debug'])){
			echo 'WEIXIN_TOKEN：'.$weixin_token."\n";
		}
		$tmpArr = array($weixin_token, $timestamp, $nonce);
		//sort($tmpArr);
		sort($tmpArr,SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
}

// 开始加载其他文件。
function weixin_robot_get_wpjam_include_dir(){
	$wpjam_include_versions = get_transient('wpjam_include_versions');
	$version = '20140320';
    if($wpjam_include_versions === false || empty($wpjam_include_versions[$version])){
        $wpjam_include_versions[$version] = WEIXIN_ROBOT_PLUGIN_DIR.'/include';
        set_transient('wpjam_include_versions', $wpjam_include_versions, 600);
    }
    krsort($wpjam_include_versions);
    return current($wpjam_include_versions);
}

$wpjam_include_dir = weixin_robot_get_wpjam_include_dir();

if(!function_exists('wpjam_net_check_domain')){
	include($wpjam_include_dir.'/wpjam-net-api.php');	// WPJAM 应用商城接口
}

if(!function_exists('wpjam_option_page')){
	include($wpjam_include_dir.'/wpjam-setting-api.php');
}

include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-functions.php');			// 自定义接口和常用函数

include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-options.php');			// 后台选项
include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-custom-reply.php');		// 自定义回复
include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-custom-menu.php');		// 自定义菜单
if(weixin_robot_get_setting('weixin_disable_stats') == false) {
	include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-stats.php');			// 数据统计分析
}

include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-user.php');				// 微信用户系统

if(weixin_robot_get_setting('weixin_credit')){
	include(WEIXIN_ROBOT_PLUGIN_DIR.'/weixin-robot-credit.php');		// 微信积分系统
}

$weixin_extend_dir = WEIXIN_ROBOT_PLUGIN_DIR.'/extends';
if (is_dir($weixin_extend_dir)) {
	if ($weixin_extend_handle = opendir($weixin_extend_dir)) {   
		while (($weixin_extend_file = readdir($weixin_extend_handle)) !== false) {
			if ($weixin_extend_file!="." && $weixin_extend_file!=".." && is_file($weixin_extend_dir.'/'.$weixin_extend_file)) {
				if(pathinfo($weixin_extend_file, PATHINFO_EXTENSION) == 'php'){
					include($weixin_extend_dir.'/'.$weixin_extend_file);
				}
			}
		}   
		closedir($weixin_extend_handle);   
	}   
}