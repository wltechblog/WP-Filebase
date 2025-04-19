<?php
wpfb_loadclass('File');

$multi_edit = !empty($multi_edit);
$in_widget = !empty($in_widget);
$in_editor = !empty($in_editor);

$update = $multi_edit ? !empty($item) : (isset($item) && is_object($item) && !empty($item->file_id));
$exform = $update || ( /* !$in_editor && */
    !empty($exform));


if (empty($item))
    $file = new WPFB_File();
else
    $file = &$item;

if (!empty($post_id))
    $file->file_post_id = $post_id;

$action = ($update ? 'updatefile' : 'addfile');
$title = $update ? __('Edit File', 'wp-filebase') : __('Add File', 'wp-filebase');

$default_roles = WPFB_Core::$settings->default_roles;
$user_roles = ($update || empty($default_roles)) ? $file->GetReadPermissions() : $default_roles;
$file_members_only = !empty($user_roles);

if (empty($form_url))
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


$form_name = 'wpfb-file-form';
WPFB_Admin::PrintAdminSchemeCss();
?>

<?php $adv_uploader->PrintScripts(); ?>

<div class="wrap">
    <h2><?php echo $title; ?></h2>

    <?php if (!empty($error)) { ?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>
    <?php if (!empty($success_msg)) { ?><div class="updated fade"><p><?php echo $success_msg; ?></p></div><?php } ?>

    <form enctype="multipart/form-data" name="<?php echo $form_name; ?>" id="<?php echo $form_name; ?>" method="post" action="<?php echo $form_url; ?>" class="validate">
        <?php wp_nonce_field($nonce_action, 'wpfb-file-nonce'); ?>
        <?php if ($update) { ?><input type="hidden" name="file_id" id="file_id" value="<?php echo $file->file_id; ?>" /><?php } ?>
        <?php if ($in_editor) { ?><input type="hidden" name="editor_mode" value="1" /><?php } ?>
        <?php if ($in_widget) { ?><input type="hidden" name="widget_mode" value="1" /><?php } ?>
        <?php if ($multi_edit) { ?><input type="hidden" name="files" value="<?php echo $item_ids; ?>" /><?php } ?>
        <?php if (!empty($_GET['redirect_to'])) { ?><input type="hidden" name="redirect_to" value="<?php echo esc_attr($_GET['redirect_to']); ?>" /><?php } ?>
        <?php if (!empty($_GET['redirect_referer'])) { ?><input type="hidden" name="redirect_referer" value="1" /><?php } ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <div id="titlediv">
                        <div id="titlewrap">
                            <label class="screen-reader-text" id="title-prompt-text" for="title"><?php _e('Enter file name here', 'wp-filebase'); ?></label>
                            <input type="text" name="file_display_name" size="30" tabindex="1" value="<?php echo esc_attr($file->file_display_name); ?>" id="title" autocomplete="off" />
                        </div>
                    </div>

                    <?php if ($visual_editor) { ?>
                        <div class="postarea" id="postdivrich">
                            <?php wp_editor($file->file_description, 'file_description', array('media_buttons' => false, 'textarea_name' => 'file_description')); ?>
                        </div>
                    <?php } else { ?>
                        <div class="postarea">
                            <div id="descriptiondiv" class="postbox">
                                <h3 class="hndle"><span><?php _e('Description', 'wp-filebase'); ?></span></h3>
                                <div class="inside">
                                    <div id="descriptionwrap">
                                        <textarea rows="5" cols="50" name="file_description" id="file_description" tabindex="2" style="width: 100%;"><?php echo esc_html($file->file_description); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (!$update) { ?>
                        <div id="uploaddiv" class="postbox">
                            <h3 class="hndle"><span><?php _e('File Upload', 'wp-filebase'); ?></span></h3>
                            <div class="inside">
                                <?php $adv_uploader->Display(); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div id="postbox-container-1" class="postbox-container">
                    <div id="submitdiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Publish', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <div id="submitpost" class="submitbox">
                                <div id="minor-publishing">
                                    <div id="minor-publishing-actions">
                                        <div id="save-action">
                                            <input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save'); ?>" tabindex="3" class="button button-highlighted" />
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>

                                <div id="major-publishing-actions">
                                    <?php if ($update) { ?>
                                        <div id="delete-action">
                                            <a class="submitdelete deletion" href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'files' => $file->file_id), $form_url), WPFB . '-delete-file' . $file->file_id); ?>"><?php _e('Delete'); ?></a>
                                        </div>
                                    <?php } ?>

                                    <div id="publishing-action">
                                        <input name="submit-btn" type="submit" class="button-primary" id="publish" tabindex="4" accesskey="p" value="<?php echo $update ? __('Update') : $title; ?>" />
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="categorydiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Category', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <?php
                            wpfb_loadclass('Category');
                            WPFB_Output::CatSelTree('file_category', $file_category, true, WPFB_Category::GetCats(), true);
                            ?>
                        </div>
                    </div>

                    <div id="tagsdiv-post_tag" class="postbox">
                        <h3 class="hndle"><span><?php _e('Tags', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <input type="text" name="file_tags" class="newtag form-input-tip" size="16" autocomplete="off" value="<?php echo esc_attr($file->file_tags); ?>" />
                        </div>
                    </div>

                    <div id="authordiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Author', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <label for="file_author" class="screen-reader-text"><?php _e('Author', 'wp-filebase'); ?></label>
                            <input type="text" name="file_author" size="13" id="file_author" value="<?php echo esc_attr($file->file_author); ?>" />
                        </div>
                    </div>

                    <div id="versiondiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Version', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <label for="file_version" class="screen-reader-text"><?php _e('Version', 'wp-filebase'); ?></label>
                            <input type="text" name="file_version" size="13" id="file_version" value="<?php echo esc_attr($file->file_version); ?>" />
                        </div>
                    </div>

                    <div id="licensediv" class="postbox">
                        <h3 class="hndle"><span><?php _e('License', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <label for="file_license" class="screen-reader-text"><?php _e('License', 'wp-filebase'); ?></label>
                            <input type="text" name="file_license" size="13" id="file_license" value="<?php echo esc_attr($file->file_license); ?>" />
                        </div>
                    </div>

                    <div id="requiredpermissionsdiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Required User Permissions', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <?php WPFB_Admin::RolesCheckList('file_user_roles', $user_roles); ?>
                        </div>
                    </div>
                </div>

                <div id="postbox-container-2" class="postbox-container">
                    <div id="advanceddiv" class="postbox">
                        <h3 class="hndle"><span><?php _e('Advanced', 'wp-filebase'); ?></span></h3>
                        <div class="inside">
                            <table class="form-table">
                                <?php if ($update) { ?>
                                    <tr>
                                        <th scope="row"><label for="file_path"><?php _e('File Path', 'wp-filebase'); ?></label></th>
                                        <td><input type="text" name="file_path" id="file_path" value="<?php echo esc_attr($file->GetLocalPathRel()); ?>" class="large-text" /></td>
                                    </tr>
                                <?php } ?>

                                <tr>
                                    <th scope="row"><label for="file_remote_uri"><?php _e('Remote File', 'wp-filebase'); ?></label></th>
                                    <td><input type="text" name="file_remote_uri" id="file_remote_uri" value="<?php echo esc_attr($file->file_remote_uri); ?>" class="large-text" /></td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php _e('Direct Linking', 'wp-filebase'); ?></th>
                                    <td>
                                        <label><input type="radio" name="file_direct_linking" value="0" <?php checked($file->file_direct_linking, 0); ?> /> <?php _e('Default', 'wp-filebase'); ?> (<?php echo WPFB_Core::SettingEnabled('allow_direct_linking') ? __('Allow', 'wp-filebase') : __('Disallow', 'wp-filebase'); ?>)</label><br />
                                        <label><input type="radio" name="file_direct_linking" value="1" <?php checked($file->file_direct_linking, 1); ?> /> <?php _e('Allow', 'wp-filebase'); ?></label><br />
                                        <label><input type="radio" name="file_direct_linking" value="2" <?php checked($file->file_direct_linking, 2); ?> /> <?php _e('Disallow', 'wp-filebase'); ?></label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php _e('Offline', 'wp-filebase'); ?></th>
                                    <td><label><input type="checkbox" name="file_offline" value="1" <?php checked($file->file_offline); ?> /> <?php _e('The download is offline.', 'wp-filebase'); ?></label></td>
                                </tr>

                                <?php
                                $custom_fields = WPFB_Core::GetCustomFields();
                                foreach ($custom_fields as $ct => $cn) {
                                    ?>
                                    <tr>
                                        <th scope="row"><label for="file_custom_<?php echo $ct; ?>"><?php echo $cn; ?></label></th>
                                        <td><input type="text" name="file_custom_<?php echo $ct; ?>" id="file_custom_<?php echo $ct; ?>" value="<?php echo esc_attr($file->GetCustomField($ct)); ?>" class="large-text" /></td>
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
                            if ($update) {
                                wpfb_loadclass('AdminGuiFiles');
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>