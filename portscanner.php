<?php
require "db.php";

abstract class PortScanner
{
	protected $idServer;

	protected $socket;

	protected $socketAddress;

	protected $socketPort;

	protected $socketDomain = AF_INET;

	protected $socketType = SOCK_STREAM;

	protected $socketProtocol = SOL_TCP;

	protected $socketMessage;

	protected $expectedResult;

	/**
	 * PortScanner constructor.
	 *
	 * @param $idServer
	 * @param $address
	 * @param $port
	 * @param $message
	 * @param $expectedResult
	 */
	function __construct($idServer, $address, $port, $message, $expectedResult)
	{
		$this->idServer = $idServer;
		$this->socketAddress = $address;
		$this->socketPort = $port;
		$this->socketMessage = $message;
		$this->expectedResult = $expectedResult;
	}

	/**
	 * Creates the socket
	 */
	public function socketCreate()
	{
		$this->socket = socket_create($this->socketDomain, $this->socketType, $this->socketProtocol);
	}

	/**
	 * @return bool
	 */
	public function socketConnect()
	{
		$this->socketCreate();

		return socket_connect($this->socket, $this->socketAddress, $this->socketPort);
	}

	/**
	 * @return int
	 */
	public function socketWrite()
	{
		return socket_write($this->socket, $this->socketMessage, strlen($this->socketMessage));
	}

	/**
	 * @return string
	 */
	public function socketRead()
	{
		return socket_read($this->socket, 2048);
	}

	public function socketClose()
	{
		return socket_close($this->socket);
	}

	/**
	 * @return mixed
	 */
	public function getSocket()
	{
		return $this->socket;
	}

	/**
	 * @return mixed
	 */
	public function getIdServer()
	{
		return $this->idServer;
	}

	/**
	 * @param $readResponse
	 *
	 * @return bool
	 */
	public function validateResponse($readResponse)
	{
		return preg_match("/" . $this->expectedResult . " /", $readResponse) == 1 ? true : false;
	}
}

/**
 * Class SocketTcp
 */
class SocketTcp extends PortScanner
{
	/**
	 * SocketTcp constructor.
	 *
	 * @param $idServer
	 * @param $address
	 * @param $port
	 * @param $expectedResult
	 */
	function __construct($idServer, $address, $port, $expectedResult)
	{
		$socketMessage = "HEAD / HTTP/1.1\r\n";
		$socketMessage .= "Host: " . $address . "\r\n";
		$socketMessage .= "Connection: Close\r\n\r\n";

		parent::__construct($idServer, $address, $port, $socketMessage, $expectedResult);
	}
}

/**
 * Class SocketHandler
 */
class SocketHandler
{
	protected $dbAccess;

	protected $idServer;

	/**
	 * SocketHandler constructor.
	 *
	 * @param $dbAccess
	 * @param $idServer
	 */
	function __construct($dbAccess, $idServer)
	{
		$this->dbAccess = $dbAccess;
		$this->idServer = $idServer;
	}

	/**
	 * Writes the response into the DataBase
	 */
	public function writeResponseInvalid()
	{
		return $this->dbAccess->execute("INSERT INTO server_results (id_server, result) VALUES (" . $this->idServer . ", 'Invalid response')");
	}

	public function writeResponseInvalidHost()
	{
		return $this->dbAccess->execute("INSERT INTO server_results (id_server, result) VALUES (" . $this->idServer . ", '" . socket_strerror(socket_last_error()) . "')");
	}
}

/**
 * Class ScanServers
 */
class ScanServers
{
	protected $dbAccess;

	protected $servers = [];

	protected $sockets = [];

	protected $socket;

	function getAllServers()
	{
		$this->dbAccess = new Database('localhost', 'root', '', 'portscanner', '3306');
		return $this->servers = $this->dbAccess->getAll('SELECT * from server_list');
	}
	/**
	 * Creates an array of sockets
	 */
	function getAllSockets()
	{
		foreach ($this->servers as $server)
		{
			$this->sockets[] = new SocketTcp(
				$server[ 'idserver' ],
				$server[ 'address' ],
				$server[ 'port' ],
				$server[ 'expected_result' ]
			);
		}
		return $this->sockets;
	}

	/**
	 * Runs the scan in all the servers from the database
	 */
	function runScan()
	{
		/**
		 * Scans the servers and writes invalid responses in the db
		 */
		$this->getAllServers();

		foreach ($this->getAllSockets() as $socket)
		{
			$socketId = $socket->getIdServer();
			$socketHandler = new SocketHandler($this->dbAccess, $socketId);

			@$exists = $socket->socketConnect();

			if ($exists)
			{
				$socket->socketWrite();
				$result = $socket->socketRead();

				$status = $socket->validateResponse($result);

				if (!$status)
				{
					$socketHandler->writeResponseInvalid();
					echo "Server id: " . $socketId . " Invalid Response \n";
				}
			}
			else
			{
				$socketHandler->writeResponseInvalidHost();
				echo "Server id: " . $socketId . " Invalid Host\n";
			}

			$socket->socketClose();
		}

		echo "Data stored in database\n";
		$this->dbAccess->disconnect();
	}
}

(new ScanServers())->runScan();