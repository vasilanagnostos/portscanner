<?php
require "db.php";

/**
 * Interface ScannerInterface
 */
interface ScannerInterface
{
    /**
     * @return string
     */
    public function getIdServer();

    /**
     * @return string
     */
    public function runScan();
}

/**
 * Class PortScanner
 */
abstract class PortScanner implements ScannerInterface
{
    /**
     * @var string
     */
    protected $idServer;

    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var string
     */
    protected $socketAddress;

    /**
     * @var int
     */
    protected $socketPort;

    /**
     * @var int
     */
    protected $socketDomain = AF_INET;

    /**
     * @var int
     */
    protected $socketType = SOCK_STREAM;

    /**
     * @var int
     */
    protected $socketProtocol = SOL_TCP;

    /**
     * @var string
     */
    protected $socketMessage;

    /**
     * @var string
     */
    protected $expectedResult;

    /**
     * PortScanner constructor.
     *
     * @param $idServer
     * @param $address
     * @param $port
     * @param $expectedResult
     * @param $message
     */
    public function __construct($idServer, $address, $port, $expectedResult, $message = null)
    {
        $this->idServer = $idServer;
        $this->socketAddress = $address;
        $this->socketPort = $port;
        $this->expectedResult = $expectedResult;
        $this->socketMessage = $message;
    }

    /**
     * @return string
     */
    public function runScan()
    {
        if (!$this->socketConnect()) {
            return 'Connection failed: ' . socket_strerror(socket_last_error());
        }

        $this->socketWrite();

        $result = $this->socketRead();

        $return = $this->validateResponse($result) ? null : 'Invalid response: ' . $result;

        $this->socketClose();

        return $return;
    }

    /**
     * Creates the socket
     */
    public function socketCreate()
    {
        $this->socket = socket_create($this->socketDomain, $this->socketType, $this->socketProtocol);

        socket_set_option(
            $this->socket,
            SOL_SOCKET,
            SO_RCVTIMEO,
            ['sec' => 10, 'usec' => 0]
        );
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
        if ($this->socketMessage) {
            return socket_write($this->socket, $this->socketMessage, strlen($this->socketMessage));
        }

        return 0;
    }

    /**
     * @return string
     */
    public function socketRead()
    {
        return socket_read($this->socket, 8192);
    }

    /**
     * @return void
     */
    public function socketClose()
    {
        socket_close($this->socket);
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
        return preg_match("/" . $this->expectedResult . "/", $readResponse) == 1 ? true : false;
    }
}

/**
 * Class MailServerScanner
 */
class MailServerScanner extends PortScanner
{
    /**
     * SocketTcp constructor.
     *
     * @param $idServer
     * @param $address
     * @param $port
     * @param $expectedResult
     */
    public function __construct($idServer, $address, $port, $expectedResult)
    {
        parent::__construct($idServer, $address, $port, null, $expectedResult);
    }

    /**
     * @return string
    public function runScan(){
     * change the logic completely if you like ...
     * for example mail server does not require socketWrite call
     * }
     */
}

/**
 * Class WebServerScanner
 */
class WebServerScanner extends PortScanner
{
    /**
     * SocketTcp constructor.
     *
     * @param $idServer
     * @param $address
     * @param $port
     * @param $expectedResult
     */
    public function __construct($idServer, $address, $port, $expectedResult)
    {
        $socketMessage = "HEAD / HTTP/1.1\r\n";
        $socketMessage .= "Host: " . $address . "\r\n";
        $socketMessage .= "Connection: Close\r\n\r\n";

        parent::__construct($idServer, $address, $port, $expectedResult, $socketMessage);
    }
}

/**
 * Class FtpServerScanner
 */
class FtpServerScanner extends MailServerScanner{

}

/**
 * Class StorageHandler
 */
class StorageHandler
{
    /**
     * @var Database
     */
    protected $dbAccess;

    /**
     * SocketHandler constructor.
     *
     * @param $dbAccess
     */
    public function __construct(Database $dbAccess)
    {
        $this->dbAccess = $dbAccess;
    }

    /**
     * Writes the response into the DataBase
     * @param $socketId
     * @param $message
     * @return mixed
     */
    public function writeResponseInvalid($socketId, $message)
    {
        return $this->dbAccess->execute(
            "INSERT INTO server_results (id_server, result) " .
            "VALUES (" . $socketId . ", '" . $message . "')" // FIXME this should be quoted!
        );
    }

    /**
     * @return mixed
     */
    public function getAllServers()
    {
        return $this->dbAccess->getAll('SELECT * from server_list');
    }
}

/**
 * Class ScanServers
 */
class ScanServers
{
    /**
     * @var DataBase
     */
    protected $dbAccess;

    /**
     * @var StorageHandler
     */
    protected $storageHandler;

    /**
     * @var array
     */
    protected $sockets = [];

    /**
     * @var array
     */
    protected $scanners = [
        'web' => WebServerScanner::class,
        'mail' => MailServerScanner::class,
        'ftp' => FtpServerScanner::class,
    ];

    /**
     * ScanServers constructor.
     * @param DataBase $dbAccess
     */
    public function __construct(DataBase $dbAccess)
    {
        $this->dbAccess = $dbAccess;
        $this->storageHandler = new StorageHandler($this->dbAccess);
    }

    /**
     * Runs the scan in all the servers from the database
     */
    public function runScan()
    {
        foreach ($this->storageHandler->getAllServers() as $server) {
            $socket = $this->scannerFactory($server);

            if (false != ($issueDetected = $socket->runScan())) {
                $socketId = $socket->getIdServer();
                $this->storageHandler->writeResponseInvalid($socketId, $issueDetected);
                echo "Issue detected with server " . $socketId . ": " . $issueDetected . PHP_EOL;
            }
        }

        echo "Scan completed\n";
        $this->dbAccess->disconnect();
    }

    /**
     * Creates scanner instance depending on config
     * @param array $config
     * @return ScannerInterface
     */
    protected function scannerFactory(array $config)
    {
        $scanner = $this->scanners[$config['type']];

        return new $scanner(
            $config['idserver'],
            $config['address'],
            $config['port'],
            $config['expected_result']
        );
    }
}

(new ScanServers(
// Db access is normally in a config file
    new Database('localhost', 'root', 'alabala12', 'portscanner', '3306')
))->runScan();
