<?php
/**
 * 辅助函数和工具方法
 * 提供调试、日志记录和其他实用功能
 */

/**
 * 调试日志记录函数
 * 仅在WP_DEBUG为true时记录日志
 * 
 * @param mixed $data 要记录的数据
 * @param string $label 日志标签
 */
function auto_save_images_debug( $data, $label = 'AUTO_SAVE_IMAGES' ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            error_log( $label . ': ' . print_r( $data, true ) );
        } else {
            error_log( $label . ': ' . $data );
        }
    }
}

/**
 * 检查当前是否为管理员界面
 * 
 * @return bool 是否为管理员界面
 */
function auto_save_images_is_admin() {
    return is_admin();
}

/**
 * 获取插件设置页面URL
 * 
 * @return string 设置页面URL
 */
function auto_save_images_get_settings_url() {
    return admin_url( 'options-general.php?page=auto-save-images' );
}

/**
 * 格式化文件大小
 * 
 * @param int $bytes 字节数
 * @return string 格式化后的文件大小
 */
function auto_save_images_format_size( $bytes ) {
    if ( $bytes == 0 ) return '0 B';
    
    $k = 1024;
    $sizes = array( 'B', 'KB', 'MB', 'GB' );
    $i = floor( log( $bytes ) / log( $k ) );
    
    return round( $bytes / pow( $k, $i ), 2 ) . ' ' . $sizes[$i];
}

/**
 * 获取用户设置的默认保存选项
 * 如果未设置，返回默认值
 * 
 * @return string 默认保存选项 (yes/no)
 */
function auto_save_images_get_default_save_option() {
    // 默认为"否"，用户需要明确选择才保存远程图片
    return 'no';
}

/**
 * 检查是否应该处理特定文章类型
 * 
 * @param string $post_type 文章类型
 * @return bool 是否应该处理
 */
function auto_save_images_should_process_post_type( $post_type ) {
    // 默认处理文章和页面
    $supported_post_types = apply_filters( 'auto_save_images_supported_post_types', array( 'post', 'page' ) );
    return in_array( $post_type, $supported_post_types );
}

/**
 * 获取文章中的所有图片URL
 * 
 * @param string $content 文章内容
 * @return array 图片URL数组
 */
function auto_save_images_extract_images_from_content( $content ) {
    if ( empty( $content ) ) {
        return array();
    }
    
    preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
    
    if ( empty( $matches[1] ) ) {
        return array();
    }
    
    return $matches[1];
}

/**
 * 验证URL是否有效
 * 
 * @param string $url URL字符串
 * @return bool 是否有效
 */
function auto_save_images_is_valid_url( $url ) {
    return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
}

/**
 * 检查HTTP错误
 * 
 * @param array|WP_Error $response 响应对象
 * @return bool 是否有错误
 */
function auto_save_images_has_http_error( $response ) {
    if ( is_wp_error( $response ) ) {
        return true;
    }
    
    $status_code = wp_remote_retrieve_response_code( $response );
    return $status_code >= 400;
}

/**
 * 获取HTTP错误信息
 * 
 * @param array|WP_Error $response 响应对象
 * @return string 错误信息
 */
function auto_save_images_get_http_error( $response ) {
    if ( is_wp_error( $response ) ) {
        return $response->get_error_message();
    }
    
    $status_code = wp_remote_retrieve_response_code( $response );
    $status_message = wp_remote_retrieve_response_message( $response );
    
    return "HTTP Error: {$status_code} {$status_message}";
}

/**
 * 在插件主文件中包含helpers.php
 */
if ( ! function_exists( 'auto_save_images_include_helpers' ) ) {
    function auto_save_images_include_helpers() {
        require_once AUTO_SAVE_IMAGES_PLUGIN_DIR . 'includes/helpers.php';
    }
}
