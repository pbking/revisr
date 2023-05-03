<?php
/**
 * Revisr API for Frontend Client
 * @package   	Revisr
 * @license   	GPLv3
 * @link      	https://revisr.io
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the routes the frontend client will use.
 */
 add_action( 'rest_api_init', function () {
	register_rest_route( 'revisr/v1', '/info', array(
		'methods' => 'GET',
		'callback' => 'api_get_repo_info',
	) );
	register_rest_route( 'revisr/v1', '/branches', array(
		'methods' => 'GET',
		'callback' => 'api_get_remote_branches',
	) );
	register_rest_route( 'revisr/v1', '/checkout', array(
		'methods' => 'POST',
		'callback' => 'api_checkout',
	) );
	register_rest_route( 'revisr/v1', '/pull', array(
		'methods' => 'POST',
		'callback' => 'api_pull_changes',
	) );
	register_rest_route( 'revisr/v1', '/revert', array(
		'methods' => 'POST',
		'callback' => 'api_revert_changes',
	) );
	register_rest_route( 'revisr/v1', '/status', array(
		'methods' => 'GET',
		'callback' => 'api_get_status',
	) );
} );

function api_get_status( WP_REST_Request $request = null ) {
	$git = new Revisr_Git_API();
	if($git->is_repo) {
		$response = $git->status();
		if ( ! $response->success ) {
			return new WP_REST_Response( array(
				'status' => 'FAILURE',
				'message' => $response->output,
			) );
		}
		return new WP_REST_Response( array(
			'status' => 'OK',
			'files' => $response->output,
		) );
	} else {

		return new WP_REST_Response( array(
        		'status' => 'NO_REPOSITORY'
      		) );

	}
}

function api_revert_changes( WP_REST_Request $request ) {
	$git = new Revisr_Git_API();
	$response = $git->revert('HEAD');
	if ( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
}

function api_pull_changes( WP_REST_Request $request ) {
	$git = new Revisr_Git_API();
	$response = $git->pull();
	if ( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
}

function api_checkout( WP_REST_Request $request ) {
	$git = new Revisr_Git_API();
	$branch = $request->get_param( 'branch' );
	$response = $git->checkout( $branch );
	if ( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
}

function api_get_remote_branches ( WP_REST_Request $request ) {
	$git = new Revisr_Git_API();
	if($git->is_repo) {
		$response = $git->get_remote_branches();

		if ( $response->success ) {
			return new WP_REST_Response( array(
				'status' => 'OK',
				'branches' => $response->output,
	      		) );
		}
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
      		) );

	} else {
		return new WP_REST_Response( array(
			'status' => 'NO_REPOSITORY'
      		) );
	}
}

function api_get_repo_info ( WP_REST_Request $request = null ) {
	$git = new Revisr_Git_API();
	if($git->is_repo) {

		// Make sure we're up-to-date
		$git->fetch();

		// Get the info we're interested in
		$branch = $git->current_branch()->output;
		$count_unpushed = $git->count_unpushed()->output;
		$count_untracked = $git->count_untracked()->output;
		$count_unpulled = $git->count_unpulled()->output;

		// Put it all together and return it
		return new WP_REST_Response( array(
        		'status' => 'OK',
        		'branch' => $branch,
			'count_unpulled' => $count_unpulled,
			'count_unpushed' => $count_unpushed,
			'count_untracked' => $count_untracked,
      		) );

	} else {

		return new WP_REST_Response( array(
        		'status' => 'NO_REPOSITORY'
      		) );

	}
}


class Revisr_Git_API_Callback {
	public $success = false;
	public $output;
}



/**
 * This does the same thing as the Revisr_Git class, but is used for the API.
 */

class Revisr_Git_API {

	private $revisr_git;	
	public $is_repo;

	public function __construct() {
		$this->revisr_git = new Revisr_Git();
		$this->is_repo = $this->revisr_git->is_repo;
	}

	public function fetch() {
		$this->revisr_git->fetch();
	}

	/**
	 * Runs a Git command and fires the given callback.
	 * This is why we had to have an api-replacement...
	 * @access 	public
	 * @param 	string 			$command 	The command to use.
	 * @param 	array 			$args 		Arguements provided by user.
	 * @param 	string 			$callback 	The callback to use.
	 * @param 	string|array 	$info 		Additional info to pass to the callback
	 */
	public function run( $command, $args ) {

		// Setup the command for safe usage.
		$safe_path 		= Revisr_Admin::escapeshellarg( $this->revisr_git->git_path );
		$safe_cmd 		= Revisr_Admin::escapeshellarg( $command );
		$safe_args 		= join( ' ', array_map( array( 'Revisr_Admin', 'escapeshellarg' ), $args ) );

		// Allow for customizing the git work-tree and git-dir paths.
		$git_dir 	= $this->revisr_git->git_dir;
		$git_dir 	= Revisr_Admin::escapeshellarg( "--git-dir=$git_dir" );
		$work_tree 	= $this->revisr_git->work_tree;
		$work_tree 	= Revisr_Admin::escapeshellarg( "--work-tree=$work_tree" );

		if( "clone" == $command ) {
			exec( "$safe_path $safe_cmd $safe_args 2>&1", $output, $return_code );
		} else {
			chdir( $this->revisr_git->work_tree );
			exec( "$safe_path $git_dir $work_tree $safe_cmd $safe_args 2>&1", $output, $return_code );
			chdir( $this->revisr_git->current_dir );
		}


		// Process the response.
		$response = new Revisr_Git_API_Callback();
		$response->output = $output;
		$response->success = $return_code === 0;

		return $response;
	}

	/**
	 * Returns the current branch.
	 * @access public
	 */
	public function current_branch() {
		$response = $this->run( 'rev-parse', array( '--abbrev-ref', 'HEAD' ) );
		if ( $response->success != false && is_array( $response->output )) {
			$response->output = $response->output[0];
		}
		return $response;
	}

	/**
	 * Returns the number of unpushed commits.
	 * @access public
	 */
	public function count_unpushed() {
		$response = $this->run( 'log', array( $this->revisr_git->branch, '--not', '--remotes', '--oneline' ) );
		if ( $response->success ) {
			$response->output = count( $response->output );
		}
		return $response;
	}

	/**
	 * Returns the number of untracked/modified files.
	 * @access public
	 */
	public function count_untracked() {
		$response = $this->run( 'status', array( '--short', '--untracked-files=all' ) );
		if ( $response->success ) {
			$response->output = count( $response->output );
		}
		return $response;
	}

	/**
	 * Returns the number of unpulled commits.
	 * @access public
	 */
	public function count_unpulled() {
		$response = $this->run( 'log', array( $this->revisr_git->branch . '..' . $this->revisr_git->remote . '/' . $this->revisr_git->branch, '--pretty=oneline' ) );
		if ( $response->success ) {
			$response->output = count( $response->output );
		}
		return $response;
	}

	/**
	 * Returns available branches on the remote repository.
	 * @access public
	 */
	public function get_remote_branches() {
		return $this->run( 'branch', ['-r'] );
	}

	/**
	 * Checks out an existing branch.
	 * @access public
	 * @param string $branch The branch to checkout.
	 */
	public function checkout( $branch ) {
		return $this->run( 'checkout', array( $branch, '-q' ) );
	}

	/**
	 * Reverts the working directory to a specified commit.
	 * @access public
	 * @param  string $commit The hash of the commit to revert to.
	 */
	public function revert( $commit ) {
		$this->reset( '--hard', 'HEAD', true );
		$this->reset( '--hard', $commit );
		$this->reset( '--soft', 'HEAD@{1}' );

		//TODO: This doesn't accurately reflect the success of the revert.
		$response = new Revisr_Git_API_Callback();
		$response->success = true;
		return $response;
	}

	/**
	 * Resets the working directory.
	 * @access public
	 * @param  string 	$mode	The mode to use for the reset (hard, soft, etc.).
	 * @param  string 	$path 	The path to apply the reset to.
	 * @param  boolean 	$clean 	Whether to remove any untracked files.
	 * @return boolean
	 */
	public function reset( $mode = '--hard', $path = 'HEAD', $clean = false ) {

		if ( $this->run( 'reset', array( $mode, $path ) )->success ) {

			if ( true === $clean ) {

				if ( ! $this->run( 'clean', array( '-f', '-d' ) )->success ) {
					return false;
				}

			}

			return true;
		}

		return false;
	}

	/**
	 * Pulls changes from the remote repository.
	 * @access public
	 * @param  array $commits The commits we're pulling (used in callback).
	 */
	public function pull( $commits = array() ) {
		return $this->run( 'pull', array( '-Xtheirs', '--quiet', $this->revisr_git->remote, $this->revisr_git->branch ), null, $commits );
	}

	/**
	 * Returns the current status.
	 * @access public
	 * @param  array $args Defaults to "--short".
	 */
	public function status() {
		return $this->run( 'status', array( '--short', '--untracked-files=all' ) );
	}


}
