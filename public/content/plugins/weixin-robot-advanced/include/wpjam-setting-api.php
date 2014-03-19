<?php
/* WPJAM OPTIONS 
** Version: 1.0
*/

function wpjam_option_page($labels, $title='', $type='default', $icon='options-general'){
	extract($labels);
	?>
	<div class="wrap">
	<?php if($icon){?>
		<div id="icon-<?php echo $icon;?>" class="icon32"><br></div>
	<?php } ?>
	<?php if($type == 'tab'){ ?>
		<h2 class="nav-tab-wrapper">
	        <?php foreach ( $sections as $section_name => $section) { ?>
	            <a class="nav-tab" href='javascript:void();' id="tab-title-<?php echo $section_name; ?>"><?php echo $section['title']; ?></a>
	        <?php } ?>    
	    </h2>
		<form action="options.php" method="POST">
			<?php settings_fields( $option_group ); ?>
			<?php foreach ( $sections as $section_name => $section ) { ?>
	            <div id="tab-<?php echo $section_name; ?>" class="div-tab hidden">
	                <?php wpjam_do_settings_section($option_page, $section_name); ?>
	            </div>                      
	        <?php } ?>
			<input type="hidden" name="<?php echo $option_name;?>[current_tab]" id="current_tab" value="" />
			<?php submit_button(); ?>
		</form>
		<?php wpjam_option_tab_script($option_name);?>
	<?php }else{ ?>
		<?php if($title){?>
		<h2><?php echo $title; ?></h2>
		<?php }?>
		<form action="options.php" method="POST">
			<?php settings_fields( $option_group ); ?>
			<?php do_settings_sections( $option_page ); ?>
			<?php submit_button(); ?>
		</form>
	<?php } ?>
	</div>
	<?php
}

function wpjam_option_tab_script($option_name=''){
	$current_tab = '';

	if($option_name){
		$option = get_option( $option_name );
		if(!empty($_GET['settings-updated'])){
			$current_tab = $option['current_tab'];
		}
	}
	?>
	<script type="text/javascript">
	<?php if($current_tab){ ?>
		jQuery('#tab-title-<?php echo $current_tab; ?>').addClass('nav-tab-active');
		jQuery('#tab-<?php echo $current_tab; ?>').show();
	<?php } else{ ?>
		//设置第一个显示
		jQuery('a.nav-tab').first().addClass('nav-tab-active');
		jQuery('div.div-tab').first().show();
	<?php } ?>
		jQuery(document).ready(function(){
			jQuery('a.nav-tab').on('click',function(){
		        jQuery('a.nav-tab').removeClass('nav-tab-active');
		        jQuery(this).addClass('nav-tab-active');
		        jQuery('div.div-tab').hide();
		        jQuery('#'+jQuery(this)[0].id.replace('title-','')).show();
		        jQuery('#current_tab').val(jQuery(this)[0].id.replace('tab-title-',''));
		    });
		});
	</script>
<?php
}

function wpjam_add_settings($labels,$defaults){
	extract($labels);
	register_setting( $option_group, $option_name, $field_validate );
	$field_callback = 'wpjam_field_callback';
	foreach ($sections as $section_name => $section) {
		add_settings_section( $section_name, $section['title'], $section['callback'], $option_page );

		$fields = isset($section['fields'])?$section['fields']:(isset($section['fileds'])?$section['fileds']:''); // 尼玛写错英文单词的 fallback

		if($fields){
			foreach ($fields as $field_name=>$field) {
				$field['option']	= $option_name;
				$field['name']		= $field_name;

				$filed_title		= $field['title'];

				if(in_array($field['type'], array('text','select','datetime','textarea','checkbox'))){
					$filed_title = '<label for="'.$field_name.'">'.$filed_title.'</label>';
				}

				$field['default'] 	= isset($defaults[$field_name])?$defaults[$field_name]:'';
				add_settings_field( 
					$field_name,
					$filed_title,		
					$field_callback,	
					$option_page, 
					$section_name,	
					$field
				);	
			}

		}
	}
}

function wpjam_field_callback($args) {

	$option_name	= $args['option'];
	$field_name		= $args['name'];

	$wpjam_option	= get_option( $option_name );

	$value			= (isset($wpjam_option[$field_name]))?$wpjam_option[$field_name]:$args['default'];
	$field 			= $option_name.'['.$field_name.']';
	$type			= $args['type'];
	$description	= (isset($args['description']))?($type == 'checkbox')?' <label for="'.$field_name.'">'.$args['description'].'</label>':'<br />'.$args['description']:'';

	if($type == 'text'){
		echo '<input type="text" id="'.$field_name.'" name="'.$field.'" value="'.$value.'" class="regular-text" />';
	}elseif($type == 'checkbox'){
		echo '<input type="checkbox" id="'.$field_name.'" name="'.$field.'" value="1" '.checked("1",$value,false).' />';
	}elseif($type == 'textarea'){
		$rows = isset($args['rows'])?$args['rows']:10;
		echo '<textarea id="'.$field_name.'" name="'.$field.'" rows="'.$rows.'" cols="50" class="large-text  code">'.$value.'</textarea>';
	}elseif ($type == 'select'){
		echo '<select id="'.$field_name.'" name="'.$field.'">';
		foreach ($args['options'] as $option_value => $option_title){ 
			echo '<option value="'.$option_value.'" '.selected($option_value,$value,false).'>'.$option_title.'</option>';
		}
		echo '</select>';
	}
	echo $description;
}

// 拷贝自 do_settings_sections 函数，用于 tab 显示选项。
function wpjam_do_settings_section($option_page, $section_name){
	global $wp_settings_sections, $wp_settings_fields;

	if ( ! isset( $wp_settings_sections[$option_page] ) )
		return;

	$section = $wp_settings_sections[$option_page][$section_name];

	if ( $section['title'] )
		echo "<h3>{$section['title']}</h3>\n";

	if ( $section['callback'] )
		call_user_func( $section['callback'], $section );

	if ( isset( $wp_settings_fields ) && isset( $wp_settings_fields[$option_page] ) && !empty($wp_settings_fields[$option_page][$section['id']] ) ){
		echo '<table class="form-table">';
		do_settings_fields( $option_page, $section['id'] );
		echo '</table>';
	}
}


function wpjam_get_setting($option, $setting_name){
	if(isset($option[$setting_name])){
		return str_replace("\r\n", "\n", $option[$setting_name]);
	}else{
		return '';
	}
}

function wpjam_get_option($option_name,$defaults){
	$option = get_option( $option_name );
	return wp_parse_args($option, $defaults);
}

function wpjam_admin_pagenavi($total_count, $number_per_page=50){

	$current_page = isset($_GET['paged'])?$_GET['paged']:1;

	if(isset($_GET['paged'])){
		unset($_GET['paged']);
	}

	$base_url = add_query_arg($_GET,admin_url('admin.php'));

	$total_pages	= ceil($total_count/$number_per_page);

	$first_page_url	= $base_url.'&amp;paged=1';
	$last_page_url	= $base_url.'&amp;paged='.$total_pages;
	
	if($current_page > 1 && $current_page < $total_pages){
		$prev_page		= $current_page-1;
		$prev_page_url	= $base_url.'&amp;paged='.$prev_page;

		$next_page		= $current_page+1;
		$next_page_url	= $base_url.'&amp;paged='.$next_page;
	}elseif($current_page == 1){
		$prev_page_url	= '#';
		$first_page_url	= '#';
		if($total_pages > 1){
			$next_page		= $current_page+1;
			$next_page_url	= $base_url.'&amp;paged='.$next_page;
		}else{
			$next_page_url	= '#';
		}
	}elseif($current_page == $total_pages){
		$prev_page		= $current_page-1;
		$prev_page_url	= $base_url.'&amp;paged='.$prev_page;
		$next_page_url	= '#';
		$last_page_url	= '#';
	}
	?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<span class="displaying-num">每页 <?php echo $number_per_page;?> 共 <?php echo $total_count;?></span>
			<span class="pagination-links">
				<a class="first-page <?php if($current_page==1) echo 'disabled'; ?>" title="前往第一页" href="<?php echo $first_page_url;?>">«</a>
				<a class="prev-page <?php if($current_page==1) echo 'disabled'; ?>" title="前往上一页" href="<?php echo $prev_page_url;?>">‹</a>
				<span class="paging-input">第 <?php echo $current_page;?> 页，共 <span class="total-pages"><?php echo $total_pages; ?></span> 页</span>
				<a class="next-page <?php if($current_page==$total_pages) echo 'disabled'; ?>" title="前往下一页" href="<?php echo $next_page_url;?>">›</a>
				<a class="last-page <?php if($current_page==$total_pages) echo 'disabled'; ?>" title="前往最后一页" href="<?php echo $last_page_url;?>">»</a>
			</span>
		</div>
		<br class="clear">
	</div>
	<?php
}

function wpjam_admin_display_fields($fields, $fields_type = 'table'){
	$new_fields = array();
	foreach($fields as $name => $field){ 
		$type		= $field['type'];
		$value		= $field['value'];

		$class		= isset($field['calss'])?$field['class']:'regular-text';
		$description= (isset($field['description']))?($type == 'checkbox')?' <label for="'.$name.'">'.$field['description'].'</label>':'<br />'.$field['description']:'';

		$title 		= $field['title'];
		if(in_array($type, array('text','select','datetime','textarea','checkbox'))){
			$title = '<label for="'.$name.'">'.$title.'</label>';
		}

		if($type == 'text' || $type == 'datetime'){
			$new_fields[$name] = array('title'=>$title, 'html'=>'<input name="'.$name.'" id="'. $name.'" type="text"  value="'.esc_attr($value).'" class="'.$class.'" />'.$description);
		}elseif ($type == 'hidden'){
			$new_fields[$name] = array('title'=>$title, 'html'=>'<input name="'.$name.'" id="'. $name.'" type="hidden"  value="'.esc_attr($value).'" />'.$description);
		}elseif ($type == 'checkbox'){
			$new_fields[$name] = array('title'=>$title, 'html'=>'<input name="'.$name.'" id="'. $name.'" type="checkbox"  value="'.esc_attr($value).'" '.$field['checked'].' />'.$description);
		}elseif($type == 'file'){
			$new_fields[$name] = array('title'=>$title, 'html'=>'<input name="'.$name.'" id="'. $name.'" type="text"  value="'.esc_attr($value).'" '.$field['checked'].' /><input onclick="wpjam_media_upload(\''. $name.'\')" class="button button-highlighted" type="button" value="上传'.$title.'" />').$description;
		}elseif($type == 'textarea'){
			$rows = isset($field['rows'])?$field['rows']:6;
			$new_fields[$name] = array('title'=>$title, 'html'=>'<textarea name="'.$name.'" id="'. $name.'" rows="'.$rows.'" cols="50"  class="'.$class.' code" >'.esc_attr($value).'</textarea>'.$description);
		}elseif ($type == 'select'){
			$new_field_html  = '<select name="'.$name.'" id="'. $name.'">';
			foreach ($field['options'] as $option_value => $option_title){ 
				$new_field_html .= '<option value="'.$option_value.'" '.selected($option_value,$value,false).'>'.$option_title.'</option>';
			}
			$new_field_html .= '</select>';
			$new_fields[$name] = array('title'=>$title, 'html'=>$new_field_html.$description);
		}elseif ($type == 'radio'){
			$new_field_html  = '';
			foreach ($field['options'] as $option_value => $option_title) {
				$new_field_html  .= '<p><input name="'.$name.'" type="radio" id="'.$name.'" value="'.$option_value .'" '.checked($option_value,$value,false).' /> '.$option_title.'</p>';
			}
			$new_fields[$name] = array('title'=>$title, 'html'=>$new_field_html.$description);
		}
	}
	
	?>
	<?php if($fields_type == 'table'){ ?>

	<table class="form-table" cellspacing="0">
		<tbody>
		<?php foreach ($new_fields as $name=>$field) { ?>

			<tr valign="top" id="tr_<?php echo $name; ?>">
				<th scope="row"><?php echo $field['title']; ?></th>
				<td><?php echo $field['html']; ?></td>
			</tr>

		<?php } ?>
		</tbody>
	</table>

	<?php } elseif($fields_type == 'list'){ ?>

	<ul>
	<?php foreach ($new_fields as $name=>$field) { ?>

		<li><?php echo $field['title']; ?> <?php echo $field['html']; ?> </li>

	<?php } ?>
	</ul>

	<?php } ?>

	<?php
}

function wpjam_confim_delete_script(){
	?>
	<script type="text/javascript">
	jQuery(function(){
		jQuery('span.delete a').click(function(){
			return confirm('确实要删除吗?'); 
		}); 
	});
	</script> 
	<?php
}


/*已经舍弃的函数*/
function wpjam_admin_display_form_table($form_fields){
 	?>
	<table class="form-table" cellspacing="0">
		<tbody>
			<?php foreach($form_fields as $form_field){ ?>
			<?php 
				$type		= $form_field['type'];
				$value		= $form_field['value'];

				$name		= $form_field['name'];

				$class		= isset($form_field['calss'])?$form_field['class']:'regular-text';
			?>
			<tr valign="top" id="tr_<?php echo $name; ?>">
				<th scope="row"><label for="<?php echo $form_field['name']; ?>"><?php echo $form_field['label'];?></label></th>
				<td>
				<?php if($type == 'text'){ ?>
					<input name="<?php echo $name;?>" id="<?php echo $name;?>" type="text"  value="<?php echo esc_attr($value); ?>" class="<?php echo $class; ?>" />
				<?php }elseif($type == 'datetime'){ ?>
					<input name="<?php echo $name;?>" id="<?php echo $name;?>" type="text"  value="<?php echo $value; ?>" class="<?php echo $class; ?>" />
				<?php }elseif($type == 'textarea'){ ?>
					<textarea name="<?php echo $name;?>" id="<?php echo $name;?>" rows="6" cols="50"  class="<?php echo $class; ?> code"><?php echo esc_textarea($value); ?></textarea>
				<?php }elseif ($type == 'hidden'){ ?>
					<input name="<?php echo $name;?>" id="<?php echo $name;?>" type="hidden"  value="<?php $value ;?>" />
				<?php }elseif ($type == 'checkbox'){ ?>
					<input name="<?php echo $name;?>" id="<?php echo $name;?>" type="checkbox"  value="<?php echo $value ?>" <?php echo $form_field['checked']; ?> /> 是否激活
				<?php }elseif ($type == 'select'){ ?>
					<select name="<?php echo $name;?>" id="<?php echo $name;?>"  >
					<?php foreach ($form_field['options'] as $option_value => $option_title){ $selected = ($option_value == $value)?'selected':''; ?>
						<option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $option_title; ?></option>
					<?php }?>
					</select>
				<?php }elseif($type == 'file'){ ?>
					<input name="<?php echo $name;?>" id="<?php echo $name;?>" type="text"  value="<?php echo $value; ?>" class="<?php echo $class; ?>" /><input onclick="wpjam_media_upload('<?php $form_field['name']; ?>')" class="button button-highlighted" type="button" value="上传' <?php echo $form_field['label']; ?>" />
				<?php } ?>
				<?php if(isset($form_field['description'])) { ?><p class="description"><?php echo $form_field['description'];?></p><?php } ?>
				</td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
}