<?php

namespace SPIE;

class Simple_Product_Images_Exporter
{
  const VERSION = '1.0.0';

  protected $plugin_slug = 'simple-product-images-exporter';

  public static function init()
  {
    load_plugin_textdomain('simple-product-images-exporter', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    // enqueue admin style and scripts
    add_action('admin_enqueue_scripts', array(__CLASS__, 'add_scripts'));

    add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
    add_action('admin_init', array(__CLASS__, 'add_settings'));

    // delete old folder before spie-export-folder setting update
    add_action('update_option_spie_export_folder', array(__CLASS__, 'spie_delete_old_folder'), 10, 3);

    #add_action('admin_init', array(__CLASS__, 'spie_download_folder'));

    // ajax actions
    add_action('wp_ajax_spie_check_folder', array(__CLASS__, 'spie_check_folder'));
    add_action('wp_ajax_spie_delete_folder', array(__CLASS__, 'spie_delete_folder'));
    add_action('wp_ajax_spie_copy_images', array(__CLASS__, 'spie_copy_images'));
    add_action('wp_ajax_spie_download_folder', array(__CLASS__, 'spie_download_folder'));
  }

  // plugin activation
  public static function plugin_activation()
  {
    // check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      // Deactivate the plugin
      deactivate_plugins(plugin_basename(__FILE__));
      // return error message
      $error_message = __('This plugin requires WooCommerce to be installed and active.', 'simple-product-images-exporter');
      die($error_message);
    }
  }

  // plugin deactivation
  public static function plugin_deactivation()
  {
    // code
  }

  // plugin uninstall
  public static function plugin_uninstall()
  {
    // delete folder with images
    $folder_path = self::spie_get_folder_path();
    if (file_exists($folder_path)) {
      self::spie_delete_files_folder($folder_path);
    }
    // clear settings
    delete_option('spie_export_folder');
  }

  // add scripts
  public static function add_scripts()
  {
    wp_register_style('spie-admin-style', plugins_url('assets/admin.css', __FILE__), array(), self::VERSION);
    wp_register_script('spie-admin-script', plugins_url('assets/admin.js', __FILE__), array('jquery'), self::VERSION, true);
  }

  // add settings
  public static function add_settings()
  {
    register_setting('spie-settings-group', 'spie_export_folder');
    register_setting('spie-settings-group', 'spie_product_status');

    add_settings_section(
      'spie-settings-section',
      __('Settings', 'simple-product-images-exporter'),
      array(__CLASS__, 'spie_settings_section_callback'),
      'simple-product-images-exporter'
    );

    // text field for export folder
    add_settings_field(
      'spie-export-folder',
      __('Export folder', 'simple-product-images-exporter'),
      array(__CLASS__, 'spie_export_folder_callback'),
      'simple-product-images-exporter',
      'spie-settings-section'
    );
    // select field for product status
    add_settings_field(
      'spie-product-status',
      __('Product status', 'simple-product-images-exporter'),
      array(__CLASS__, 'spie_product_status_callback'),
      'simple-product-images-exporter',
      'spie-settings-section'
    );

    // setting default
    if (!get_option('spie_export_folder')) {
      update_option('spie_export_folder', 'spie-export');
    }
    if (!get_option('spie_product_status')) {
      update_option('spie_product_status', 'publish');
    }
  }

  // spie settings section callback
  public static function spie_settings_section_callback()
  {
    echo __('Settings for Simple Product Images Exporter', 'simple-product-images-exporter');
  }

  // spie export folder callback
  public static function spie_export_folder_callback()
  {
    $value = get_option('spie_export_folder');
    ?>
      <input type="text" name="spie_export_folder" value="<?php echo $value; ?>" />
      <div>
        <small><?php echo __('Select folder name on wp-content/uploads', 'simple-product-images-exporter') ?></small>
      </div>
    <?php
  }

  // spie product status callback
  public static function spie_product_status_callback()
  {
    $value = get_option('spie_product_status');
    ?>
      <select id="spie_product_status" name="spie_product_status">
        <option value="all" <?php selected($value, 'all'); ?>><?php echo __('All', 'simple-product-images-exporter'); ?></option>
        <option value="publish" <?php selected($value, 'publish'); ?>><?php echo __('Published', 'simple-product-images-exporter'); ?></option>
        <option value="pending" <?php selected($value, 'pending'); ?>><?php echo __('Pending review', 'simple-product-images-exporter'); ?></option>
        <option value="draft" <?php selected($value, 'draft'); ?>><?php echo __('Draft', 'simple-product-images-exporter'); ?></option>
      </select>
    <?php
  }

  // delete old folder before spie-export-folder setting update
  public static function spie_delete_old_folder($old_value, $value)
  {
    if ($old_value != $value) {
      $old_folder_path = ABSPATH . 'wp-content/uploads/' . $old_value . '/';;
      if (file_exists($old_folder_path)) {
        self::spie_delete_files_folder($old_folder_path);
      }
    }
    return $value;
  }

  // add admin menu
  public static function add_admin_menu()
  {
    // add submenu page to the "Tools" menu 
    add_submenu_page(
      'tools.php',
      __('Simple Product Images Exporter', 'simple-product-images-exporter'),
      __('SPIE - Imgs Exporter', 'simple-product-images-exporter'),
      'manage_options',
      'simple-product-images-exporter',
      array(__CLASS__, 'admin_page')
    );
  }

  // admin page
  public static function admin_page()
  {
    // WordPress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
      // add settings saved message with the class of "updated"
      add_settings_error('spie_messages', 'spie_message', __('Settings Saved', 'simple-product-images-exporter'), 'updated');
    }

    // show error/update messages
    settings_errors('spie_messages');

    // check user capabilities
    if (!current_user_can('manage_options')) {
      return;
    }

    // add style and scripts
    wp_enqueue_style('spie-admin-style');
    wp_enqueue_script('spie-admin-script');

    $folder_mex = __('No folder created', 'simple-product-images-exporter');
    $folder_path = self::spie_get_folder_path();
    $zip_name = self::spie_check_zip_existence($folder_path);
    $zip_link = '#';
    if ($zip_name) {
      $zip_link = self::spie_get_folder_link() . $zip_name;
    }
    if(self::spie_check_folder_existence($folder_path)) {
      $folder_mex = __('Folder created', 'simple-product-images-exporter');
    }
    if($files_tot = self::spie_count_files_folder($folder_path)) {
      // translators: %d is the number of files in the folder
      $folder_mex = __('Folder contain %d files', 'simple-product-images-exporter');
    }

    wp_localize_script('spie-admin-script', 'spie', array(
      'ajax_url' => admin_url('admin-ajax.php'),
    ));
  ?>
    <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <div id="spie-container">
        <form id="spie_check_folder" action="<?php echo admin_url('admin-ajax.php'); ?>" class="spie-action-container">
          <input type="hidden" name="action" value="spie_check_folder">
          <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('spie_check_folder'); ?>">
          <button type="submit" id="spie-check-button"><?php echo __('Check Folder', 'simple-product-images-exporter') ?></button>
          <p class="spie-help-text"><?php echo __('Check if the folder exists (if not, create) and how many files it contains', 'simple-product-images-exporter') ?></p>
        </form>
        <form id="spie_delete_folder" action="<?php echo admin_url('admin-ajax.php'); ?>" class="spie-action-container">
          <input type="hidden" name="action" value="spie_delete_folder">
          <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('spie_delete_folder'); ?>">
          <button type="submit" id="spie-delete-button"><?php echo __('Delete Folder', 'simple-product-images-exporter') ?></button>
          <p class="spie-help-text"><?php echo __('Delete the folder and all files in it', 'simple-product-images-exporter') ?></p>
        </form>
        <form id="spie_copy_images" action="<?php echo admin_url('admin-ajax.php'); ?>" class="spie-action-container">
          <input type="hidden" name="action" value="spie_copy_images">
          <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('spie_copy_images'); ?>">
          <button type="submit" id="spie-copy-button"><?php echo __('Copy the images', 'simple-product-images-exporter') ?></button>
          <p class="spie-help-text"><?php echo __('Copy all images from products (status selected) to the folder', 'simple-product-images-exporter') ?></p>
        </form>
        <form id="spie_download_folder" action="<?php echo admin_url('admin-ajax.php'); ?>" class="spie-action-container">
          <input type="hidden" name="action" value="spie_download_folder">
          <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('spie_download_folder'); ?>">
          <button type="submit" id="spie-download-button"><?php echo __('Download as zip', 'simple-product-images-exporter') ?></button>
          <p class="spie-help-text"><?php echo __('Download the folder as a zip file', 'simple-product-images-exporter') ?></p>
        </form>
        <p id="spie-total-files-text"><?php printf($folder_mex, $files_tot) ?></p>
        <p id="spie-download-text" class="<?php echo ($zip_name ? '' : 'spie-hide') ?>">
          <?php echo __('You can download the zip file here:') ?>
          <a id="spie-download-link" href="<?php echo($zip_link) ?>"><?php echo($zip_name ? $zip_name : '') ?></a>
        </p>
        <div id="spie-loader-container">
          <div class="spie-loader spie-animate"></div>
        </div>
      </div>
      <h1><?php __('Options', 'simple-product-images-exporter'); ?></h1>
      <form action="options.php" method="post">
        <?php
        // output security fields for the registered setting "spie"
        settings_fields('spie-settings-group');
        // output setting sections and their fields
        // (sections are registered for "spie", each field is registered to a specific section)
        do_settings_sections('simple-product-images-exporter');
        // output save settings button
        submit_button(__('Save Settings', 'simple-product-images-exporter'));
        ?>
      </form>
    </div>
  <?php
  }

  public static function spie_check_folder()
  {
    //check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spie_check_folder')) {
      wp_send_json_error(__('Nonce error', 'simple-product-images-exporter'));
    }
    $folder_path = self::spie_get_folder_path();
    if(self::spie_check_folder_existence($folder_path, true)) {
      $tot = self::spie_count_files_folder($folder_path);
      $mex = __('Folder created', 'simple-product-images-exporter');
      if($tot >= 0) {
        $mex = sprintf(__('Folder contain %d files', 'simple-product-images-exporter'), $tot);
      }
      wp_send_json_success(array('mex' => $mex, 'tot' => $tot));
    } else {
      wp_send_json_error(__('Folder not created error', 'simple-product-images-exporter'));
    }
  }

  public static function spie_delete_folder()
  {
    //check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spie_delete_folder')) {
      wp_send_json_error(__('Nonce error', 'simple-product-images-exporter'));
    }
    $folder_path = self::spie_get_folder_path();
    if(self::spie_check_folder_existence($folder_path)) {
      if(self::spie_delete_files_folder($folder_path)) {
        $mex = __('Folder deleted', 'simple-product-images-exporter');
        wp_send_json_success(array('mex' => $mex));
      } else {
        wp_send_json_error(__('Folder not empty error', 'simple-product-images-exporter'));
      }
    } else {
      wp_send_json_error(__('Folder not created yet', 'simple-product-images-exporter'));
    }
  }

  public static function spie_copy_images()
  {
    // check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spie_copy_images')) {
      wp_send_json_error(__('Nonce error', 'simple-product-images-exporter'));
    }
    $folder_path = self::spie_get_folder_path();
    if(!self::spie_check_folder_existence($folder_path)) {
      wp_send_json_error(__('No folder to copy files to', 'simple-product-images-exporter'));
    }
    // check if folder is empty
    if(self::spie_count_files_folder($folder_path) > 0) {
      wp_send_json_error(__('Folder not empty error', 'simple-product-images-exporter'));
    }
    $args = array(
      'post_type' => 'product',
      'posts_per_page' => -1,
    );
    $product_status = get_option('spie_product_status');
    if ($product_status && $product_status != 'all') {
      $args['post_status'] = $product_status;
    }
    $products = new \WP_Query($args);
    if ($products->have_posts()) {
      $count = 0;
      while ($products->have_posts()) {
        $products->the_post();
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        $product_name = $product->get_name();
        $product_name = sanitize_title($product_name);
        $product_name = str_replace('-', '_', $product_name);
        $product_name = str_replace(' ', '_', $product_name);
        $product_name = str_replace('.', '_', $product_name);
        $product_name = str_replace('/', '_', $product_name);
        $product_name = str_replace('\\', '_', $product_name);
        $images = $product->get_gallery_image_ids();
        $main_image = get_post_thumbnail_id($product_id);
        $images[] = $main_image;
        foreach ($images as $i => $image) {
          $image_url = wp_get_attachment_image_src($image, 'full');
          $image_url = $image_url[0];
          $image_name = basename($image_url);
          $image_path = $folder_path . $product_name . '_' . $i . '_' . $image_name;
          if (!file_exists($image_path)) {
            copy($image_url, $image_path);
          }
        }
        $count++;
      }
      // translators: %d is the number of images copied
      $mex = sprintf(__('Copyed %d images', 'simple-product-images-exporter'), $count);
      wp_send_json_success(array('mex' => $mex, 'tot' => $count));
    } else {
      wp_send_json_error(__('No products found', 'simple-product-images-exporter'));
    }
  }

  public static function spie_download_folder()
  {
    // check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spie_download_folder')) {
      wp_send_json_error(__('Nonce error', 'simple-product-images-exporter'));
    }
    $folder_path = self::spie_get_folder_path();
    if(self::spie_check_folder_existence($folder_path)) {
      $zip = new \ZipArchive();
      $zip_name = get_option('spie_export_folder');
      if(!$zip_name) {
        $zip_name = 'spie_export';
      }
      $zip_name = $zip_name . '.zip';
      $zip_path = $folder_path . $zip_name;
      // check if zip already exists
      if(file_exists($zip_path)) {
        unlink($zip_path);
      }
      if ($zip->open($zip_path, \ZipArchive::CREATE) === TRUE) {
        $files = glob($folder_path . '*');
        foreach ($files as $file) {
          $zip->addFile($file, basename($file));
        }
        $zip->close();
        $mex = __('Zip file created', 'simple-product-images-exporter');
        $zip_link = self::spie_get_folder_link() . $zip_name;
        wp_send_json_success(array(
          'zip_link' => $zip_link,
          'zip_name' => $zip_name,
          'mex' => $mex
        ));
      } else {
        wp_send_json_error(__('Zip file not created error', 'simple-product-images-exporter'));
      }
    } else {
      wp_send_json_error(__('Folder not created error', 'simple-product-images-exporter'));
    }
  }

  // get folder link
  protected static function spie_get_folder_link()
  {
    $folder = get_option('spie_export_folder');
    if (!$folder) {
      $folder = 'spie-export';
    }
    return get_site_url() . '/wp-content/uploads/' . $folder . '/';
  }

  // get folder path
  protected static function spie_get_folder_path()
  {
    $folder = get_option('spie_export_folder');
    if (!$folder) {
      $folder = 'spie-export';
    }
    return ABSPATH . 'wp-content/uploads/' . $folder . '/';
  }

  // check folder existence
  protected static function spie_check_folder_existence($folder_path, $create = false)
  {
    if (!file_exists($folder_path)) {
      if ($create) {
        return mkdir($folder_path, 0777, true);
      }
      return false;
    }
    return true;
  }

  // delete all files in folder
  protected static function spie_delete_files_folder($folder_path)
  {
    if(file_exists($folder_path)) {
      $files = glob($folder_path . '*'); // get all file names
      foreach ($files as $file) { // iterate files
        if (is_file($file)) {
          unlink($file); // delete file
        }
      }
    } else {
      return false;
    }
    // check if folder is empty and delete it
    $files = array_diff(scandir($folder_path), array('.', '..'));
    if (count($files) == 0) {
      rmdir($folder_path);
      return true;
    } else {
      foreach ($files as $file) {
        if (is_dir($folder_path . $file)) {
          self::spie_delete_files_folder($folder_path . $file . '/');
        }
      }
    }
    try {
      rmdir($folder_path);
      return true;
    } catch (\Throwable $th) {
      return false;
    }
    return false;
  }

  // count files in folder
  protected static function spie_count_files_folder($folder_path)
  {
    if(!self::spie_check_folder_existence($folder_path)) return null;
    $files = glob($folder_path . '*'); // get all file names+
    if(!$files) return false;
    if (empty($files)) return 0;
    return count($files);
  }

  // check if zip file exists
  protected static function spie_check_zip_existence($folder_path)
  {
    $zip_name = get_option('spie_export_folder');
    if(!$zip_name) {
      $zip_name = 'spie_export';
    }
    $zip_name = $zip_name . '.zip';
    $zip_path = $folder_path . $zip_name;
    if(file_exists($zip_path)) {
      return $zip_name;
    }
    return false;
  }
}
