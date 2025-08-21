import {useState, useEffect} from "react";
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from "@wordpress/server-side-render";
import {useBlockProps, InspectorControls} from "@wordpress/block-editor";
import {
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
	const [showNewContactList, setShowNewContactList] = useState(attributes.contactList === '');
	const [isConnected, setIsConnected] = useState(true);
	const [contactLists, setContactLists] = useState([]);
	const [status, setStatus] = useState(statuses.ready);

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
		{!isConnected && <Connect/>}
		<ServerSideRender
			key="hostinger-reach-server-side-renderer"
			block="hostinger-reach/subscription"
			attributes={attributes}
		/>
	</div>

}

export default Edit;
