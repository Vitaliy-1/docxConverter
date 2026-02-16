/**
 * @file plugins/generic/docxConverter/resources/js/main.js
 *
 * Copyright (c) 2021-2026 TIB Hannover
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_docxconverter
 *
 * @brief Vite main file
 */

pkp.registry.storeExtend('fileManager_COPYEDITED_FILES',
	(piniaContext) => {
		const dashboardStore = pkp.registry.getPiniaStore('dashboard');
		const fileStore = piniaContext.store;
		const {useModal} = pkp.modules.useModal;
		const {useLocalize} = pkp.modules.useLocalize;
		const {useUrl} = pkp.modules.useUrl;
		const {useFetch} = pkp.modules.useFetch;
		const {useDataChanged} = pkp.modules.useDataChanged;

		const {t, localize} = useLocalize();
		const {triggerDataChange} = useDataChanged();
		const {openDialog} = useModal();

		function dataUpdateCallback() {
			triggerDataChange();
		}

		if (dashboardStore.dashboardPage !== 'editorialDashboard' || fileStore.props.submissionStageId !== pkp.const.WORKFLOW_STAGE_ID_EDITING) {
			return;
		}

		fileStore.extender.extendFn('getItemActions', (originalResult, args) => {
			let newResult = originalResult;
			const localizedName = localize(args.file.name);
			if (localizedName.endsWith('.docx')) {
				newResult.push({
					label: t('plugins.generic.docxConverter.button.parseDocx'),
					name: 'convertAction',
					icon: 'FileText',
					actionFn: ({file}) => {
						const {apiUrl} = useUrl(`submissions/docxConverter/${file.id}`);
						openDialog({
							title: t('plugins.generic.docxConverter.button.parseDocx'),
							message: t('grid.action.parse'),
							actions: [
								{
									label: 'Yes',
									isPrimary: true,
									callback: async (close) => {
										close();
										const {fetch} = useFetch(`${apiUrl.value}/convert`, {
											method: 'GET',
											headers: {
												'Content-Type': 'application/json',
												'X-Csrf-Token': pkp.currentUser.csrfToken,
											},
										});
										await fetch().then(() => {
											dataUpdateCallback();
										});
									},
								},
								{
									label: 'No',
									isWarnable: true,
									callback: (close) => {
										close();
									},
								},
							],
						});
					},
				});
			}

			return [...newResult];
		});
	}
);

pkp.registry.storeExtend('fileManager_PRODUCTION_READY_FILES',
	(piniaContext) => {
		const dashboardStore = pkp.registry.getPiniaStore('dashboard');
		const fileStore = piniaContext.store;
		const {useModal} = pkp.modules.useModal;
		const {useLocalize} = pkp.modules.useLocalize;
		const {useUrl} = pkp.modules.useUrl;
		const {useFetch} = pkp.modules.useFetch;
		const {useDataChanged} = pkp.modules.useDataChanged;

		const {t, localize} = useLocalize();
		const {triggerDataChange} = useDataChanged();
		const {openDialog} = useModal();

		function dataUpdateCallback() {
			triggerDataChange();
		}

		if (dashboardStore.dashboardPage !== 'editorialDashboard' || fileStore.props.submissionStageId !== pkp.const.WORKFLOW_STAGE_ID_PRODUCTION) {
			return;
		}

		fileStore.extender.extendFn('getItemActions', (originalResult, args) => {
			let newResult = originalResult;
			const localizedName = localize(args.file.name);
			if (localizedName.endsWith('.docx')) {
				newResult.push({
					label: t('plugins.generic.docxConverter.button.parseDocx'),
					name: 'convertAction',
					icon: 'FileText',
					actionFn: ({file}) => {
						const {apiUrl} = useUrl(`submissions/docxConverter/${file.id}`);
						openDialog({
							title: t('plugins.generic.docxConverter.button.parseDocx'),
							message: t('grid.action.parse'),
							actions: [
								{
									label: 'Yes',
									isPrimary: true,
									callback: async (close) => {
										close();
										const {fetch} = useFetch(`${apiUrl.value}/convert`, {
											method: 'GET',
											headers: {
												'Content-Type': 'application/json',
												'X-Csrf-Token': pkp.currentUser.csrfToken,
											},
										});
										await fetch().then(() => {
											dataUpdateCallback();
										});
									},
								},
								{
									label: 'No',
									isWarnable: true,
									callback: (close) => {
										close();
									},
								},
							],
						});
					},
				});
			}

			return [...newResult];
		});
	}
);
