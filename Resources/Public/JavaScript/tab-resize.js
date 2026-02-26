/**
 * Remembers recently used tabs across page navigations and fixes chart
 * rendering inside hidden tab panes.
 *
 * localStorage stores a most-recently-used list of section identifiers.
 * On load, the first identifier that matches an available tab is activated.
 */
const STORAGE_KEY = 'pageInfoTabs_recent';
const MAX_RECENT = 20;

function getRecentTabs() {
	try {
		return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
	} catch {
		return [];
	}
}

function pushRecentTab(id) {
	const recent = getRecentTabs().filter(t => t !== id);
	recent.unshift(id);
	localStorage.setItem(STORAGE_KEY, JSON.stringify(recent.slice(0, MAX_RECENT)));
}

const tabs = document.querySelectorAll('#page-info-tabs [data-bs-toggle="tab"]');

if (tabs.length > 1) {
	const availableIds = new Set([...tabs].map(t => t.dataset.sectionId));
	const recent = getRecentTabs();
	const match = recent.find(id => availableIds.has(id));

	if (match) {
		const targetTab = [...tabs].find(t => t.dataset.sectionId === match);
		if (targetTab && !targetTab.classList.contains('active')) {
			// Deactivate the server-side default (first tab)
			document.querySelector('#page-info-tabs .nav-link.active')?.classList.remove('active');
			document.querySelector('.tab-content > .tab-pane.active')?.classList.remove('active');
			// Activate the remembered tab
			targetTab.classList.add('active');
			document.querySelector(targetTab.dataset.bsTarget)?.classList.add('active');
		}
	}

	// Track tab switches and fix chart rendering in newly shown panes
	tabs.forEach(tab => {
		tab.addEventListener('shown.bs.tab', () => {
			pushRecentTab(tab.dataset.sectionId);
			window.dispatchEvent(new Event('resize'));
		});
	});
}
