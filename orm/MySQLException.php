<?php
	/**
	 * MySQL Exception
	 * 
	 * Base container for handling MySQL errors
	 * 
	 * @package MySQLException
	 */
	 class MySQLException extends Exception {
	 	//TODO: Create a log class and wire it through the base exception class (as a super cushion)
	 	
	 	/**
		 * @param String $Message Error Message
		 */
		private $Message;
		
		/**
		 * @param int $ErrorCode Error code
		 */
		private $ErrorCode;
		
		/**
		 * @param Exception $Exception The inner exception
		 */
		private $Exception;
		
		public function __construct($message, $code, $innerException=null) {
			$Message = $message;
			$Code = $code;
			$Exception = $innerException;
			parent::__construct($message, $code, $innerException);
		}
		
		public function __toString() {
			return __CLASS__ . " (Err Code: {$this->code}): [{$this->message}]\n";
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
			parent::__construct($Exception->message, $Exception->code, $Exception);
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
			parent::__construct($Exception->message, $Exception->code, $Exception);
		}
	 }
?>
