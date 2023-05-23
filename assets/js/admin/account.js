window.addEventListener( 'DOMContentLoaded', () => {
	const loginToAccount = document.getElementById( 'wp-tesla-login-to-account' );
	const loginAuthUrl = document.getElementById( 'wp-tesla-login-auth-url' );
	const connectToVehicles = document.getElementById( 'wp-tesla-connect-to-vehicles' );

	if ( loginToAccount && connectToVehicles ) {
		loginToAccount.addEventListener( 'click', () => {
			loginToAccount.disabled = true;
		} );

		loginAuthUrl.addEventListener( 'keyup', () => {
			if ( '' !== loginAuthUrl.value ) {
				connectToVehicles.disabled = false;
			}
		} );
	}
} );
