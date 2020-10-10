<?php /* Template Name: homepage */ ?>

<?php
/* portfolio homepage template */

get_header(); ?>

<div id="primary" class="content-area">
	<div id="main" class="site-main" role="main">	
		<div class="content-inner">
			<div id="blog-section">
			    <div class="container">
			        <div class="row">
                        
			        	<?php
			        		if('right'===esc_attr(get_theme_mod('el_blog_sidebar','right'))) {
			        			?>
			        				<?php
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
			        <div class="row">
                        
                       
                        
                        <div>
                            <h1>About Me</h1>
                            <p>More about myself and how I love to learn</p>
                            <p>What am I currently pursuing?</p>
                            <p>Education and Certificates</p>
                            <p>Summer reading list</p>
                            <p>Break these into widgets</p>
                        
                        </div>
                </div>
            </div>    
            
		</div> <!-- content-inner end -->
	</div> <!-- main end -->
</div> <!-- primary end -->




<?php

get_footer();
