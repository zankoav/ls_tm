<?php

defined( 'ABSPATH' ) || exit;

class LS_TM_REST_Controller {
	private $plugin;
	private $types = array(
		'projects' => LS_TM_Plugin::PROJECT,
		'issues'   => LS_TM_Plugin::ISSUE,
		'invoices' => LS_TM_Plugin::INVOICE,
	);

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->register_routes();
	}

	public function register_routes() {
		foreach ( $this->types as $route => $post_type ) {
			register_rest_route( 'ls-tm/v1', '/' . $route, array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function( $request ) use ( $post_type ) { return $this->get_collection( $request, $post_type ); },
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function( $request ) use ( $post_type ) { return $this->create_item( $request, $post_type ); },
					'permission_callback' => array( $this, 'can_manage' ),
				),
			) );

			register_rest_route( 'ls-tm/v1', '/' . $route . '/(?P<id>\d+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function( $request ) use ( $post_type ) { return $this->get_item( $request, $post_type ); },
					'permission_callback' => function( $request ) use ( $post_type ) { return $this->can_read_item( $request, $post_type ); },
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function( $request ) use ( $post_type ) { return $this->update_item( $request, $post_type ); },
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function( $request ) use ( $post_type ) { return $this->delete_item( $request, $post_type ); },
					'permission_callback' => array( $this, 'can_manage' ),
				),
			) );
		}
	}

	public function can_read() {
		return is_user_logged_in();
	}

	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public function can_read_item( $request, $post_type ) {
		$post = get_post( (int) $request['id'] );
		return $post && $post_type === $post->post_type && $this->can_access_post( get_current_user_id(), $post );
	}

	private function get_collection( $request, $post_type ) {
		$args = array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			$project_ids = $this->assigned_project_ids( get_current_user_id() );
			if ( LS_TM_Plugin::PROJECT === $post_type ) {
				$args['post__in'] = $project_ids ?: array( 0 );
			} else {
				$args['meta_key']   = '_ls_tm_project_id';
				$args['meta_value'] = $project_ids ?: array( 0 );
				$args['meta_compare'] = 'IN';
			}
		}

		$items = array_map( array( $this, 'prepare_item' ), get_posts( $args ) );
		return rest_ensure_response( $items );
	}

	private function get_item( $request, $post_type ) {
		return rest_ensure_response( $this->prepare_item( get_post( (int) $request['id'] ) ) );
	}

	private function create_item( $request, $post_type ) {
		$post_id = wp_insert_post( array(
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $request->get_param( 'title' ) ?: 'Untitled ' . $this->singular_label( $post_type ) ),
			'post_content' => wp_kses_post( $request->get_param( 'content' ) ?: '' ),
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = $this->save_fields( $post_id, $post_type, $request );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( $post_id, true );
			return $result;
		}

		return new WP_REST_Response( $this->prepare_item( get_post( $post_id ) ), 201 );
	}

	private function update_item( $request, $post_type ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || $post_type !== $post->post_type ) {
			return new WP_Error( 'ls_tm_not_found', 'Item not found.', array( 'status' => 404 ) );
		}
		$update = array( 'ID' => $post->ID );
		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$update['post_content'] = wp_kses_post( $request->get_param( 'content' ) );
		}
		wp_update_post( $update );
		$result = $this->save_fields( $post->ID, $post_type, $request );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $this->prepare_item( get_post( $post->ID ) ) );
	}

	private function delete_item( $request, $post_type ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || $post_type !== $post->post_type ) {
			return new WP_Error( 'ls_tm_not_found', 'Item not found.', array( 'status' => 404 ) );
		}
		wp_trash_post( $post->ID );
		return new WP_REST_Response( null, 204 );
	}

	private function save_fields( $post_id, $post_type, $request ) {
		if ( LS_TM_Plugin::PROJECT === $post_type ) {
			$this->save_int( $post_id, '_ls_tm_product_owner_id', $request, 'product_owner_id' );
			$this->save_text( $post_id, '_ls_tm_project_contact_address', $request, 'company_address' );
			$this->save_url( $post_id, '_ls_tm_project_contact_website', $request, 'production_website' );
			$this->save_email( $post_id, '_ls_tm_project_contact_email', $request, 'contact_email' );
		}

		if ( LS_TM_Plugin::ISSUE === $post_type ) {
			$this->save_int( $post_id, '_ls_tm_project_id', $request, 'project_id' );
			$this->save_choice( $post_id, '_ls_tm_issue_type', $request, 'issue_type', array( 'bug', 'feature' ) );
			$this->save_choice( $post_id, '_ls_tm_issue_status', $request, 'status', array_keys( LS_TM_Plugin::issue_statuses() ) );
			$this->save_choice( $post_id, '_ls_tm_issue_priority', $request, 'priority', array( 'high', 'medium', 'low' ) );
			if ( null !== $request->get_param( 'estimated_hours' ) ) {
				update_post_meta( $post_id, '_ls_tm_estimated_hours', max( 0, (float) $request->get_param( 'estimated_hours' ) ) );
			}
			$this->save_url( $post_id, '_ls_tm_figma_url', $request, 'figma_url' );
		}

		if ( LS_TM_Plugin::INVOICE === $post_type ) {
			$this->save_int( $post_id, '_ls_tm_project_id', $request, 'project_id' );
			if ( '' === get_post_meta( $post_id, '_ls_tm_hourly_rate', true ) ) {
				update_post_meta( $post_id, '_ls_tm_hourly_rate', $this->plugin->global_hourly_rate() );
			}
			if ( null !== $request->get_param( 'issue_ids' ) ) {
				$result = $this->save_invoice_issues( $post_id, (array) $request->get_param( 'issue_ids' ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			// Set Paid only after its issue snapshot is safely established.
			$this->save_choice( $post_id, '_ls_tm_invoice_status', $request, 'status', array_keys( LS_TM_Plugin::invoice_statuses() ) );
		}

		return true;
	}

	private function save_invoice_issues( $invoice_id, $issue_ids ) {
		if ( 'paid' === get_post_meta( $invoice_id, '_ls_tm_invoice_status', true ) ) {
			return new WP_Error( 'ls_tm_invoice_paid', 'Paid invoices cannot be changed.', array( 'status' => 409 ) );
		}
		$project_id = (int) get_post_meta( $invoice_id, '_ls_tm_project_id', true );
		$issue_ids = array_values( array_unique( array_filter( array_map( 'absint', $issue_ids ) ) ) );
		$previous = array_map( 'absint', (array) get_post_meta( $invoice_id, '_ls_tm_issue_ids', true ) );
		foreach ( $issue_ids as $issue_id ) {
			if ( LS_TM_Plugin::ISSUE !== get_post_type( $issue_id ) || 'completed' !== get_post_meta( $issue_id, '_ls_tm_issue_status', true ) || $project_id !== (int) get_post_meta( $issue_id, '_ls_tm_project_id', true ) ) {
				return new WP_Error( 'ls_tm_invalid_issue', 'Every invoice issue must be completed and belong to the invoice project.', array( 'status' => 400 ) );
			}
			$current_invoice = (int) get_post_meta( $issue_id, '_ls_tm_invoice_id', true );
			if ( $current_invoice && $current_invoice !== $invoice_id ) {
				return new WP_Error( 'ls_tm_issue_invoiced', 'An Issue can belong to only one invoice.', array( 'status' => 409 ) );
			}
		}
		foreach ( array_diff( $previous, $issue_ids ) as $issue_id ) {
			delete_post_meta( $issue_id, '_ls_tm_invoice_id' );
		}
		foreach ( $issue_ids as $issue_id ) {
			update_post_meta( $issue_id, '_ls_tm_invoice_id', $invoice_id );
		}
		update_post_meta( $invoice_id, '_ls_tm_issue_ids', $issue_ids );
		return true;
	}

	private function prepare_item( $post ) {
		$type = $post->post_type;
		$item = array( 'id' => $post->ID, 'type' => $type, 'title' => $post->post_title, 'content' => $post->post_content );
		if ( LS_TM_Plugin::PROJECT === $type ) {
			$item['product_owner_id'] = (int) get_post_meta( $post->ID, '_ls_tm_product_owner_id', true );
			$item['company_address'] = get_post_meta( $post->ID, '_ls_tm_project_contact_address', true );
			$item['production_website'] = get_post_meta( $post->ID, '_ls_tm_project_contact_website', true );
			$item['contact_email'] = get_post_meta( $post->ID, '_ls_tm_project_contact_email', true );
		}
		if ( LS_TM_Plugin::ISSUE === $type ) {
			$item['project_id']      = (int) get_post_meta( $post->ID, '_ls_tm_project_id', true );
			$item['issue_type']     = get_post_meta( $post->ID, '_ls_tm_issue_type', true );
			$item['status']         = get_post_meta( $post->ID, '_ls_tm_issue_status', true );
			$item['priority']       = get_post_meta( $post->ID, '_ls_tm_issue_priority', true );
			$item['estimated_hours'] = (float) get_post_meta( $post->ID, '_ls_tm_estimated_hours', true );
			$item['figma_url']      = get_post_meta( $post->ID, '_ls_tm_figma_url', true );
			$item['invoice_id']     = (int) get_post_meta( $post->ID, '_ls_tm_invoice_id', true );
		}
		if ( LS_TM_Plugin::INVOICE === $type ) {
			$item['project_id'] = (int) get_post_meta( $post->ID, '_ls_tm_project_id', true );
			$item['issue_ids'] = array_map( 'absint', (array) get_post_meta( $post->ID, '_ls_tm_issue_ids', true ) );
			$item['status'] = get_post_meta( $post->ID, '_ls_tm_invoice_status', true );
			$item['hourly_rate'] = (float) get_post_meta( $post->ID, '_ls_tm_hourly_rate', true );
			$item['total_price'] = (float) get_post_meta( $post->ID, '_ls_tm_total_price', true );
			$item['pdf_url'] = wp_get_attachment_url( (int) get_post_meta( $post->ID, '_ls_tm_pdf_id', true ) );
		}

		return $item;
	}

	private function can_access_post( $user_id, $post ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		$project_id = LS_TM_Plugin::PROJECT === $post->post_type ? $post->ID : (int) get_post_meta( $post->ID, '_ls_tm_project_id', true );
		return $project_id && $this->plugin->user_can_access_project( $user_id, $project_id );
	}

	private function assigned_project_ids( $user_id ) {
		return get_posts( array( 'post_type' => LS_TM_Plugin::PROJECT, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => '_ls_tm_product_owner_id', 'meta_value' => $user_id ) );
	}

	private function save_int( $post_id, $meta_key, $request, $parameter ) {
		if ( null !== $request->get_param( $parameter ) ) {
			update_post_meta( $post_id, $meta_key, absint( $request->get_param( $parameter ) ) );
		}
	}

	private function save_text( $post_id, $meta_key, $request, $parameter ) {
		if ( null !== $request->get_param( $parameter ) ) {
			update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $request->get_param( $parameter ) ) );
		}
	}

	private function save_url( $post_id, $meta_key, $request, $parameter ) {
		if ( null !== $request->get_param( $parameter ) ) {
			update_post_meta( $post_id, $meta_key, esc_url_raw( $request->get_param( $parameter ) ) );
		}
	}

	private function save_email( $post_id, $meta_key, $request, $parameter ) {
		if ( null !== $request->get_param( $parameter ) ) {
			update_post_meta( $post_id, $meta_key, sanitize_email( $request->get_param( $parameter ) ) );
		}
	}

	private function save_choice( $post_id, $meta_key, $request, $parameter, $allowed ) {
		$value = $request->get_param( $parameter );
		if ( null !== $value && in_array( $value, $allowed, true ) ) {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	private function singular_label( $post_type ) {
		return str_replace( 'ls_tm_', '', $post_type );
	}
}
