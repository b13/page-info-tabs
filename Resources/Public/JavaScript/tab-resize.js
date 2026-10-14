/**
 * Remembers recently used tabs across page navigations and fixes chart
 * rendering inside hidden tab panes.
 *
 * localStorage stores a most-recently-used list of section identifiers.
 * On load, the first identifier that matches an available tab is activated.
 *
 * Tab switching itself is done by the backend: Bootstrap in TYPO3 13,
 * @typo3/backend/tab.js in TYPO3 14. The latter drops the data-bs-*
 * attributes, so tabs and panels are looked up via role and aria-controls.
 */
const STORAGE_KEY = 'pageInfoTabs_recent';
const MAX_RECENT = 20;

function getRecentTabs() {
	try {
		const recent = JSON.parse(localStorage.getItem(STORAGE_KEY));
		return Array.isArray(recent) ? recent : [];
	} catch {
		return [];
	}
}

function pushRecentTab(id) {
	const recent = [id, ...getRecentTabs().filter(t => t !== id)].slice(0, MAX_RECENT);
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(recent));
	} catch {
		// Storage full or disabled: the tab is simply not remembered
	}
}

const tablist = document.getElementById('page-info-tabs');
const tabs = tablist ? [...tablist.querySelectorAll('[role="tab"][data-section-id]')] : [];
const panelOf = tab => document.getElementById(tab.getAttribute('aria-controls') ?? '');

function activate(targetTab) {
	for (const tab of tabs) {
		const isActive = tab === targetTab;
		tab.classList.toggle('active', isActive);
		tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
		tab.setAttribute('tabindex', isActive ? '0' : '-1');
		panelOf(tab)?.classList.toggle('active', isActive);
	}
}

function onTabShown(event) {
	const tab = event.target.closest?.('[role="tab"][data-section-id]');
	if (!tab || !tabs.includes(tab)) {
		return;
	}
	// TYPO3 14 dispatches typo3:tab:shown and, for compatibility, shown.bs.tab
	if (event.type === 'shown.bs.tab' && tab.hasAttribute('data-typo3-tab')) {
		return;
	}
	pushRecentTab(tab.dataset.sectionId);
	window.dispatchEvent(new Event('resize'));
}

if (tabs.length > 1) {
	const availableIds = new Set(tabs.map(t => t.dataset.sectionId));
	const match = getRecentTabs().find(id => availableIds.has(id));
	const targetTab = match ? tabs.find(t => t.dataset.sectionId === match) : null;
	if (targetTab && !targetTab.classList.contains('active')) {
		activate(targetTab);
	}

	tablist.addEventListener('typo3:tab:shown', onTabShown);
	tablist.addEventListener('shown.bs.tab', onTabShown);
}
