<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @author raphael.ksi@gmail.com
 * @version 0.1
 *
 * This widget is a child widget to the plugin "simple image widget"
 * https://github.com/cedaro/simple-image-widget
 * 
 * What it does:
 * - extends the simple image widget with fields from woocommerce product listing widget
 * - adds the option of category sorting to product listings 
 * 
 * Wordpress 2.8 and above
 * @see http://codex.wordpress.org/Widgets_API#Developing_Widgets
 */
class image_product_listing_widget extends Simple_Image_Widget {

    /**
     * Constructor
     *
     * @return void
     **/
    public function __construct() {

		$id_base = 'image-product-listing';
		$name    = 'Product and image listing widget';
		
		$widget_options = array( 

				'classname'   => 'image-product-listing',
				'description' => 'Creates a listing of products with a banner image',

		);

		parent::__construct( $id_base, $name, $widget_options, $control_options = array() ); 

    }

	public function form_fields() {
		return array( 
			'image_size', 
			'link', 
			'link_text', 
			'link_classes', 
			'text', 
			'show', 
			'orderby',
			'number',
			'order',
			'category',
			'hide_free',
			'show_hidden'

		);
	}    

	/**
	 * Query woocommerce products and return them
	 *
	 * This is a modified version of woocommerce class-wc-widget-products.php
	 * 
	 * The modifications:
	 * - category dropdown filter with product taxonomies
	 *
	 * @param  array $args
	 * @param  array $instance
	 * @return WP_Query
	 */
	public function get_products( $args, $instance ) {

		$category  = ! empty( $instance['category'] ) ? absint( $instance['category'] ) : $this->settings['category']['std'];
		$number  = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : $this->settings['number']['std'];
		$show    = ! empty( $instance['show'] ) ? sanitize_title( $instance['show'] ) : $this->settings['show']['std'];
		$orderby = ! empty( $instance['orderby'] ) ? sanitize_title( $instance['orderby'] ) : $this->settings['orderby']['std'];
		$order   = ! empty( $instance['order'] ) ? sanitize_title( $instance['order'] ) : $this->settings['order']['std'];

		
		$query_args = array(

			'tax_query' => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,					
				),
			),
			'posts_per_page' => $number,
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'no_found_rows'  => 1,
			'order'          => $order,
			'meta_query'     => array()
		);

		if ( empty( $instance['show_hidden'] ) ) {
			$query_args['meta_query'][] = WC()->query->visibility_meta_query();
			$query_args['post_parent']  = 0;
		}

		if ( ! empty( $instance['hide_free'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'DECIMAL',
			);
		}

		$query_args['meta_query'][] = WC()->query->stock_status_meta_query();
		$query_args['meta_query']   = array_filter( $query_args['meta_query'] );

		switch ( $show ) {
			case 'featured' :
				$query_args['meta_query'][] = array(
					'key'   => '_featured',
					'value' => 'yes'
				);
				break;
			case 'onsale' :
				$product_ids_on_sale    = wc_get_product_ids_on_sale();
				$product_ids_on_sale[]  = 0;
				$query_args['post__in'] = $product_ids_on_sale;
				break;
		}

		switch ( $orderby ) {
			case 'price' :
				$query_args['meta_key'] = '_price';
				$query_args['orderby']  = 'meta_value_num';
				break;
			case 'rand' :
				$query_args['orderby']  = 'rand';
				break;
			case 'sales' :
				$query_args['meta_key'] = 'total_sales';
				$query_args['orderby']  = 'meta_value_num';
				break;
			default :
				$query_args['orderby']  = 'date';
		}
		
		$result = new WP_Query( apply_filters( 'smyck_widget_query_args', $query_args ) );

		return $result;
	}

	// this function is needed to get the woocommerce products and pass them on
	public function widget( $args, $instance ) {

		$instance['products'] = $this->get_products( $args, $instance );

		parent::widget( $args, $instance );
	}


    
}


/* INITIATE THE WIDGET */
add_action( 'widgets_init', create_function( '', "register_widget( 'image_product_listing_widget' );" ) );


/* ADD FIELDS TO iNSTANCE */
add_filter( 'simple_image_widget_instance', function( $instance, $new_instance, $old_instance, $id_base ) {
		
		$instance['show'] = sanitize_title( $new_instance['show'] );
		$instance['orderby'] = sanitize_title( $new_instance['orderby'] );
		$instance['number'] = absint( $new_instance['number'] );
		$instance['order'] = sanitize_title( $new_instance['order'] );
		$instance['category'] = absint( $new_instance['category'] );
		$instance['hide_free'] = isset( $new_instance['hide_free'] );
		$instance['show_hidden'] = isset( $new_instance['show_hidden'] );

		return $instance;
}, 10, 4 );


/* CHOOSE THE TEMPLATE PATH TO USE FOR OUTPUT OF THE WIDGET */
add_filter( 'simple_image_widget_template_paths', function( $file_paths ) {
		
		$file_paths[1] = trailingslashit( get_template_directory() ) . '/includes/widgets/simple-image-widget/templates';
		
		return $file_paths;

}, 10, 1 );


/* ENABLE USE OF {$id_base}_widget.php TEMPLATE NAMES */
add_filter( 'simple_image_widget_templates', function( $templates, $args, $instance, $id_base ) {
		
		array_unshift($templates, $id_base . "_widget.php");

return $templates;

}, 1, 4 );


/* RENDER THE NEW FIELDS */
add_action( 'simple_image_widget_field-show', function( $instance, $that ) {
	$options = array( 
		'' => 'Alla produkter',
		'featured' => 'Rekommenderade produkter',
		'onsale' => 'Reaprodukter',
	);
	?>
	<p>
		<label for="<?php echo esc_attr( $that->get_field_id( 'show' ) ); ?>">Visa</label>
		<select name="<?php echo esc_attr( $that->get_field_name( 'show' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'show' ) ); ?>" class="widefat show"<?php echo ( sizeof( $options ) < 2 ) ? ' disabled="disabled"' : ''; ?>>
			<?php
			foreach ( $options as $id => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $id ),
					selected( $instance['show'], $id, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
	</p>							
	<?php
}, 10, 2);


add_action( 'simple_image_widget_field-orderby', function( $instance, $that ) {
	$options = array( 
		'date' => 'Datum',
		'price' => 'Pris',
		'rand' => 'Slumpvis',
		'sales' => 'Rea',
	);
	?>
	<p>
		<label for="<?php echo esc_attr( $that->get_field_id( 'orderby' ) ); ?>">Sortera efter</label>
		<select name="<?php echo esc_attr( $that->get_field_name( 'orderby' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'orderby' ) ); ?>" class="widefat show"<?php echo ( sizeof( $options ) < 2 ) ? ' disabled="disabled"' : ''; ?>>
			<?php
			foreach ( $options as $id => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $id ),
					selected( $instance['orderby'], $id, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
	</p>							
	<?php	
}, 10, 2);


add_action( 'simple_image_widget_field-number', function( $instance, $that ) {
	?>
	<p>
		<label for="<?php echo esc_attr( $that->get_field_id( 'number' ) ); ?>">Antalet produkter som ska visas</label>
		<input class="widefat" id="<?php echo esc_attr( $that->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $that->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" max="" value="<?php echo $instance['number'] ?>">
	</p>	
	<?php
}, 10, 2);


add_action( 'simple_image_widget_field-order', function( $instance, $that ) {
	$options = array( 
		'asc' => 'Stigande',
		'dsc' => 'Fallande',
	);
	?>
	<p>
		<label for="<?php echo esc_attr( $that->get_field_id( 'order' ) ); ?>">Order</label>
		<select name="<?php echo esc_attr( $that->get_field_name( 'order' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'order' ) ); ?>" class="widefat show"<?php echo ( sizeof( $options ) < 2 ) ? ' disabled="disabled"' : ''; ?>>
			<?php
			foreach ( $options as $id => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $id ),
					selected( $instance['order'], $id, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
	</p>							
	<?php		
}, 10, 2);


add_action( 'simple_image_widget_field-category', function( $instance, $that ) {
	$options = get_terms( 'product_cat', array(
				    'orderby'	=> 'name', 
				    'order'		=> 'ASC',
				    'hide_empty'=> true,  
				    'fields'	=> 'id=>name',
				)
	);
	?>
	<p>
		<label for="<?php echo esc_attr( $that->get_field_id( 'category' ) ); ?>">Kategori</label>
		<select name="<?php echo esc_attr( $that->get_field_name( 'category' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'category' ) ); ?>" class="widefat show"<?php echo ( sizeof( $options ) < 2 ) ? ' disabled="disabled"' : ''; ?>>
			<?php
			foreach ( $options as $id => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $id ),
					selected( $instance['category'], $id, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
	</p>							
	<?php	
}, 10, 2);


add_action( 'simple_image_widget_field-hide_free', function( $instance, $that ) {	
	?>
		<p>
			<label for="<?php echo esc_attr( $that->get_field_id( 'hide_free' ) ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $that->get_field_name( 'hide_free' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'hide_free' ) ); ?>" <?php checked( $instance['hide_free'] ); ?>>
				Dölj gratis produkter
			</label>
		</p>
	<?php	
}, 10, 2);


add_action( 'simple_image_widget_field-show_hidden', function( $instance, $that ) {	
	?>
		<p>
			<label for="<?php echo esc_attr( $that->get_field_id( 'show_hidden' ) ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $that->get_field_name( 'show_hidden' ) ); ?>" id="<?php echo esc_attr( $that->get_field_id( 'show_hidden' ) ); ?>" <?php checked( $instance['show_hidden'] ); ?>>
				Visa dolda produkter
			</label>
		</p>
	<?php	
}, 10, 2);

?>