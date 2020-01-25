<?php
/**
 * Класс, которые сохраняет и отдаёт значения конфига (Хранит настройки в wordpress)
 */
class IM_Config
{
	protected $_defaultData = array(
		'ps_url'             => '',
		'ps_get_enable'      => 1,
		'ps_access_code'     => '',
		'import_price'       => '',
		'import_title'       => '',
		'import_vendor'      => '',
		'import_categorypid'  => '',
		'import_categoryid'  => '',
		'ps_download_images' => 0,
		'ps_delete_prod'     => 0,
		'ps_delete_cats'     => 0,
		'ps_offer'           => 0,
	);

	protected $_data = array();

	protected function __construct(){}

	public function get($option)
	{
		if (!array_key_exists($option, $this->_data))
		{
			$this->_data[$option] = get_option($option);
		}
		return $this->_data[$option];
	}

	public function set($option, $value)
	{
		$this->_data[$option] = $value;
		update_option($option, $value);
	}

	/**
	 * Базовые опции. Инициализация происходит один раз, при активации плагина.
	 */
	public function activate()
	{
		foreach($this->_defaultData as $option => $value)
		{
			/**
			 * Генерация уникального ключа доступа.
			 */
			if ($option == 'ps_access_code')
			{
				$value = md5(rand(1, 10000).rand(1, 1000).time());
			}
			update_option($option, $value);
		}
	}

	/**
	 * Сброс опций. Вызывается при отключении плагина.
	 */
	public function deactivate()
	{
		foreach($this->_defaultData as $option => $value)
		{
			/**
			 * Генерация уникального ключа доступа.
			 */
			if ($option == 'ps_access_code')
			{
				$value = md5(rand(1, 10000).rand(1, 1000).time());
			}
			delete_option($option, $value);
		}
	}

	/**
	 * Закрываем конструктор и прикручиваем паттерн Singleton.
	 * @static
	 * @return IM_Config
	 */
	public static function init()
	{
		static $self;
		if (!is_object($self))
		{
			$self = new self();
		}
		return $self;
	}
}