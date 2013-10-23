<?php
/**
* 
*/
class HikerCommand extends WP_CLI_Command
{
	/**
	* print a greeting. defaults to --draft=false
	*
	* @synopsis --title=<string> --content=<string> [--draft]
	*/
	public function add($arg, $assoc_args)
	{
		extract($assoc_args);
		$postarr = array(
		"post_type" => 'post',
		"post_title" => $title,
		"post_content" => $conent,
		"post_status" => !empty($draft) ? 'draft' :  'publish'
		);
		wp_insert_post( $postarr, $wp_error = false );
		WP_CLI::success("创建成功");
	}
}

WP_CLI::add_command("hiker", "HikerCommand");