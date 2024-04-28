import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Block from './block';

registerBlockType( metadata, {
	edit: Block,
	save() {
		return null;
	}
} );
