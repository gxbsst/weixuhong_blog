<?php 

function weixin_robot_get_remote_user($weixin_openid){
	$weixin_robot_access_token = weixin_robot_get_access_token();

	$url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$weixin_robot_access_token.'&openid='.$weixin_openid;

	$response = wp_remote_get($url,array('sslverify'=>false));

	if(is_wp_error($response)){
		echo $response->get_error_code().'：'. $response->get_error_message();
		return false;
	}

	$weixin_user = json_decode($response['body'],true);	

	if(isset($weixin_user['errcode'])){
		return false;
	}

	$weixin_user['last_update'] = current_time('timestamp');

	return $weixin_user;
}

// type = 0 -- 默认
// type = 1 -- 高级接口获取
// type = 2 -- OAuth 获取
function weixin_robot_get_user($weixin_openid='',$from=''){

	if(!$weixin_openid ) {
		$weixin_openid = weixin_robot_get_user_openid();
	}

	if(!$weixin_openid )  return false;
	if(strlen($weixin_openid) < 28) return false;

	$weixin_user = wp_cache_get($weixin_openid,'weixin_user');

	if($weixin_user === false){

		global $wpdb;

		$weixin_users_table = weixin_robot_get_users_table();

		$weixin_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$weixin_users_table} WHERE openid=%s",$weixin_openid),ARRAY_A);

		if($weixin_user){
			if(weixin_robot_get_setting('weixin_advanced_api') && (current_time('timestamp') - $weixin_user['last_update']) > 86400*30 ) {
				$weixin_user = weixin_robot_get_remote_user($weixin_openid);
				if($weixin_user){
					$wpdb->update($weixin_users_table,$weixin_user,array('openid'=>$weixin_openid));

					wp_cache_set($weixin_openid, $weixin_user, 'weixin_user',3600);
				}else{
					return false;
				}
			}
		}else{
			if($from == 'local'){
				return false;
			}else{
				if($from == ''){
					if(weixin_robot_get_setting('weixin_advanced_api')){
						$weixin_user = weixin_robot_get_remote_user($weixin_openid);
					}
				}else{
					$weixin_user = array('openid'=>trim($weixin_openid));
				}
				
				if(isset($weixin_user['openid'])){
					$wpdb->insert($weixin_users_table,$weixin_user);
					
					$weixin_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$weixin_users_table} WHERE openid=%s",$weixin_openid),ARRAY_A);
					wp_cache_set($weixin_openid, $weixin_user, 'weixin_user',3600);
				}
			}
		}
	}
	return $weixin_user;
}

function weixin_robot_update_user($weixin_openid,$weixin_user){ // 更新自定义字段
	global $wpdb;

	$weixin_users_table = weixin_robot_get_users_table();

	$old_user = weixin_robot_get_user($weixin_openid);

	if($old_user){
		$weixin_user = wp_parse_args($weixin_user,$old_user);

		$wpdb->update($weixin_users_table,$weixin_user,array('openid'=>$weixin_openid));

		wp_cache_delete($weixin_openid, 'weixin_user');
	}

	return $weixin_user;
}

function weixin_rebot_sent_user($weixin_openid, $content, $reply_type='text'){
	$weixin_robot_access_token = weixin_robot_get_access_token();
	$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$weixin_robot_access_token;

	$request = array();

	$request['touser']	= $weixin_openid;

	if($reply_type == 'text'){
		$request['msgtype']	= 'text';
		$request['text']	= array('content' => urlencode($content));
	}elseif($reply_type == 'img'){
		// 以后再说
	}
	

	$response = wp_remote_post($url,array( 'body' => urldecode(json_encode($request)),'sslverify'=>false));

	if(is_wp_error($response)){
		echo $response->get_error_code().'：'. $response->get_error_message();
		exit;
	}

	$response = json_decode($response['body'],true);

	if($response['errcode']){
		return $response['errcode'].': '.$response['errmsg'];
	}else{
		return '发送成功';
	}
}


function weixin_robot_get_user_query_key(){
	return apply_filters('weixin_user_query_key','weixin_user_id');
}

function weixin_robot_get_user_query_id($weixin_openid=''){
	if($weixin_openid){
		$weixin_robot_user_md5 = apply_filters('weixin_robot_user_md5','weixin');
	    $check = substr(md5($weixin_robot_user_md5.$weixin_openid),0,2);
	    return $check . $weixin_openid;
	}else{
		$query_key = weixin_robot_get_user_query_key();

		if(isset($_GET[$query_key])){
			return $_GET[$query_key];
		}elseif(isset($_COOKIE[$query_key])){
			return $_COOKIE[$query_key];
		}else{
			return '';
		}	
	}
}

function weixin_robot_get_user_openid($query_id=''){
	if(!$query_id){
		$query_id = weixin_robot_get_user_query_id();
	}

    $weixin_openid = substr($query_id, 2);
    if($query_id == weixin_robot_get_user_query_id($weixin_openid)){
        return $weixin_openid;
    }else{
        return false;
    }
}

// 获取用户的最新的地理位置并缓存10分钟。
function weixin_robot_get_user_location($weixin_openid){
	$location = wp_cache_get($weixin_openid,'weixin_location');
	if($location === false){
		global $wpdb;
		$weixin_messages_table = weixin_robot_get_messages_table();

		$time = current_time('timestamp') - 3600*(2+get_option('gmt_offset'));

		$location = $wpdb->get_row($wpdb->prepare("SELECT Location_X as x, Location_Y as y FROM {$weixin_messages_table} WHERE Location_X >0 AND Location_Y >0 AND FromUserName=%s AND CreateTime>%d ORDER BY CreateTime DESC LIMIT 0,1;",$weixin_openid,$time),ARRAY_A);
		wp_cache_set($weixin_openid, $location,'weixin_location', 600);
	}
	return $location;
}

//定义微信个人中心模板
add_action('init','weixin_robot_user_init');
function weixin_robot_user_init(){

	$query_key = weixin_robot_get_user_query_key();

	if(isset($_GET['weixin_user_profile']) && !empty($_GET[$query_key])){
		if(file_exists(TEMPLATEPATH.'/weixin/weixin-user-profile.php')){
			include(TEMPLATEPATH.'/weixin/weixin-user-profile.php');
		}else{
			include(WEIXIN_ROBOT_PLUGIN_DIR.'/template/weixin-user-profile.php');
		}
        exit;
	}

	if(!empty($_GET[$query_key])){
        $expire = time() + (60*60*24*365);
        setcookie($query_key, $_GET[$query_key], $expire, COOKIEPATH, COOKIE_DOMAIN);
        if ( COOKIEPATH != SITECOOKIEPATH ){
            setcookie($query_key, $_GET[$query_key], $expire, SITECOOKIEPATH, COOKIE_DOMAIN);
        }
    }
}

// 舍弃的函数
function weixin_robot_user_get_openid($query_id){
    $weixin_openid = substr($query_id, 2);
    if($query_id == weixin_robot_user_get_query_id($weixin_openid)){
        return $weixin_openid;
    }else{
        return false;
    }
}

function weixin_robot_user_get_query_id($weixin_openid){
	$weixin_robot_user_md5 = apply_filters('weixin_robot_user_md5','weixin');
    $check = substr(md5($weixin_robot_user_md5.$weixin_openid),0,2);
    return $check . $weixin_openid;
}

function weixin_robot_get_weixin_query_id(){
	$query_key = weixin_robot_get_user_query_key();

	if(isset($_GET[$query_key])){
		return $_GET[$query_key];
	}elseif(isset($_COOKIE[$query_key])){
		return $_COOKIE[$query_key];
	}else{
		return '';
	}
}
