<?
class initList extends Acl {

	public $deleteAction			= "";
	public $date_mask				= "dd-mm-yyyy";
	public $recordsPerPage			= 25;
	public $multiEdit				= "";
	public $paintClor				= "ffeeee";
	public $printImage				= "";
	public $aboutImage				= "";
	public $classText				= array('SQL_ERROR'=>'',
										'SEARCH'=>'поиск',
										'CLEAR'=>'очистить',
										'START_SEARCH'=>'Начать поиск',
										'ALL'=>'все',
										'EDIT'=>'Редактировать',
										'ADD'=>'Добавить',
										'DELETE'=>'Удалить',
										'NUM'=>'№',
										'FROM'=>'из',
										'TOTAL'=>'Всего',
										'NORESULT'=>'Нет записей',
										'PAGIN_ALL'=>'все',
										'DELETE_MSG'=>'Вы действительно хотите удалить эту запись?'
										);
	public function __construct() {
		parent::__construct();
	}
}

class initEdit extends Acl {
	public $tableClass				= "editTable";
	public $date_mask				= "dd-mm-yyyy";
	public $imgDir   				= "core/img";
	public $FCKPath					= "core/ext";
	public $CKConf					= array('language' => "ru",
											'baseHref' => "/",
											'skin' => "v2",
											'toolbar_Full' => array(
												array('Source','-','Undo','Redo','-','Preview','Templates'),
											    array('Cut','Copy','Paste','PasteText','PasteFromWord','-','SpellChecker', 'Scayt'),
											    array('Find','Replace','-','SelectAll','RemoveFormat'),
											    array('Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'),
											    '/',
											    array('Bold','Italic','Underline','Strike','-','Subscript','Superscript'),
											    array('NumberedList','BulletedList','-','Outdent','Indent','Blockquote'),
											    array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'),
											    array('Link','Unlink','Anchor'),
											    array('Image','Flash','Table','HorizontalRule','SpecialChar','PageBreak'),
											    '/',
											    array('Styles','Format','Font','FontSize'),
											    array('TextColor','BGColor'),
											    array('Maximize', 'ShowBlocks')
											),
											'toolbar_noform' => array(
												array('Undo','Redo','-','Preview'),
												array('Cut','Copy','Paste','PasteText','PasteFromWord','-','SpellChecker', 'Scayt'),
												array('Find','Replace','-','SelectAll','RemoveFormat'),
												array('Link','Unlink','Anchor'),
												array('Image','Table','HorizontalRule','SpecialChar','PageBreak'),
												'/',
												array('Bold','Italic','Underline','Strike','-','Subscript','Superscript'),
												array('NumberedList','BulletedList','-','Outdent','Indent','Blockquote'),
												array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'),
												array('TextColor','BGColor'),
												array('Maximize', 'ShowBlocks'),
												'/',
												array('Styles','Format','Font','FontSize'),
												
											),
										);
	public $MCEConf		= array(
		'theme' => "advanced"
	);
	public $back					= "";
	public $firstColWidth			= "";
	public $classText				= array('SAVE' => 'Сохранить',
											'MODAL_BUTTON' => 'Выбрать',
											'noReadAccess' => 'Нет доступа для чтения этой записи'
										);
	public function __construct() {
		parent::__construct();
	}
}

class initTabs {
	public $classText					= array('DISABLED' => 'Эта вкладка неактивна'
										);
}
