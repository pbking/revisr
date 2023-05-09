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
	register_rest_route( 'revisr/v1', '/getPullRequestUrl', array(
		'methods' => 'GET',
		'callback' => 'api_get_pull_request_url',
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
	register_rest_route( 'revisr/v1', '/branch', array(
		'methods' => 'POST',
		'callback' => 'api_create_branch',
	) );
	register_rest_route( 'revisr/v1', '/commit', array(
		'methods' => 'POST',
		'callback' => 'api_commit_changes',
	) );
	register_rest_route( 'revisr/v1', '/push', array(
		'methods' => 'POST',
		'callback' => 'api_push_changes',
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

function api_get_pull_request_url ( WP_REST_Request $request ) {

	$git = new Revisr_Git_API();
	$branch = $git->current_branch()->output;
	
	$response = $git->run( 'ls-remote', array( '--get-url' ) );

	if ( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}

	if ( is_array( $response->output ) ) {
		$repo_url = preg_replace('/.git$/', '', $response->output[0]);
		$PR_url =  $repo_url . '/compare/' . $branch . '?expand=1';
		return new WP_REST_Response( array(
			'status' => 'OK',
			'url' => $PR_url,
		) );
	}

	return new WP_REST_Response( array(
		'status' => 'FAILURE',
		'message' => 'There is no remote setup for this repository.',
	) );

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

function api_create_branch ( WP_REST_Request $request ) {
	$branch = $request->get_param( 'branch' );
	$git = new Revisr_Git_API();
	$response = $git->create_and_switch_to_branch($branch);
	if( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
}

function api_commit_changes ( WP_REST_Request $request ) {
	$comment = $request->get_param( 'comment' );
	$git = new Revisr_Git_API();
	$response = $git->stage_and_commit( $comment );
	if( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
}

function api_push_changes ( WP_REST_Request $request ) {
	$git = new Revisr_Git_API();
	$response = $git->push();
	if( ! $response->success ) {
		return new WP_REST_Response( array(
			'status' => 'FAILURE',
			'message' => $response->output,
		) );
	}
	return api_get_repo_info();
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

	/**
	 * Creates a new branch and switch to it.
	 * @access public
	 * @param  string $branch The name of the branch to create.
	 */
	public function create_and_switch_to_branch( $branch ) {
		return $this->run( 'checkout', array( '-b', $branch ) );
	}

	/**
	 * Stage and commit files to the local repository.
	 * @access public
	 * @param  string $message The message to use with the commit.
	 */
	public function stage_and_commit( $message ) {

		$git_username = $this->revisr_git->get_config( 'user', 'name' );
		$git_email =  $this->revisr_git->get_config( 'user', 'email' );

		if ( ! $git_username || ! $git_email ) {
			$error = new Revisr_Git_API_Callback;
			$error->output = 'Please set your Git username and email address before comitting.';
			return $error;
		}

		$add_result = $this->run( 'add', array( '-A' ) );

		if( ! $add_result->success ) {
			return $add_result;
		}

		$author        = "$git_username <$git_email>";
		$commit_result = $this->run( 'commit', array( '-m', $message, '--author', $author ) );

		return $commit_result;
	}

	/**
	 * Pushes changes to the remote repository.
	 * @access public
	 */
	public function push() {
		// Get stored remote
		$check_remote_result = $this->run( 'ls-remote', array( '--get-url' ) );
		if ( $check_remote_result->success && is_array( $check_remote_result->output ) ) {
			$remote = $check_remote_result->output[0];
		} else {
			$remote = '';
		}

		// Get stored credentials
		$git_password = $this->revisr_git->get_config( 'user', 'password' );
		$git_username = $this->revisr_git->get_config( 'user', 'name' );
		$git_credentials = "";
		if ( $git_password ) {
			$git_credentials = $git_username . ":" . $git_password . "@";
		}

		$remote = str_replace( 'https://', 'https://' . $git_credentials, $remote );

		return $this->run( 'push', array( $remote, 'HEAD', '--quiet' ) );
	}

}