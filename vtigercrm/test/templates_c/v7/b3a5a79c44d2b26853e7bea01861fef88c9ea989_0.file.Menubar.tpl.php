<?php
/* Smarty version 4.5.5, created on 2026-11-08 08:01:55
  from '/var/www/html/layouts/v7/modules/Documents/partials/Menubar.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.5',
  'unifunc' => 'content_6af02c73598e80_10471658',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b3a5a79c44d2b26853e7bea01861fef88c9ea989' => 
    array (
      0 => '/var/www/html/layouts/v7/modules/Documents/partials/Menubar.tpl',
      1 => 1752039682,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6af02c73598e80_10471658 (Smarty_Internal_Template $_smarty_tpl) {
if ($_smarty_tpl->tpl_vars['REQ']->value->get('view') == 'Detail') {?>
<div id="modules-menu" class="modules-menu">    
    <ul>
        <li class="active">
            <a href="<?php echo $_smarty_tpl->tpl_vars['MODULE_MODEL']->value->getListViewUrl();?>
">
				<?php echo $_smarty_tpl->tpl_vars['MODULE_MODEL']->value->getModuleIcon();?>

                <span><?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
</span>
            </a>
        </li>
    </ul>
</div>
<?php }
}
}
