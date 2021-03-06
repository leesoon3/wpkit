<?php

class WpakComponentTypePostsList extends WpakComponentType {

	protected function compute_data( $component, $options, $args = array() ) {
		global $wpdb;

		do_action( 'wpak_before_component_posts_list', $component, $options );

		$before_post_date = '';
		if ( !empty( $args['before_item'] ) && is_numeric( $args['before_item'] ) ) {
			$before_post = get_post( $args['before_item'] );
			if ( !empty( $before_post ) ) {
				$before_post_date = $before_post->post_date;
			}
		}

		if( $options['post-type'] == 'custom' ) {

			//Custom posts list generated via hook :
			//Choose "Custom, using hook" when creating the component in BO, and use the following
			//hook "wpak_posts_list_custom-[your-hook]" to set the component posts.
			//The wpak_posts_list_custom-[your-hook] filter must return the given $posts_list_data filled in with
			//your custom data :
			//- posts : array of the posts retrieved by your component, in the same format as a "get_posts()" or "new WP_Query($query_args)"
			//- total : total number of those posts (not only those retrieved in posts, taking pagination into account)
			//- query : data about your query that you want to retrieve on the app side.

			$posts_list_data = array(
				'posts' => array(),
				'total' => 0,
				'query' => array( 'type' => 'custom-posts-list', 'taxonomy' => '', 'terms' => array(), 'is_last_page' => true, 'before_item' => 0 )
			);

			/**
			 * Filter data from a posts list component.
			 *
			 * @param array 			$posts_list_data    	An array of default data.
			 * @param WpakComponent 	$component 				The component object.
			 * @param array 			$options 				An array of options.
			 * @param array 			$args 					An array of complementary arguments.
			 * @param array 			$before_post_date 		The publication of the last displayed post.
			 */
			$posts_list_data = apply_filters( 'wpak_posts_list_custom-' . $options['hook'], $posts_list_data, $component, $options, $args, $before_post_date );

			$posts = $posts_list_data['posts'];
			$total = !empty( $posts_list_data['total'] ) ? $posts_list_data['total'] : count( $posts );
			$query = $posts_list_data['query'];

		} else { //WordPress Post type or "Latest posts"

			$is_last_posts = $options['post-type'] == 'last-posts';

			$post_type = !empty( $options['post-type'] ) && !$is_last_posts ? $options['post-type'] : 'post';

			$query = array( 'post_type' => $post_type );

			$query_args = array( 'post_type' => $post_type );

			/**
			 * Filter the number of posts displayed into a posts list component.
			 *
			 * @param int 			    					Default number of posts.
			 * @param WpakComponent 	$component 			The component object.
			 * @param array 			$options 			An array of options.
			 * @param array 			$args 				An array of complementary arguments.
			 */
			$query_args['posts_per_page'] = apply_filters('wpak_posts_list_posts_per_page', WpakSettings::get_setting( 'posts_per_page' ), $component, $options, $args );

			if( $is_last_posts ){

				$query['type'] = 'last-posts';

			}elseif ( !empty( $options['taxonomy'] ) && !empty( $options['term'] ) ) {

				$query_args['tax_query'] = array(
					array(
						'taxonomy' => $options['taxonomy'],
						'field' => 'slug',
						'terms' => $options['term']
					)
				);

				$query['type'] = 'taxonomy';
				$query['taxonomy'] = $options['taxonomy'];
				$query['terms'] = is_array( $options['term'] ) ? $options['term'] : array( $options['term'] );
			}

			if ( !empty( $before_post_date ) ) {
				if ( is_numeric( $before_post_date ) ) { //timestamp
					$before_post_date = date( 'Y-m-d H:i:s', $before_post_date );
				}

				if ( preg_match( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $before_post_date ) ) {
					$query['before_item'] = intval( $args['before_item'] );
					$posts_where_callback = create_function( '$where', 'return $where .= " AND post_date < \'' . $before_post_date . '\'";' );
					add_filter( 'posts_where', $posts_where_callback );
				} else {
					$before_post_date = '';
				}
			}

			/**
			 * Filter args used for the query made into a posts list component.
			 *
			 * @param array 			$query_args    		An array of default args.
			 * @param WpakComponent 	$component 			The component object.
			 * @param array 			$options 			An array of options.
			 * @param array 			$args 				An array of complementary arguments.
			 * @param array 			$query 				Data about the query to retrieve on the app side.
			 */
			$query_args = apply_filters( 'wpak_posts_list_query_args', $query_args, $component, $options, $args, $query );

			$posts_query = new WP_Query( $query_args );

			if ( !empty( $before_post_date ) ) {
				remove_filter( 'posts_where', $posts_where_callback );
				$query['is_last_page'] = $posts_query->found_posts <= count( $posts_query->posts );
			}

			$posts = $posts_query->posts;
			$total = $posts_query->found_posts;
		}

		$posts_by_ids = array();
		foreach ( $posts as $post ) {
			$posts_by_ids[$post->ID] = self::get_post_data( $component, $post );
		}

		$this->set_specific( 'ids', array_keys( $posts_by_ids ) );
		$this->set_specific( 'total', $total );
		$this->set_specific( 'query', $query );
		$this->set_globals( 'posts', $posts_by_ids );
	}

	protected static function get_post_data( $component, $_post ) {
		global $post;
		$post = $_post;
		setup_postdata( $post );

		$post_data = array(
			'id' => $post->ID,
			'post_type' => $post->post_type,
			'date' => strtotime( $post->post_date ),
			'title' => $post->post_title,
			'content' => '',
			'excerpt' => '',
			'thumbnail' => '',
			'author' => get_the_author_meta( 'nickname' ),
			'nb_comments' => ( int ) get_comments_number()
		);

		/**
		 * Filter post content into a posts list component. Use this to format app posts content your own way.
		 *
		 * To apply the default App Kit formating to the content and add only minor modifications to it,
		 * use the "wpak_post_content_format" filter instead.
		 *
		 * @see WpakComponentsUtils::get_formated_content()
		 *
		 * @param string 			''    			The post content: an empty string by default.
		 * @param WP_Post 			$post 			The post object.
		 * @param WpakComponent 	$component		The component object.
		 */
		$content = apply_filters( 'wpak_posts_list_post_content', '', $post, $component );
		if ( empty( $content ) ) {
			$content = WpakComponentsUtils::get_formated_content();
		}
		$post_data['content'] = $content;

		$post_data['excerpt'] = WpakComponentsUtils::get_post_excerpt( $post );

		$post_featured_img_id = get_post_thumbnail_id( $post->ID );
		if ( !empty( $post_featured_img_id ) ) {
			$featured_img_src = wp_get_attachment_image_src( $post_featured_img_id, 'mobile-featured-thumb' );
			@$post_data['thumbnail']['src'] = $featured_img_src[0];
			$post_data['thumbnail']['width'] = $featured_img_src[1];
			$post_data['thumbnail']['height'] = $featured_img_src[2];
		}

		/**
		 * Filter post data sent to the app from a posts list component.
		 *
		 * Use this for example to add a post meta to the default post data.
		 *
		 * @param array 			$post_data    	The default post data sent to an app.
		 * @param WP_Post 			$post 			The post object.
		 * @param WpakComponent 	$component		The component object.
		 */
		$post_data = apply_filters( 'wpak_post_data', $post_data, $post, $component );

		return ( object ) $post_data;
	}

	public function get_options_to_display( $component ) {
		if ( $component->options['post-type'] != 'custom' ) {
			$post_type = get_post_type_object( $component->options['post-type'] );
			$taxonomy = get_taxonomy( $component->options['taxonomy'] );
			$term = get_term_by( 'slug', $component->options['term'], $component->options['taxonomy'] );
			$options = array();
			if ( !is_wp_error( $term ) ) {
				$options = array(
					'post-type' => array( 'label' => __( 'Post type' ), 'value' => $post_type->labels->name ),
					'taxonomy' => array( 'label' => __( 'Taxonomy' ), 'value' => $taxonomy->labels->name ),
					'term' => array( 'label' => __( 'Term' ), 'value' => $term->name )
				);
			}
		} else {
			$options = array(
				'hook' => array( 'label' => __( 'Hook', WpAppKit::i18n_domain ), 'value' => $component->options['hook'] ),
			);
		}
		return $options;
	}

	public function echo_form_fields( $component ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' ); //TODO : hook on arg array
		unset( $post_types['attachment'] );

		$has_options = !empty( $component ) && !empty( $component->options );

		reset( $post_types );
		$first_post_type = key( $post_types );

		$current_post_type = $first_post_type;
		$current_taxonomy = '';
		$current_term = '';
		$current_hook = '';
		if ( $has_options ) {
			$options = $component->options;
			$current_post_type = $options['post-type'];
			$current_taxonomy = $options['taxonomy'];
			$current_term = $options['term'];
			$current_hook = !empty( $options['hook'] ) ? $options['hook'] : '';
		}

		?>
		<div class="component-params">
			<label><?php _e( 'List type', WpAppKit::i18n_domain ) ?> : </label>
			<select name="post-type" class="posts-list-post-type">
				<?php foreach ( $post_types as $post_type => $post_type_object ): ?>
					<?php $selected = $post_type == $current_post_type ? 'selected="selected"' : '' ?>
					<option value="<?php echo $post_type ?>" <?php echo $selected ?>><?php echo $post_type_object->labels->name ?></option>
				<?php endforeach ?>
				<option value="last-posts" <?php echo 'last-posts' == $current_post_type ? 'selected="selected"' : '' ?>><?php _e( 'Latest posts', WpAppKit::i18n_domain ) ?></option>
				<option value="custom" <?php echo 'custom' == $current_post_type ? 'selected="selected"' : '' ?>><?php _e( 'Custom, using hooks', WpAppKit::i18n_domain ) ?></option>
			</select>
		</div>

		<div class="ajax-target">
			<?php self::echo_sub_options_html( $current_post_type, $current_taxonomy, $current_term, $current_hook ) ?>
		</div>

		<?php
	}

	public function echo_form_javascript() {
		?>
		<script type="text/javascript">
			(function() {
				var $ = jQuery;
				$('.wrap').delegate('.posts-list-post-type', 'change', function() {
					var post_type = $(this).find(":selected").val();
					WpakComponents.ajax_update_component_options(this, 'posts-list', 'change-post-list-option', {taxonomy: '', post_type: post_type});
				});
				$('.wrap').delegate('.posts-list-taxonomies', 'change', function() {
					var post_type = $(this).closest('.ajax-target').prev('div.component-params').find('select.posts-list-post-type').eq(0).find(":selected").val();
					var taxonomy = $(this).find(":selected").val();
					WpakComponents.ajax_update_component_options(this, 'posts-list', 'change-post-list-option', {taxonomy: taxonomy, post_type: post_type});
				});
			})();
		</script>
		<?php
	}

	public function get_ajax_action_html_answer( $action, $params ) {
		switch ( $action ) {
			case 'change-post-list-option':
				$post_type = $params['post_type'];
				$taxonomy = $params['taxonomy'];
				self::echo_sub_options_html( $post_type, $taxonomy );
				break;
		}
	}

	protected static function echo_sub_options_html( $current_post_type, $current_taxonomy = '', $current_term = '', $current_hook = '' ) {
		?>
		<?php if( $current_post_type == 'last-posts' ) : //Custom posts list ?>
			<?php //no sub option for now ?>
		<?php elseif( $current_post_type == 'custom' ): //Custom posts list ?>
			<label><?php _e( 'Hook name', WpAppKit::i18n_domain ) ?></label> : <input type="text" name="hook" value="<?php echo $current_hook ?>" />
		<?php else: //Post type ?>

			<?php
				$taxonomies = get_object_taxonomies( $current_post_type );
				$taxonomies = array_diff( $taxonomies, array( 'nav_menu', 'link_category' ) );

				/**
				 * Filter taxonomies list displayed into a "Posts list" component select field.
				 *
				 * @param array 	$taxonomies    	The default taxonomies list to display.
				 */
				$taxonomies = apply_filters( 'wpak_component_type_posts_list_form_taxonomies', $taxonomies );

				$first_taxonomy = reset( $taxonomies );
				$current_taxonomy = empty( $current_taxonomy ) ? $first_taxonomy : $current_taxonomy;
			?>
			<label><?php _e( 'Taxonomy', WpAppKit::i18n_domain ) ?> : </label>
			<?php if ( !empty( $taxonomies ) ): ?>
				<select name="taxonomy" class="posts-list-taxonomies">
					<?php foreach ( $taxonomies as $taxonomy_slug ): ?>
						<?php $taxonomy = get_taxonomy( $taxonomy_slug ) ?>
						<?php $selected = $taxonomy_slug == $current_taxonomy ? 'selected="selected"' : '' ?>
						<option value="<?php echo $taxonomy_slug ?>" <?php echo $selected ?>><?php echo $taxonomy->labels->name ?></option>
					<?php endforeach ?>
				</select>
				<br/>
				<?php
					$taxonomy_obj = get_taxonomy( $current_taxonomy );
					$terms = get_terms( $current_taxonomy );
				?>
				<label><?php echo $taxonomy_obj->labels->name ?> : </label>
				<?php if ( !empty( $terms ) ): ?>
					<select name="term">
						<?php foreach ( $terms as $term ): ?>
							<?php $selected = $term->slug == $current_term ? 'selected="selected"' : '' ?>
							<option value="<?php echo $term->slug ?>" <?php echo $selected ?>><?php echo $term->name ?></option>
						<?php endforeach ?>
					</select>
				<?php else: ?>
					<?php echo sprintf( __( 'No %s found', WpAppKit::i18n_domain ), $taxonomy_obj->labels->name ); ?>
				<?php endif ?>
			<?php else: ?>
				<?php echo sprintf( __( 'No taxonomy found for post type %s', WpAppKit::i18n_domain ), $current_post_type ); ?>
			<?php endif ?>
		<?php endif ?>
		<?php
	}

	public function get_options_from_posted_form( $data ) {
		$post_type = !empty( $data['post-type'] ) ? $data['post-type'] : '';
		$taxonomy = !empty( $data['taxonomy'] ) ? $data['taxonomy'] : '';
		$term = !empty( $data['term'] ) ? $data['term'] : '';
		$hook = !empty( $data['hook'] ) ? $data['hook'] : '';
		$options = array( 'post-type' => $post_type, 'taxonomy' => $taxonomy, 'term' => $term, 'hook' => $hook );
		return $options;
	}

}

WpakComponentsTypes::register_component_type( 'posts-list', array( 'label' => __( 'Posts list', WpAppKit::i18n_domain ) ) );
