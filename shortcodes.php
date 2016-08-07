<?php
add_shortcode('result', 'result_func');
function result_func() {
	$status = $_GET['status'];
	$comment_id = $_GET['comment_id'];
	$error_msg = $_GET['error_msg'];
	
	switch($status) {
	case 'empty':
		$result  =get_tie_form(__('请填写评论网址'));
		break;
	case 'fail':
		$result = get_tie_form(__($error_msg ? $error_msg : '不能读取评论内容，请检查网址是否正确.'));
		break;
	case 'existed':
		$result = get_result(__('该评论已存在'), $comment_id, 'warning');
		break;
	case 'succ':
		$result = get_result(__('评论已提交'), $comment_id, 'succ');
		break;
	default:
		$result = get_tie_form(__(''));
	}	
	
	return $result;
}

function get_result($title, $comment_id, $cls = '') {
	global $wpdb;
	$comment_link = get_comment_link($comment_id);
	$table = $wpdb->prefix . '163pinglun';
	$content_json_data = $wpdb->get_var($wpdb->prepare("SELECT content_json_data FROM $table WHERE comment_id=%d", $comment_id));
	$json = json_decode($content_json_data, true);
	if(json_last_error() != JSON_ERROR_NONE) {
		wp_die(__('出错：不能正确解析评论内容'));
	}
	$length = count($json);
	$excerpt = $json[$length - 1]['content'];

	return <<<EOT
<div class="result $cls">
	<h1 class="result-hd">$title</h1>
	<div class="result-bd">$excerpt <a href="$comment_link">详细评论<span class="gt">&gt;&gt;</span></a><a href="$comment_link" class="continue"></a></div>
</div>
EOT;
}

add_shortcode('help', 'help_func');
function help_func() {
	$url = get_bloginfo('url');
	$template_directory = get_bloginfo('template_directory');
	
	return <<<EOT
	<div style="font-size:0; line-height:0;">
		<div><img src="$template_directory/images/help1.png" alt=""></div>
		<div><img src="$template_directory/images/help2.png" alt=""></div>
		<div><img src="$template_directory/images/help3.png" alt=""></div>
	</div>
	<div class="navigation" style="margin:35px 0 0 230px;">
		<div class="nav-previous"><a href="$url">返回到首页</a></div>
	</div>
EOT;
}
?>