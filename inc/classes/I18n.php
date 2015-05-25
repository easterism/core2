<?
    require_once "Zend/Translate.php";

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

    protected $translate;


    /**
     * @return void
     *
     * инициализируется свойство $translate
     */
	public function __construct(Zend_Config $config)
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
                $this->setup(array(
                        'adapter' => $config->translate->adapter,
                        'content' => DOC_ROOT . $content,
                        'locale'  => $lng
                ));
            } catch (Zend_Translate_Exception $e) {
                Error::Exception($e->getMessage());
            }
        }
        Zend_Registry::set('translate', $this);
	}


    /**
     * Добавляем все имеющиеся варианты перевода текста и определяем язык пользователя
     *
     * @return void
     */
	public function setup($config)
	{
        if ($config['locale'] == 'ru') return;
        $this->translate = new Zend_Translate($config);
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
