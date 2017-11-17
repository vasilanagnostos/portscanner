<?php

/**
 * Interface DatabaseInterface
 */
interface DatabaseInterface {
	public function connect();
	public function getAll($query);
	public function execute($query);
	function disconnect();
}

/**
 * Class DataBase
 */
class DataBase implements DatabaseInterface {
	private $conn;
	private $host;
	private $user;
	private $password;
	private $baseName;
	private $port;

	/**
	 * DataBase constructor.
	 *
	 * @param $host
	 * @param $user
	 * @param $password
	 * @param $dbname
	 * @param $port
	 */
	function __construct($host, $user, $password, $dbname, $port) {
		$this->conn = false;
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->baseName = $dbname;
		$this->port = $port;
		$this->connect();
	}

	function __destruct() {
		$this->disconnect();
	}

	/**
	 * @return bool|PDO
	 */
	function connect() {
		if (!$this->conn) {
			try {
				$this->conn = new PDO('mysql:host='.$this->host.';dbname='.$this->baseName.'', $this->user, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			}
			catch (Exception $e) {
				die('Erreur : ' . $e->getMessage());
			}

			if (!$this->conn) {
				$this->status_fatal = true;
				echo 'Connection BDD failed';
				die();
			}
			else {
				$this->status_fatal = false;
			}
		}

		return $this->conn;
	}


	function disconnect() {
		if ($this->conn) {
			$this->conn = null;
		}
	}

	/**
	 * Fetches all results from a given query
	 */
	function getAll($query) {
		$result = $this->conn->prepare($query);
		$ret = $result->execute();
		if (!$ret) {
			echo 'PDO::errorInfo():';
			echo '<br />';
			echo 'error SQL: '.$query;
			die();
		}
		$result->setFetchMode(PDO::FETCH_ASSOC);
		$reponse = $result->fetchAll();

		return $reponse;
	}

	/**
	 * Executes a given query
	 */
	function execute($query) {
		if (!$response = $this->conn->exec($query)) {
			echo 'PDO::errorInfo():';
			echo '<br />';
			echo 'error SQL: '.$query;
			die();
		}
		return $response;
	}
}