/**
 * Internal dependencies
 */
import './style.scss';
import { BaseHeader } from './shared';

export const EmbedHeader = ( {
	sections,
	query,
}: {
	sections: string[];
	query: Record< string, string >;
} ) => {
	return (
		<BaseHeader isEmbedded={ true } query={ query } sections={ sections } />
	);
};
