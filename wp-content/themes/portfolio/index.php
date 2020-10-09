<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package elvinaa
 */

get_header(); ?>

<!-- uncomment to list actions and debug --> 
<?php /* R_Debug::list_hooks(); */  ?>


<div id="primary" class="content-area">
	<div id="main" class="site-main" role="main">	
		<div class="content-inner">
			<div id="blog-section">
			    <div class="container">
			        <div class="row">
                        <h1 class="section-head">Projects</h1>
			        	<?php
			        		if('right'===esc_attr(get_theme_mod('el_blog_sidebar','right'))) {
			        			?>
			        				<?php
                                query_posts('cat=-12'); /* removes books cat from loop */
	        							if ( is_active_sidebar('sidebar-1')){
	        								?>
	        									<div id="post-wrapper" class="col-md-9">
													<?php
                                            
														if(have_posts() ) {									

															while(have_posts() ) {
																the_post();
																/*
																 * Include the Post-Format-specific template for the content.
																 * If you want to override this in a child theme, then include a file
																 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
																 */
																get_template_part( 'template-parts/content', get_post_format());										
															}									

															?>
									                			<nav class="pagination">
									                    			<?php the_posts_pagination(); ?>
									                			</nav>
															<?php	
														}
														
													?>
									            </div>
									            <div id="sidebar-wrapper" class="col-md-3">
									                <?php get_sidebar('sidebar-1'); ?>
									            </div>
	        								<?php		
	        							}
	        							else{
	        								?>
	        									<div class="col-md-12">
													<?php
														if(have_posts() ) {									

															while(have_posts() ) {
																the_post();
																/*
																 * Include the Post-Format-specific template for the content.
																 * If you want to override this in a child theme, then include a file
																 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
																 */
																get_template_part( 'template-parts/content', get_post_format());										
															}									

															?>
									                			<nav class="pagination">
									                    			<?php the_posts_pagination(); ?>
									                			</nav>
															<?php	
														}
														
													?>
									            </div>
	        								<?php
	        							}
	        						?>

			        				
			        			<?php
			        		}
			        		else{
			        			?>
			        				<?php
        								if ( is_active_sidebar('sidebar-1')){
        									?>
        										<div id="sidebar-wrapper" class="col-md-3">
									                <?php get_sidebar('sidebar-1'); ?>
									            </div>
									            <div id="post-wrapper" class="col-md-9">
													<?php
														if(have_posts() ) {									

															while(have_posts() ) {
																the_post();
																/*
																 * Include the Post-Format-specific template for the content.
																 * If you want to override this in a child theme, then include a file
																 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
																 */
																get_template_part( 'template-parts/content', get_post_format());										
															}									

															?>
									                			<nav class="pagination">
									                    			<?php the_posts_pagination(); ?>
									                			</nav>
															<?php	
														}
														
													?>
									            </div>
        									<?php
        								}
        								else{
        									?>
        										<div class="col-md-12">
                                                    
													<?php
														if(have_posts() ) {									

															while(have_posts() ) {
																the_post();
																/*
																 * Include the Post-Format-specific template for the content.
																 * If you want to override this in a child theme, then include a file
																 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
																 */
																get_template_part( 'template-parts/content', get_post_format());										
															}									

															?>
									                			<nav class="pagination">
									                    			<?php the_posts_pagination(); ?>
									                			</nav>
															<?php	
														}
														
													?>
									            </div>
        									<?php
        								}
        							?>			        				
			        			<?php
			        		}
			        	?>			            
			        </div> <!-- row end -->
			    </div> <!-- container -->
			</div> <!-- blog section -->
            
            <div id="about-me" class="container">
                <div class="row" style="margin-bottom:75px;">
                    <div>
                        <?php
                            // query for the about page
                            $aboutme_query = new WP_Query( 'pagename=aboutme' );
                            // "loop" through query (even though it's just one page) 
                            while ( $aboutme_query->have_posts() ) : $aboutme_query->the_post();
                                echo '<h1 class="section-head">'; the_title(); echo '</h1>';
                                echo '<div class="section-body">'; the_content(); echo'</div>';
                            endwhile;
                            // reset post data (important!)
                            wp_reset_postdata();
                        ?>
                    </div>
                </div>
            </div><!-- about-me end -->
            
            <!-- <div id="education" class="container">
                <div class="row">
                    <div>
                        <?php
                            // comment query for the about page
                            // $readingList_query = new WP_Query( 'pagename=reading-list' );
                            // comment "loop" through query (even though it's just one page) 
                            /* while ( $readingList_query->have_posts() ) : $readingList_query->the_post();
                                echo '<h3>'; the_title(); echo '</h3>';
                                the_content();
                            endwhile; */
                            // comment reset post data (important!)
                            // wp_reset_postdata();
                        ?>
                        
                    </div> -->
                </div>
            </div><!-- education end -->
            
		</div> <!-- content-inner end -->
	</div> <!-- main end -->
</div> <!-- primary end -->




<?php

get_footer();
