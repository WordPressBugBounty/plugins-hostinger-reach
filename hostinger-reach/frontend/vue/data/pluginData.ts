import contactForm7PluginIcon from '@/assets/images/icons/contact-form-7-plugin.svg';
import emailReachPluginIcon from '@/assets/images/icons/email-reach-plugin.svg';
import wpFormsLitePluginIcon from '@/assets/images/icons/wp-forms-lite-plugin.svg';
import type { Integration } from '@/types/models';

export const PLUGIN_IDS = {
	HOSTINGER_REACH: 'hostinger-reach',
	CONTACT_FORM_7: 'contact-form-7',
	WP_FORMS_LITE: 'wp-forms-lite'
} as const;

export const INTEGRATION_TO_FORM_TYPE_MAP: Record<string, string> = {
	hostingerReach: 'hostinger-reach',
	'contactForm-7': 'contact-form-7',
	wpformsLite: 'wpforms-lite'
} as const;

export const PLUGIN_TITLES = {
	HOSTINGER_REACH: 'hostinger_reach_plugin_titles_hostinger_reach',
	CONTACT_FORM_7: 'hostinger_reach_plugin_titles_contact_form_7',
	WP_FORMS_LITE: 'hostinger_reach_plugin_titles_wp_forms_lite'
} as const;

export const PLUGIN_STATUSES = {
	ACTIVE: 'active',
	INACTIVE: 'inactive'
} as const;

export type PluginId = (typeof PLUGIN_IDS)[keyof typeof PLUGIN_IDS];
export type PluginStatus = (typeof PLUGIN_STATUSES)[keyof typeof PLUGIN_STATUSES];

export interface PluginInfo {
	id: string;
	title: string;
	icon: string;
}

export const DEFAULT_PLUGIN_DATA: Record<string, PluginInfo> = {
	hostingerReach: {
		id: 'hostingerReach',
		title: PLUGIN_TITLES.HOSTINGER_REACH,
		icon: emailReachPluginIcon
	},
	'contactForm-7': {
		id: 'contactForm-7',
		title: PLUGIN_TITLES.CONTACT_FORM_7,
		icon: contactForm7PluginIcon
	},
	wpformsLite: {
		id: 'wpformsLite',
		title: PLUGIN_TITLES.WP_FORMS_LITE,
		icon: wpFormsLitePluginIcon
	}
} as const;

export const PLUGIN_DATA = DEFAULT_PLUGIN_DATA;

export const getPluginInfo = (integration: Integration): PluginInfo => {
	const defaultInfo = DEFAULT_PLUGIN_DATA[integration.id];

	return {
		id: integration.id,
		title: defaultInfo?.title || integration.title,
		icon: defaultInfo?.icon || ''
	};
};
