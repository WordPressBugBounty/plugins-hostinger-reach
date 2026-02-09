import { computed } from 'vue';

import { useGeneralDataStore } from '@/stores/generalDataStore';

export const useReachUrls = () => {
	const generalStore = useGeneralDataStore();

	const reachBaseDomain = computed(() => (generalStore.isStaging ? 'reach.hostinger.dev' : 'reach.hostinger.com'));
	const hpanelBaseDomain = computed(() => (generalStore.isStaging ? 'hpanel.hostinger.dev' : 'hpanel.hostinger.com'));
	const resourceId = computed(() => (generalStore.hasValidResourceId ? generalStore.resourceId : null));

	return {
		reachUpgradeLink: computed(() => `https://${hpanelBaseDomain.value}/reach`),
		reachYourPlanLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=settings-your-plan`
				: `https://${reachBaseDomain.value}/settings/your-plan`
		),
		reachCampaignsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=campaigns`
				: `https://${reachBaseDomain.value}/campaigns`
		),
		reachTemplatesLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=templates`
				: `https://${reachBaseDomain.value}/templates`
		),
		reachSettingsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=settings`
				: `https://${reachBaseDomain.value}/settings`
		),
		reachBaseDomain,
		hpanelBaseDomain
	};
};
