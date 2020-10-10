<?php

/* POST LISTS */
function books_list_function() {
    ob_start();
	include( get_stylesheet_directory() . '/lists/books-list.php');
	$output = ob_get_clean();
	return $output;
}

function mysql_posts_function() {
    ob_start();
	include( get_stylesheet_directory() . '/lists/sql-posts.php');
	$output = ob_get_clean();
	return $output;
}

function wordpress_posts_function() {
    ob_start();
	include( get_stylesheet_directory() . '/lists/wordpress-posts.php');
	$output = ob_get_clean();
	return $output;
}

function php_posts_function() {
    ob_start();
	include( get_stylesheet_directory() . '/lists/php-posts.php');
	$output = ob_get_clean();
	return $output;
}

function design_posts_function() {
    ob_start();
	include( get_stylesheet_directory() . '/lists/design-posts.php');
	$output = ob_get_clean();
	return $output;
}

function loop_end_test_function(){
    ob_start();
    include( get_stylesheet_directory() . '/loop-end-test.php');
    $output = ob_get_clean();
    return $output;
}

function register_shortcodes(){
    /* POST LISTS */
    add_shortcode('books-list', 'books_list_function');
    add_shortcode('mysql-posts', 'mysql_posts_function');
    add_shortcode('wordpress-posts', 'wordpress_posts_function');
    add_shortcode('php-posts', 'php_posts_function');
    add_shortcode('design-posts', 'design_posts_function');
}

add_action( 'init', 'register_shortcodes');

?>