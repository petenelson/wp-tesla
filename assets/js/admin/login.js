( window => {

	const form = document.getElementById( 'wp-tesla-login-form' );

	/**
	 * Handles the login submit button.
	 *
	 * @return {object} The event.
	 */
	const handleSubmit = e => {

		e.preventDefault();

		const submit = form.querySelector( '#submit' );
		submit.setAttribute( 'disabled', 'disabled' );

		const {
			wp: {
				apiFetch,
			},
			wpTeslaAdmin: {
				endpoints: {
					auth,
				},
			},
		} = window;

		apiFetch( {
			path: auth,
			method: 'POST',
			data: {
				email: document.getElementById( 'tesla-user-email' ).value,
				password: document.getElementById( 'tesla-user-password' ).value,
			},
		} ).then( res => {
			console.log( res );
			// TODO we need to handle invalid logins and such.
			submit.removeAttribute( 'disabled' );
			document.location = `${window.location  }&rnd=${  Math.random()}`;
		} );
	};

	/**
	 * Process the login form events.
	 *
	 * @return {void}
	 */
	const loginForm = () => {

		if ( ! form ) {
			return;
		}

		const submit = form.querySelector( '#submit' );
		if ( submit ) {
			submit.addEventListener( 'click', handleSubmit );
		}
	};

	// Wire up the login form.
	document.addEventListener( 'DOMContentLoaded', loginForm );

} ) ( window );
