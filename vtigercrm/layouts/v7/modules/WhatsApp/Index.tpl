<div class="container-fluid">
	<h2>{if $TRANSPORT eq 'meta'}WhatsApp Business API{else}WhatsApp Mock Gateway{/if}</h2>
	<div class="alert alert-info">
		{if $TRANSPORT eq 'meta'}
			Official Meta transport is selected. Live sends are queued only when explicitly enabled and fully configured. Approved templates for messages outside the customer-service window are not implemented.
		{else}
			Demo only: messages are stored locally. Nothing is sent to WhatsApp or an external provider.
		{/if}
	</div>

	{if $RESULT eq 'sent'}<div class="alert alert-success">Mock message saved as Sent.</div>{/if}
	{if $RESULT eq 'queued'}<div class="alert alert-success">Message queued for the official provider. Check status for provider acceptance or delivery updates.</div>{/if}
	{if $RESULT eq 'matched'}<div class="alert alert-success">Inbound mock event received and matched to one CRM record.</div>{/if}
	{if $RESULT eq 'unmatched'}<div class="alert alert-warning">Inbound event saved, but no Lead or Contact matched that phone number.</div>{/if}
	{if $RESULT eq 'ambiguous'}<div class="alert alert-warning">Inbound event saved as unmatched because multiple CRM records share that phone number.</div>{/if}
	{if $RESULT eq 'duplicate'}<div class="alert alert-info">Duplicate event detected; it was not processed twice.</div>{/if}
	{if $RESULT eq 'status_updated'}<div class="alert alert-success">Mock delivery status updated.</div>{/if}

	<div class="row">
		<div class="col-md-6">
			<div class="panel panel-default">
				<div class="panel-heading"><strong>Simulate outbound message</strong></div>
				<div class="panel-body">
					<form method="post" action="index.php" class="form-horizontal">
						<input type="hidden" name="module" value="WhatsApp">
						<input type="hidden" name="action" value="Send">
						<input type="hidden" name="request_id" value="{$SEND_TOKEN|escape:'html'}">
						{if $SOURCE_RECORD}
							<input type="hidden" name="sourceModule" value="{$SOURCE_RECORD.module|escape:'html'}">
							<input type="hidden" name="sourceRecord" value="{$SOURCE_RECORD.recordid|escape:'html'}">
							<p>Recipient: {$SOURCE_RECORD.label|escape:'html'} ({$SOURCE_RECORD.phone|escape:'html'})</p>
						{else}
							<div class="form-group">
								<label class="col-sm-3 control-label">Phone</label>
								<div class="col-sm-9"><input class="form-control" name="phone" required maxlength="40" placeholder="+1 555 0100"></div>
							</div>
						{/if}
						<div class="form-group">
							<label class="col-sm-3 control-label">Message</label>
							<div class="col-sm-9"><textarea class="form-control" name="message" required maxlength="4096" rows="4"></textarea></div>
						</div>
						{if $TRANSPORT eq 'meta'}
							<div class="form-group"><div class="col-sm-offset-3 col-sm-9">
								<label><input type="checkbox" name="consent_acknowledged" value="1" required> Recipient opted in to WhatsApp messages</label>
							</div></div>
						{/if}
						<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary" type="submit">{if $TRANSPORT eq 'meta'}Queue WhatsApp message{else}Simulate send{/if}</button></div></div>
					</form>
				</div>
			</div>
		</div>
		{if $TRANSPORT eq 'mock'}
		<div class="col-md-6">
			<div class="panel panel-default">
				<div class="panel-heading"><strong>Simulate inbound webhook event</strong></div>
				<div class="panel-body">
					<form method="post" action="index.php" class="form-horizontal">
						<input type="hidden" name="module" value="WhatsApp">
						<input type="hidden" name="action" value="Inbound">
						<div class="form-group">
							<label class="col-sm-3 control-label">Event ID</label>
							<div class="col-sm-9"><input class="form-control" name="event_id" required minlength="8" maxlength="100" value="demo-{$EVENT_TOKEN|escape:'html'}"></div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">From phone</label>
							<div class="col-sm-9"><input class="form-control" name="phone" required maxlength="40"></div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Message</label>
							<div class="col-sm-9"><textarea class="form-control" name="message" required maxlength="4096" rows="3"></textarea></div>
						</div>
						<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-default" type="submit">Simulate inbound event</button></div></div>
					</form>
				</div>
				{/if}
			</div>
		</div>
	</div>

	<h3>{if $TRANSPORT eq 'meta'}Recent WhatsApp messages{else}Recent mock messages{/if}</h3>
	<p id="whatsapp-live-status" role="status" aria-live="polite">Live updates check every 5 seconds while this page is open.</p>
	<div class="table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Time</th><th>Direction</th><th>Phone</th><th>Message</th><th>Status</th><th>CRM match</th><th>Delivery note</th><th>Simulate status</th></tr></thead>
			<tbody id="whatsapp-messages-body">
				{foreach from=$MESSAGES item=ROW}
					<tr>
						<td>{$ROW.created_at|escape:'html'}</td>
						<td>{$ROW.direction|escape:'html'}</td>
						<td>{$ROW.phone|escape:'html'}</td>
						<td>{$ROW.message|escape:'html'}</td>
						<td>{$ROW.status|escape:'html'}</td>
						<td>{if $ROW.related_module}{$ROW.related_module|escape:'html'} #{$ROW.related_recordid|escape:'html'}{else}{$ROW.match_state|escape:'html'}{/if}</td>
						<td>{$ROW.last_error|escape:'html'}</td>
						<td>
							{if $ROW.transport eq 'mock' && $ROW.direction eq 'OUT' && $ROW.status eq 'SENT'}
								{foreach from=$OUTBOUND_TRANSITIONS item=NEXT_STATUS}
									<form method="post" action="index.php" style="display:inline">
										<input type="hidden" name="module" value="WhatsApp"><input type="hidden" name="action" value="Status">
										<input type="hidden" name="message_id" value="{$ROW.message_id|escape:'html'}">
										<input type="hidden" name="status" value="{$NEXT_STATUS}">
										<button class="btn btn-xs btn-default" type="submit">{$NEXT_STATUS|escape:'html'}</button>
									</form>
								{/foreach}
							{elseif $ROW.transport eq 'mock' && $ROW.direction eq 'OUT' && $ROW.status eq 'DELIVERED'}
								<form method="post" action="index.php" style="display:inline">
									<input type="hidden" name="module" value="WhatsApp"><input type="hidden" name="action" value="Status">
									<input type="hidden" name="message_id" value="{$ROW.message_id|escape:'html'}">
									<input type="hidden" name="status" value="READ">
									<button class="btn btn-xs btn-default" type="submit">READ</button>
								</form>
							{/if}
						</td>
					</tr>
				{foreachelse}
					<tr><td colspan="8" class="text-center">No messages yet.</td></tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>
<script>
(function () {
	var body = document.getElementById('whatsapp-messages-body');
	var liveStatus = document.getElementById('whatsapp-live-status');
	var refreshUrl = 'index.php?module=WhatsApp&action=Messages';
	var refreshInProgress = false;
	var lastSnapshot = null;
	var csrfField = document.querySelector('form input[name="__vtrftk"]');
	var csrfName = csrfField ? csrfField.name : '__vtrftk';
	var csrfToken = csrfField ? csrfField.value : '';

	function addCell(row, value) {
		var cell = document.createElement('td');
		cell.textContent = value === null || typeof value === 'undefined' ? '' : String(value);
		row.appendChild(cell);
		return cell;
	}

	function addStatusForm(cell, messageId, status) {
		var form = document.createElement('form');
		form.method = 'post';
		form.action = 'index.php';
		form.style.display = 'inline';

		[
			['module', 'WhatsApp'],
			['action', 'Status'],
			['message_id', messageId],
			['status', status],
			[csrfName, csrfToken]
		].forEach(function (field) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = field[0];
			input.value = field[1];
			form.appendChild(input);
		});

		var button = document.createElement('button');
		button.type = 'submit';
		button.className = 'btn btn-xs btn-default';
		button.textContent = status;
		form.appendChild(button);
		cell.appendChild(form);
	}

	function renderMessages(messages) {
		var fragment = document.createDocumentFragment();
		if (!messages.length) {
			var emptyRow = document.createElement('tr');
			var emptyCell = addCell(emptyRow, 'No mock messages yet.');
			emptyCell.colSpan = 8;
			emptyCell.className = 'text-center';
			fragment.appendChild(emptyRow);
		} else {
			messages.forEach(function (message) {
				var row = document.createElement('tr');
				addCell(row, message.created_at);
				addCell(row, message.direction);
				addCell(row, message.phone);
				addCell(row, message.message);
				addCell(row, message.status);
				addCell(row, message.related_module
					? message.related_module + ' #' + message.related_recordid
					: message.match_state);
				addCell(row, message.last_error);

				var actions = document.createElement('td');
				if (message.transport === 'mock' && message.direction === 'OUT' && message.status === 'SENT') {
					addStatusForm(actions, message.message_id, 'DELIVERED');
					addStatusForm(actions, message.message_id, 'FAILED');
				} else if (message.transport === 'mock' && message.direction === 'OUT' && message.status === 'DELIVERED') {
					addStatusForm(actions, message.message_id, 'READ');
				}
				row.appendChild(actions);
				fragment.appendChild(row);
			});
		}
		body.replaceChildren(fragment);
	}

	function refreshMessages() {
		if (refreshInProgress || document.hidden) {
			return;
		}
		refreshInProgress = true;
		fetch(refreshUrl, {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { Accept: 'application/json' }
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		}).then(function (data) {
			if (!data || !Array.isArray(data.messages)) {
				throw new Error('Unexpected message response');
			}
			if (data.csrf && data.csrf.name && data.csrf.token) {
				csrfName = data.csrf.name;
				csrfToken = data.csrf.token;
			}
			var snapshot = JSON.stringify(data.messages);
			if (snapshot !== lastSnapshot) {
				renderMessages(data.messages);
				lastSnapshot = snapshot;
			}
			liveStatus.textContent = 'Live updates are active. Last checked ' + new Date().toLocaleTimeString() + '.';
		}).catch(function () {
			liveStatus.textContent = 'Live updates are unavailable. The page will retry automatically.';
		}).then(function () {
			refreshInProgress = false;
		});
	}

	window.setInterval(refreshMessages, 5000);
	document.addEventListener('visibilitychange', refreshMessages);
	refreshMessages();
}());
</script>
