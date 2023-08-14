(($) => {
	'use strict';

	const scope = $('body');

	const sels = {
		ref: '#poweredby'
	};

	const init = () => {
		const ref = scope.find(sels.ref);
		const html = $('<a href="/actions/agency-auth/dialog" class="btn dhh-btn" />')
			.text('Agency Login');

		html.insertBefore(ref);
	};

	init();
})(jQuery);
