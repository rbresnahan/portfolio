<?php

/**
 * portfolio Page Title
 */

if ( ! function_exists( 'portfolio_get_page_title' ) ) :
function portfolio_get_page_title($blogtitle=false,$archivetitle=false,$categorytitle=false,$searchtitle=false,$pagenotfoundtitle=false) {
	if(!is_front_page()){
		?>
			<div class="content-section img-overlay">
				<div class="container">
					<div class="row text-center">
						<div class="col-md-12">
							<div class="section-title page-title"> 
								<?php
									if($blogtitle){
										?><h1 class="main-title"><?php single_post_title(); ?></h1><?php
									}
									if($archivetitle){
										?><h1 class="main-title"><?php the_archive_title(); ?></h1><?php
									}
                                    if($categorytitle){
										?><h1 class="main-title"><?php single_cat_title(); ?> Projects</h1><?php
									}
									if($searchtitle){
										?><h1 class="main-title"><?php _e('SEARCH RESULTS','portfolio') ?></h1><?php
									}
									if($pagenotfoundtitle){
										?><h1 class="main-title"><?php _e('PAGE NOT FOUND','portfolio') ?></h1><?php
									}
									if($blogtitle==false && $archivetitle==false && $categorytitle==false && $searchtitle==false && $pagenotfoundtitle==false){
										?><h1 class="main-title"><?php the_title(); ?></h1><?php
									}
								?>                                                          
			                </div>						
						</div>
					</div>
				</div>	
			</div>
			</div>	<!-- End page-title -->	
		<?php
	}	
}
endif;


/* Custom Footer Text */

if ( ! function_exists( 'portfolio_footer_copyrights' ) ) :
function portfolio_footer_copyrights() {
	?>
		<div class="row">
            <div class="copyrights">
                <p><?php //echo esc_attr(get_theme_mod( 'el_copyright_text', __('Copywrite Ryan Bresnahan. All Rights Reserved','portfolio')) ); ?></p>
                <p><?php echo '&copy'.date("Y"). ' Ryan Bresnahan All Rights Reserved' ?></p>
                
            </div>
        </div>
	<?php
}
endif;


if ( ! function_exists( 'portfolio_action_footer_hook' ) ) :
function portfolio_action_footer_hook() {
	add_action( 'portfolio_action_footer', 'portfolio_footer_copyrights' );	
}
endif;
add_action( 'wp', 'portfolio_action_footer_hook' );








?>