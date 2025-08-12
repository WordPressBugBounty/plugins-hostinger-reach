import {useState, useEffect} from "react";
import apiFetch from '@wordpress/api-fetch';
import {useBlockProps, InspectorControls} from "@wordpress/block-editor";
import {
	Button,
	PanelBody, SelectControl,
	TextControl,
	ToggleControl,
} from "@wordpress/components";
import {__} from '@wordpress/i18n';
import Connect from "./Components/Connect";

const statuses = {
	ready: 'ready',
	success: 'success',
	error: 'error',
	loading: 'loading',
}

const Edit = ({attributes, setAttributes, clientId}) => {
	const blockProps = useBlockProps();
	const nonce = wp.data.select('core/editor').getEditorSettings().nonce || '';
	const [email, setEmail] = useState('');
	const [name, setName] = useState('');
	const [surname, setSurname] = useState('');
	const [showNewContactList, setShowNewContactList] = useState(attributes.contactList === '');
	const [isConnected, setIsConnected] = useState(true);
	const [contactLists, setContactLists] = useState([]);
	const [status, setStatus] = useState(statuses.ready);
	const [message, setMessage] = useState('');
	const isLoading = status === statuses.loading;

	useEffect(() => {
		fetchContactLists();
		checkConnection();
	}, []);


	useEffect(() => {
		if (attributes.formId) return;
		setAttributes({formId: clientId});
	}, [setAttributes]);

	const fetchContactLists = async () => {
		await getContactLists();
	};


	const checkConnection = async () => {
		try {
			const response = await apiFetch({
				path: '/hostinger-reach/v1/overview',
				method: 'GET',
				headers: {
					'X-WP-Nonce': nonce,
				},
				parse: false,
			});

			if (response.ok) {
				setIsConnected(true);
			} else {
				setIsConnected(false);
			}

		} catch (err) {
			setIsConnected(false);
		}
	}

	const getContactLists = async () => {
		const response = await apiFetch({
			path: '/hostinger-reach/v1/contact-lists',
			method: 'GET',
			headers: {
				'X-WP-Nonce': nonce,
			},
			parse: false,
		});

		if (response.ok) {
			setContactLists(await response.json());
		}
	}

	const handleSubmit = async (e) => {
		e.preventDefault();
		setStatus(statuses.loading);
		try {
			const response = await apiFetch({
				path: '/hostinger-reach/v1/contact',
				method: 'POST',
				headers: {
					'X-WP-Nonce': nonce,
				},
				data: {
					email,
					name,
					surname,
					id: attributes.formId,
					group: attributes.contactList,
				},
				parse: false,
			});

			if (response.ok) {
				setStatus(statuses.success);
				setMessage(__('Thanks for subscribing', 'hostinger-reach'));
			} else {
				setStatus(statuses.error);
				setMessage(__('Something went wrong. Please try again', 'hostinger-reach'));
			}

		} catch (err) {
			setStatus(statuses.error);
			setMessage(__('Something went wrong. Please try again', 'hostinger-reach'));
		}
	};


	return <div {...blockProps}>
		<InspectorControls key="hostinger-reach-block-controls">
			<PanelBody title={__("Settings", "hostinger-reach")}>
				{!isConnected && <Connect/>}
				<TextControl
					disabled
					label={__('Form ID', 'hostinger-reach')}
					value={attributes.formId}
					help={__('Unique identifier for this form', 'hostinger-reach')}
				/>
				<SelectControl
					label={__('Contact List', 'hostinger-reach')}
					value={attributes.contactList}
					options={[
						{label: __("Create New List", "hostinger-reach"), value: ''},
						...contactLists.map(list => {
							return {label: list.name, value: list.name}
						})
					]}
					onChange={(value) => {
						setAttributes({contactList: value})
						if (!value) {
							setShowNewContactList(true);
						} else {
							setShowNewContactList(false);
						}
					}}
				/>
				{showNewContactList && <TextControl
					label={__('New Contact List', 'hostinger-reach')}
					value={attributes.contactList}
					onChange={(value) => {
						setAttributes({contactList: value})
					}}
					help={__('Name for the new Contact List', 'hostinger-reach')}
				/>}
				<ToggleControl
					label={__("Show Name Field?", "hostinger-reach")}
					key="hostinger-reach-block-show-name-field"
					checked={attributes.showName}
					onChange={(value) =>
						setAttributes({showName: value})
					}
				/>
				<ToggleControl
					label={__("Show Surname Field?", "hostinger-reach")}
					key="hostinger-reach-block-show-surname-field"
					checked={attributes.showSurname}
					onChange={(value) =>
						setAttributes({showSurname: value})
					}
				/>
			</PanelBody>
		</InspectorControls>
		<form className="hostinger-reach-block-subscription-form" onSubmit={handleSubmit}>
			{!isConnected && <Connect/>}
			<input type='hidden' name='group' value={attributes.contactList}/>
			<input type='hidden' name='id' value={attributes.formId}/>
			<TextControl
				required
				label={__('Email', 'hostinger-reach')}
				type="email"
				key="hostinger-reach-block-email-field"
				value={email}
				onChange={(value) => setEmail(value)}
			/>
			{attributes.showName && <TextControl
				label={__('Name', 'hostinger-reach')}
				key="hostinger-reach-block-name-field"
				value={name}
				onChange={(value) => setName(value)}
			/>}
			{attributes.showSurname && <TextControl
				label={__('Surname', 'hostinger-reach')}
				key="hostinger-reach-block-surname-field"
				value={surname}
				onChange={(value) => setSurname(value)}
			/>}
			{status !== statuses.success &&
				<Button
					disabled={isLoading}
					type="submit"
					variant="primary"
					key="hostinger-reach-block-submit"
					className="hostinger-reach-block-submit wp-block-button__link has-dark-color has-color-1-background-color has-text-color has-background has-link-color has-medium-font-size wp-element-button"
				>
					{__("Subscribe", "hostinger-affiliate-plugin")}
				</Button>
			}
			{
				message && <div className="reach-subscription-message">{message}</div>
			}
		</form>
	</div>

}

export default Edit;
