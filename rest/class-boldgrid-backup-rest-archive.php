<?php
/**
 * File: class-boldgrid-backup-rest-archive.php
 *
 * @link       https://www.boldgrid.com
 * @since      X.X.X
 *
 * @package    Boldgrid_Backup
 * @copyright  BoldGrid
 * @version    $Id$
 * @author     BoldGrid <support@boldgrid.com>
 */

/**
 * Class: Boldgrid_Backup_Rest_Archive
 *
 * REST endpoints to perform simple archive manipulation.
 *
 * @since X.X.X
 */
class Boldgrid_Backup_Rest_Archive extends Boldgrid_Backup_Rest_Controller {

	/**
	 * Resource name.
	 *
	 * @since  X.X.X
	 * @access private
	 * @var    string
	 */
	protected $resource = 'archives';

	/**
	 * Register all routes.
	 *
	 * @since X.X.X
	 */
	public function register_routes() {
		$this->register_creation();
		$this->register_restore();
		$this->register_list();
	}

	/**
	 * Register the route for creating an archive.
	 *
	 * @since X.X.X
	 */
	public function register_creation() {
		register_rest_route( $this->namespace, '/' . $this->resource, [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			'schema' => [ $this, 'get_schema' ],
		] );
	}

	/**
	 * Register the route for restoring a backup.
	 *
	 * @since X.X.X
	 */
	public function register_restore() {
		register_rest_route( $this->namespace, '/' . $this->resource, [
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'restore' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'url' => [
						'required'            => false,
						'description'         => esc_html__( 'Route URL to restore.', 'boldgrid-backup' ),
						'type'                => 'string',
						'sanitation_callback' => function ( $field ) {
							return esc_url_raw( $field );
						},
					],
					'id'  => [
						'required'            => false,
						'description'         => esc_html__( 'Backup id to restore.', 'boldgrid-backup' ),
						'type'                => 'int',
						'sanitation_callback' => function ( $field ) {
							return (int) $field;
						},
					],
				],
			],
			'schema' => [ $this, 'get_schema' ],
		] );
	}

	/**
	 * Register the route for viewing a list of backups.
	 *
	 * @since X.X.X
	 */
	public function register_list() {
		register_rest_route( $this->namespace, '/' . $this->resource, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			'schema' => [ $this, 'get_schema' ],
		] );
	}

	/**
	 * Get our sample schema for an archive.
	 *
	 * @since X.X.X
	 *
	 * @return array Schema Format.
	 */
	public function get_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->resource,
			'type'       => 'object',
			'properties' => [
				'id'            => [
					'context'     => [ 'view' ],
					'description' => esc_html__( 'Unique identifier for the object.', 'boldgrid-backup' ),
					'type'        => 'string',
				],
				'title'         => [
					'context'     => [ 'view' ],
					'description' => esc_html__( 'Name of the archive.', 'boldgrid-backup' ),
					'type'        => 'string',
				],
				'description'   => [
					'context'     => [ 'view' ],
					'description' => esc_html__( 'Description of the archive.', 'boldgrid-backup' ),
					'type'        => 'array',
				],
				'creation_date' => [
					'context'     => [ 'view' ],
					'description' => esc_html__( 'Date the archive was created.', 'boldgrid-backup' ),
					'type'        => 'string',
				],
			],
		];

		return $schema;
	}

	/**
	 * Prepare the item for the REST response.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return $this->filter_schema_properties( $item );
	}

	/**
	 * Create a new archive.
	 *
	 * Example call to trigger this endpoint:
	 * jQuery.post( 'https://domain.com/wp-json/bgbkup/v1/archives' );
	 *
	 * @since X.X.X
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return array                   Job Resource.
	 */
	public function create_item( $request ) {
		// Initialize a new task.
		$task = new Boldgrid_Backup_Admin_Task();
		$task->init( [ 'type' => 'backup' ] );
		$task->update();

		// Trigger our backup.
		$nopriv = new Boldgrid_Backup_Admin_Nopriv();
		$nopriv->do_backup( [ 'task_id' => $task->get_id() ] );

		return new WP_REST_Response( $task->get(), 200 );
	}

	/**
	 * Get all archives for a WordPress.
	 *
	 * Example call:
	 * jQuery.get( 'https://domain.com/wp-json/bgbkup/v1/archives/' );
	 *
	 * @since X.X.X
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return array                   A collection of archive resources.
	 */
	public function get_items( $request ) {
		// Init our list of raw backup data.
		$core = apply_filters( 'boldgrid_backup_get_core', null );
		$core->archives_all->init();

		// Init our list of backups to return.
		$backups = [];

		foreach ( $core->archives_all->all as $backup ) {
			$archive = Boldgrid\Backup\Archive\Factory::get_by_filename( $backup['filename'] );

			$backups[] = [
				'id'            => $archive->get_id(),
				'title'         => $archive->get_attribute( 'title' ),
				'description'   => $archive->get_attribute( 'description' ),
				'creation_date' => date( 'c', $archive->timestamp ),
			];
		}

		foreach ( $backups as &$backup ) {
			$backup = $this->prepare_item_for_response( $backup, $request );
		}

		return $backups;
	}

	/**
	 * Restore an archive.
	 *
	 * Example call:
	 * jQuery.ajax({
	 *     url: 'https://domain.com/wp-json/bgbkup/v1/archives/?url=' + encodeURIComponent( <URL> ),
	 *     type: 'put'
	 * });
	 *
	 * @since X.X.X
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return array                   Job Resource.
	 */
	public function restore( $request ) {
		$id  = $request->get_param( 'id' );
		$url = $request->get_param( 'url' );

		// Initialize a new task.
		$task = new Boldgrid_Backup_Admin_Task();
		$task->init( [ 'type' => 'restore' ] );

		if ( ! empty( $url ) || ! empty( $id ) ) {
			// Update our task with either url or backup id.
			if ( ! empty( $url ) ) {
				$task->update_data( 'url', $url );
			} else {
				$task->update_data( 'backup_id', $id );
			}

			// Trigger our backup.
			$nopriv = new Boldgrid_Backup_Admin_Nopriv();
			$nopriv->do_restore( [ 'task_id' => $task->get_id() ] );
		} else { // phpcs:ignore
			// todo Add an error here.
		}

		return new WP_REST_Response( $task->get(), 200 );
	}
}
