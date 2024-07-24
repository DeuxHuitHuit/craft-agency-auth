(() => {
	'use strict';

	const init = () => {
		const container = document.querySelector('.login-form-container');
		const button = document.createElement('a');
		button.href = '/actions/agency-auth/dialog';
		button.className = 'btn dhh-btn';
		button.textContent = 'Agency Login';

		container.appendChild(button);
	};

	init();
})();
