import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './SelectFormModal.scss';

const EMBED_SCRIPT_URL = window.hostinger_reach_block_editor_data?.embed_script_url;

const previewDoc = (uuid) =>
	`<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">` +
	`<div data-reach-form="${uuid}"></div>` +
	`<script src="${EMBED_SCRIPT_URL}" defer></scr` +
	`ipt></body></html>`;

const SelectFormModal = ({ forms, isLoading, previewImageUrl, initialSelectedId, createFormUrl, onRefresh, onClose, onContinue }) => {
	const [selectedId, setSelectedId] = useState(initialSelectedId || '');
	const [failedPreviews, setFailedPreviews] = useState({});

	useEffect(() => {
		if (selectedId) {
			return;
		}

		if (initialSelectedId && forms.some((form) => form.uuid === initialSelectedId)) {
			setSelectedId(initialSelectedId);
		} else if (forms.length > 0) {
			setSelectedId(forms[0].uuid);
		}
	}, [forms, initialSelectedId]);

	const markPreviewFailed = (uuid) => setFailedPreviews((prev) => ({ ...prev, [uuid]: true }));

	const handleContinue = () => {
		if (!selectedId) {
			return;
		}

		onContinue(selectedId);
	};

	return (
		<Modal
			title={__('Select a form', 'hostinger-reach')}
			onRequestClose={onClose}
			className="hostinger-reach-select-form-modal"
			size="large"
		>
			<div className="hostinger-reach-select-form-modal__content">
				{isLoading && (
					<div className="hostinger-reach-select-form-modal__loading">
						<Spinner />
					</div>
				)}

				{!isLoading && forms.length === 0 && (
					<div className="hostinger-reach-select-form-modal__empty">
						{__('No forms yet. Create your first form in Hostinger Reach.', 'hostinger-reach')}
					</div>
				)}

				{!isLoading && forms.length > 0 && (
					<div className="hostinger-reach-select-form-modal__list">
						<div className="hostinger-reach-select-form-modal__list-forms">
							{forms.map((form) => (
								<button
									key={form.uuid}
									type="button"
									className={
										'hostinger-reach-select-form-modal__form-item' +
										(form.uuid === selectedId ? ' hostinger-reach-select-form-modal__form-item--selected' : '')
									}
									onClick={() => setSelectedId(form.uuid)}
								>
									<span className="hostinger-reach-select-form-modal__form-name">{form.name}</span>
									<div className="hostinger-reach-select-form-modal__form-thumb">
										{failedPreviews[form.uuid] ? (
											<span className="hostinger-reach-select-form-modal__form-thumb-fallback" />
										) : (
											<img
												className="hostinger-reach-select-form-modal__form-image"
												src={previewImageUrl(form.uuid)}
												alt={form.name}
												loading="lazy"
												onError={() => markPreviewFailed(form.uuid)}
											/>
										)}
									</div>
								</button>
							))}
						</div>

						<div className="hostinger-reach-select-form-modal__list-preview">
							{selectedId && (
								<iframe
									key={selectedId}
									className="hostinger-reach-select-form-modal__preview-frame"
									srcDoc={previewDoc(selectedId)}
									title={__('Select a form', 'hostinger-reach')}
								/>
							)}
						</div>
					</div>
				)}
			</div>

			<div className="hostinger-reach-select-form-modal__footer">
				<div className="hostinger-reach-select-form-modal__footer-start">
					{createFormUrl && (
						<Button
							variant="secondary"
							href={createFormUrl}
							target="_blank"
							rel="noopener noreferrer"
							className="hostinger-reach-select-form-modal__create"
						>
							{__('Create a new form', 'hostinger-reach')}
						</Button>
					)}

					{onRefresh && (
						<Button
							variant="secondary"
							disabled={isLoading}
							onClick={onRefresh}
							className="hostinger-reach-select-form-modal__refresh"
						>
							{__('Refresh', 'hostinger-reach')}
						</Button>
					)}
				</div>

				<div className="hostinger-reach-select-form-modal__footer-actions">
					<Button variant="tertiary" onClick={onClose}>
						{__('Cancel', 'hostinger-reach')}
					</Button>
					<Button variant="primary" disabled={!selectedId} onClick={handleContinue}>
						{__('Continue', 'hostinger-reach')}
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default SelectFormModal;
