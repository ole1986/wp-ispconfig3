
IspconfigField.prototype = Object.create(React.Component.prototype);

function IspconfigField(props) {
	React.Component.constructor.call(this);
	var self = this;

	var options = [];

	self.state = props.field;

	var getLabel = function (id) {
		// use wordpress i18n translations stored in
		// plugins languages directory as json file
		return wp.i18n.__(id, 'wp-ispconfig3');
	}

	var getAdvancedLabel = function(id, computed) {
		var info = '';
		switch(id) {
			case 'client_username':
				if(computed === '') {
					info = 'Predefined user';
				} else if (computed === 'generated') {
					info = 'Random username';
				} else if (computed === 'wp-user') {
					info = 'User need to login first';
				} else if (computed === 'session') {
					info = 'Gather from session';
				}
				break;
			case 'client_template_master':
				info = 'Enter Template ID';
				break;
			case 'domain_name':
			case 'website_domain':
				if (computed == 'get-data') {
					info = 'GET Parameter';
				}
				break;
		}

		return info;
	}

	var IsAdvanced = function(id, computed) {
		var advanced = false;

		switch(id) {
			case 'client_username':
				if ([''].indexOf(computed) != -1) {
					advanced = true;
				}
				break;
			case 'client_template_master':
				advanced = true;
				break;
			case 'domain_name':
			case 'website_domain':
				if (computed === 'get-data') {
					advanced = true;
				}
				break;
		}

		return advanced;
	}

	var loadField = function(nextProps) {
		options = [];
		options.push({ name: 'Default', id: '' });

		switch(nextProps.field.id) {
			case 'client_username':
				if(nextProps.action === 'action_create_client') {
					options.push({ name: 'Generated', id: 'generate' });
				} else {
					options.push({ name: 'WP User', id: 'wp-user' });
					options.push({ name: 'Session', id: 'session' });
				}

				info = 'Client user (optional)';
				break;
			case 'client_contact_name':
				options.push({ name: 'WP Full Name', id: 'wp-name' });
				break;
			case 'client_email':
				options.push({ name: 'WP Email', id: 'wp-email' });
				break;
			case 'client_template_master':
				break;
			case 'client_language':
				options.push({ name: 'WP Locale', id: 'wp-locale' });
				break;
			case 'website_domain':
				options.push({ name: 'GET data', id: 'get-data' });
				break;
			case 'domain_name':
				options.push({ name: 'GET data', id: 'get-data' });
				break;
		}
	}

	var updateComputed = function (computed) {
		self.setState({ computed }, function() {
			props.onFieldChanged(self.state);
		});
	}

	var updateValue = function(value) {
		self.setState({ value }, function() {
			props.onFieldChanged(self.state);
		});
	}

	var updateHiddenCheckbox = function (hidden) {
		self.setState({ hidden }, function() {
			props.onFieldChanged(self.state);
		});
	}

	var updateReadonlyCheckbox = function (readonly) {
		self.setState({ readonly }, function() {
			props.onFieldChanged(self.state);
		});
	}

	self.componentDidUpdate = function(prevProp, prevState, snap) {
		
	};

	self.componentDidMount = function () {
		self.setState(props.field);
		loadField(props);
	};

	self.componentWillReceiveProps = function(nextProps) {
		self.setState(nextProps.field);
		loadField(nextProps);
	}

	self.render = function () {

		var hidden = wp.element.createElement(wp.components.CheckboxControl, { className: 'ispconfig-block-inline', checked: self.state.hidden, label: wp.i18n.__('Hide', 'wp-ispconfig3'), onChange: updateHiddenCheckbox.bind(this)});
		var readonly = wp.element.createElement(wp.components.CheckboxControl, { className: 'ispconfig-block-inline', checked: self.state.readonly, label: wp.i18n.__('Readonly', 'wp-ispconfig3'), onChange: updateReadonlyCheckbox.bind(this)});

		var delicon = wp.element.createElement(wp.components.Button, {onClick: props.onDelete, disabled: self.state.protected, style: { 'margin-left': '10px' }, className: 'components-button button-link-delete is-button is-default is-large'}, wp.i18n.__('Delete', 'wp-ispconfig3'));
		var ddl = wp.element.createElement(wp.components.TreeSelect, { className: 'ispconfig-block-inline', label: getLabel(self.state.id),tree: options, selectedId: self.state.computed, onChange: updateComputed });
		var txt = wp.element.createElement(wp.components.TextControl, { className: 'ispconfig-block-inline', label: getAdvancedLabel(self.state.id, self.state.computed), value:self.state.value, hidden: !IsAdvanced(self.state.id, self.state.computed), onChange: updateValue.bind(this) });

		return wp.element.createElement('div', { className: 'ispconfig-block-field' }, ddl, txt, wp.element.createElement('div'), hidden, readonly, delicon);
	}
}

IspconfigSubmit.prototype = Object.create(React.Component.prototype);

function IspconfigSubmit(props) {
	React.Component.constructor.call(this);
	var self = this;

	self.state = props.submission;

	var getOptions = function() {
		return [{ label: wp.i18n.__('Save', 'wp-ispconfig3'), value: 'save' }, {label: wp.i18n.__('Continue with...', 'wp-ispconfig3'), value: 'continue'}];
	}

	var IsAdvanced = function(value) {
		var advanced = false;
		switch(value) {
			case 'save':
				break;
			case 'continue':
				advanced = true;
				break;
		}

		return advanced;
	}

	var changeAction = function(value) {
		self.setState({
			action: value
		}, function() { props.onSubmitChanged(self.state) });
	};

	var changeValue = function(value) {
		self.setState({
			url: value
		}, function() { props.onSubmitChanged(self.state) });
	}

	var changeTitle = function(value) {
		self.setState({
			button_title: value
		}, function() { props.onSubmitChanged(self.state) });
	}

	var backButtonVisible = function(value) {
		self.setState({
			button_back: value
		}, function() { props.onSubmitChanged(self.state) });
	}

	var backButtonTitle = function(value) {
		self.setState({
			button_back_title: value
		}, function() { props.onSubmitChanged(self.state) });
	}

	self.componentDidUpdate = function(prevProp, prevState, snap) {
		//props.onSubmitChanged(self.state);
	};

	self.componentDidMount = function () {
		//self.setState(props.submission);
	};

	self.componentWillReceiveProps = function(nextProps) {
		
	}

	self.render = function () {
		var title = wp.element.createElement('div', null,  wp.element.createElement('label', null, 'Submission'));
		var actionRadio = wp.element.createElement(wp.components.RadioControl, { className: 'ispconfig-block-inline', selected: self.state.action || 'save', options: getOptions(), label: wp.i18n.__('Action', 'wp-ispconfig3'), onChange: changeAction.bind(this) });
		var txt = wp.element.createElement(wp.components.TextControl, { className: 'ispconfig-block-inline', placeholder: 'Enter url', value: self.state.url, hidden: !IsAdvanced(self.state.action), onChange: changeValue.bind(this) });

		var buttonName = wp.element.createElement(wp.components.TextControl, { className: 'ispconfig-block-inline', label: 'Title', placeholder: 'Button title (E.g. Submit)', value: self.state.button_title, onChange: changeTitle.bind(this) });

		var container = wp.element.createElement('div', null);

		var showBackButton = wp.element.createElement(wp.components.CheckboxControl, { className: 'ispconfig-block-inline', checked: self.state.button_back, label: 'Show back button', onChange: backButtonVisible.bind(this)});
		
		var backButtonName = null;
		if (self.state.button_back) {
			backButtonName = wp.element.createElement(wp.components.TextControl, { className: 'ispconfig-block-inline', label: 'Title', placeholder: 'Back button title', value: self.state.button_back_title, onChange: backButtonTitle.bind(this) });
		}

		return wp.element.createElement('div', { className: 'ispconfig-block-action' }, title, actionRadio, txt, buttonName, container, showBackButton, backButtonName);
	}
}

IspconfigFieldList.prototype = Object.create(React.Component.prototype);

function IspconfigFieldList(props) {
	React.Component.constructor.call(this);
	var self = this;

	self.state = { action: props.attributes.action, fields: props.attributes.fields, submission: props.attributes.submission };

	var onFieldChanged = function (i, changes) {
		self.state.fields[i] = changes;

		props.setAttributes({ fields: self.state.fields });
		props.setAttributes({ updated: (new Date).getTime() });
	};

	var onFieldDelete = function(index) {
		self.setState(function(prevState) {
			prevState.fields.splice(index, 1);
			props.setAttributes({ fields: prevState.fields});
			return { fields: prevState.fields };
		});

		props.setAttributes({ updated: (new Date).getTime() });
	}

	var onSubmitChanged = function(changes) {
		console.log("onSubmitChanged:", changes);

		self.state.submission = changes;

		props.setAttributes({submission: changes});

		props.setAttributes({ updated: (new Date).getTime() });
	}

	self.componentDidUpdate = function(prevProp, prevState, snap) {
		
	};

	self.componentWillReceiveProps = function(nextProps) {
		console.log("IspconfigFieldList -> componentWillReceiveProps", nextProps);
		self.setState({ action: nextProps.attributes.action, fields: nextProps.attributes.fields });
	}

	self.componentDidMount = function () {
		console.log('List -> componentDidMount',props);
		self.setState({ action: props.attributes.action, fields: props.attributes.fields });
	};

	self.componentWillUnmount = function () {
		
	};

	self.render = function () {
		var rendered = [];
		for (var i = 0; i < self.state.fields.length; i++) {
			let item = self.state.fields[i];
			rendered.push(wp.element.createElement(IspconfigField, { style: { display: 'inline-block' }, action: self.state.action, field: item, onDelete: onFieldDelete.bind(this, i), onFieldChanged: onFieldChanged.bind(this, i) }));
		}

		rendered.push( wp.element.createElement(IspconfigSubmit, { submission: self.state.submission, onSubmitChanged: onSubmitChanged.bind(this)}));

		return wp.element.createElement("div", null, rendered);
	}
}

const elIcon = wp.element.createElement('svg', { width: 20, height: 15, viewBox: '0 0 20 15' }, 
	wp.element.createElement('path', {
		d: 'M 5.3048465,14.970562 V 12.93761 l 1.6768945,-0.0011 5.653e-4,0.372631 c 0,0 4.6906337,6.02e-4 6.4569827,0 v -0.92398 C 9.4023678,12.358811 5.3640408,12.377639 1.3279806,12.317011 0.28622115,12.387807 -0.08461699,11.289746 0.0158199,10.436723 0.01868566,7.5879543 0.01017125,4.7390348 0.01996225,1.8904169 0.01619648,0.93745001 0.75222413,-0.06266199 1.7734261,0.01461101 c 5.703932,0.04217 11.4086539,-0.0037 17.1122209,-0.0037 0.827596,0.120882 1.223545,1.02643659 1.088328,1.78497589 -0.03127,2.9991735 0.06576,6.002452 -0.08572,8.9986511 0.01959,1.01962 -1.053353,1.621993 -1.969736,1.582226 -0.492126,0.01883 -0.984724,0.0075 -1.477076,0.0113 V 10.539854 H 18.03107 V 2.0384118 H 1.7816957 V 10.539792 H 14.255958 c 1.318901,0.47042 1.522367,2.208475 1.033742,3.351801 -0.234608,0.561401 -0.643984,1.250387 -1.339862,1.08281 -2.881725,-2.26e-4 -5.7634492,-4.9e-4 -8.645176,-7.16e-4 z',
		style: {'fill': '#d40000'}
	}),
	wp.element.createElement('path', {
		d: 'M 5.2965279,8.2844714 V 9.6895939 H 6.981741 V 8.2844714 Z',
		style: {'fill': '#666666'}
	})
);

wp.blocks.registerBlockType('ole1986/ispconfig-block', {
	title: 'ISPconfig Fields',
	icon: elIcon,
	category: 'common',
	attributes: {
		fields: { type: 'array', default: [] },
		submission: { type: 'object', default: {}},
		action: { type: 'string', default: 'action_create_client'},
		updated: { type: 'number' },
		description: { type: 'string', default: 'Please select an option' }
	},
	edit: function (props) {
		var actionControls = [
			{
				name: wp.i18n.__('action_create_client', 'wp-ispconfig3'),
				description: 'Provide a form to create a new client login',
				id: 'action_create_client', 
				fields: ['client_username', 'client_contact_name', 'client_company_name', 'client_email', 'client_password', 'client_template_master']
			},
			{
				name: wp.i18n.__('action_create_website', 'wp-ispconfig3'),
				description: 'Provide a form to create a new website based on a given client login',
				id: 'action_create_website',
				fields: ['client_username', 'website_domain']
			},
			{
				name: wp.i18n.__('action_create_database', 'wp-ispconfig3'),
				description: 'Provide a form to create a new database based on a given client login',
				id: 'action_create_database',
				fields: ['client_username', 'database_name', 'database_user', 'database_password']
			},
			{
				name: wp.i18n.__('action_update_client', 'wp-ispconfig3'),
				description: 'Provide a form to update general client information',
				id: 'action_update_client',
				fields: [ 'client_username', 'client_contact_name', 'client_company_name', 'client_language','client_street', 'client_zip', 'client_city', 'client_telephone']
			},
			{
				name: wp.i18n.__('action_update_client_bank', 'wp-ispconfig3'),
				description: 'Provide a form to update client bank details',
				id: 'action_update_client_bank',
				fields: [ 'client_username', 'client_bank_account_owner', 'client_bank_name', 'client_bank_account_iban', 'client_bank_account_swift']
			},
			{
				name: wp.i18n.__('action_check_domain', 'wp-ispconfig3'),
				description: 'Validate a domain and check for availibility',
				id: 'action_check_domain',
				fields: ['domain_name']
			},
			{
				name: wp.i18n.__('action_lock_client', 'wp-ispconfig3'),
				description: 'Provide a form to lock a client account base on the given login',
				id: 'action_lock_client',
				fields: ['client_username']
			},
			{
				name: wp.i18n.__('action_unlock_client', 'wp-ispconfig3'),
				description: 'Provide a form to unlock a client account base on the given login',
				id: 'action_unlock_client',
				fields: ['client_username']
			}
		];

		function changeAction(controlId) {
			var curAction = actionControls.filter(function(a) { return a.id === controlId; })[0];

			var newFields = [];

			curAction.fields.forEach(function(field_id) {
				var field = { id: field_id, value: '', computed: '', password: false, hidden: false, readonly: false, protected: false };

				if (['action_update_client','action_update_client_bank', 'action_create_database','action_locknow_client', 'action_create_website', 'action_create_mail'].indexOf(controlId) > -1 && field_id === 'client_username') {
					field.protected = true;
					field.computed = 'wp-user';
				}

				if (['domain_name'].indexOf(field_id) > -1) {
					field.protected = true;
				}

				if (['client_password', 'database_password'].indexOf(field_id) > -1) {
					field.password = true;
				}

				newFields.push(field);
			});

			props.setAttributes({ action: controlId, description: curAction.description, fields: newFields });
		}

		return wp.element.createElement(
			"div",
			{className: 'ispconfig-block'},
			wp.element.createElement('label', null, 'Choose option'),
			wp.element.createElement('div', null,
				wp.element.createElement(wp.components.TreeSelect, { className: 'ispconfig-block-inline', style: { 'max-width': '200px' }, tree: actionControls, selectedId: props.attributes.action, onChange: changeAction }),
				wp.element.createElement('div', { className: 'ispconfig-block-inline ispconfig-block-info'}, props.attributes.description),
			),
			wp.element.createElement(IspconfigFieldList, props),
			wp.element.createElement('div', {style: {'margin-top': '1em', 'font-size': '90%'}}, 'Learn more about customizing the field input using wordpress filters or shortcodes - ', wp.element.createElement('a', {target: '_blank', href: 'https://github.com/ole1986/wp-ispconfig3/wiki'}, 'Click here for details'))
		);
	},
	save: function () {
		return null;
	}
})