
IspconfigField.prototype = Object.create(React.Component.prototype);

function IspconfigField(props) {
	React.Component.constructor.call(this);
	var self = this;

	self.state = {field: props.field, options: [], advanced: false, info: ''};

	var getLabel = function (id) {
		//return wp.i18n.__(id, 'wp-ispconfig3');
		switch (id) {
			case 'client_username':
				return 'Client Login';
			case 'client_password':
				return "Client Password";
			case 'client_template_master':
				return 'Client Template';
			case 'client_contact_name':
				return 'Contact Name';
			case 'client_company_name':
				return 'Company Name';
			case 'client_email':
				return 'Email';
			case 'client_language':
				return 'Language code (ISO-639-1)';
			case 'client_street':
				return 'Street';
			case 'client_zip':
				return 'Zip Code';
			case 'client_city':
				return 'City';
			case 'client_telephone':
				return 'Phone number';
			case 'client_bank_name':
				return 'Bank Name';
			case 'client_bank_account_owner':
				return 'Bank Account Owner';
			case 'client_bank_account_iban':
				return 'Bank IBAN';
			case 'client_bank_account_swift':
				return 'BIC / Swift';

			case 'website_domain':
				return "Website Domain";

			case 'mail_address':
				return "Mail address";
			case 'mail_name':
				return 'Mail Name';
			case 'mail_password':
				return 'Mail password';
		}
	}

	var loadField = function(field) {

		var options = [ { name: 'Default', id: '' } ];
		var advanced = false;
		var info = '';

		switch(field.id) {
			case 'client_username':
				options.push({ name: 'WP User', id: 'wp-user' });
				if (field.computed === '') {
					advanced = true;
					info = 'Client user (optional)';
				}
				break;
			case 'client_contact_name':
				options.push({ name: 'WP Full Name', id: 'wp-name' });
				break;
			case 'client_email':
				options.push({ name: 'WP Email', id: 'wp-email' });
				break;
			case 'client_template_master':
				advanced = true;
				info = 'Enter template id';
				break;
			case 'client_language':
				options.push({ name: 'WP Locale', id: 'wp-locale' });
				break;
			case 'website_domain':
				options.push({ name: 'GET data', id: 'get-data' });
				if (field.computed === 'get-data') {
					advanced = true;
					info = 'GET Parameter';
				}
				break;
			case 'mail_address':
				break;
		}

		self.setState(function() {
			return { options, advanced, info, field};
		});
	}

	var updateComputed = function (computed) {

		self.setState(function () {
			props.onChange({ computed });
			return props;
		});
	}

	var updateValue = function(value) {
		self.setState(function() {
			props.onChange({ value });
			return props;
		});
	}

	var updateHiddenCheckbox = function (hidden) {
		self.setState(function () {
			props.onChange({ hidden });
			return props;
		});
	}

	var updateReadonlyCheckbox = function (readonly) {
		self.setState(function () {
			props.onChange({ readonly });
			return props;
		});
	}

	self.componentDidMount = function () {
		loadField(props.field);		
	};

	self.componentWillReceiveProps = function(nextProps) {
		loadField(nextProps.field);
	}

	self.render = function () {

		var hidden = wp.element.createElement(wp.components.CheckboxControl, { className: 'ispconfig-block-inline', checked: self.state.field.hidden, label: 'Hidden', onChange: updateHiddenCheckbox.bind(this) });
		var readonly = wp.element.createElement(wp.components.CheckboxControl, { className: 'ispconfig-block-inline', checked: self.state.field.readonly, label: 'Read only', onChange: updateReadonlyCheckbox.bind(this) });

		var delicon = wp.element.createElement(wp.components.Button, {onClick: props.onDelete, disabled: self.state.field.protected, style: { 'margin-left': '10px' }, className: 'components-button button-link-delete is-button is-default is-large'}, "Delete");
		var ddl = wp.element.createElement(wp.components.TreeSelect, { className: 'ispconfig-block-inline', label: getLabel(self.state.field.id),tree: self.state.options, selectedId: self.state.field.computed, onChange: updateComputed });
		var txt = wp.element.createElement(wp.components.TextControl, { className: 'ispconfig-block-inline', label: self.state.info, value: self.state.field.value, hidden: !self.state.advanced, onChange: updateValue.bind(this) });

		return wp.element.createElement('div', { className: 'ispconfig-block-field' }, ddl, txt, wp.element.createElement('div'), hidden, readonly, delicon);
	}
}

IspconfigFieldList.prototype = Object.create(React.Component.prototype);

function IspconfigFieldList(props) {
	React.Component.constructor.call(this);
	var self = this;

	self.state = { fields: props.attributes.fields };

	var onFieldChanged = function (index, changes) {
		// updating changes in current field
		self.setState(function(prevState) {
			for (name in changes) {
				prevState.fields[index][name] = changes[name];
			}
			return { fields: prevState.fields };
		});

		props.setAttributes({updated: new Date().getTime()});
	};

	var onFieldDelete = function(index, current) {
		console.log("Removing field from index:", index);

		self.setState(function(prevState) {
			prevState.fields.splice(index, 1);
			return { fields: prevState.fields };
		});

		props.setAttributes({updated: new Date().getTime()});
	}

	self.componentWillReceiveProps = function(nextProps) {
		self.setState(function(prevState) {
			return { fields: nextProps.attributes.fields };
		});
	}

	self.componentDidMount = function () {
		
	};

	self.componentWillUnmount = function () {
		
	};

	self.render = function () {

		var rendered = [];
		for (var i = 0; i < self.state.fields.length; i++) {
			let item = self.state.fields[i];
			rendered.push(wp.element.createElement(IspconfigField, { style: { display: 'inline-block' }, field: item, onDelete: onFieldDelete.bind(this, i), onChange: onFieldChanged.bind(this, i) }));
		}

		return wp.element.createElement("div", null, rendered);
	}
}

wp.blocks.registerBlockType('ole1986/ispconfig-block', {
	title: 'ISPconfig Fields',
	icon: 'smiley',
	category: 'common',
	attributes: {
		fields: { type: 'array', default: [] },
		action: { type: 'string', default: 'action_create_client'},
		updated: { type: 'number' },
		description: { type: 'string', default: 'Please select an option' }
	},
	edit: function (props) {
		var actionControls = [
			{
				name: 'Create Client',
				description: 'Provide a form to create a new client login',
				id: 'action_create_client', 
				fields: ['client_username', 'client_contact_name', 'client_company_name', 'client_email', 'client_password', 'client_template_master']
			},
			{ 
				name: 'Create Website (existing client)',
				description: 'Provide a form to create a new website based on a given client login (must exists)',
				id: 'action_create_website',
				fields: ['client_username', 'website_domain']
			},
			{ 
				name: 'Create Website (new client)',
				description: 'Provide a form to create a new website and client login at once. An email will be submitted to the given address containing the login information',
				id: 'action_create_website_new',
				fields: ['client_contact_name', 'client_company_name', 'client_email','website_domain']
			},
			{
				name: 'Create Mail [NOT YET IMPLEMENTED]',
				description: 'Provide a form to create a new email address based on a given client login',
				id: 'action_create_mail',
				fields: ['client_username', 'mail_address', 'mail_name', 'mail_password']
			},
			{
				name: 'Update Client info',
				description: 'Provide a form to update general client information',
				id: 'action_update_client',
				fields: [ 'client_username', 'client_contact_name', 'client_company_name', 'client_language','client_street', 'client_zip', 'client_city', 'client_telephone']
			},
			{
				name: 'Update bank details',
				description: 'Provide a form to update client bank details',
				id: 'action_update_client_bank',
				fields: [ 'client_username', 'client_bank_account_owner', 'client_bank_name', 'client_bank_account_iban', 'client_bank_account_swift']
			},
			{
				name: 'Lock client',
				description: 'Provide a form to lock a client account base on the given login',
				id: 'action_lock_client',
				fields: ['client_username']
			},
			{
				name: 'Unlock client',
				description: 'Provide a form to unlock a client account base on the given login',
				id: 'action_unlock_client',
				fields: ['client_username']
			}
		];

		function changeAction(controlId) {
			var curAction = actionControls.filter(function(a) { return a.id === controlId; })[0];

			var newFields = [];

			while(props.attributes.fields.length > 0) {
				props.attributes.fields.pop();
			}

			curAction.fields.forEach(function(field_id) {
				var field = { id: field_id, value: '', computed: '', hidden: false, readonly: false, protected: false };

				if (['action_update_client','action_update_client_bank', 'action_locknow_client', 'action_create_website', 'action_create_mail'].indexOf(controlId) > -1 && field_id === 'client_username') {
					field.protected = true;
					field.computed = 'wp-user';
				}

				newFields.push(field);
			});

			props.attributes.action = controlId;

			props.setAttributes({ action: controlId, description: curAction.description, fields: props.attributes.fields.concat(newFields) });
		}

		return wp.element.createElement(
			"div",
			{className: 'ispconfig-block'},
			wp.element.createElement('label', null, 'Choose option'),
			wp.element.createElement(wp.components.TreeSelect, { style: { 'max-width': '200px' }, tree: actionControls, selectedId: props.attributes.action, onChange: changeAction }),
			wp.element.createElement('div', { className:'ispconfig-block-infobox' }, props.attributes.description),
			wp.element.createElement(IspconfigFieldList, props),
			wp.element.createElement('div', {style: {'margin-top': '1em', 'font-size': '90%'}}, 'Learn more about customizing the field input using wordpress filters or shortcodes - ', wp.element.createElement('a', {target: '_blank', href: 'https://github.com/ole1986/wp-ispconfig3/wiki'}, 'Click here for details'))
		);
	},
	save: function () {
		return null;
	}
})