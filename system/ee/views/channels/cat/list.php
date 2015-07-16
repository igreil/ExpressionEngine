<?php $this->extend('_templates/default-nav'); ?>

<div class="tbl-ctrls">
	<?=form_open($base_url)?>
		<fieldset class="tbl-search right">
			<a class="btn tn action" href="<?=ee('CP/URL', 'channels/cat/create-cat/'.$cat_group->group_id)?>"><?=lang('create_new')?></a>
		</fieldset>
		<h1><?=$cp_page_title?></h1>
		<?=ee('Alert')->getAllInlines()?>
		<div class="tbl-list-wrap">
			<?php if (count($categories->children()) != 0): ?>
				<div class="tbl-list-ctrl">
					<label class="ctrl-all"><span>select all</span> <input type="checkbox"></label>
				</div>
			<?php endif ?>
			<div class="nestable">
				<ul class="tbl-list">
					<?php foreach ($categories->children() as $category): ?>
						<?php $this->view('channels/cat/_category', array('category' => $category)); ?>
					<?php endforeach ?>
					<?php if (count($categories->children()) == 0): ?>
						<li>
							<div class="tbl-row no-results">
								<div class="none">
									<p><?=lang('categories_not_found')?> <a class="btn action" href="<?=ee('CP/URL', 'channels/cat/create-cat/'.$cat_group->group_id)?>"><?=lang('create_category_btn')?></a></p>
								</div>
							</div>
						</li>
					<?php endif ?>
				</ul>
			</div>
		</div>
		<fieldset class="tbl-bulk-act">
			<select name="bulk_action">
				<option>-- <?=lang('with_selected')?> --</option>
				<option value="remove" data-confirm-trigger="selected" rel="modal-confirm-remove"><?=lang('remove')?></option>
			</select>
			<input class="btn submit" data-conditional-modal="confirm-trigger" type="submit" value="<?=lang('submit')?>">
		</fieldset>
	</form>
</div>

<?php $this->startOrAppendBlock('modals'); ?>

<?php

$modal_vars = array(
	'name'		=> 'modal-confirm-remove',
	'form_url'	=> ee('CP/URL', 'channels/cat/remove-cat'),
	'hidden'	=> array(
		'bulk_action'	=> 'remove',
		'cat_group_id'	=> $cat_group->group_id
	)
);

$this->ee_view('_shared/modal_confirm_remove', $modal_vars);
?>

<?php $this->endBlock(); ?>
