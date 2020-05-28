/**
 * Battery Level
 */

const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;

const {
	wpTeslaAdmin: {
		blocks: {
			battery_level, // eslint-disable-line camelcase
		},
		currentVehicle: {
			batteryLevel,
		}
	}
} = window;

export default registerBlockType( battery_level, {
	title: __( 'Battery Level', 'wp-tesla' ),
	description: __( 'The vehicle\'s battery level.', 'wp-tesla' ),
	category: 'wp-tesla',
	icon: 'lightbulb',
	attributes: {},

	edit: props => {
		const { className } = props;

		return (
			<span className={ className }>
				{ batteryLevel }
			</span>
		);
	},

	save: () => {
		return null;
	}
} );
