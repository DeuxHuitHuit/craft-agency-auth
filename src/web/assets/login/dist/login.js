(() => {
	'use strict';

	const formContainer = document.querySelector('.login-form-container');
	if (!formContainer) {
		return;
	}
	const link = document.createElement('a');
	link.href = '/actions/agency-auth/dialog';
	link.className = 'btn dhh-btn';
	link.textContent = 'Agency Login';
	formContainer.appendChild(link);
})();
