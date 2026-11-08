<?php
/* Smarty version 4.5.5, created on 2026-11-08 13:31:05
  from '/var/www/html/layouts/v7/modules/WhatsApp/Index.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.5',
  'unifunc' => 'content_6af079998cfcf1_53325109',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6df61ee3165e0def8e276f01d9bf0a1b61f6eaa4' => 
    array (
      0 => '/var/www/html/layouts/v7/modules/WhatsApp/Index.tpl',
      1 => 1794144409,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6af079998cfcf1_53325109 (Smarty_Internal_Template $_smarty_tpl) {
?><div class="container-fluid">
	<h2>WhatsApp Mock Gateway</h2>
	<div class="alert alert-info">
		Demo only: messages are stored locally. Nothing is sent to WhatsApp or an external provider.
	</div>

	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'sent') {?><div class="alert alert-success">Mock message saved as Sent.</div><?php }?>
	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'matched') {?><div class="alert alert-success">Inbound mock event received and matched to one CRM record.</div><?php }?>
	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'unmatched') {?><div class="alert alert-warning">Inbound event saved, but no Lead or Contact matched that phone number.</div><?php }?>
	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'ambiguous') {?><div class="alert alert-warning">Inbound event saved as unmatched because multiple CRM records share that phone number.</div><?php }?>
	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'duplicate') {?><div class="alert alert-info">Duplicate event detected; it was not processed twice.</div><?php }?>
	<?php if ($_smarty_tpl->tpl_vars['RESULT']->value == 'status_updated') {?><div class="alert alert-success">Mock delivery status updated.</div><?php }?>

	<div class="row">
		<div class="col-md-6">
			<div class="panel panel-default">
				<div class="panel-heading"><strong>Simulate outbound message</strong></div>
				<div class="panel-body">
					<form method="post" action="index.php" class="form-horizontal">
						<input type="hidden" name="module" value="WhatsApp">
						<input type="hidden" name="action" value="Send">
						<input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['SEND_TOKEN']->value, ENT_QUOTES, 'UTF-8', true);?>
">
						<?php if ($_smarty_tpl->tpl_vars['SOURCE_RECORD']->value) {?>
							<input type="hidden" name="sourceModule" value="<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['SOURCE_RECORD']->value['module'], ENT_QUOTES, 'UTF-8', true);?>
">
							<input type="hidden" name="sourceRecord" value="<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['SOURCE_RECORD']->value['recordid'], ENT_QUOTES, 'UTF-8', true);?>
">
							<p>Recipient: <?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['SOURCE_RECORD']->value['label'], ENT_QUOTES, 'UTF-8', true);?>
 (<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['SOURCE_RECORD']->value['phone'], ENT_QUOTES, 'UTF-8', true);?>
)</p>
						<?php } else { ?>
							<div class="form-group">
								<label class="col-sm-3 control-label">Phone</label>
								<div class="col-sm-9"><input class="form-control" name="phone" required maxlength="40" placeholder="+1 555 0100"></div>
							</div>
						<?php }?>
						<div class="form-group">
							<label class="col-sm-3 control-label">Message</label>
							<div class="col-sm-9"><textarea class="form-control" name="message" required maxlength="4096" rows="4"></textarea></div>
						</div>
						<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary" type="submit">Simulate send</button></div></div>
					</form>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="panel panel-default">
				<div class="panel-heading"><strong>Simulate inbound webhook event</strong></div>
				<div class="panel-body">
					<form method="post" action="index.php" class="form-horizontal">
						<input type="hidden" name="module" value="WhatsApp">
						<input type="hidden" name="action" value="Inbound">
						<div class="form-group">
							<label class="col-sm-3 control-label">Event ID</label>
							<div class="col-sm-9"><input class="form-control" name="event_id" required minlength="8" maxlength="100" value="demo-<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['EVENT_TOKEN']->value, ENT_QUOTES, 'UTF-8', true);?>
"></div>
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
			</div>
		</div>
	</div>

	<h3>Recent mock messages</h3>
	<p id="whatsapp-live-status" role="status" aria-live="polite">Live updates check every 5 seconds while this page is open.</p>
	<div class="table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Time</th><th>Direction</th><th>Phone</th><th>Message</th><th>Status</th><th>CRM match</th><th>Simulate status</th></tr></thead>
			<tbody id="whatsapp-messages-body">
				<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['MESSAGES']->value, 'ROW');
$_smarty_tpl->tpl_vars['ROW']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['ROW']->value) {
$_smarty_tpl->tpl_vars['ROW']->do_else = false;
?>
					<tr>
						<td><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['created_at'], ENT_QUOTES, 'UTF-8', true);?>
</td>
						<td><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['direction'], ENT_QUOTES, 'UTF-8', true);?>
</td>
						<td><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['phone'], ENT_QUOTES, 'UTF-8', true);?>
</td>
						<td><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['message'], ENT_QUOTES, 'UTF-8', true);?>
</td>
						<td><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['status'], ENT_QUOTES, 'UTF-8', true);?>
</td>
						<td><?php if ($_smarty_tpl->tpl_vars['ROW']->value['related_module']) {
echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['related_module'], ENT_QUOTES, 'UTF-8', true);?>
 #<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['related_recordid'], ENT_QUOTES, 'UTF-8', true);
} else {
echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['match_state'], ENT_QUOTES, 'UTF-8', true);
}?></td>
						<td>
							<?php if ($_smarty_tpl->tpl_vars['ROW']->value['direction'] == 'OUT' && $_smarty_tpl->tpl_vars['ROW']->value['status'] == 'SENT') {?>
								<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['OUTBOUND_TRANSITIONS']->value, 'NEXT_STATUS');
$_smarty_tpl->tpl_vars['NEXT_STATUS']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['NEXT_STATUS']->value) {
$_smarty_tpl->tpl_vars['NEXT_STATUS']->do_else = false;
?>
									<form method="post" action="index.php" style="display:inline">
										<input type="hidden" name="module" value="WhatsApp"><input type="hidden" name="action" value="Status">
										<input type="hidden" name="message_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['message_id'], ENT_QUOTES, 'UTF-8', true);?>
">
										<input type="hidden" name="status" value="<?php echo $_smarty_tpl->tpl_vars['NEXT_STATUS']->value;?>
">
										<button class="btn btn-xs btn-default" type="submit"><?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['NEXT_STATUS']->value, ENT_QUOTES, 'UTF-8', true);?>
</button>
									</form>
								<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
							<?php } elseif ($_smarty_tpl->tpl_vars['ROW']->value['direction'] == 'OUT' && $_smarty_tpl->tpl_vars['ROW']->value['status'] == 'DELIVERED') {?>
								<form method="post" action="index.php" style="display:inline">
									<input type="hidden" name="module" value="WhatsApp"><input type="hidden" name="action" value="Status">
									<input type="hidden" name="message_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->tpl_vars['ROW']->value['message_id'], ENT_QUOTES, 'UTF-8', true);?>
">
									<input type="hidden" name="status" value="READ">
									<button class="btn btn-xs btn-default" type="submit">READ</button>
								</form>
							<?php }?>
						</td>
					</tr>
				<?php
}
if ($_smarty_tpl->tpl_vars['ROW']->do_else) {
?>
					<tr><td colspan="7" class="text-center">No mock messages yet.</td></tr>
				<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
			</tbody>
		</table>
	</div>
</div>
<?php echo '<script'; ?>
>
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
			emptyCell.colSpan = 7;
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

				var actions = document.createElement('td');
				if (message.direction === 'OUT' && message.status === 'SENT') {
					addStatusForm(actions, message.message_id, 'DELIVERED');
					addStatusForm(actions, message.message_id, 'FAILED');
				} else if (message.direction === 'OUT' && message.status === 'DELIVERED') {
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
<?php echo '</script'; ?>
>
<?php }
}
