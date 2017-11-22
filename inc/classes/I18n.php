<?
namespace Core2;
/**
 * Локализация core2
 *
 * Класс для переводов текста.
 *
 * @package    Сlasses
 * @subpackage I18n
 */
class I18n
{

    private $translate;
    private $locale;


    /**
     * @return void
     *
     * инициализируется свойство $translate
     */
	public function __construct(\Zend_Config $config)
	{
        if (isset($config->translate) && $config->translate->on) {
            try {
                if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                    $lng = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
                }
                if ($config->translate->locale) $lng = $config->translate->locale;
                if ($config->translate->adapter == 'gettext') {
                    $content = "core2/translations/$lng.mo";
                } else {
                    Error::Exception("Адаптер перевода не поддерживается");
                }
                $this->locale = $lng;
                $this->setup(array(
                        'adapter' => $config->translate->adapter,
                        'content' => DOC_ROOT . $content,
                        'locale'  => $lng
                ));
            } catch (\Zend_Translate_Exception $e) {
                Error::Exception($e->getMessage());
            }
        }
        \Zend_Registry::set('translate', $this);
	}


    /**
     * Добавляем все имеющиеся варианты перевода текста и определяем язык пользователя
     *
     * @return void
     */
	public function setup($config)
	{
        if ($config['locale'] == 'ru') return;
        $this->translate = new \Zend_Translate($config);
	}

    /**
     * Проверяет, создан ли объект для переводов
     * @return mixed
     */
    public function isSetup()
    {
        return $this->translate;
    }


	/**
     * Определяет язык пользователя
	 *
	 * @param $lng
     * @return void
	 */
	public function setLocale($lng)
	{
		$this->translate->setLocale($lng);
        $this->locale = $lng;
	}

    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Добавление переводов для модулей
     * @param $location
     */
    public function setupExtra($location) {
        $ini = $location . "/conf.ini";
        if ($this->translate && is_dir($location . "/translations") && file_exists($ini)) {
            $temp = parse_ini_file($ini, true);
            $goit = false;
            foreach ($temp as $k => $v) {
                $k = explode(":", $k);
                if ($_SERVER['SERVER_NAME'] == trim($k[0])) {
                    $goit = true;
                    break;
                }
            }
            if ($goit) {
                $config = new \Zend_Config_Ini($location . "/conf.ini", $_SERVER['SERVER_NAME']);
            } else {
                $config = new \Zend_Config_Ini($location . "/conf.ini", 'production');
            }
            if (isset($config->translate) && $config->translate->on) {

                $lng = $this->getLocale();
                if ($config->translate->adapter == 'gettext') {
                    $content = $location . "/translations/$lng.mo";
                } else {
                    Error::Exception("Адаптер перевода модуля не поддерживается");
                }
                try {
                    $translate_second = new \Zend_Translate(
                            array(
                                    'adapter' => $config->translate->adapter,
                                    'content' => $content,
                                    'locale'  => $lng
                            )
                    );
                    $this->translate->addTranslation(
                            array(
                                    'content' => $translate_second,
                                    'locale'  => $lng
                            )
                    );
                    unset($translate_second);
                } catch (\Zend_Translate_Exception $e) {
                    Error::Exception($e->getMessage());
                }
                \Zend_Registry::set('translate', $this);
            }
        }
    }


	/**
	 * Получение перевода с английского на язык пользователя
     *
	 * @param   string $str   Строка на английском, которую следует перевести на язык пользователя
	 * @param   string $categ Категория к которой относится строка(необязательный параметр)
	 * @return  string        Переведеная строка (если перевод не найден, возращает $str)
	 */
	public function tr($str, $categ = "")
	{
        if (!$this->translate) {
            return $str;
        }
		return $this->translate->_($str);
	}
}
