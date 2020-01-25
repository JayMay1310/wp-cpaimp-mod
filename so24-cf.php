<?php

return [

	'path' => 'test3/', 
	'categoryidFilter' => '',
	'categorypidFilter' => '',
	'FilterPrice' => '',
	'FilterTitle' => '',
	'FilterVendor' => '',
	'offer' => 'so24',

	'replace' => "[unit=\"US\";\t] [unit=\"EUR\";\t] [unit=\"\";\t]",

	'ps_delete_prod'     => 0, //Удалять отсутствующие товары
	'ps_delete_cats'     => 0, //Удалять пустые каталоги
	'ps_offer'           => 0, //Сквозной импорт 
	'ps_get_enable'      => 1,//Обновлять по GET-запросу

];