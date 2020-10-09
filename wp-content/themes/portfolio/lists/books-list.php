<?php
/* // LIST
 query_posts( array ( 'post_type' => 'books' ) );
    echo '<ul style="list-style:none;">';
   if (have_posts()) {
      while (have_posts()) {
       the_post();
         echo '<li><a href="'.get_permalink().'">'.get_the_title().'</a></li>';
      }
   }
    wp_reset_query();

    echo '</ul>';
*/

book_table();

?>