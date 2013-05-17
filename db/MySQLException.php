<?php
	/**
	 * MySQL Exception
	 * 
	 * Base container for handling MySQL errors
	 * 
	 * @package MySQLException
	 */
	 class MySQLException extends Exception {
	 	/**
		 * @param String $Message Error Message
		 */
		protected $Message;
		
		/**
		 * @param int $ErrorCode Error code
		 */
		protected $ErrorCode;
		
		/**
		 * @param Exception $Exception The inner exception
		 */
		protected $Exception;
		
		public function __construct($message, $code, $innerException) {
			parent::_construct($Message, $ErrorCode, $innerException);
		}
		
		public function __toString() {
			return __CLASS__ . ": [{$this->ErrorCode}]: {$this->$Message}\n";
		}
	 }
	 
	 /**
	  * MySQL Connection Exception
	  * 
	  * Exception for handling MySQL connection errors
	  * 
	  * @package MySQLException
	  */
	 class MySQLConnectionException extends MySQLException {	 	
		public function __construct($Exception) {
			parent::_construct($Exception->getMessage(), $Exception->getCode(), $Exception->getPrevious());
		}
	 }
	 
	 /**
	  * MySQL Query Exception
	  * 
	  * Exception for handling MySQL Query errors
	  * 
	  * @package MySQLException
	  */
	 class MySQLQueryException extends MySQLException {
		public function __construct($Exception) {
			parent::__construct($Exception->getMessage(), $Exception->getCode(), $Exception->getPrevious());
		}
	 }
?>
