<?
/**
 * Created by PhpStorm.
 * User: StepovichPE
 * Date: 01.07.14
 * Time: 18:57
 */
require_once("Zend/Db/Adapter/Pdo/Mysql.php");

class Core_Db_Adapter_Pdo_Mysql extends Zend_Db_Adapter_Pdo_Mysql {

	/**
	 * Current Transaction Level
	 *
	 * @var int
	 */
	protected $_transactionLevel = 0;

	/**
	 * Begin new DB transaction for connection
	 *
	 * @return App_Zend_Db_Adapter_Mysqli
	 */
	public function beginTransaction()
	{
		if ($this->_transactionLevel === 0) {
			parent::beginTransaction();
		}
		$this->_transactionLevel++;

		return $this;
	}

	/**
	 * Commit DB transaction
	 *
	 * @return App_Zend_Db_Adapter_Mysqli
	 */
	public function commit()
	{
		if ($this->_transactionLevel === 1) {
			parent::commit();
		}
		$this->_transactionLevel--;

		return $this;
	}

	/**
	 * Rollback DB transaction
	 *
	 * @return App_Zend_Db_Adapter_Mysqli
	 */
	public function rollBack()
	{
		if ($this->_transactionLevel === 1) {
			parent::rollBack();
		}
		$this->_transactionLevel--;

		return $this;
	}

	/**
	 * Get adapter transaction level state. Return 0 if all transactions are complete
	 *
	 * @return int
	 */
	public function getTransactionLevel()
	{
		return $this->_transactionLevel;
	}
} 