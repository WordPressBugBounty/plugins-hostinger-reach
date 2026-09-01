<script lang="ts" setup>
import { HButton, HHyperlink, HIcon, HIconButton, HSkeletonLoader, HText } from '@hostinger/hcomponents';
import { storeToRefs } from 'pinia';
import { computed, onMounted, ref } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import { useModal } from '@/composables';
import { useReachUrls } from '@/composables/useReachUrls';
import { useBuilderFormsStore } from '@/stores';
import { ModalName } from '@/types/enums';
import { translate } from '@/utils/translate';

interface Props {
	data?: Record<string, unknown>;
}

const props = defineProps<Props>();

const { closeModal, openModal } = useModal();
const { reachFormsLink } = useReachUrls();

const EMBED_SCRIPT_URL = hostinger_reach_reach_data.embed_script_url;

const builderFormsStore = useBuilderFormsStore();
const { forms, isLoading, isLoaded } = storeToRefs(builderFormsStore);
const { previewImageUrl } = builderFormsStore;

const selectedFormId = ref('');
const failedPreviews = ref<Set<string>>(new Set());

const markPreviewFailed = (uuid: string) => {
	failedPreviews.value = new Set(failedPreviews.value).add(uuid);
};

const isLoadingForms = computed(() => isLoading.value || !isLoaded.value);
const isEmpty = computed(() => isLoaded.value && forms.value.length === 0);
const showFooter = computed(() => !isEmpty.value);

const selectedForm = computed(() => forms.value.find((form) => form.uuid === selectedFormId.value));

const previewDoc = (uuid: string) =>
	`<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">` +
	`<div data-reach-form="${uuid}"></div>` +
	`<script src="${EMBED_SCRIPT_URL}" defer></scr` +
	`ipt></body></html>`;

const initSelection = () => {
	const preselectedId = props.data?.selectedFormId as string | undefined;
	selectedFormId.value =
		preselectedId && forms.value.some((form) => form.uuid === preselectedId)
			? preselectedId
			: (forms.value[0]?.uuid ?? '');
};

const loadForms = async () => {
	await builderFormsStore.loadForms();
	initSelection();
};

const handleRefresh = async () => {
	await builderFormsStore.loadForms(true);
	initSelection();
};

const openFormModal = () => {
	openModal(
		ModalName.SELECT_FORM_MODAL,
		{ data: { selectedFormId: selectedFormId.value } },
		{ hasCloseButton: true, isXXL: true, noContentPadding: true }
	);
};

const handleContinue = () => {
	if (!selectedForm.value) return;

	const onContinue = props.data?.onContinue as ((formBuilderId: string) => void) | undefined;
	if (onContinue) {
		onContinue(selectedForm.value.uuid);
		closeModal();

		return;
	}

	openModal(
		ModalName.SELECT_PAGE_MODAL,
		{
			data: {
				selectedForm: selectedForm.value,
				formPreviewUrl: previewImageUrl(selectedForm.value.uuid),
				backButtonRedirectAction: openFormModal
			}
		},
		{ hasCloseButton: true, noContentPadding: true }
	);
};

onMounted(loadForms);
</script>

<template>
	<BaseModal
		title-alignment="left"
		:title="translate('hostinger_reach_select_form_modal_title')"
		:subtitle="translate('hostinger_reach_select_form_modal_subtitle')"
	>
		<div class="select-form-modal">
			<div
				class="select-form-modal__content"
				:class="{
					'select-form-modal__content--empty': isEmpty,
					'select-form-modal__content--forms': isLoadingForms || forms.length > 0
				}"
			>
				<div v-if="isLoadingForms" class="select-form-modal__list">
					<div class="select-form-modal__list-forms">
						<div v-for="n in 4" :key="`skeleton-${n}`" class="select-form-modal__form-item">
							<HSkeletonLoader width="60%" height="20px" border-radius="sm" />
							<div class="select-form-modal__form-thumb">
								<HSkeletonLoader width="100%" height="100%" border-radius="lg" />
							</div>
						</div>
					</div>

					<div class="select-form-modal__list-preview">
						<HSkeletonLoader width="100%" height="100%" border-radius="md" />
					</div>
				</div>

				<div v-else-if="forms.length > 0" class="select-form-modal__list">
					<div class="select-form-modal__list-forms">
						<button
							v-for="form in forms"
							:key="form.uuid"
							type="button"
							class="select-form-modal__form-item"
							:class="{ 'select-form-modal__form-item--selected': form.uuid === selectedFormId }"
							@click="selectedFormId = form.uuid"
						>
							<HText variant="body-1-bold" as="span" class="select-form-modal__form-name">
								{{ form.name }}
							</HText>
							<div class="select-form-modal__form-thumb">
								<img
									v-if="!failedPreviews.has(form.uuid)"
									class="select-form-modal__form-image"
									:src="previewImageUrl(form.uuid)"
									:alt="form.name"
									loading="lazy"
									@error="markPreviewFailed(form.uuid)"
								/>
								<HIcon v-else name="ic-image-24" color="neutral--400" />
							</div>
						</button>
					</div>

					<div class="select-form-modal__list-preview">
						<iframe
							v-if="selectedFormId"
							:key="selectedFormId"
							class="select-form-modal__preview-frame"
							:srcdoc="previewDoc(selectedFormId)"
							:title="translate('hostinger_reach_select_form_modal_title')"
						></iframe>
					</div>
				</div>

				<div v-else class="select-form-modal__empty">
					<div class="select-form-modal__empty-icon">
						<HIcon name="ic-file-16" color="neutral--800" />
					</div>

					<div class="select-form-modal__empty-body">
						<div class="select-form-modal__empty-text">
							<HText as="h3" variant="heading-3" class="select-form-modal__empty-title">
								{{ translate('hostinger_reach_select_form_modal_empty_title') }}
							</HText>
							<HText as="p" variant="body-2" class="select-form-modal__empty-subtitle">
								{{ translate('hostinger_reach_select_form_modal_empty_subtitle') }}
							</HText>
						</div>

						<div class="select-form-modal__empty-action">
							<HHyperlink
								:href="reachFormsLink"
								target="_blank"
								variant="button-look"
								icon-prepend="ic-plus-16"
								icon-append="ic-arrow-up-right-square-16"
								icon-size="16px"
								:button-look-props="{ variant: 'contain', color: 'neutral', size: 'small' }"
							>
								{{ translate('hostinger_reach_select_form_modal_empty_create') }}
							</HHyperlink>
						</div>
					</div>
				</div>
			</div>

			<div v-if="showFooter" class="select-form-modal__footer">
				<div class="select-form-modal__footer-start">
					<HHyperlink
						:href="reachFormsLink"
						target="_blank"
						variant="button-look"
						icon-prepend="ic-plus-16"
						icon-append="ic-arrow-up-right-square-16"
						icon-size="16px"
						:button-look-props="{ variant: 'outline', color: 'neutral', size: 'small' }"
					>
						{{ translate('hostinger_reach_select_form_modal_create_new') }}
					</HHyperlink>

					<HIconButton
						icon="ic-refresh-16"
						:icon-description="translate('hostinger_reach_select_form_modal_refresh')"
						variant="outline"
						color="neutral"
						size="small"
						:is-loading="isLoadingForms"
						@click="handleRefresh"
					/>
				</div>

				<div class="select-form-modal__footer-actions">
					<HButton variant="text" color="neutral" size="small" @click="closeModal">
						{{ translate('hostinger_reach_select_form_modal_cancel') }}
					</HButton>
					<HButton color="primary" size="small" :is-disabled="!selectedFormId" @click="handleContinue">
						{{ translate('hostinger_reach_select_form_modal_continue') }}
					</HButton>
				</div>
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
:deep(.base-modal__header) {
	padding: 24px 24px 0;
}

:deep(.base-modal__subtitle) {
	padding: 0 24px;
}

.select-form-modal {
	border-top: 1px solid var(--neutral--200);

	&__content {
		padding: 24px;

		&--empty {
			padding-top: 56px;
			padding-bottom: 56px;
		}

		&--forms {
			padding: 0;
		}
	}

	&__list {
		display: grid;
		grid-template-columns: 1fr 1fr;
		height: 480px;
	}

	&__list-forms {
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-auto-rows: 200px;
		gap: 16px;
		align-content: start;
		overflow-y: auto;
		padding: 16px;
	}

	&__list-preview {
		display: flex;
		flex-direction: column;
		overflow-y: auto;
		padding: 16px;
		background: var(--neutral--100);
	}

	&__form-item {
		display: flex;
		flex-direction: column;
		gap: 8px;
		padding: 0;
		border: none;
		background: transparent;
		cursor: pointer;
		text-align: left;

		&:focus-visible {
			outline: none;
		}
	}

	&__form-name {
		color: var(--neutral--800);
	}

	&__form-thumb {
		display: flex;
		flex: 1;
		align-items: center;
		justify-content: center;
		width: 100%;
		min-height: 0;
		overflow: hidden;
		background: var(--neutral--100);
		border-radius: 16px;
		border: 3px solid transparent;
		transition: border-color 0.2s ease;
	}

	&__form-image {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	&__form-item--selected &__form-thumb {
		border-color: var(--h-bg-brand-default);
	}

	&__preview-frame {
		width: 100%;
		height: 100%;
		min-height: 320px;
		border: 1px solid var(--neutral--200);
		border-radius: 12px;
	}

	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 32px;
		max-width: 372px;
		margin: 0 auto;
		text-align: center;
	}

	&__empty-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 60px;
		height: 60px;
		border: 1px solid rgba(235, 235, 235, 1);
		border-radius: 12px;
		background: rgba(250, 250, 250, 1);
	}

	&__empty-body {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 24px;
	}

	&__empty-text {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 4px;
	}

	&__empty-title {
		font-size: 18px;
		font-weight: 600;
	}

	&__empty-subtitle {
		color: var(--neutral--300);
	}

	&__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
		border-top: 1px solid var(--neutral--200);
		padding: 16px 24px;
	}

	&__footer-start {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__footer-actions {
		display: flex;
		align-items: center;
		gap: 8px;
	}
}
</style>
