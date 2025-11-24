<?php
/**
 * 远程图片保存核心功能
 * 负责检测和保存远程图片到本地媒体库
 */

/**
 * 处理文章中的远程图片
 * 
 * @param int $post_id 文章ID
 */
function auto_save_images_process_post_images( $post_id ) {
    // 获取文章对象
    $post = get_post( $post_id );
    if ( ! $post || empty( $post->post_content ) ) {
        return;
    }
    
    // 获取文章内容
    $content = $post->post_content;
    
    // 提取文章中的所有图片URL
    preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
    
    if ( empty( $matches[1] ) ) {
        return; // 没有找到图片
    }
    
    $images = $matches[1];
    $updated = false;
    
    // 处理每个图片URL
    foreach ( $images as $image_url ) {
        // 检查是否为远程图片
        if ( auto_save_images_is_remote_image( $image_url ) ) {
            // 保存远程图片到媒体库
            $attachment_id = auto_save_images_save_remote_image( $image_url, $post_id );
            
            // 如果保存成功，获取本地URL并替换文章中的链接
            if ( $attachment_id ) {
                $local_url = wp_get_attachment_url( $attachment_id );
                if ( $local_url ) {
                    $content = str_replace( $image_url, $local_url, $content );
                    $updated = true;
                }
            }
        }
    }
    
    // 如果内容有更新，保存文章
    if ( $updated ) {
        // 避免触发save_post钩子导致无限循环
        remove_action( 'save_post', 'auto_save_images_save_meta_box_data' );
        
        // 更新文章内容
        wp_update_post( array(
            'ID' => $post_id,
            'post_content' => $content
        ) );
        
        // 重新添加钩子
        add_action( 'save_post', 'auto_save_images_save_meta_box_data' );
    }
}

/**
 * 检查URL是否为远程图片
 * 
 * @param string $url 图片URL
 * @return bool 是否为远程图片
 */
function auto_save_images_is_remote_image( $url ) {
    // 解析站点URL和图片URL
    $site_url = parse_url( get_site_url() );
    $image_url = parse_url( $url );
    
    // 如果图片URL解析失败，可能是相对URL，视为本地图片
    if ( ! $image_url || ! isset( $image_url['host'] ) ) {
        return false;
    }
    
    // 如果站点URL解析失败，无法判断是否为远程图片
    if ( ! $site_url || ! isset( $site_url['host'] ) ) {
        return false;
    }
    
    // 检查图片URL的主机是否与站点主机相同
    if ( $image_url['host'] === $site_url['host'] ) {
        return false; // 不是远程图片
    }
    
    // 检查是否为图片文件
    $image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp' );
    $path_parts = pathinfo( $url );
    
    if ( isset( $path_parts['extension'] ) && in_array( strtolower( $path_parts['extension'] ), $image_extensions ) ) {
        return true; // 是远程图片
    }
    
    return false;
}

/**
 * 保存远程图片到媒体库
 * 
 * @param string $url 远程图片URL
 * @param int $post_id 关联的文章ID
 * @return int|false 成功返回附件ID，失败返回false
 */
function auto_save_images_save_remote_image( $url, $post_id ) {
    // 检查URL是否可访问
    $response = wp_remote_head( $url, array( 'timeout' => 10 ) );
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
        return false;
    }
    
    // 检查内容类型是否为图片
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( $content_type && strpos( $content_type, 'image/' ) !== 0 ) {
        return false;
    }
    
    // 获取图片内容
    $image_data = wp_remote_get( $url, array( 'timeout' => 30 ) );
    
    if ( is_wp_error( $image_data ) || wp_remote_retrieve_response_code( $image_data ) != 200 ) {
        return false;
    }
    
    $image_content = wp_remote_retrieve_body( $image_data );
    
    // 确保获取到了图片内容
    if ( empty( $image_content ) ) {
        return false;
    }
    
    // 获取图片文件名
    $filename = basename( $url );
    
    // 清理文件名
    $filename = sanitize_file_name( $filename );
    
    // 如果文件名没有扩展名，尝试从内容类型中推断
    if ( strpos( $filename, '.' ) === false && $content_type ) {
        $extension = '';
        switch ( $content_type ) {
            case 'image/jpeg':
                $extension = '.jpg';
                break;
            case 'image/png':
                $extension = '.png';
                break;
            case 'image/gif':
                $extension = '.gif';
                break;
            case 'image/webp':
                $extension = '.webp';
                break;
            case 'image/svg+xml':
                $extension = '.svg';
                break;
        }
        $filename .= $extension;
    }
    
    // 确保文件名存在
    if ( strpos( $filename, '.' ) === false ) {
        $filename .= '.jpg'; // 默认使用jpg扩展名
    }
    
    // 生成唯一文件名，避免覆盖
    $uploads = wp_upload_dir();
    $file_path = $uploads['path'] . '/' . $filename;
    $i = 1;
    $original_filename = $filename;
    
    while ( file_exists( $file_path ) ) {
        $path_parts = pathinfo( $original_filename );
        $filename = $path_parts['filename'] . '-' . $i . '.' . $path_parts['extension'];
        $file_path = $uploads['path'] . '/' . $filename;
        $i++;
    }
    
    // 保存图片到本地
    $saved = file_put_contents( $file_path, $image_content );
    
    if ( ! $saved ) {
        return false;
    }
    
    // 检查文件类型
    $wp_filetype = wp_check_filetype( $filename, null );
    
    // 准备附件数组
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_parent' => $post_id
    );
    
    // 插入附件
    $attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
    
    if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
        // 生成附件元数据
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );
        
        return $attachment_id;
    }
    
    return false;
}
