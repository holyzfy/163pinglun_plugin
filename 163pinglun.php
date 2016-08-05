<?php
/*
Plugin Name: 163pinglun
Description: 分享最精彩的网易评论
Version: 1.1
Author: zhaofuyun
*/
if(!defined('ZFY163PINGLUN_VERSION')) {
	define('ZFY163PINGLUN_VERSION', '1.0');
}

require_once(ABSPATH . 'wp-includes/pluggable.php');

if(!function_exists('install')) {
	function install() {
		global $wpdb;
		$zfy163pinglun_options = get_option('zfy163pinglun_options');
		if(!$zfy163pinglun_options) {
			$num=0;
			$pages[$num]['post_name'] = 'result';
			$pages[$num]['post_title'] = '推荐精彩评论';
			$pages[$num]['post_content'] = '[result]';
			$pages[$num]['post_type'] = 'page';
			$pages[$num]['post_status'] = 'publish';
			$pages[$num]['ping_status'] ='closed';
			$pages[$num]['comment_status'] ='closed';
			
			$num++;
			$pages[$num]['post_name'] = 'help';
			$pages[$num]['post_title'] = '如何复制网易评论网址';
			$pages[$num]['post_content'] = '[help]';
			$pages[$num]['post_type'] = 'page';
			$pages[$num]['post_status'] = 'publish';
			$pages[$num]['ping_status'] ='closed';
			$pages[$num]['comment_status'] ='closed';
			
			foreach($pages as $page) {
				$post_id = wp_insert_post($page);
				$zfy163pinglun_options[$page['post_name']] = $post_id;
			}
					
			update_option('zfy163pinglun_options', $zfy163pinglun_options);
		}
		update_option('require_name_email', 0);
		
		$table = $wpdb->prefix . '163pinglun';
		if ($wpdb->get_var("show tables like '$table'") != $table) {
			$sql = 'CREATE TABLE '.$table. '(
						id BIGINT(20) NOT NULL AUTO_INCREMENT,
						tie_url VARCHAR(255) NOT NULL,
						news_url VARCHAR(255) NOT NULL,
						post_id BIGINT(20) NOT NULL,
						comment_id BIGINT(20) NOT NULL,
						content_json_data TEXT,
						PRIMARY KEY (id),
						KEY tie_url (tie_url)
					);';
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}
}
register_activation_hook( __FILE__, 'install');

add_filter('wp_list_pages_excludes', 'exclude_result_page');
if(!function_exists('exclude_result_page')) {
	function exclude_result_page($exclude_array) {
		$zfy163pinglun_options = get_option('zfy163pinglun_options');
		$post_id = $zfy163pinglun_options['result'];
		return array_merge($exclude_array, array($post_id));
	}
}

add_filter('comments_open', 'hide_comment_form');
if(!function_exists('hide_comment_form')) {
	function hide_comment_form() {
		if(is_home()) {
			$open = true;
		} else {
			$open = false;
		}
		return $open;
	}
}

add_filter( 'comments_open', 'my_comments_open', 10, 2);
if(!function_exists('my_comments_open')) {
	function my_comments_open($open, $post_id) {
		return true;
	}
}

if(!function_exists('get_tie_data')) {
	function get_tie_data($tie_url) {
		$result = wp_cache_get('tieCache');
		if($result) {
			return $result;
		}

		$host = parse_url($tie_url, PHP_URL_HOST);
		$path = parse_url($tie_url, PHP_URL_PATH);
		$arr = preg_split('/[\/\\\]/', substr($path, 1, -5));
		$board_id = $arr[0];
		$thread_id = $arr[1]."_".$arr[2];
		$data_url = 'http://'.$host.'/data/'.$board_id.'/re/'.$thread_id.'_1.html';
		$url = $data_url;
		// $url = 'http://api.yiifcms.com/get_content.php?url=' . urlencode($data_url);
		$request = new WP_Http;
		$data = $request->get($url, array('timeout' => 120));
		if(is_wp_error($data)) {
			$result = array(
				'status' => 0,
				'msg' => $data->get_error_message()
			);
			wp_cache_set('tieCache', $result);
			return $result;
		}

		$httpCode = $data['response']['code'];
		if($httpCode != 200) {
			$result = array(
				'status' => 0,
				'msg' => '跟帖已被小偏删除了'
			);
			wp_cache_set('tieCache', $result);
			return $result;
		}

		preg_match('/var replyDataOne=(.+);$/is', $data['body'], $matches_json);
		$json = json_decode($matches_json[1], true);
		$errCode = json_last_error();
		if(!$json) {
			$result = array(
				'status' => 0,
				'msg' => '解析评论数据遇到格式错误'
			);
		} else if($errCode != JSON_ERROR_NONE) {
			$result = array(
				'status' => 0,
				'msg' => "解析评论数据遇到格式错误，错误代码：". $errCode
			);
		} else {
			unset($json['postData']['d']);
			$result = array(
				'status' => 1,
				'data' => $json
			);
		}
		wp_cache_set('tieCache', $result);
		return $result;		
	}
}

add_action('pre_comment_on_post', 'validate');
if(!function_exists('validate')) {
	function validate($comment_post_ID) {
		global $wpdb;
		$comment_content = ( isset($_POST['comment']) ) ? trim($_POST['comment']) : null;
		preg_match('/(http.+html)/i', $comment_content, $matches);
		$tie_url = $matches[1];
		$tie_url = str_replace("comment.3g.163.com", "comment.news.163.com", $tie_url);
		
		if('' == $comment_content) {
			//评论网址不能为空
			$status = 'empty';
		} else if ('' == $tie_url) {
			//不能读取评论内容，请检查网址是否正确
			$status = 'fail';
			$tie_url = $comment_content;
		} else {
			$table = $wpdb->prefix . '163pinglun';
			$row = $wpdb->get_row("SELECT * FROM $table WHERE tie_url='".$tie_url."'");
			if($row) {
				//评论已存在
				$status = 'existed';
				$comment_id = $row->comment_id;
				
				if($comment_id < 1) { //没找到评论
					wp_die(__('<h2 class="entry-title" style="color:#f00;">老兄，程序出错啦，请把下面的信息发给我（zhaofuyun202@gmail.com），多谢多谢！</h2>'
					.'<div>@评论id=' . $comment_id . '</div>'
					.'<div>您刚才提交的评论地址：' . $tie_url . '</div>'));
				} else {
					//如果第二个提交这条评论的人的IP与数据库里的对应的IP不一样，则直接通过审核（两个人都推荐这条评论，说明这条评论质量还行）
					$comment_author_IP = $row->comment_author_IP;
					$comment_author_IP_2 = preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR']);
					if($comment_author_IP != $comment_author_IP_2) {
						wp_update_comment(array(
							'comment_ID' => $comment_id,
							'comment_approved' => '1'
						));
					}
				}
			} else {
				$result = get_tie_data($tie_url);
				if(0 == $result['status']) {
					$status = 'fail';
					$error_msg = $result['msg'];
				}
			}			
		}
		
		if(isset($status)) {
			$params = array(
				'status' => $status,
				'tie_url' => $tie_url,
				'comment_id' => $comment_id,
				'error_msg' => $error_msg
			);
			$location = get_bloginfo("url") . "/?pagename=result&" . http_build_query($params, null, '&');
			wp_redirect($location);
			exit;
		}
	}
}

if(!function_exists('get_tie')) {
	function get_tie($comment_id, $post_data) {
		$tie_content = '';
		$post_data_length = count($post_data);
		if($post_data_length > 1) {
			//生成一楼的内容
			$author = $post_data[1]['f'];
			$content = $post_data[1]['b'];
			$tie_content = '<div class="commentBox"><div class="commentInfo"><span class="comment-author">'.$author.'</span><span class="floorCount">1</span></div><p class="content">'.$content.'</p></div>';
			
			//追加二楼至倒数第二楼的内容
			for($i = 2; $i < $post_data_length; $i++) {
				$author = $post_data[$i]['f'];
				$content = $post_data[$i]['b'];
				$classname = '';
				if($i > 9 && $i < $post_data_length - 10) {
					$tie_content .= '<div class="commentBox midOfCommentBox">'.'<div class="commentInfo"><span class="comment-author">'.$author.'</span><span class="floorCount">'.$i.'</span></div><p class="content">'.$content.'</p></div>';
				} else {
					$tie_content = '<div class="commentBox">'.$tie_content.'<div class="commentInfo"><span class="comment-author">'.$author.'</span><span class="floorCount">'.$i.'</span></div><p class="content">'.$content.'</p></div>';
				}
			}
			
		}
		//追加最后一楼的内容
		$author = $post_data[$post_data_length]['f'];
		$content = $post_data[$post_data_length]['b'];
		$post_time = $post_data[$post_data_length]['t'];
		$tie_content = $tie_content.'<p class="content">'.$content.'</p>';
		$from = ' '.get_comment_author_link($comment_id).' ';
		return '<span class="comment-author">'.$author.'</span><span class="comment-meta">'.$post_time.$from.'推荐</span><div class="tie-content">'.$tie_content.'</div>';
	}
}

add_filter('preprocess_comment', 'insert_post_if_not_existed');
if(!function_exists('insert_post_if_not_existed')) {
	function insert_post_if_not_existed($commentdata) {
		//如果文章不存在，则创建文章
		global $wpdb;
		preg_match('/(http.+html)/i', $commentdata['comment_content'], $matches);
		$tie_url = $matches[1];
		$result = get_tie_data($tie_url);	
		if(0 == $result['status']) {
			wp_die(__('<h2 class="entry-title" style="color:#f00;">老兄，程序出错啦，'. $result['msg'] .'请返回首页重试</h2>'
					.'<div>您刚才提交的评论地址：' . $tie_url . '</div>'));
		}
		
		$data = $result['data'];
		$news_title = $data['thread']['title'];
		$news_url = $data['thread']['url'];
		$table = $wpdb->prefix . '163pinglun';
		$post_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $table WHERE news_url=%s", $news_url));
		if(!$post_id) {
			$my_post = array(
				'post_title' => $news_title,
				'post_content' => $news_title.' <a class="continue" href="'.$news_url.'" target="_blank">'.__('查看网易新闻原文↗').'</a>',
				'post_status' => 'publish'
			);
			$post_id = wp_insert_post($my_post);
			if(!post_id) {
				wp_die(__('<h2 class="entry-title" style="color:#f00;">老兄，程序出错啦，请返回首页重试</h2>'
					.'<div>您刚才提交的评论地址：' . $tie_url . '</div>'));
			}
		}

		//如果用户已登录
		$user = wp_get_current_user();
		if ( $user->ID ) {
			if ( empty( $user->display_name ) )
				$user->display_name=$user->user_login;
			$commentdata['comment_author'] = $wpdb->escape($user->display_name);
			$commentdata['comment_author_url'] = $wpdb->escape($user->user_url);
		}
		
		if(!$commentdata['comment_author']) {
			$commentdata['comment_author'] = __('阿猫阿狗');
		}
		$commentdata['comment_post_ID'] = $post_id;
		$commentdata['comment_content'] = $tie_url;
		$post_data = json_encode($data['postData']);

		//向wp_163pinglun表中插入一行数据
		$table = $wpdb->prefix . '163pinglun';
		$wpdb->insert($table, array('tie_url' => $tie_url, 'news_url' => $news_url, 'post_id' => $post_id, 'comment_id' => -1, 'content_json_data' => $post_data), array('%s', '%s', '%d', '%d', '%s'));	
		
		return $commentdata;
	}
}

/*
* 把最新通过审核的评论作为对应文章的摘要
*/
add_action('wp_set_comment_status', 'set_latest_comment_as_excerpt', 10, 2);
if(!function_exists('set_latest_comment_as_excerpt')) {
	function set_latest_comment_as_excerpt($comment_id, $comment_status) {
		if('1' == $comment_status || 'approve' == $comment_status) {
			global $wpdb;
			$table = $wpdb->prefix . '163pinglun';
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE comment_id = %d", $comment_id));
			if($row) {
				$json = json_decode($row->content_json_data, true);
				if(($json_error_code = json_last_error()) != JSON_ERROR_NONE) {
					wp_die(__('老兄，程序出错啦：不能正确解析评论内容 @wp_set_comment_status. JSON ERROR CODE=' . $json_error_code));
				}
				$last_index = count($json);
				$excerpt = $json[$last_index]['b'];
				//$wpdb->update($wpdb->prefix . 'posts', array('post_excerpt' => $excerpt), array('ID' => $row->post_id), array('%s'), array('%d'));
				
				//Note that when the post is "updated", the existing Post record is duplicated for audit/revision purposes. 
				//@see http://codex.wordpress.org/Function_Reference/wp_update_post
				wp_update_post(array(
					'ID' => $row->post_id,
					'post_excerpt' => $excerpt
				));
			}
		} else {
			//TODO: 如果驳回这条评论，那摘要怎么处理？？？
		}
	}
}

add_action('wp_insert_comment', 'insert_163pinglun', 10, 2);
if(!function_exists('insert_163pinglun')) {
	function insert_163pinglun($id, $comment) {
		global $wpdb;
		$table = $wpdb->prefix . '163pinglun';
		
		//把评论ID更新到wp_163pinglun表的相应行
		$wpdb->update($table, array('comment_id' => $id), array('tie_url' => $comment->comment_content), array('%d'), array('%s'));
			
		//替换评论内容
		$content_json_data = $wpdb->get_var($wpdb->prepare("SELECT content_json_data FROM $table WHERE comment_id = %d", $id));
		$tie = get_tie($id, json_decode($content_json_data, true));
		if(($json_error_code = json_last_error()) != JSON_ERROR_NONE) {
			wp_die(__('老兄，程序出错啦：不能正确解析评论内容 @wp_insert_comment. JSON ERROR CODE=' . $json_error_code));
		}
		$wpdb->update($wpdb->prefix . 'comments', array('comment_content' => $tie), array('comment_ID' => $id), array('%s'), array('%d'));
		
		//如果当前评论所在的文章还没有通过审核的评论，那么把当前评论设置为通过审核（得保证文章里至少能看到一条评论吧）
		$post_id = $comment->comment_post_ID;
		$post = get_post($post_id);
		$comment_count = $post->comment_count;
		if($comment_count < 1) {
			wp_set_comment_status($id, 1);
		}
		do_action('wp_set_comment_status', $id, $comment->comment_approved);
	}
}

add_filter('comment_post_redirect', 'comment_post_succ', 10, 2);
if(!function_exists('comment_post_succ')) {
	function comment_post_succ($location, $comment) {
		$params = array(
			'status' => 'succ',
			'comment_id' => $comment->comment_ID
		);
		$location = get_bloginfo("url") . "/?pagename=result&" . http_build_query($params, null, '&');
		return $location;
	}
}

/*
*在后台彻底删除评论时，把wp_163pinglun表中的相应行也删除
*/
add_action('delete_comment', 'delete_comment_fn');
if(!function_exists('delete_comment_fn')) {
	function delete_comment_fn($comment_id) {
		global $wpdb;
		$table = $wpdb->prefix . '163pinglun';
		$wpdb->query( $wpdb->prepare("DELETE FROM $table WHERE comment_id = %d LIMIT 1", $comment_id) );
	}
}

/*
*在后台彻底删除文章时，把wp_163pinglun表中的相应行也删除
*/
global $user;
if(user_can($user->ID, 'delete_posts')) {
	add_action('delete_post', 'delete_post_fn');
}
if(!function_exists('delete_post_fn')) {
	function delete_post_fn($post_id) {
		global $wpdb;
		$table = $wpdb->prefix . '163pinglun';
		$wpdb->query( $wpdb->prepare("DELETE FROM $table WHERE post_id = %d LIMIT 1", $post_id) );
	}
}

/*
*后台管理“评论”面板里，增加comment.css样式
*/
add_filter('admin_print_styles', 'add_comment_style');
if(!function_exists('add_comment_style')) {
	function add_comment_style() {
		$comment_style = WP_PLUGIN_URL . '/163pinglun/comment.css';
		wp_register_style('comment_style', $comment_style);
		wp_enqueue_style('comment_style');
	}
}

/**
 * 修改 JSON REST API 返回评论的接口，返回json格式的评论
 */
add_filter('my_comment_text', 'get_comment_json', 999, 2);
if(!function_exists('get_comment_json')) {
	function get_comment_json($comment_content, $comment) {
		global $wpdb;
		$table = $wpdb->prefix . '163pinglun';
		$jsonTxt = $wpdb->get_var($wpdb->prepare("SELECT content_json_data FROM $table WHERE comment_id = %d", $comment->comment_ID));
		$json = json_decode($jsonTxt, true);
		if(json_last_error() == JSON_ERROR_NONE) {
			return $json;
		} else {
			return $comment_content;
		}
	}
}

add_filter('json_prepare_post', 'add_prev_next_link');
if(!function_exists('add_prev_next_link')) {
	function add_prev_next_link($_post) {
		global $post;
		$post = get_post($_post['ID']);
		$prev = get_previous_post();
		$next = get_next_post();
		$_post['prev_post'] = $prev;
		$_post['next_post'] = $next;
		return $_post;
	}
}

add_filter('json_prepare_post', 'show_post_meta');
if(!function_exists('show_post_meta')) {
	function show_post_meta($_post) {
		$fields = get_post_custom($_post['ID']);
		foreach ($fields as $key => $value) {
			if($key[0] == "_") {
				 // 下划线开头的数据是插件私有使用的，不用返回给接口数据
				unset($fields[$key]);
			}
		}
		$_post['post_meta'] = $fields;
		return $_post;
	}
}

include_once 'shortcodes.php';
include_once 'tie-form.php';
?>
