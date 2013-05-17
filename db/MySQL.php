<?php
	require_once('MySQLException.php');
	
	/**
	 * Manage a MySQL connection
	 * 
	 * @package MySQL
	 */
	class MySQL
	{		
		private $Host;
		private $DBName;
		private $User;
		private $Pass;
		
		private $Connection;
		
		/**
		 * Connect to a MySQL database
		 * 
		 * @param String $host The host or domain name
		 * @param String $dbname The name of the database to connect to
		 * @param String $user The db user name
		 * @param String $padd The db user name's password
		 */
		public function __construct($host, $dbname, $user, $pass, $options=null)
		{
			$Host = $host;
			$DBName = $dbname;
			$User = $user;
			$Pass = $pass;
			
			try {
				$Connection = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, $options);
				$Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				throw new MySQLConnectionException($e);
			}
		}
		
		/**
		 * Process query
		 * @param String $query The query to be processed
		 * @param Array @queryParams Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
		 * @return Boolean True, if the query was sucessfully processed. False, if otherwise.
		 */
		protected function process($query, $queryParams = null) {
			$preparedQuery = $Connection->prepare($query);
			if ($queryParams !== null && gettype($queryParams) === 'array') {
				foreach ($queryParam as $name => $value) {
					if (count($value) === 2) {
						$preparedQuery->bindParam(':$name', $value[0], $value[1]);					
					}
				}
			}
			if ($preparedQuery->execute()) {
				return $preparedQuery;
			}
			return null;
		}
		
		/**
		 * Get a result from mysql query
		 * @param String $query The query to be processed
		 * @param Array @queryParams Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
		 * @return Array The result entry fetched. Null, if otherwise
		 */
		protected function getOne($query, $queryParams = null) {
			$result = process($query, $queryParams);
			if ($result !== null) {
				return $Connection->fetch(PDO::FETCH_ASSOC);
			}
			return null;
		}
		
		/**
		 * Get all results from mysql query
		 * @param String $query The query to be processed
		 * @param Array @queryParams Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
		 * @return Array All results that meets query criteria. Null, if otherwise
		 */
		protected function getAll($query, $queryParams = null) {
			$result = process($query, $queryParams);
			if ($result !== null) {
				return $Connection->fetchAll(PDO::FETCH_ASSOC);
			}
			return null;
		}		
	}
?>