<?php
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
     * Safe function-call dispatcher for table processing callbacks.
     * @param string $processing
     * @param array  $row
     * @param mixed  $fallback
     * @return mixed
     */
    protected function safeProcessRowFunction(string $processing, array $row, mixed $fallback = null): mixed {

        $processing = trim($processing);
        if ($processing === '') {
            return $fallback;
        }

        // only function/method-like names, no code fragments
        if ( ! preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $processing)) {
            return $fallback;
        }

        if ( ! is_callable($processing)) {
            return $fallback;
        }

        try {
            return call_user_func($processing, $row);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }


    /**
     * Safe replacement for legacy eval("if ($expr)").
     * Accepts only a strict boolean subset.
     * @param string $expr
     * @return int
     */
    protected function safeEvaluateCondition(string $expr): int {

        $expr = trim($expr);
        if ($expr === '') {
            return 0;
        }

        $lower = strtolower($expr);
        if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
            return 1;
        }
        if (in_array($lower, ['0', 'false', 'no', 'off', 'null', ''], true)) {
            return 0;
        }

        // reject anything that can contain function calls, variables, or operators not in a strict whitelist
        if (preg_match('/[^\w\s\-\.\'"=!<>\(\)&|]/u', $expr)) {
            return 0;
        }
        if (str_contains($expr, '$') || str_contains($expr, '->') || str_contains($expr, '::')) {
            return 0;
        }

        // boolean literals with optional NOT
        if (preg_match('/^!?\s*(true|false|1|0)$/i', $expr)) {
            return preg_match('/^!?\s*(true|1)$/i', $expr) ? 1 : 0;
        }

        // simple binary comparator: left OP right
        if (preg_match('/^\s*("[^"]*"|\'[^\']*\'|-?\d+(?:\.\d+)?|[\w\-\.]+)\s*(==|!=|<=|>=|<|>)\s*("[^"]*"|\'[^\']*\'|-?\d+(?:\.\d+)?|[\w\-\.]+)\s*$/u', $expr, $m)) {
            $leftRaw  = $m[1];
            $op       = $m[2];
            $rightRaw = $m[3];

            $left  = $this->normalizeConditionOperand($leftRaw);
            $right = $this->normalizeConditionOperand($rightRaw);

            if (is_numeric($left) && is_numeric($right)) {
                $left  = (float)$left;
                $right = (float)$right;
            } else {
                $left  = (string)$left;
                $right = (string)$right;
            }

            return match ($op) {
                '==' => $left == $right ? 1 : 0,
                '!=' => $left != $right ? 1 : 0,
                '<'  => $left <  $right ? 1 : 0,
                '>'  => $left >  $right ? 1 : 0,
                '<=' => $left <= $right ? 1 : 0,
                '>=' => $left >= $right ? 1 : 0,
                default => 0,
            };
        }

        return 0;
    }


    /**
     * @param string $value
     * @return string|float|int
     */
    protected function normalizeConditionOperand(string $value): string|float|int {

        $value = trim($value);
        if ((str_starts_with($value, "\"") && str_ends_with($value, "\"")) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return $value;
    }


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
