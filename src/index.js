import { registerPlugin } from '@wordpress/plugins';
import { Component } from '@wordpress/element';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-site';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';
import { Button, SelectControl, RadioControl, ToggleControl, MenuGroup, MenuItem, TextareaControl, TextControl } from '@wordpress/components';
import { SVG, Path } from '@wordpress/primitives';
import {
	__experimentalNavigatorProvider as NavigatorProvider,
	__experimentalNavigatorScreen as NavigatorScreen,
	__experimentalNavigatorButton as NavigatorButton,
	__experimentalNavigatorToParentButton as NavigatorToParentButton,
	__experimentalUseNavigator as useNavigator,
} from '@wordpress/components';

const revisrIcon = ( <SVG version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="245.8 381.1 81.9 89.5">
	<Path d="M295.2,387.2c-5.1,5.1-5.1,13.3,0,18.3c3.8,3.8,9.3,4.7,13.9,2.9l7.2-7.2c1.8-4.7,0.9-10.2-2.9-13.9 C308.5,382.1,300.3,382.1,295.2,387.2z M309.7,401.6c-2.9,2.9-7.6,2.9-10.6,0c-2.9-2.9-2.9-7.6,0-10.6c2.9-2.9,7.6-2.9,10.6,0 C312.6,394,312.6,398.7,309.7,401.6z"/>
	<Path d="M268.1,454c-13.2-10.1-16.1-29-6.4-42.6c4-5.6,9.4-9.4,15.4-11.4l-2-10.2c-8.5,2.5-16.2,7.7-21.7,15.5 c-12.9,18.2-8.9,43.5,8.8,57l-5.6,8.3l25.9-1.2l-8.6-23.6L268.1,454z"/>
	<Path d="M318.3,403.3c1.1-2.1,1.7-4.5,1.7-7c0-8.4-6.8-15.2-15.2-15.2s-15.2,6.8-15.2,15.2s6.8,15.2,15.2,15.2 c2.1,0,4.1-0.4,5.9-1.2c8.4,10.6,9.2,25.8,1,37.2c-3.9,5.6-9.4,9.4-15.4,11.4l2,10.2c8.5-2.5,16.2-7.7,21.7-15.5 C331.2,438.1,329.9,417.4,318.3,403.3z M304.8,403.3c-3.8,0-6.9-3.1-6.9-6.9s3.1-6.9,6.9-6.9s6.9,3.1,6.9,6.9 S308.7,403.3,304.8,403.3z"/>
</SVG>);

const actions = {
	getInfo() {
		return {
			"type": "GET_INFO",
			"path": "/revisr/v1/info"
		}
	},
	setInfo( status ) {
		return {
			"type": "SET_INFO",
			"status": status
		}
	},
	getBranches() {
		return {
			"type": "GET_BRANCHES",
			"path": "/revisr/v1/branches"
		}
	},
	setBranches( branches ) {
		return {
			"type": "SET_BRANCHES",
			"branches": branches
		}
	},
	getCommitDetails() {
		return {
			"type": "GET_COMMIT_DETAILS",
		}
	},
	setCommitDetails( commitDetails ) {
		return {
			"type": "SET_COMMIT_DETAILS",
			"commitDetails": commitDetails
		}
	}
};

const DEFAULT_STATE = {
	"status": "NO_REPOSITORY",
}

const store = createReduxStore( 'revisr/store', {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'SET_INFO':
				return {
					...state,
					status: action.status
				}
			case 'SET_BRANCHES':
				return {
					...state,
					branches: action.branches
				}
			case 'SET_COMMIT_DETAILS':
				return {
					...state,
					commitDetails: action.commitDetails
				}
			default:
				return state;
		}
	},
	actions,
	selectors: {
		getInfo( state ) {
			const { status } = state;
			return status;
		},
		getBranches( state ) {
			const { branches } = state;
			return branches;
		},
		getCommitDetails( state ) {
			const { commitDetails } = state;
			return commitDetails || {
				commitNewBranch: false,
				openPullRequestForNewBranch: true,
				branchName: '',
				commitMessage: '' 
			};
		}
	},

	controls: {
		GET_INFO( action ) {
			return apiFetch( { path: action.path } );
		},
		GET_BRANCHES( action ) {
			return apiFetch( { path: action.path } );
		},
	},

	resolvers: {
		*getInfo() {
    			const status = yield actions.getInfo();
    			return actions.setInfo( status );
		},
		*getBranches() {
			const branches = yield actions.getBranches();
			return actions.setBranches( branches );
		},
	},
});

register(store);

class RevisrPluginComponent extends Component {

	getBranchOptions( branches ) {
		if (!branches ) return [];
		return branches.map((branch)=>{
			branch = branch.replace('origin/HEAD -> ', '');
			branch = branch.replace('origin/', '');
			branch = branch.trim();
			return {
				label: branch,
				value: branch
			}
		});
	}

	render() {
		let { 
			info, 
			branches, 
			switchBranch, 
			pullChangesFromRemote, 
			revertChanges,
			commitDetails,
			setCommitDetails
		} = this.props;

		const onSubmitSrc = ( event ) => {
			event.preventDefault();

			console.log('Let us commit!', event, commitDetails );
		};

		let statusMarkup =  <p>No Repository Setup</p>;

		if( info.status === "OK" ) {

			let pullMarkup = info.count_unpulled > 0 ? 
				<>
					<Button variant="secondary" label={ __('Pull Changes from Remote') } onClick={ pullChangesFromRemote }>
						{ sprintf( __('Pull %s change(s) from remote' ), info.count_unpulled ) }
					</Button>
				</>
				: '';


			let changesMarkup = info.count_untracked > 0 ? 
				<>
					<p>You have { info.count_untracked } changes.</p> 
					<pre>LIST OF CHANGED FILES GOES HERE</pre>
					<Button variant="secondary" label={ __('Revert these changes') } onClick={ revertChanges }>
						{ __('Revert these changes') }
					</Button>
					<NavigatorButton path="/commit" variant="primary" >
						{ __('Commit Changes...') }
      					</NavigatorButton>
				</>
				: <p>No changes to commit.</p>;

			statusMarkup = <>
				<p>Current branch: { info.branch }</p>
				<NavigatorButton variant="secondary" path="/switchBranch" >
					{ __('Change branch ...') }
				</NavigatorButton>
	
				{ pullMarkup }
				{ changesMarkup }

				<pre>{JSON.stringify(info, null, ' ')}</pre>
			</>; 

		}

		const commitFormMarkup = <>
			<form onSubmit={ onSubmitSrc } >
				<ToggleControl
					label={ __( 'Do you want to put these changes into a new branch?' ) }
					checked={ commitDetails.commitNewBranch }
					onChange={ ( value ) =>
						setCommitDetails( { ...commitDetails,
							commitNewBranch: value
						} )
					}
				/>

				{ commitDetails.commitNewBranch && (<>
				<ToggleControl
					label={ __( 'Do you want to open a Pull Request for this new branch?' ) }
					checked={ commitDetails.openPullRequestForNewBranch }
					onChange={ ( value ) =>
						setCommitDetails( { ...commitDetails,
							openPullRequestForNewBranch: value
						} )	
					}
				/>
				<TextControl label="branch" labelposition="top" placeholder="add/theme-name" required
					value={ commitDetails.branchName }
					onChange={ ( value ) =>	
						setCommitDetails( { ...commitDetails,
							branchName: value
						} )
					}
				/>
				</>)}

				<TextareaControl label="Describe the changes you are making" labelposition="top" required
					value={ commitDetails.commitMessage }
					onChange={ ( value ) =>	
						setCommitDetails( { ...commitDetails,
							commitMessage: value
						} )
					}
				/>

				<Button type="submit" variant="primary">{ __('Commit changes') } </Button>

			</form>
		</>;

		const switchBranchMarkup = <>
			<SelectControl
				label={ __( 'Current branch' ) }
				options={ this.getBranchOptions( branches?.branches ) }
				value={ info.branch }
				onChange={ switchBranch }
				labelPosition="top"
				required
			/>
		</>;

		return (
			<Fragment>

				<PluginSidebarMoreMenuItem target="revisr-sidebar" icon={ revisrIcon }>
					{ __( 'Revisr' ) }
				</PluginSidebarMoreMenuItem>

				<PluginSidebar name="revisr-sidebar" icon={ revisrIcon } title={ __( 'Revisr' ) }>

				<NavigatorProvider initialPath="/">
					<NavigatorScreen path="/">
						{ statusMarkup }
					</NavigatorScreen>
					<NavigatorScreen path="/switchBranch">
						{ switchBranchMarkup }
					</NavigatorScreen>
					<NavigatorScreen path="/commit">
						{ commitFormMarkup }
					</NavigatorScreen>
	
				</NavigatorProvider>

				</PluginSidebar>
			</Fragment>
		)
	}
}

const RevisrPluginComponentComposed = compose( [
  	withSelect( ( select ) => {
		return {
			info: select( 'revisr/store' ).getInfo(),
			branches: select( 'revisr/store' ).getBranches(),
			commitDetails: select( 'revisr/store' ).getCommitDetails(),
		};
  	} ),
	withDispatch( function( dispatch  ) {
		return {
			setCommitDetails: (commitDetails)=>{
				dispatch( 'revisr/store' ).setCommitDetails(commitDetails)
			},
			switchBranch: function( branch) {
				apiFetch ( { 
						path: "/revisr/v1/checkout", 
						method: "POST",
						data: { branch: branch } 
					} )
					.then( ( response ) => {
						if(response.status === 'OK') {
							dispatch( 'revisr/store' ).setInfo( response );
						} else {
							alert('something went wrong switching branches');
						}
					} );
			},
			pullChangesFromRemote: function() {
				apiFetch ( { 
						path: "/revisr/v1/pull", 
						method: "POST"
					} )
					.then( ( response ) => {
						if(response.status === 'OK') {
							dispatch( 'revisr/store' ).setInfo( response );
						} else {
							alert('something went wrong pulling changes');
						}
					} );
			},
			revertChanges: function() {
				apiFetch ( { 
						path: "/revisr/v1/revert", 
						method: "POST"
					} )
					.then( ( response ) => {
						if(response.status === 'OK') {
							dispatch( 'revisr/store' ).setInfo( response );
						} else {
							alert('something went wrong pulling changes');
						}
					} );
			}
		}
	} ),
] )( RevisrPluginComponent )

registerPlugin( 'plugin-sidebar-expanded-test', { 
	render: ()=> { return ( <RevisrPluginComponentComposed/> ) } 
} );