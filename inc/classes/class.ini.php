<?
require_once 'Acl.php';


/**
 * Class initList
 */
class initList extends \Core2\Acl {

	public $deleteAction   = "";
	public $date_mask	   = "dd-mm-yyyy";
	public $recordsPerPage = 25;
	public $multiEdit	   = "";
	public $paintClor	   = "ffeeee";
	public $printImage	   = "";
	public $aboutImage	   = "";
	public $classText	   = array(
        'SQL_ERROR'    => '',
        'SEARCH'       => 'поиск',
        'CLEAR'        => 'очистить',
        'START_SEARCH' => 'Начать поиск',
        'ALL'          => 'все',
        'EDIT'         => 'Редактировать',
        'ADD'          => 'Добавить',
        'DELETE'       => 'Удалить',
        'NUM'          => '№',
        'TOTAL'        => 'Всего',
        'NORESULT'     => 'Нет записей',
        'PAGIN_ALL'    => 'все',
        'SWITCH'       => 'вкл/выкл',
        'DELETE_MSG'   => 'Вы действительно хотите удалить эту запись?'
    );


    /**
     * initList constructor.
     */
	public function __construct() {
        parent::__construct();
        $mask_date = $this->getSetting('mask_date');
        if ($mask_date) {
            $this->date_mask = $mask_date;
        }
	}
}


/**
 * Class initEdit
 */
class initEdit extends \Core2\Acl {
	public $tableClass = "editTable";
	public $date_mask  = "dd-mm-yyyy";
	public $imgDir     = "core/img";
	public $FCKPath	   = "core/ext";
	public $CKConf	   = array(
        'language'       => "ru",
        'baseHref'       => "/",
        'skin'           => "v2",
        'toolbar_Full'   => array(
            array('Source', '-', 'Undo', 'Redo', '-', 'Preview', 'Templates'),
            array('Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'SpellChecker', 'Scayt'),
            array('Find', 'Replace', '-', 'SelectAll', 'RemoveFormat'),
            array('Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'),
            '/',
            array('Bold', 'Italic', 'Underline', 'Strike', '-', 'Subscript', 'Superscript'),
            array('NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', 'Blockquote'),
            array('JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'),
            array('Link', 'Unlink', 'Anchor'),
            array('Image', 'Flash', 'Table', 'HorizontalRule', 'SpecialChar', 'PageBreak'),
            '/',
            array('Styles', 'Format', 'Font', 'FontSize'),
            array('TextColor', 'BGColor'),
            array('Maximize', 'ShowBlocks')
        ),
        'toolbar_noform' => array(
            array('Undo', 'Redo', '-', 'Preview'),
            array('Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'SpellChecker', 'Scayt'),
            array('Find', 'Replace', '-', 'SelectAll', 'RemoveFormat'),
            array('Link', 'Unlink', 'Anchor'),
            array('Image', 'Table', 'HorizontalRule', 'SpecialChar', 'PageBreak'),
            '/',
            array('Bold', 'Italic', 'Underline', 'Strike', '-', 'Subscript', 'Superscript'),
            array('NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', 'Blockquote'),
            array('JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'),
            array('TextColor', 'BGColor'),
            array('Maximize', 'ShowBlocks'),
            '/',
            array('Styles', 'Format', 'Font', 'FontSize'),

        ),
    );
	public $MCEConf	      = array('theme' => "modern");
	public $back		  = "";
	public $firstColWidth = "";
	public $classText	  = [];


    /**
     * initEdit constructor.
     */
	public function __construct() {
		parent::__construct();

        $mask_date = $this->getSetting('mask_date');
        if ($mask_date) {
            $this->date_mask = $mask_date;
        }


        $this->classText = [
            'SAVE'               => parent::__get('translate')->tr('Сохранить'),
            'MODAL_BUTTON'       => parent::__get('translate')->tr('Выбрать'),
            'MODAL_BUTTON_CLEAR' => parent::__get('translate')->tr('Очистить'),
            'noReadAccess'       => parent::__get('translate')->tr('Нет доступа для чтения этой записи'),
        ];
	}
}


/**
 * Class initTabs
 */
class initTabs {
	public $classText = array('DISABLED' => 'Эта вкладка неактивна');
}
