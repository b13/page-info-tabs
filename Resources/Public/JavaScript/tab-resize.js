/**
 * Dispatches a window resize event when a Bootstrap tab is shown.
 * This fixes chart libraries (Frappe Charts, Chart.js, etc.) that render
 * incorrectly inside hidden tab panes due to zero-width containers.
 */
document.querySelectorAll('#page-info-tabs [data-bs-toggle="tab"]').forEach(tab => {
	tab.addEventListener('shown.bs.tab', () => {
		window.dispatchEvent(new Event('resize'));
	});
});
