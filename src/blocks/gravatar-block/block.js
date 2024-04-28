import './editor.scss';
import { useState, useEffect } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import {
	ToggleControl,
	TextControl,
	SelectControl,
	Button,
	Spinner,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import {
	BlockControls,
} from '@wordpress/block-editor';

import { 
	useSelect,
} from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * GravatarBlock
 *
 * @param {Object} props Props.
 */
const GravatarBlock = ( props ) => {
	const { attributes, setAttributes, clientId } = props;

	// Get block attributes.
	const { gravatarHash, gravatarSize } = attributes;

	// Set input state.
	const [ isAuthorAvatar, setIsAuthorAvatar ] = useState( false );
	const [ email, setEmail ] = useState( '' );
	const [ size, setSize ] = useState( dlxGravatarBlock.avatarSizes[ dlxGravatarBlock.avatarSizes.length - 1 ].value );

	// Set loading state for when a Gravatar request is being made.
	const [ isLoading, setIsLoading ] = useState( gravatarHash.length > 0 );

	// Set the Avatar URL.
	const [ avatarUrl, setAvatarUrl ] = useState( '' );

	// Get the post author ID.
	const authorId = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostAttribute( 'author' ),
		[]
	);

	const blockProps = useBlockProps(
		{
			className: 'dlx-gravatar-block',
		}
	);

	/**
	 * Retrieve avatar onload or when the author id changes.
	 */
	useEffect( () => {
		if ( gravatarHash.length > 0 ) {
			getGravatar( gravatarHash );
		}
	}, [] );

	/**
	 * Get the Gravatar based on the email or ID.
	 *
	 * @param {string} emailHash The email hash.
	 * 
	 * @returns {void}
	 */
	const getGravatar = ( emailHash = '' ) => {
		// Set the loading state to true.
		setIsLoading( true );

		let maybeIdorEmail = authorId;
		if ( ! isAuthorAvatar ) {
			maybeIdorEmail = email;
			if ( emailHash.length > 0 ) {
				maybeIdorEmail = emailHash;
			}
		}

		// Make the request to get the Gravatar.
		apiFetch( {
			path: `dlx-gravatar-block/v1/gravatar/${ encodeURIComponent( maybeIdorEmail ) }`,
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': dlxGravatarBlock.nonce,
			},
		} ).then( ( response ) => {
			const isError = Object.keys( response ).includes( 'code' );
			if ( isError ) {
				// todo - display error.
				return;
			} else {
				const maybeHash = response.emailHash;
				// If there's a hash, update it. Else, ignore.
				if ( maybeHash.length > 0 ) {
					setAttributes( {
						gravatarHash: maybeHash,
					} );
				}

				// Get avatar based on size.
				const avatarUrls = response.avatarUrls;
				const avatarUrl = avatarUrls[ size ];
				setAvatarUrl( avatarUrl );
			}
		} ).catch( ( error ) => {
			console.error( error );
		} ).finally( () => {
			// Set the loading state to false.
			setIsLoading( false );
		} );
	}

	/**
	 * Whether the Get Avatar button should be disabled.
	 *
	 * @returns {boolean} Whether the button should be disabled.
	 */
	const isButtonDisabled = () => {
		if ( isAuthorAvatar ) {
			return false;
		}
		const newEmail = email.trim();
		if ( newEmail.length === 0 ) {
			return true;
		}
		return false;
	}

	if ( isLoading ) {
		return (
			<div { ...blockProps }>
				<Spinner /> { __( 'Loading Avatar...', 'dlx-gravatar-block' ) }
			</div>
		);
	}

	// Begin toolbar.
	const toolbar = (
		<BlockControls>
			<ToolbarGroup>
				<ToolbarButton
					icon="edit"
					title={ __( 'Change', 'dlx-gravatar-block' ) }
					onClick={ () => {
						setAvatarUrl( '' );
						setEmail( '' );
					} }
				>
					{ __( 'Change', 'dlx-gravatar-block' ) }
				</ToolbarButton>
			</ToolbarGroup>
		</BlockControls>
	);

	// If there's an avatar URL, display it.
	if ( avatarUrl ) {
		return (
			<div { ...blockProps }>
				{ toolbar }
				<img
					src={ avatarUrl }
					alt={ __( 'Gravatar', 'dlx-gravatar-block' ) }
					width={ gravatarSize || size }
					height={ gravatarSize || size }
				/>
			</div>
		);
	}


	return (
		<div { ...blockProps }>
			<ToggleControl
				label={ __( 'Use author avatar', 'dlx-gravatar-block' ) }
				checked={ isAuthorAvatar }
				onChange={ ( value ) => {
					setIsAuthorAvatar( value );
				} }
				help={ __( 'Use the post author\'s Gravatar instead of a custom Gravatar.', 'dlx-gravatar-block' )}
			/>
			{ ! isAuthorAvatar && (
				<>
					<TextControl
						label={ __( 'Username, ID, or email', 'dlx-gravatar-block' ) }
						value={ email }
						onChange={ ( value ) => {
							setEmail( value );
						} }
					/>
				</>
			) }
			<SelectControl
				label={ __( 'Avatar Size', 'dlx-gravatar-block' ) }
				value={ size }
				options={ dlxGravatarBlock.avatarSizes }
				onChange={ ( value ) => {
					setSize( value );
				} }
			/>
			<Button
				variant="primary"
				onClick={() => {
					getGravatar();
				} }
				disabled={ isButtonDisabled() }
			>
				{ __( 'Get Avatar', 'dlx-gravatar-block' ) }
			</Button>
		</div>
	);
	
};

export default GravatarBlock;