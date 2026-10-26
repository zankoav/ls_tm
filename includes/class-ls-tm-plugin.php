<?php

defined( 'ABSPATH' ) || exit;

class LS_TM_Plugin {
	const PROJECT = 'ls_tm_project';
	const ISSUE = 'ls_tm_issue';
	const INVOICE = 'ls_tm_invoice';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'init', array( $this, 'ensure_roles_and_capabilities' ), 20 );
		add_action( 'cmb2_admin_init', array( $this, 'register_metaboxes' ) );
		add_action( 'cmb2_admin_init', array( $this, 'register_settings_page' ) );
		add_action( 'add_meta_boxes_' . self::INVOICE, array( $this, 'add_invoice_pdf_metabox' ) );
		add_action( 'admin_post_ls_tm_generate_pdf', array( $this, 'generate_invoice_pdf' ) );
		add_action( 'updated_post_meta', array( $this, 'archive_paid_invoice_issues' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'archive_paid_invoice_issues' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_invoice_issue_relationships' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'sync_invoice_issue_relationships' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'recalculate_invoice_on_rate_change' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'recalculate_invoice_on_rate_change' ), 10, 4 );
		add_action( 'rest_api_init', array( $this, 'register_rest_controller' ) );
	}

	public static function activate() {
		$plugin = self::instance();
		$plugin->register_content_types();
		$plugin->register_roles_and_capabilities();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function register_content_types() {
		$this->register_type( self::PROJECT, 'Project', 'Projects', array( 'title', 'editor', 'thumbnail' ) );
		$this->register_type( self::ISSUE, 'Issue', 'Issues', array( 'title', 'editor' ) );
		$this->register_type( self::INVOICE, 'Invoice', 'Invoices', array( 'title' ) );
	}

	private function register_type( $type, $singular, $plural, $supports ) {
		register_post_type(
			$type,
			array(
				'labels' => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'add_new_item'  => 'Add New ' . $singular,
					'edit_item'     => 'Edit ' . $singular,
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'capability_type'     => array( 'ls_tm_' . substr( $type, 6 ), 'ls_tm_' . substr( $type, 6 ) . 's' ),
				'map_meta_cap'        => true,
				'supports'            => $supports,
				'rewrite'             => false,
				'has_archive'         => false,
				'menu_icon'           => 'dashicons-clipboard',
			)
		);
	}

	public function ensure_roles_and_capabilities() {
		if ( get_role( 'ls_tm_product_owner' ) ) {
			return;
		}

		$this->register_roles_and_capabilities();
	}

	public function register_roles_and_capabilities() {
		add_role( 'ls_tm_product_owner', 'Product Owner', array( 'read' => true ) );

		$administrator = get_role( 'administrator' );
		if ( ! $administrator ) {
			return;
		}

		foreach ( array( 'project', 'issue', 'invoice' ) as $type ) {
			$plural = $type . 's';
			foreach ( array(
				'edit_ls_tm_' . $type,
				'read_ls_tm_' . $type,
				'delete_ls_tm_' . $type,
				'edit_ls_tm_' . $plural,
				'edit_others_ls_tm_' . $plural,
				'publish_ls_tm_' . $plural,
				'read_private_ls_tm_' . $plural,
				'delete_ls_tm_' . $plural,
				'delete_private_ls_tm_' . $plural,
				'delete_published_ls_tm_' . $plural,
				'delete_others_ls_tm_' . $plural,
				'edit_private_ls_tm_' . $plural,
				'edit_published_ls_tm_' . $plural,
			) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}

	public function register_metaboxes() {
		if ( ! function_exists( 'new_cmb2_box' ) ) {
			return;
		}

		$project = new_cmb2_box( array(
			'id'           => 'ls_tm_project_details',
			'title'        => 'Project details',
			'object_types' => array( self::PROJECT ),
		) );
		$project->add_field( array(
			'name'       => 'Product Owner',
			'id'         => '_ls_tm_product_owner_id',
			'type'       => 'select',
			'options_cb' => array( $this, 'user_options' ),
		) );
		$project->add_field( array( 'name' => 'Company address', 'id' => '_ls_tm_project_contact_address', 'type' => 'textarea_small' ) );
		$project->add_field( array( 'name' => 'Production website', 'id' => '_ls_tm_project_contact_website', 'type' => 'text_url' ) );
		$project->add_field( array( 'name' => 'Contact email', 'id' => '_ls_tm_project_contact_email', 'type' => 'text_email' ) );

		$issue = new_cmb2_box( array(
			'id'           => 'ls_tm_issue_details',
			'title'        => 'Issue details',
			'object_types' => array( self::ISSUE ),
		) );
		$issue->add_field( array( 'name' => 'Project', 'id' => '_ls_tm_project_id', 'type' => 'select', 'options_cb' => array( $this, 'project_options' ) ) );
		$issue->add_field( array( 'name' => 'Issue type', 'id' => '_ls_tm_issue_type', 'type' => 'select', 'default' => 'feature', 'options' => array( 'bug' => 'Bug', 'feature' => 'Feature' ) ) );
		$issue->add_field( array( 'name' => 'Status', 'id' => '_ls_tm_issue_status', 'type' => 'select', 'default' => 'open', 'options' => self::issue_statuses() ) );
		$issue->add_field( array( 'name' => 'Priority', 'id' => '_ls_tm_issue_priority', 'type' => 'select', 'default' => 'medium', 'options' => array( 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low' ) ) );
		$issue->add_field( array( 'name' => 'Estimated hours', 'id' => '_ls_tm_estimated_hours', 'type' => 'text_small', 'attributes' => array( 'type' => 'number', 'min' => '0', 'step' => '0.25' ) ) );
		$issue->add_field( array( 'name' => 'Figma prototype URL', 'id' => '_ls_tm_figma_url', 'type' => 'text_url' ) );
		$issue->add_field( array( 'name' => 'Invoice', 'id' => '_ls_tm_invoice_id', 'type' => 'select', 'show_option_none' => true, 'options_cb' => array( $this, 'invoice_options' ) ) );

		$invoice = new_cmb2_box( array(
			'id'           => 'ls_tm_invoice_details',
			'title'        => 'Invoice details',
			'object_types' => array( self::INVOICE ),
		) );
		$invoice->add_field( array( 'name' => 'Project', 'id' => '_ls_tm_project_id', 'type' => 'select', 'options_cb' => array( $this, 'project_options' ) ) );
		$invoice->add_field( array( 'name' => 'Included Issues', 'id' => '_ls_tm_issue_ids', 'type' => 'multicheck', 'options_cb' => array( $this, 'completed_issue_options' ) ) );
		$invoice->add_field( array( 'name' => 'Status', 'id' => '_ls_tm_invoice_status', 'type' => 'select', 'default' => 'created', 'options' => self::invoice_statuses() ) );
		$invoice->add_field( array( 'name' => 'Hourly rate', 'id' => '_ls_tm_hourly_rate', 'type' => 'text_money', 'before_field' => 'BYN ', 'default' => $this->global_hourly_rate(), 'attributes' => array( 'readonly' => 'readonly' ) ) );
		$invoice->add_field( array( 'name' => 'Total price', 'id' => '_ls_tm_total_price', 'type' => 'text_money', 'before_field' => 'BYN ', 'attributes' => array( 'readonly' => 'readonly' ) ) );
	}

	public function register_settings_page() {
		if ( ! function_exists( 'new_cmb2_box' ) ) {
			return;
		}

		$options = new_cmb2_box( array(
			'id'           => 'ls_tm_settings',
			'title'        => 'LS_TM Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'ls_tm_settings',
			'parent_slug'  => 'options-general.php',
		) );
		$options->add_field( array( 'name' => 'Global hourly rate', 'id' => 'global_hourly_rate', 'type' => 'text_money', 'before_field' => 'BYN ' ) );
	}

	public function user_options() {
		$options = array( '' => '— Select Product Owner —' );
		foreach ( get_users( array( 'role' => 'ls_tm_product_owner', 'orderby' => 'display_name' ) ) as $user ) {
			$options[ $user->ID ] = $user->display_name . ' (' . $user->user_email . ')';
		}

		return $options;
	}

	public function project_options() {
		return $this->post_options( self::PROJECT, '— Select Project —' );
	}

	public function invoice_options() {
		return $this->post_options( self::INVOICE, '— No Invoice —' );
	}

	public function completed_issue_options( $field = null ) {
		$options = array();
		$invoice_id = $field ? absint( $field->object_id() ) : 0;
		$project_id = $invoice_id ? (int) get_post_meta( $invoice_id, '_ls_tm_project_id', true ) : 0;
		$args = array( 'post_type' => self::ISSUE, 'numberposts' => -1, 'post_status' => 'any', 'meta_query' => array( array( 'key' => '_ls_tm_issue_status', 'value' => 'completed' ) ) );
		if ( $project_id ) {
			$args['meta_query'][] = array( 'key' => '_ls_tm_project_id', 'value' => $project_id );
		}
		foreach ( get_posts( $args ) as $issue ) {
			$invoice_id = (int) get_post_meta( $issue->ID, '_ls_tm_invoice_id', true );
			if ( ! $invoice_id || $invoice_id === ( $field ? absint( $field->object_id() ) : 0 ) ) {
				$options[ $issue->ID ] = $issue->post_title;
			}
		}

		return $options;
	}

	private function post_options( $post_type, $placeholder ) {
		$options = array( '' => $placeholder );
		foreach ( get_posts( array( 'post_type' => $post_type, 'numberposts' => -1, 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		return $options;
	}

	public static function issue_statuses() {
		return array( 'open' => 'Open', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'in_review' => 'In review', 'completed' => 'Completed', 'archived' => 'Archived', 'deleted' => 'Deleted' );
	}

	public static function invoice_statuses() {
		return array( 'created' => 'Created', 'sent' => 'Sent', 'paid' => 'Paid' );
	}

	public function add_invoice_pdf_metabox() {
		add_meta_box( 'ls_tm_invoice_pdf', 'Invoice PDF', array( $this, 'render_invoice_pdf_metabox' ), self::INVOICE, 'side' );
	}

	public function render_invoice_pdf_metabox( $post ) {
		$pdf_id = (int) get_post_meta( $post->ID, '_ls_tm_pdf_id', true );
		if ( $pdf_id ) {
			echo '<p><a href="' . esc_url( wp_get_attachment_url( $pdf_id ) ) . '" target="_blank" rel="noopener">Download current PDF</a></p>';
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=ls_tm_generate_pdf&invoice_id=' . $post->ID ), 'ls_tm_generate_pdf_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">Generate PDF</a></p>';
	}

	public function generate_invoice_pdf() {
		$invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
		if ( ! $invoice_id || ! current_user_can( 'edit_post', $invoice_id ) ) {
			wp_die( 'You are not allowed to generate this invoice.' );
		}
		check_admin_referer( 'ls_tm_generate_pdf_' . $invoice_id );

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || self::INVOICE !== $invoice->post_type ) {
			wp_die( 'Invoice not found.' );
		}

		$project_id = (int) get_post_meta( $invoice_id, '_ls_tm_project_id', true );
		$issue_ids  = array_map( 'absint', (array) get_post_meta( $invoice_id, '_ls_tm_issue_ids', true ) );
		$rate       = (float) get_post_meta( $invoice_id, '_ls_tm_hourly_rate', true );
		$lines      = array( 'Invoice: ' . $invoice->post_title, 'Project: ' . get_the_title( $project_id ), '' );
		$total_hours = 0;
		foreach ( $issue_ids as $issue_id ) {
			$hours = (float) get_post_meta( $issue_id, '_ls_tm_estimated_hours', true );
			$total_hours += $hours;
			$lines[] = get_the_title( $issue_id ) . ' - ' . $hours . ' hours';
		}
		$lines[] = '';
		$lines[] = 'Total hours: ' . $total_hours;
		$lines[] = 'Hourly rate: ' . number_format( $rate, 2 ) . ' BYN';
		$lines[] = 'Total: ' . number_format( $total_hours * $rate, 2 ) . ' BYN';
		update_post_meta( $invoice_id, '_ls_tm_total_price', $total_hours * $rate );

		$filename = sanitize_file_name( 'invoice-' . $invoice_id . '-' . gmdate( 'Ymd-His' ) . '.pdf' );
		$upload   = wp_upload_bits( $filename, null, $this->make_simple_pdf( $lines ) );
		if ( $upload['error'] ) {
			wp_die( esc_html( $upload['error'] ) );
		}
		$attachment_id = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => $invoice->post_title . ' PDF', 'post_status' => 'inherit' ), $upload['file'], $invoice_id );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $invoice_id, '_ls_tm_pdf_id', $attachment_id );

		wp_safe_redirect( add_query_arg( 'ls_tm_pdf_generated', 1, get_edit_post_link( $invoice_id, 'url' ) ) );
		exit;
	}

	private function make_simple_pdf( $lines ) {
		$content = "BT\n/F1 12 Tf\n50 780 Td\n";
		foreach ( $lines as $index => $line ) {
			$line = preg_replace( '/[^\x20-\x7E]/', '?', (string) $line );
			$line = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $line );
			// Use a real PDF line-break operator between entries. A single-quoted
			// "\\n" was previously written literally, which joined all text together.
			$content .= ( 0 === $index ? '' : "0 -18 Td\n" ) . '(' . $line . ") Tj\n";
		}
		$content .= 'ET';
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
			'<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . '\nendstream',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		);
		$pdf = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $number => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf .= ( $number + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( array_slice( $offsets, 1 ) as $offset ) {
			$pdf .= sprintf( '%010d 00000 n ' . "\n", $offset );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

		return $pdf;
	}

	public function archive_paid_invoice_issues( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_ls_tm_invoice_status' !== $meta_key || 'paid' !== $meta_value || self::INVOICE !== get_post_type( $object_id ) ) {
			return;
		}
		foreach ( (array) get_post_meta( $object_id, '_ls_tm_issue_ids', true ) as $issue_id ) {
			update_post_meta( absint( $issue_id ), '_ls_tm_issue_status', 'archived' );
		}
	}

	public function sync_invoice_issue_relationships( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_ls_tm_issue_ids' !== $meta_key || self::INVOICE !== get_post_type( $object_id ) ) {
			return;
		}

		$issue_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $meta_value ) ) ) );
		$previous = get_posts( array(
			'post_type'      => self::ISSUE,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'numberposts'    => -1,
			'meta_key'       => '_ls_tm_invoice_id',
			'meta_value'     => $object_id,
		) );
		foreach ( array_diff( array_map( 'absint', $previous ), $issue_ids ) as $issue_id ) {
			delete_post_meta( $issue_id, '_ls_tm_invoice_id' );
		}
		foreach ( $issue_ids as $issue_id ) {
			update_post_meta( $issue_id, '_ls_tm_invoice_id', $object_id );
		}

		$this->snapshot_and_total_invoice( $object_id, $issue_ids );
	}

	public function recalculate_invoice_on_rate_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_ls_tm_hourly_rate' !== $meta_key || self::INVOICE !== get_post_type( $object_id ) ) {
			return;
		}
		$this->snapshot_and_total_invoice( $object_id, array_map( 'absint', (array) get_post_meta( $object_id, '_ls_tm_issue_ids', true ) ) );
	}

	/** Keep historical billable hours stable after an Issue is included. */
	private function snapshot_and_total_invoice( $invoice_id, $issue_ids ) {
		$snapshots = (array) get_post_meta( $invoice_id, '_ls_tm_issue_snapshots', true );
		foreach ( $issue_ids as $issue_id ) {
			if ( ! isset( $snapshots[ $issue_id ] ) ) {
				$snapshots[ $issue_id ] = array(
					'issue_id'        => (int) $issue_id,
					'title'           => get_the_title( $issue_id ),
					'estimated_hours' => (float) get_post_meta( $issue_id, '_ls_tm_estimated_hours', true ),
				);
			}
		}
		$snapshots = array_intersect_key( $snapshots, array_flip( $issue_ids ) );
		update_post_meta( $invoice_id, '_ls_tm_issue_snapshots', $snapshots );
		$hours = array_sum( wp_list_pluck( $snapshots, 'estimated_hours' ) );
		update_post_meta( $invoice_id, '_ls_tm_total_price', round( $hours * (float) get_post_meta( $invoice_id, '_ls_tm_hourly_rate', true ), 2 ) );
	}

	public function global_hourly_rate() {
		$settings = (array) get_option( 'ls_tm_settings', array() );
		return isset( $settings['global_hourly_rate'] ) ? (float) $settings['global_hourly_rate'] : 0;
	}

	public function register_rest_controller() {
		new LS_TM_REST_Controller( $this );
	}

	public function user_can_access_project( $user_id, $project_id ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$user = get_userdata( $user_id );
		return $user && in_array( 'ls_tm_product_owner', (array) $user->roles, true ) && (int) get_post_meta( $project_id, '_ls_tm_product_owner_id', true ) === (int) $user_id;
	}
}
