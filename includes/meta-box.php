<?php
/**
 * 为文章编辑界面添加远程图片保存选项
 * 在侧边栏"状态、发布、别名、作者、模板、讨论、格式"下方添加选项
 */

/**
 * 注册元数据框
 */
function auto_save_images_add_meta_box() {
    // 为文章和页面添加元数据框
    $post_types = array( 'post', 'page' );
    
    foreach ( $post_types as $post_type ) {
        add_meta_box(
            'auto-save-images-metabox', // 元数据框ID
            __( '是否保存远程图片', 'auto-save-images' ), // 标题
            'auto_save_images_meta_box_callback', // 回调函数
            $post_type, // 文章类型
            'side', // 位置 (side表示侧边栏)
            'low' // 优先级 (low使其显示在其他默认选项下方)
        );
    }
}
add_action( 'add_meta_boxes', 'auto_save_images_add_meta_box' );

/**
 * 元数据框内容回调函数
 */
function auto_save_images_meta_box_callback( $post ) {
    // 添加安全检查
    wp_nonce_field( 'auto_save_images_save_meta_box_data', 'auto_save_images_meta_box_nonce' );
    
    // 获取当前值（如果存在），默认为"否"
    $value = get_post_meta( $post->ID, '_auto_save_remote_images', true );
    $value = ( empty( $value ) ) ? 'no' : $value;
    
    // 输出单选按钮
    ?>
    <div class="misc-pub-section">
        <p>
            <label for="auto_save_images_yes">
                <input type="radio" id="auto_save_images_yes" name="_auto_save_remote_images" value="yes" <?php checked( $value, 'yes' ); ?> />
                <?php _e( '是', 'auto-save-images' ); ?>
            </label>
        </p>
        <p>
            <label for="auto_save_images_no">
                <input type="radio" id="auto_save_images_no" name="_auto_save_remote_images" value="no" <?php checked( $value, 'no' ); ?> />
                <?php _e( '否', 'auto-save-images' ); ?>
            </label>
        </p>
        <p class="description">
            <?php _e( '选择"是"将在保存文章时下载并保存远程图片到本地媒体库。', 'auto-save-images' ); ?>
        </p>
    </div>
    <?php
}

/**
 * 保存元数据框数据
 */
function auto_save_images_save_meta_box_data( $post_id ) {
    // 检查nonce以确保请求有效
    if ( ! isset( $_POST['auto_save_images_meta_box_nonce'] ) ) {
        return;
    }
    
    if ( ! wp_verify_nonce( $_POST['auto_save_images_meta_box_nonce'], 'auto_save_images_save_meta_box_data' ) ) {
        return;
    }
    
    // 如果这是自动保存，不做任何操作
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // 检查用户权限
    $post_type = get_post_type( $post_id );
    if ( 'post' === $post_type ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    } elseif ( 'page' === $post_type ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    }
    
    // 检查值是否存在，设置默认值为"no"
    $new_value = isset( $_POST['_auto_save_remote_images'] ) ? sanitize_text_field( $_POST['_auto_save_remote_images'] ) : 'no';
    
    // 确保值只能是"yes"或"no"
    if ( ! in_array( $new_value, array( 'yes', 'no' ) ) ) {
        $new_value = 'no';
    }
    
    // 保存元数据
    update_post_meta( $post_id, '_auto_save_remote_images', $new_value );
    
    // 如果用户选择保存远程图片，则在保存文章后处理图片
    if ( 'yes' === $new_value ) {
        // 延迟处理，确保文章已完全保存
        add_action( 'shutdown', function() use ( $post_id ) {
            auto_save_images_process_post_images( $post_id );
        } );
    }
}
add_action( 'save_post', 'auto_save_images_save_meta_box_data' );
