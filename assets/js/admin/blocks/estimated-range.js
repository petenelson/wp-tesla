/**
 * Estimated Range
 */

const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;

const {
	wpTeslaAdmin: {
		blocks: {
			estimated_range, // eslint-disable-line camelcase
		},
		currentVehicle: {
			estimatedRange,
		}
	}
} = window;

export default registerBlockType( estimated_range, {
	title: __( 'Estimated Range', 'wp-tesla' ),
	description: __( 'The vehicle\'s estimated range.', 'wp-tesla' ),
	category: 'wp-tesla',
	attributes: {},

	edit: props => {
		const { className } = props;

		return (
			<span className={ className }>
				{ estimatedRange }
			</span>
		);
	},

	save: () => {
		return null;
	}
} );
