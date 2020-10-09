<?php



add_action('wp_enqueue_scripts', 'enqueue_parent_styles');

function enqueue_parent_styles(){
    wp_enqueue_style('parent-style', get_template_directory_uri().'/style.css');
}

/* Load Debug PHP */
require( dirname(__FILE__) . '/lib/r-debug.php');

/* SIDEBARS */
function portfolio_widgets_init() {
    
    register_sidebar( array(
		'name'          => __( 'Logo', 'portfolio' ),
		'id'            => 'header-logo',
		'description'   => __( 'Logo will appear on the homepage.', 'portfolio' ),
		'before_widget' => '<div id="%1$s" class="section %2$s header-logo">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-logo">',
		'after_title'   => '</h4>',
	) );
    
    
    register_sidebar( array(
		'name'          => __( 'Header', 'portfolio' ),
		'id'            => 'header-sidebar',
		'description'   => __( 'Content will appear on the homepage.', 'portfolio' ),
		'before_widget' => '<div id="%1$s" class="section %2$s header-sidebar">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );
    
        register_sidebar( array(
		'name'          => __( 'Header Navigation', 'portfolio' ),
		'id'            => 'header-navigation-sidebar',
		'description'   => __( 'Menu will appear on the homepage below the description.', 'portfolio' ),
		'before_widget' => '<div id="%1$s" class="section %2$s header-navigation-sidebar">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-header-navigation">',
		'after_title'   => '</h4>',
	) );
}

add_action( 'widgets_init', 'portfolio_widgets_init' );




/* Add meta box item */
/**
 * Register meta box(es).
 */
function wpdocs_register_meta_boxes() {
    add_meta_box( 'meta-box-id', __( 'Project Code', 'textdomain' ), 'wpdocs_code_display_callback', 'post' );
}
add_action( 'add_meta_boxes', 'wpdocs_register_meta_boxes' );
 
/**
 * Meta box display callback.
 *
 * @param WP_Post $post Current post object.
 */
function wpdocs_code_display_callback( $post ) {
    // Display code/markup goes here. Don't forget to include nonces!
    echo '<input type="checkbox" name="displayCodeLink" value="Display Code Link"> Display Link to Project Code';
}
 
/**
 * Save meta box content.
 *
 * @param int $post_id Post ID
 */
function wpdocs_save_meta_box( $post_id ) {
    // Save logic goes here. Don't forget to include nonce checks!
}
add_action( 'save_post', 'wpdocs_save_meta_box' );


/* custom posts */
/*
* Creating a function to create our CPT
*/
 
function custom_post_type() {
 
// Set UI labels for Custom Post Type
    $labels = array(
        'name'                => _x( 'Books', 'Post Type General Name', 'portfolio' ),
        'singular_name'       => _x( 'Book', 'Post Type Singular Name', 'portfolio' ),
        'menu_name'           => __( 'Books', 'portfolio' ),
        'parent_item_colon'   => __( 'Parent Book', 'portfolio' ),
        'all_items'           => __( 'All Books', 'portfolio' ),
        'view_item'           => __( 'View Book', 'portfolio' ),
        'add_new_item'        => __( 'Add New Book', 'portfolio' ),
        'add_new'             => __( 'Add New', 'portfolio' ),
        'edit_item'           => __( 'Edit Book', 'portfolio' ),
        'update_item'         => __( 'Update Book', 'portfolio' ),
        'search_items'        => __( 'Search Book', 'portfolio' ),
        'not_found'           => __( 'Not Found', 'portfolio' ),
        'not_found_in_trash'  => __( 'Not found in Trash', 'portfolio' ),
    );
     
// Set other options for Custom Post Type
     
    $args = array(
        'label'               => __( 'books', 'portfolio' ),
        'description'         => __( 'Book news and reviews', 'portfolio' ),
        'labels'              => $labels,
        // Features this CPT supports in Post Editor
        'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', ),
        // You can associate this CPT with a taxonomy or custom taxonomy. 
        'taxonomies'          => array( 'books' ),
        /* A hierarchical CPT is like Pages and can have
        * Parent and child items. A non-hierarchical CPT
        * is like Posts.
        */ 
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'page',
    );
     
    // Registering your Custom Post Type
    register_post_type( 'books', $args );
 
}
 
/* Hook into the 'init' action so that the function
* Containing our post type registration is not 
* unnecessarily executed. 
*/
 
add_action( 'init', 'custom_post_type', 0 );




/* SHORTCODES */
include('custom-shortcodes.php');











/* GET IMAGES */
function get_images_from_media_library() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' =>'image',
        'post_status' => 'inherit',
        'posts_per_page' => 5,
        'orderby' => 'rand'
    );
    $query_images = new WP_Query( $args );
    $images = array();
    foreach ( $query_images->posts as $image) {
        $images[]= $image->guid;
    }
    return $images;
}

function display_images_from_media_library() {
    $imgs = get_images_from_media_library();
    $html = '<div id="media-gallery">';

    foreach($imgs as $img) {
        $html .= '<img src="' . $img . '" alt="" />';
    }
    $html .= '</div>';
    return $html;
}



/* CUSTOM POST FUNCTIONS */ 
function book_table(){
    
    /*
    global $wpdb;
    $posts = $wpdb->prefix . 'posts';
    $postmeta = $wpdb->prefix . 'postmeta';

    $result = $wpdb->get_results("SELECT * FROM $postmeta JOIN $posts ON $postmeta.post_id = $posts.id WHERE $posts.post_type = 'books' && $posts.post_status != 'inherit';");

    echo '<table style="border:0px;">';

    foreach($result as $row){
        //$i++;
        $bookTitle = $row->post_title;
        $author = $row->meta_value;
        $postname = $row->post_name;
        echo '<tr><td style="width:500px; border:0px;"><a href="'.get_permalink().'/'.$postname.'">'.$bookTitle.'</a></td><td style="border:0px;">'.$author.'</td></tr>';
    }

    echo '</table>';
    */

    query_posts( array ( 'post_type' => 'books' ) );
    echo '<table class="book-table-format"><thead><tr><td class="book-table-thead" ><h4>Book Title</h4></td><td class="book-table-thead"><h4>Author</h4></td></tr></thead>';
   if (have_posts()) {
      while (have_posts()) {
          the_post();
          $value = get_field( 'book-author' );
          echo '<tr><td class="book-title-column-home"><a href="'.get_permalink().'">'.get_the_title().'</td><td>'.$value.'</a></td</tr>';
      }
   }
    wp_reset_query();

    echo '</table>';
    
    
}



/*
function book_author(){
    global $wpdb;
    $posts = $wpdb->prefix . 'posts';
    $postmeta = $wpdb->prefix . 'postmeta';

    $result = $wpdb->get_results("SELECT * FROM $postmeta JOIN $posts ON $postmeta.post_id = $posts.id WHERE $postmeta.meta_key = 'book-author' && $posts.post_status != 'inherit';");

    echo '<table style="border:0px;">';

    foreach($result as $row){
        //$i++;
        $bookTitle = $row->post_title;
        $author = $row->meta_value;
        echo '<tr><td style="width:500px; border:0px;">'.$bookTitle.'</td><td style="border:0px;">'.$author.'</td></tr>';
    }

    echo '</table>';
}
*/

/* PARENT inc/theme-functions.php overrides */
require get_stylesheet_directory() . '/inc/portfolio-functions.php';


/* Custom hooks */

function wphooks_before_footer_message(){
    /* testing only can delete function make sure to delete php file as well */
    locate_template( 'template-parts/before-footer.php', true );
}

add_action('wphooks_before_footer', 'wphooks_before_footer_message');


/* Add Drafts to Post titles */

function wphooks_add_draft_to_titles( $post_id){
    // if post revision do not proceed
    if (wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Get Post
    $post = get_post($post_id);
    
    // Add or remove 'DRAFT: ' from title based on status
    if( 'draft' === $post->post_status && 
        'DRAFT: ' !== substr( $post->post_title, 0, 7 ) ) {
        
        // Add 'DRAFT: ' to title
        $post->post_title = 'DRAFT: ' . $post->post_title;
        
    } elseif ( 'publish' === $post->post_status &&
                'DRAFT: ' === substr($post->post_title, 0, 7 ) ) {
        
        // Remove 'DRAFT: ' from title
        $post->post_title = substr($post->post_title, 7);
    }
    
    // If SLUG starts with 'draft-' remove it
    if('draft-' === substr($post->post_name, 0, 6) ) {
        $post->post_name = substr($post->post_name, 6);
    }
    
    // Unhook to avoide infinte loop
    remove_action('save_post', 'wphooks_add_draft_to_titles');
    
    // Update the post
    wp_update_post ($post);
    
    // Re-Hook
    add_action('save_post', 'wphooks_add_draft_to_titles');
}

add_action('save_post', 'wphooks_add_draft_to_titles');


/* admin hook test */


?>