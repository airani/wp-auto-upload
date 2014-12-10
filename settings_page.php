<div class="wrap">
    <?php screen_icon('options-general'); ?> <h2><?php _e('Auto Upload Images Settings', 'auto-upload-images'); ?></h2>
    
    <?php if ($message == true) : ?>
    <div id="setting-error-settings_updated" class="updated settings-error">
        <p><strong><?php _e('Settings Saved.', 'auto-upload-images'); ?></strong></p>
    </div>
    <?php endif; ?>

    <form method="POST">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="base_url">
                        <?php _e('Base URL:', 'auto-upload-images'); ?>
                    </label> 
                </th>
                <td>
                    <input type="text" name="base_url" value="<?php echo $this->options['base_url']; ?>" class="regular-text" dir="ltr" />
                    <p class="description"><?php _e('If you need to choose a new base URL for the images that will be automatically uploaded. Ex:', 'auto-upload-images'); ?> <code>http://p30design.net</code>, <code>http://cdn.p30design.net</code>, <code>/</code></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="image_name">
                        <?php _e('Image Name:', 'auto-upload-images'); ?>
                    </label> 
                </th>
                <td>
                    <input type="text" name="image_name" value="<?php echo $this->options['image_name']; ?>" class="regular-text" dir="ltr" />
                    <p class="description"><?php _e('Choose a custom filename for the new images will be uploaded. You can also use these shortcodes <code dir="ltr">%filename%</code>, <code dir="ltr">%url%</code>, <code dir="ltr">%date%</code>.', 'auto-upload-images'); ?></p>
                </td>
            </tr>
            <?php if (function_exists('image_make_intermediate_size')) : ?>
            <tr valign="top">
                <th scope="row">
                    <label><?php _e('Image Size:', 'auto-upload-images'); ?></label>
                </th>
                <td>
                    <label for="max_width"><?php _e('Max Width', 'auto-upload-images'); ?></label>
                    <input name="max_width" type="number" step="5" min="0" id="max_width" placeholder="600" class="small-text" value="<?php echo $this->options['max_width']; ?>">
                    <label for="max_height"><?php _e('Max Height', 'auto-upload-images'); ?></label>
                    <input name="max_height" type="number" step="5" min="0" id="max_height" placeholder="400" class="small-text" value="<?php echo $this->options['max_height']; ?>">
                    <p class="description"><?php _e('You can choose max width and height for images uploaded by this plugin on your site. If you leave empty each one of fields by default use the original size of the image.', 'auto-upload-images'); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <tr valign="top">
                <th scope="row">
                    <label for="exclude_urls">
                        <?php _e('Exclude Domains:', 'auto-upload-images'); ?>
                    </label>
                </th>
                <td>
                    <p><?php _e('Enter the domains you wish to be excluded from uploading images: (One domain per line)', 'auto-upload-images'); ?></p>
                    <p><textarea name="exclude_urls" rows="10" cols="50" id="exclude_urls" class="large-text code" placeholder="http://p30design.net"><?php echo $this->options['exclude_urls']; ?></textarea></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>