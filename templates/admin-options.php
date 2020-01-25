<?php if ($isUpdated):?>
<div class="updated"><p><strong>Настройки сохранены</strong></p></div>
<?php endif?>
<?php if ($isDeleted):?>
<div class="updated"><p><strong>Данные удалены</strong></p></div>
<?php endif?>
<?php if ($isError):?>
<div class="updated"><p><strong>Ошибка</strong></p></div>
<?php endif?>

<style>
	#form-ajax {
		position: relative;
	}
	.ajax-gif {
		display: none;
		position: absolute;
		top: 0;
		left: 131px;
		margin-left: 10px;
	}
</style>

<div class="wrap">
	<h2>IE Affiliate Shop - Настройки</h2>
	<h3>Импорт</h3>
	<?php if (CpaimpImport::checkCurl() || CpaimpImport::checkFileGetContentsCurl()):?>
	<div style="border: 1px solid #aaa; padding: 7px;">
		Необходимо в крон добавить запуск модуля импорта:<br /><br />
		<b>GET <?php echo admin_url( 'admin-ajax.php' )?>?action=parse_url&code=<?php echo IM_Config::init()->get('ps_access_code'); ?></b><br />
		<br />
		Либо запустите импорт товаров вручную:<br />
		<br />
		<p>Дождитесь, чтобы импорт товаров закончился, иначе Ваша выгрузка будет неполной.</p>
		<form method="post" action="<?php echo admin_url( 'admin-ajax.php' )?>?action=get_direct" target="_blank">
			<input type="hidden" class="code" name="code" value="<?php echo IM_Config::init()->get('ps_access_code'); ?>" />
			<input type="submit" class="button-primary yandex" value="Создать выгрузку для Excel" />
		</form>
	</div>
	<?php else:?>
	<p style="color:red">
		Внимание! Невозможно импортировать файл.
		Что решить эту проблему вам необходимо предпринять одно из следующих действий:
		<ul>
		<li>— Либо установить на сервере расширение для php <a href="http://www.php.net/manual/ru/book.curl.php" target="_blank">cUrl</a></li>
		<li>— Либо Включить в php.ini <a href="http://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen" target="_blank">allow_url_fopen</a></li>
		</ul>
	</p>
	<?php endif?>

	<?php if ($categoriesNumber+$productsNumber > 0):?>
	<h3>Удаление данных</h3>
	<div style="border: 1px solid #aaa; padding: 7px;">
		<p>В базе сейчас:</p>
		<p><b><?php echo $categoriesNumber?></b> категорий</p>
		<p><b><?php echo $productsNumber?></b> товаров</p>
		<form method="post" action="">
			<input type="hidden" name="action" value="delete"/>
			<p style="color:red"><input type="checkbox" name="agree" value="1" id="input-agree"/> <label for="input-agree">Подтверждаю, что хочу удалить выбранные записи из базы данных навсегда без возможности восстановления</label></p>
			<select name="type" id="">
				<option value="all">Всё</option>    
			</select>
			<input type="submit" class="button-primary" value="Удалить" />
		</form>
	</div>
	<?php endif?>
</div>
