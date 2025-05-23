<?php
wpfb_loadclass('File');

$file = new WPFB_File();

if(empty($form_url))
	$form_url = $in_editor ? remove_query_arg(array('file_id', 'page', 'action')) : add_query_arg('page', 'wpfilebase_files', admin_url('admin.php'));

if (!empty($_GET['redirect_to']))
	$form_url = add_query_arg(array('redirect' => 1, 'redirect_to' => urlencode($_GET['redirect_to'])), $form_url);
elseif (!empty($_GET['redirect_referer']))
	$form_url = add_query_arg(array('redirect' => 1, 'redirect_to' => urlencode($_SERVER['HTTP_REFERER'])), $form_url);

if (empty($nonce_action)) {
	$nonce_action = WPFB . "-" . $action;
	if ($update)
		$nonce_action .= ($multi_edit ? $item_ids : $file->file_id);
	if ($in_editor)
		$nonce_action .= "-editor";
}

if ($update)
	$file_category = $file->file_category;
else {
	$cats = array_filter(array(@$_REQUEST['file_category'], $file->file_category, WPFB_Core::$settings->default_cat));
	$file_category = reset($cats);
}

//$file_category = ($update || empty($_REQUEST['file_category'])) ? $file->file_category : $_REQUEST['file_category'];

if (!$update)
	$file->file_direct_linking = WPFB_Core::$settings->default_direct_linking;

wpfb_loadclass('AdvUploader');
$adv_uploader = WPFB_AdvUploader::Create($form_url, $update);


if (isset($_GET['visual_editor'])) {
	update_user_option(get_current_user_id(), WPFB . '_visual_editor', (int) $_GET['visual_editor']);
}
$visual_editor = get_user_option(WPFB . '_visual_editor') && !$in_widget && !$in_editor;





WPFB_Admin::PrintAdminSchemeCss();
wpfb_call('Output', 'PrintJS'); // Initialize wpfbConf JavaScript variable
?>

<?php $adv_uploader->PrintScripts(); ?>

<form enctype="multipart/form-data" name="<?php echo $action ?>" id="<?php echo $action ?>" method="post" action="<?php echo $form_url ?>" class="validate">

	<?php
	if (!$in_widget) {
		if ($in_editor) {
			?><div style="float: right;"><a style="font-style:normal;" href="<?php echo add_query_arg('exform', ($exform ? '0' : '1')); ?>"><?php _e($exform ? 'Simple Form' : 'Extended Form', 'wp-filebase') ?></a></div><h3 class="media-title"><?php echo $title ?></h3><?php
		} else {
			echo "<h2>" . $title . ' ';
			if (!$update) {
				?><a style="font-style:normal; white-space: nowrap;" href="<?php echo add_query_arg('exform', ($exform ? '0' : '1')) . '#' . $action; ?>" class="add-new-h2"><?php _e($exform ? 'Simple Form' : 'Extended Form', 'wp-filebase') ?></a> <?php
			}

			if (!$update) {
				echo ' <a style="font-style:normal; white-space: nowrap;" href="' . admin_url('admin.php?page=wpfilebase_manage&amp;action=batch-upload') . '" class="add-new-h2">' . __('Batch Upload', 'wp-filebase') . '</a>';
			}
			echo "</h2>";
		}
	}
	?>

	<?php wp_print_scripts('utils'); ?>

	<script type="text/javascript">
		var uploaderMode = 0;

		function WPFB_switchFileUpload(i)
		{
			var as = jQuery('#file-upload-wrap,#file-remote-wrap').toArray();
			jQuery(as[i]).removeClass('hidden');
			jQuery(as[!i + 0]).addClass('hidden');
			as = jQuery('a', jQuery('#wpfilebase-upload-menu')).toArray();
			jQuery(as[i]).addClass('admin-scheme-bgcolor-2 current');
			jQuery(as[!i + 0]).removeClass('admin-scheme-bgcolor-2 current');
			jQuery('#file_is_remote').val(i); //upd val
			return false;
		}

		jQuery(document).ready(function ($) {
			$('#file-upload-progress').hide();
			$('#cancel-upload').hide();

<?php if (isset($_GET['flash'])) { ?>
				WPFB_switchUploader(<?php echo (int) $_GET['flash']; ?>);
<?php } else { ?>
				WPFB_switchUploader((typeof (getUserSetting) != 'function') ? true : getUserSetting('wpfb_adv_uploader', true));
				$('#file-upload-wrap').bind('click.uploader', function (e) {
					var target = $(e.target);

					if (target.is('.upload-flash-bypass a') || target.is('a.uploader-html')) { // switch uploader to html4
						WPFB_switchUploader(0);
						return false;
					} else if (target.is('.upload-html-bypass a')) { // switch uploader to multi-file
						WPFB_switchUploader(1);
						return false;
					}
				});
<?php } ?>

			//	jQuery("#file_description").addClass("mceEditor");
			//	if ( typeof( tinyMCE ) == "object" && typeof( tinyMCE.execCommand ) == "function" ) {
			//		tinyMCE.execCommand("mceAddControl", false, "file_description");
			//	}

			$('#file_tags').keyup(function () {
				var tags = $(this).val();
				var lt = $.trim(tags.substr(tags.lastIndexOf(',') + 1));
				if (!lt || lt == '') {
					jQuery('#file_tags_proposal').empty().hide();
					return;
				}

				jQuery.ajax({
					url: wpfbConf.ajurl,
					data: {wpfb_action: "tag_autocomplete", "tag": lt}, // TODO test!
					dataType: "json",
					success: (function (data) {
						var fp = $('#file_tags_proposal');

						if (data.length == 0) {
							fp.empty().hide();
							return
						}

						if (fp.size() == 0) {
							$('#file_tags').parent().append('<div id="file_tags_proposal"></div>');
							fp = $('#file_tags_proposal');
						}

						var html = '<ul>';
						for (var i = 0; i < data.length; i++) {
							html += '<li><a href="">' + data[i].t + '</a> (' + data[i].n + ')</li>';
						}
						fp.html(html + '</ul>').show();

						fp.find('a').click(function () {
							WPFB_addTag($(this).html());
							return false;
						});

						var p = $('#file_tags').offset();
						p.top += $('#file_tags').height() + 8;
						fp.offset(p);
					})
				});
			});

			$('#file_tags').focusout(function ($) {
				jQuery('#file_tags_proposal').fadeOut(400);
			});
		});

		function WPFB_switchUploader(adv) {
			if (adv && adv != "0") {
				jQuery('#flash-upload-ui').show();
				jQuery('#html-upload-ui').hide();
				setUserSetting('wpfb_adv_uploader', 1);
				if (typeof (uploader) == 'object')
					uploader.refresh();
				if (typeof (swfuploadPreLoad) == 'function')
					swfuploadPreLoad();
			} else {
				jQuery('#flash-upload-ui').hide();
				jQuery('#html-upload-ui').show();
				setUserSetting('wpfb_adv_uploader', 0);
			}
		}

		function WPFB_addTag(tag)
		{
			var inp = jQuery('#file_tags');
			var v = inp.val();
			var i = v.lastIndexOf(',') + 1;
			inp.val(v.substr(0, i) + tag + ',');
			jQuery('#file_tags_proposal').empty().hide();
			inp.focus();
		}

	</script>


	<input type="hidden" name="action" id="file_form_action" value="<?php echo $action ?>" />
	<input type="hidden" name="file_id" id="file_id" value="<?php echo $update ? ($multi_edit ? $item_ids : $file->file_id) : ""; ?>" />
	<?php wp_nonce_field($nonce_action, 'wpfb-file-nonce'); ?>

	<div class="wpfb-upload-box">
		<?php if ($update && $file->IsScanLocked()) { ?>
			<div class="overlay-locked"><?php echo WPFB_Admin::Icon('lock', 80); ?>
				<span><?php printf(__('This file is locked for %s or until the scan process completes.', 'wp-filebase'), human_time_diff(time(), $file->file_scan_lock)); echo ' '.__('You can edit meta data only.', 'wp-filebase') ?></span>
			</div>
		<?php } ?>
		<div id="wpfilebase-upload-menu" class="admin-scheme-bgcolor-0">
			<a href="#" <?php echo ($file->IsRemote() ? '' : 'class="admin-scheme-bgcolor-2 current"'); ?> onclick="return WPFB_switchFileUpload(0)"><?php _e('Upload') ?></a>
			<a href="#" <?php echo ($file->IsRemote() ? 'class="admin-scheme-bgcolor-2 current"' : ''); ?> onclick="return WPFB_switchFileUpload(1)"><?php _e('File URL') ?></a>
			<input type="hidden" name="file_is_remote" id="file_is_remote" value="<?php echo ($file->IsRemote() ? 1 : 0); ?>" />
		</div>
		<div id="wpfilebase-upload-tabs">
			<div id="file-upload-wrap" <?php echo ($file->IsRemote() ? 'class="hidden"' : ''); ?>>
				<div id="file_thumbnail_preview"></div>

				<div id="html-upload-ui">
					<label for="file_upload"><?php _e('Choose File', 'wp-filebase') ?></label>
					<input type="file" name="file_upload" id="file_upload" /><br />
					<?php printf(__('Maximum upload file size: %s.'/* def */), WPFB_Output::FormatFilesize(WPFB_Core::GetMaxUlSize())) ?> <b>&nbsp;&nbsp;<a href="#" onclick="return alert(this.title) && false;" title="<?php printf(__('Ask your webhoster to increase this limit, it is set in %s.', 'wp-filebase'), 'php.ini'); ?>">?</a></b>
					<p class="upload-html-bypass hide-if-no-js"><?php
						_e('You are using the Browser uploader.');
						printf(__('Try the <a href="%s">Drag&amp;Drop uploader</a> instead.', 'wp-filebase'), esc_url(add_query_arg('flash', 1)));
						?>
				</div>
				<div id="flash-upload-ui"><?php $adv_uploader->Display(); ?>
					<script> jQuery('#file-upload-progress').appendTo('#wpfilebase-upload-menu').addClass('admin-scheme-color-3');</script>
				</div> <!--  flash-upload-ui -->
				<?php
				if ($update) {
					echo '<div>' . __('Rename') . ': ';
					?>
					<input name="file_rename" id="file_rename" type="text" value="<?php echo esc_attr($file->file_name); ?>" style="width:280px;" /><br />
					<?php
					echo ' (' . $file->GetFormattedSize() . ', ' . wpfb_call('Download', 'GetFileType', $file->file_name) . ', MD5: <code>' . $file->file_hash . '</code>)</div>';
				}
				?>
			</div>
			<div id="file-remote-wrap" <?php echo ($file->IsRemote() ? '' : 'class="hidden"'); ?>>
				<input name="file_remote_uri" id="file_remote_uri" type="text" value="<?php echo esc_attr($file->file_remote_uri); ?>" style="width:100%" placeholder="<?php _e('File URL', 'wp-filebase') ?>" /><br />
				<fieldset><legend class="hidden"></legend>
					<label><input type="radio" name="file_remote_redirect" value="0" <?php checked($file->IsLocal()); ?>  onchange="jQuery('#wpfilebase-remote-scan-wrap').hide();" /><?php _e('Copy file into Filebase (sideload)', 'wp-filebase') ?></label>
					<br />
					<span style="text-overflow: ellipsis; white-space: nowrap"><label><input type="radio" name="file_remote_redirect" value="1" <?php checked($file->IsRemote()); ?> onchange="jQuery('#wpfilebase-remote-scan-wrap').show();" /><?php _e('Redirect to URL', 'wp-filebase') ?></label>
						&nbsp;
						<span id="wpfilebase-remote-scan-wrap" class="hidden" ><label><input type="checkbox" name="file_remote_scan" value="1" checked="checked" /><?php _e('Scan remote file (disable for large files)', 'wp-filebase') ?></label></span>					</span>
				</fieldset>
			</div>
		</div>
	</div>

	<table class="form-table">
			<?php if (!$multi_edit) { ?>
			<tr>		
	<?php if ($exform) { ?>		
					<th scope="row" valign="top"><label for="file_upload_thumb"><?php _e('Thumbnail'/* def */) ?></label></th>
					<td class="form-field" colspan="3"><input type="file" name="file_upload_thumb" id="file_upload_thumb" />
						<br /><?php _e('You can optionally upload a thumbnail here. If the file is a valid image, a thumbnail is generated automatically.', 'wp-filebase'); ?>
						<div style="<?php if (empty($file->file_thumbnail)) echo "display:none;"; ?>" id="file_thumbnail_wrap">
							<br /><img src="<?php echo esc_attr($file->GetIconUrl()); ?>" alt="Icon" /><br />
							<b id="file_thumbnail_name"><?php echo $file->file_thumbnail; ?></b><br />
		<?php if ($update && !empty($file->file_thumbnail)) { ?> <label for="file_delete_thumb"><?php _e('Delete') ?></label><input type="checkbox" value="1" name="file_delete_thumb" id="file_delete_thumb" style="display:inline; width:30px;" />
					<?php } ?>
						</div>
					</td>
	<?php } else { ?><th scope="row"></th><td colspan="3">
						<!--
								<div style="<?php if (empty($file->file_thumbnail)) echo "display:none;"; ?>" id="file_thumbnail_wrap" class="thumbnail-centered">
									<br /><img src="<?php echo esc_attr($file->GetIconUrl()); ?>" alt="Icon" /><br />
								</div>
						-->

						<i><?php _e('The following fields are optional.', 'wp-filebase') ?></i></td><?php } ?>
			</tr>
<?php } /* multi_edit */ ?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="file_display_name"><?php _e('Title') ?></label></th>
			<td width="60%"><input name="file_display_name" id="file_display_name" type="text" value="<?php echo esc_attr($file->file_display_name); ?>" size="<?php echo ($in_editor || $in_widget) ? 20 : 40 ?>" /></td>
			<th scope="row" valign="top"><label for="file_version"><?php _e('Version') ?></label></th>
			<td width="40%"><input name="file_version" id="file_version" type="text" value="<?php echo esc_attr($file->file_version); ?>" size="<?php echo ($in_editor || $in_widget) ? 10 : 20 ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="file_author"><?php _e('Author') ?></label></th>
			<td><input name="file_author" id="file_author" type="text" value="<?php echo esc_attr(!empty($file->file_author) ? $file->file_author : WPFB_Core::$settings->default_author); ?>" size="<?php echo ($in_editor || $in_widget) ? 20 : 40 ?>" /></td>
				<?php if ($exform) { ?>
				<th scope="row" valign="top"><label for="file_date"><?php _e('Date') ?></label></th>
				<td><?php
					//create a comment object for the touch_time function
					global $comment;
					$comment = new stdClass();
					$comment->comment_date = false;
					if ($file != null)
						$comment->comment_date = $file->file_date;
					?><div class="wpfilebase-date-edit"><?php touch_time($update, 0); ?></div></td>
			</tr>
			<tr class="form-field">
					<?php } ?>
			<th scope="row" valign="top"><label for="file_category"><?php _e('Category', 'wp-filebase') ?></label></th>
			<td><select name="file_category" id="file_category" class="postform wpfb-cat-select" onchange="WPFB_formCategoryChanged();"><?php
					echo WPFB_Output::CatSelTree(array('selected' => $file_category
						 , 'add_cats' => true
					))
					?></select></td>
			<?php if ($exform && !empty(WPFB_Core::$settings->licenses)) { ?>
				<th scope="row" valign="top"><label for="file_license"><?php _e('License', 'wp-filebase') ?></label></th>
				<td><select name="file_license" id="file_license" class="postform"><?php echo WPFB_Admin::MakeFormOptsList('licenses', $file ? $file->file_license : null, true) ?></select></td>
<?php } ?>
		</tr>

		<!--
		-->
		<tr class="form-field">
			<?php if (!$in_editor) { ?>
				<th scope="row" valign="top"><label for="file_post_id"><?php _e('Post') ?> ID</label></th>
				<td><input type="text" name="file_post_id" class="small-text" size="8" style="width:60px; text-align:right;" id="file_post_id" value="<?php echo esc_attr($file->file_post_id); ?>" /> <span id="file_post_title" style="font-style:italic;"><?php if ($file->file_post_id > 0) echo get_the_title($file->file_post_id); ?></span> <a href="javascript:;" class="button" onclick="WPFB_PostBrowser('file_post_id', 'file_post_title');"><?php _e('Select') ?></a></td>
			<?php } else { ?>
				<td><input type="hidden" name="file_post_id" id="file_post_id" value="<?php echo esc_attr($file->file_post_id); ?>" /></td>
			<?php } ?>
			<?php if ($exform) { ?>
				<th scope="row" valign="top"><label for="file_hits"><?php _e('Download Counter', 'wp-filebase') ?></label></th>
				<td><input type="text" name="file_hits" class="small-text" id="file_hits" value="<?php echo (int) $file->file_hits; ?>" /></td>
			</tr>
			<tr class="form-field">
				<?php if (WPFB_Core::$settings->platforms) { ?>
					<th scope="row" valign="top"><label for="file_platforms[]"><?php _e('Platforms', 'wp-filebase') ?></label></th>
					<td><select name="file_platforms[]" size="40" multiple="multiple" id="file_platforms[]" style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('platforms', $file ? $file->file_platform : null, true) ?></select></td>
				<?php } else { ?>
					<th></th><td></td><?php
				}
				if (WPFB_Core::$settings->requirements) {
					?>
					<th scope="row" valign="top"><label for="file_requirements[]"><?php _e('Requirements', 'wp-filebase') ?></label></th>
					<td><select name="file_requirements[]" size="40" multiple="multiple" id="file_requirements[]" style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('requirements', $file ? $file->file_requirement : null, true) ?></select></td>
				<?php } else { ?>
					<th></th><td></td><?php } ?>
			</tr>
			<tr>
				<?php if (WPFB_Core::$settings->languages) { ?>
					<th scope="row" valign="top"><label for="file_languages[]"><?php _e('Languages') ?></label></th>
					<td class="form-field"><select name="file_languages[]" size="40" multiple="multiple" id="file_languages[]" style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('languages', $file ? $file->file_language : null, true) ?></select></td>
				<?php } else { ?>
					<th></th><td></td><?php } ?>

				<th scope="row" valign="top"><label for="file_direct_linking"><?php _e('Direct linking', 'wp-filebase') ?></label></th>
				<td>
					<fieldset>
						<legend class="hidden"><?php _e('Direct linking') ?></legend>
						<label title="<?php _e('Yes') ?>"><input type="radio" name="file_direct_linking" value="1" <?php checked('1', $file->file_direct_linking); ?>/> <?php _e('Allow direct linking', 'wp-filebase') ?></label><br/>
						<label title="<?php _e('No') ?>"><input type="radio" name="file_direct_linking" value="0" <?php checked('0', $file->file_direct_linking); ?>/> <?php _e('Redirect to post', 'wp-filebase') ?></label>
						
					</fieldset>
				</td>
				<?php } ?>
		</tr>
		<tr <?php if (!$visual_editor) { ?>class="form-field"<?php } ?>>
			<th scope="row" valign="top"><label for="file_description"><?php _e('Description') ?></label>
				<?php if (!$in_widget && !$in_editor) { ?><br/><br/>
					<a style="font-style:normal; font-size:9px; padding:3px; margin:0;" href="<?php echo add_query_arg('visual_editor', ($visual_editor ? '0' : '1')) . '#' . $action; ?>" class="add-new-h2"><?php _e($visual_editor ? 'Simple Editor' : 'Visual Editor', 'wp-filebase') ?></a>
				<?php } ?>
			</th>
			<td colspan="3">
				<?php
				if ($visual_editor) {
					wp_editor($file->file_description, 'file_description', array('media_buttons' => false));
				} else {
					?>
					<textarea name="file_description" id="file_description" rows="5" cols="50" style="width: 97%;"><?php echo esc_html($file->file_description); ?></textarea>
				<?php } ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="file_tags"><?php _e('Tags') ?></label></th>
			<td colspan="3"><input name="file_tags" id="file_tags" type="text" value="<?php echo esc_attr(trim($file->file_tags, ',')); ?>" size="<?php echo ($in_editor || $in_widget) ? 20 : 40 ?>" maxlength="250" autocomplete="off" /></td>
		</tr>
		<?php if ($exform) { ?>
			<tr>
				<th scope="row" valign="top"><?php _e('Access Permission', 'wp-filebase') ?></th>
				<td>
					<?php if ($update) { ?><input type="hidden" name="file_perm_explicit" value="1" />
					<?php } else { ?>
						<label><input type="radio" name="file_perm_explicit" value="0" <?php checked(true); ?> onchange="jQuery('#file_perm_wrap').hide()" /><?php _e('Inherit Permissions', 'wp-filebase') ?> (<span id="file_inherited_permissions_label"></span>)</label><br />
						<label><input type="radio" name="file_perm_explicit" value="1" onchange="jQuery('#file_perm_wrap').show()" /><?php _e('Explicitly set permissions', 'wp-filebase') ?></label>
					<?php } ?>
					<div id="file_perm_wrap" <?php
					if (!$update) {
						echo 'class="hidden"';
					}
					?>>
						<?php _e('Limit file access by selecting one or more user roles.') ?>
						<div id="file_user_roles"><?php WPFB_Admin::RolesCheckList('file_user_roles', $user_roles) ?></div>
					</div>
				</td>


				<th scope="row" valign="top"></th>
				<td><input type="checkbox" name="file_offline" id="file_offline" value="1" <?php checked('1', $file->file_offline); ?>/> <label for="file_offline"><?php _e('Offline', 'wp-filebase') ?></label></td>

			</tr>
			<!-- TODO owner -->
			<?php
		}
		$custom_fields = WPFB_Core::GetCustomFields(false, $custom_defaults);
		foreach ($custom_fields as $ct => $cn) {
			$hid = 'file_custom_' . esc_attr($ct);
			?>
			<tr class="form-field">
				<th scope="row" valign="top"><label for="<?php echo $hid; ?>"><?php echo esc_html($cn) ?></label></th>
				<td colspan="3"><textarea name="<?php echo $hid; ?>" id="<?php echo $hid; ?>" rows="2" cols="50" style="width: 97%;"><?php echo (!$update && empty($file->$hid)) ? $custom_defaults[$ct] : esc_html($file->$hid); ?></textarea></td>
			</tr> <?php
		}
		if (!empty($custom_fields)) {
			?>
			<tr><td colspan="4" style="text-align:right;margin:0;padding:0;"><a href="<?php echo admin_url('admin.php?page=wpfilebase_sets#' . sanitize_title(__('Form Presets', 'wp-filebase'))); ?>"><?php _e('Manage Custom Fields', 'wp-filebase') ?></a></td></tr>
									 <?php } ?>
	</table>
	<p class="submit"><input type="submit" class="button-primary" id="file-submit" name="submit-btn" value="<?php echo $update ? __('Update') : $title; ?>" <?php if (false && !$in_editor) { ?>onclick="this.form.submit();
				return false;"<?php } ?>/></p>

	<?php
	// GetID3 functionality has been removed for PHP 8.0+ compatibility
	?>
</form>