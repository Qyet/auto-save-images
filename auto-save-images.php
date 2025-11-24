<?php
/**
 * Plugin Name: Auto-save-images
 * Plugin URI: 
 * Description: 允许用户在编辑文章时选择是否保存远程图片到本地媒体库。
 * Version: 0.0.1
 * Author: Qyet
 * Author URI: 
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-save-images
 * Domain Path: /languages
 */

// 确保直接访问文件不会执行任何操作
if ( ! defined( 'ABSPATH' ) ) {
    exit; // 退出直接访问
}

// 定义插件的常量
define( 'AUTO_SAVE_IMAGES_VERSION', '0.0.1' );
define( 'AUTO_SAVE_IMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTO_SAVE_IMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 插件初始化函数
 */
function auto_save_images_init() {
    // 加载插件文本域以支持多语言
    load_plugin_textdomain( 'auto-save-images', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'auto_save_images_init' );

/**
 * 包含插件的核心文件
 */
require_once AUTO_SAVE_IMAGES_PLUGIN_DIR . 'includes/helpers.php';
require_once AUTO_SAVE_IMAGES_PLUGIN_DIR . 'includes/meta-box.php';
require_once AUTO_SAVE_IMAGES_PLUGIN_DIR . 'includes/image-saver.php';
