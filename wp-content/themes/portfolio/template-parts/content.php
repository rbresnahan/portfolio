<?php
/**
 * Template part for displaying posts.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package elvinaa
 */
?>


	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <div class="blog-wrapper">
            <div class="cat">
                <!-- <div class="circle-ripple">
                  <div class="dot"></div>
                </div> -->
                <div class="post-cat-name">
                    
          
                    <?php /* echo display_images_from_media_library(); */ ?>
                    <?php
                        if(is_sticky()){
                            ?>                                        
                                <span class="meta-item">
                                    <i class="fa fa-thumb-tack"></i><?php _e('Sticky Post','elvinaa') ?>
                                </span> 
                            <?php       
                        }                                
                    ?>  
                </div>
            </div>
            <div class="title-date">
                <?php
                    if(is_single()){
                        ?><h1><?php the_title(); ?></h1>
                        <div class="cat-list-formatting"><?php the_category(' / '); ?> </div>
                        <?php if (get_post_type() == 'books') {
                            $value = get_field( 'book-author' );
                            echo '<div style="font-size:1.3em;">by '.$value.'</div>'; }?>
                        <?php
                        
                    }
                    else
                    {
                        ?><h1><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h1>
                          <div class="cat-list-formatting"><?php the_category(' / '); ?></div>
                             <div class="post-image">
                                <?php
                                    if ( has_post_thumbnail()) {
                                        ?> <div class="image">
                                                <?php the_post_thumbnail( array (600, 200) ); ?>
                                            </div> 
                                            
                                 <?php                    
                                        } ?>
                            </div> <?php
                    } 
                ?>
                
            </div>
           
           
            <div class="content">
                
                    <?php
                        if(is_single()){ 
                            
                            the_content();
                        }
                        else{ ?>
                            <span class="excerpt-format">
                                <?php the_excerpt(); ?>
                            </span> <?php  
                        }
                    ?>
                
                
                
            </div>
            <?php 
                if(!is_single()){
                    ?>
                        <div class="details">
                            <a href="<?php the_permalink() ?>"><?php echo esc_attr(get_theme_mod( 'el_readmore_text',__('Details','elvinaa') )); ?></a>
                        </div>
                        
           
            
            
                        <?php edit_post_link( __( '[edit]', 'textdomain' ), '<p>', '</p>', null, 'edit-btn' ); ?>
            
                    <?php
                }
            ?>

            <?php 
                if(is_single()){
                    ?>
                        <div class="post-tags">
                            <?php the_tags(); ?>
                        </div>
                    <?php
                }
            ?>
        
            <?php
                wp_link_pages( array(
                    'before'      => '<div class="page-links">' . __( 'Pages:', 'elvinaa' ),
                    'after'       => '</div>',
                    'link_before' => '<span class="page-number">',
                    'link_after'  => '</span>',
                ) );
            ?>        
        </div>
    </article>