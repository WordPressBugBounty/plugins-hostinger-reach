const HTML_ESCAPE_MAP: Record<string, string> = {
	'&': '&amp;',
	'<': '&lt;',
	'>': '&gt;',
	'"': '&quot;',
	"'": '&#39;'
};

const ALLOWED_TAG_PATTERN = /&lt;(\/?)(strong|b)&gt;|&lt;br\s*\/?&gt;/gi;

const escapeHtml = (text: string): string => text.replace(/[&<>"']/g, (character) => HTML_ESCAPE_MAP[character]);

export const sanitizeHtml = (html: string): string => {
	if (!html) return '';

	return escapeHtml(html).replace(ALLOWED_TAG_PATTERN, (match: string, closingSlash?: string, tagName?: string) => {
		if (!tagName) return '<br>';

		return `<${closingSlash}${tagName.toLowerCase()}>`;
	});
};
