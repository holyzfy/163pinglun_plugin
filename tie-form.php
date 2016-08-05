<?php
function get_tie_form($error_msg = '') {
	if($error_msg) {
		$tie_url = $_GET['tie_url'];
		$error_html = '<strong class="left error">' . $error_msg . '<br><a class="tip-bd" href="http://weibo.com/zhaofuyun" target="_blank">有问题请@我</a></strong>';
	}
	$action_url = site_url( '/wp-comments-post.php' );
	$commenter = wp_get_current_commenter();
	$comment_author = esc_attr( $commenter['comment_author'] );
	$comment_author_url = esc_attr( $commenter['comment_author_url'] );
	$comment_id_fields = get_comment_id_fields();
	$zfy163pinglun_options = get_option('zfy163pinglun_options');
	$help_url = get_permalink($zfy163pinglun_options['help']);
	$template_directory = get_bloginfo('template_directory');
	
	$comment_fields = <<<COMMENTFIELDS
	<div class="left"><input class="field" type="text" size="30" name="author" id="author" value="$comment_author" placeholder="尊姓大名？" title="请留下你的大名（可不填）"></div>
COMMENTFIELDS;
	
	global $current_user;
	get_currentuserinfo();
	if(is_user_logged_in()) {
		$comment_fields = '<div class="left logged-in-as">' . sprintf( __( '以 <a href="%1$s">%2$s</a> 的身份登录。<a href="%3$s" title="登出此账户">登出?</a>' ), admin_url( 'profile.php' ), $current_user->user_login, wp_logout_url( home_url() ) ) . '</div>';
	}
	
	return <<<EOT
<div class="submit-form">
	<h2 class="submit-form-hd">推荐精彩的网易评论</h2>
	<div class="submit-form-bd">
		<div class="tip clearfix">$error_html<a class="tip-bd" href="$help_url">如何复制评论网址</a></div>
		<form action="$action_url" method="post">
			<textarea class="field" rows="10" cols="30" id="comment" name="comment" placeholder="请输入评论网址" title="请输入评论网址">$tie_url</textarea>
			<div class="clearfix">
				$comment_fields				
				<button class="right" type="submit" name="submit" onClick="javascript:if($(this).hasClass('disabled')) return false; $(this).addClass('disabled'); $('#wait').show();">提 交</button>
				<img id="wait" src="$template_directory/images/wait.gif" alt="正在提交..." class="right wait" style="display:none;">
				$comment_id_fields
			</div>
		</form>
	</div>
</div>
EOT;
}
?>
