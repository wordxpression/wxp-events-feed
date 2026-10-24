import { registerBlockType } from '@wordpress/blocks';

import meta from '../events-feed/block.json';
import Edit from './edit';

// The block is rendered on the server (see src/Block/FeedBlock.php), so nothing is saved in the post.
registerBlockType( meta.name, { edit: Edit, save: () => null } );
