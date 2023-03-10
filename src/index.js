import { registerPlugin } from '@wordpress/plugins';
import { Component } from '@wordpress/element';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-site';
import { blockDefault } from '@wordpress/icons';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';
import { Button, SelectControl, MenuGroup, MenuItem, TextControl } from '@wordpress/components';
import { store as noticesStore } from '@wordpress/notices';
import { useDispatch } from '@wordpress/data';


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

	openSaveWizard() {
		alert('open Save Wizard');
	}

	getBranchOptions( branches ) {
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
		let { info, branches, switchBranch } = this.props;

		let statusMarkup =  <p>No Repository Setup</p>;


		if( info.status === "OK" ) {

			let changesMarkup = info.count_untracked > 0 ? 
				<>
					<p>You have { info.count_untracked } changes.</p> 
					<Button variant="primary" label={ __('Commit Changes...') } onClick={ this.openSaveWizard }>
						{ __('Commit Changes...') }
					</Button>
				</>
				: <p>No changes to commit.</p>;

			statusMarkup = <>
				<SelectControl
					label={ __( 'Current branch' ) }
					options={ this.getBranchOptions( branches.branches ) }
					value={ info.branch }
					onChange={ switchBranch }
					labelPosition="top"
				/>
	
				{ changesMarkup }

				<pre>{JSON.stringify(info, null, ' ')}</pre>
				<pre>{JSON.stringify(branches, null, ' ')}</pre>
			</>; 

		}

		return (
			<Fragment>

				<PluginSidebarMoreMenuItem target="revisr-sidebar" icon={ blockDefault }>
					{ __( 'Revisr' ) }
				</PluginSidebarMoreMenuItem>

				<PluginSidebar name="revisr-sidebar" icon={ blockDefault } title={ __( 'Revisr' ) }>
					{ statusMarkup }
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
		};
  	} ),
	withDispatch( function( dispatch  ) {
		return {
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
			}
		}
	} ),
] )( RevisrPluginComponent )

registerPlugin( 'plugin-sidebar-expanded-test', { 
	render: ()=> { return ( <RevisrPluginComponentComposed/> ) } 
} );