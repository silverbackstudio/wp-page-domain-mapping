<?php
/**
 * Plugin Name: Page Domain Mapping
 * Version: 0.1
 * Plugin URI: 
 * Description: Maps a domain (in multisites installs) to a specific WordPress page
 * Author: Silverback Studio
 * Author URI: http://www.silverbackstudio.it
 * Text Domain: svbk-page-dm
 * Domain Path: /languages/
 * License: GPL v3
 */
 
if(!is_admin()){
    add_filter( 'do_parse_request', 'svbk_page_dm_trigger', 10, 2);
    add_action( 'template_redirect', 'svbk_page_dm_redirect_to_domain', 10, 2 );
}

add_filter('page_link', 'svbk_page_dm_permalink', 10, 2 );

function svbk_page_dm_trigger( $do_parse, $wp ) {

    $dm_query = new WP_Query( array(
        'post_type'=>'page', 
        'meta_query' => array(
           array(
               'key' => 'svbk_page_domain',
               'value' => $_SERVER['HTTP_HOST'],
               'compare' => 'LIKE'
           )
        ),
        'posts_per_page' => 1
    ) );    

    if($dm_query->have_posts()){
        
        remove_action( 'template_redirect', 'redirect_canonical' );
        remove_action( 'template_redirect', 'redirect_to_mapped_domain' );
        
        $dm_query->the_post();
        $wp->query_vars = array( 'page'=>'', 'pagename' => $dm_query->post->post_name );

        return false;
    }
    
    return $do_parse;
}

function svbk_page_dm_redirect_to_domain(){
    
    if(!is_page()){
        return;
    }
    
    $domain = get_post_meta( get_queried_object_id(), 'svbk_page_domain', true);
    
    if( $domain && ($domain !== $_SERVER['HTTP_HOST']) ){
        wp_redirect( esc_url_raw($domain) );
        exit;
    }
    
}

function svbk_page_dm_permalink($url, $post_id){

    $mapped_domain = get_post_meta($post_id, 'svbk_page_domain', true);
    
    if($mapped_domain){
        $url = esc_url($mapped_domain); 
    }
    
    return $url;
}


/* ### Admin Field ### */

add_action( 'add_meta_boxes', 'svbk_page_dm_meta_box_add' );
function svbk_page_dm_meta_box_add()
{
    add_meta_box( 'svbk_page_dm', 'Mapped Domain', 'svbk_page_dm_meta_box_cb', 'page', 'normal', 'high' );
}

function svbk_page_dm_meta_box_cb($post)
{
    $domain = get_post_meta( $post->ID , 'svbk_page_domain', true );
    
    wp_nonce_field( 'svbk_page_dm_nonce', 'svbk_page_domain_nonce' );    
    
    ?>
    <label for="svbk_page_domain"><?php _e('Page Domain', 'svbk-page-dm') ?></label>
    <input type="text" class="widefat" name="svbk_page_domain" id="svbk_page_domain" value="<?php echo esc_attr($domain); ?>" /> 
    <?php
}

add_action( 'save_post', 'svbk_page_dm_meta_box_save' );
function svbk_page_dm_meta_box_save( $post_id )
{
    // Bail if we're doing an auto save
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
     
    // if our nonce isn't there, or we can't verify it, bail
    if( !isset( $_POST['svbk_page_domain_nonce'] ) || !wp_verify_nonce( $_POST['svbk_page_domain_nonce'], 'svbk_page_dm_nonce' ) ) return;
     
    // if our current user can't edit this post, bail
    if( !current_user_can( 'edit_post' ) ) return;
     
    // Make sure your data is set before trying to save it
    if( isset( $_POST['svbk_page_domain'] ) ) {
        
        $domain = $_POST['svbk_page_domain'];
        $domain = preg_replace('@^https?://@', '', $domain, 1); //strip protocol
        $domain = preg_replace('@/(.*)@', '', $domain, 1); // strip URI
        
        update_post_meta( $post_id, 'svbk_page_domain', $domain );
    }
}