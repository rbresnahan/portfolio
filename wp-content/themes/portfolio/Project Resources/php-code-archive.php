<?php 

/* 
    This will filter posts based on category. good for seperating project posts by html, php, design, etc
*/
?>


<?php query_posts( array ( 'category_name' => 'books' ) ); /* limits posts loop to books only */ ?> 

<?php echo '<ul style="list-style:none;">'; ?>

<?php   if(have_posts() ) {
        while(have_posts() ) { 
            the_post();
            echo '<li>'; the_title(); echo '</li>';
            //get_template_part( 'template-parts/content', get_post_format());										
        }									
} ?>

<?php echo '</ul>'; ?>

<!-- end -->