<?php namespace Orchestra\Support;

use Orchestra\Support\Ftp\Morph as Facade;
use Orchestra\Support\Ftp\ServerException;

class Ftp
{
    /**
     * FTP stream connection.
     *
     * @var Stream
     */
    protected $connection = null;

    /**
     * FTP configuration.
     *
     * @var array
     */
    protected $config = array(
        'host'     => null,
        'port'     => 21,
        'user'     => null,
        'password' => null,
        'timeout'  => 90,
        'passive'  => false,
        'ssl'      => false,
    );

    /**
     * System type of FTP server.
     *
     * @var string
     */
    protected $systemType;

    /**
     * Make a new FTP instance.
     *
     * @param  array    $config
     * @return self
     */
    public static function make($config = array())
    {
        return new static($config);
    }

    /**
     * Make a new FTP instance.
     *
     * @param  array    $config
     * @return void
     */
    public function __construct($config = array())
    {
        if (! empty($config)) {
            $this->setUp($config);
        }
    }

    /**
     * Configure FTP.
     *
     * @param  array    $config
     * @return void
     */
    public function setUp($config = array())
    {
        $this->connection = array_pull($config, 'connection', $this->connection);

        if (preg_match('/^(ftp|sftp):\/\/([a-zA-Z0-9\.\-_]*):?(\d{1,4})$/', array_get($config, 'host'), $matches)) {
            $config['host'] = $matches[2];
            $config['ssl']  = ($matches[1] === 'sftp' ? true : false);

            isset($matches[3]) && $config['port'] = $matches[3];
        }

        $this->config = array_merge($this->config, $config);
    }

    /**
     * Change current directory on FTP server.
     *
     * @param  string   $directory
     * @return boolean
     */
    public function changeDirectory($directory)
    {
        return @Facade::fire('chdir', array($this->connection, $directory));
    }

    /**
     * Get current directory path.
     *
     * @return string
     */
    public function currentDirectory()
    {
        return @Facade::pwd($this->connection);
    }

    /**
     * Download file from FTP server.
     *
     * @param  string   $remoteFile
     * @param  string   $localFile
     * @param  integer  $mode
     * @return boolean
     */
    public function get($remoteFile, $localFile, $mode = FTP_ASCII)
    {
        return @Facade::fire('get', array($this->connection, $localFile, $remoteFile, $mode));
    }

    /**
     * Upload file to FTP server.
     *
     * @param  string   $localFile
     * @param  string   $remoteFile
     * @param  integer  $mode
     * @return boolean
     */
    public function put($localFile, $remoteFile, $mode = FTP_ASCII)
    {
        return @Facade::fire('put', array($this->connection, $remoteFile, $localFile, $mode));
    }

    /**
     * Rename file on FTP server.
     *
     * @param  string   $oldName
     * @param  string   $newName
     * @return boolean
     */
    public function rename($oldName, $newName)
    {
        return @Facade::fire('rename', array($this->connection, $oldName, $newName));
    }

    /**
     * Delete file on FTP server.
     *
     * @param  string   $remoteFile
     * @return boolean
     */
    public function delete($remoteFile)
    {
        return @Facade::fire('delete', array($this->connection, $remoteFile));
    }

    /**
     * Set file permissions.
     *
     * @param  string   $remoteFile
     * @param  integer  $permissions    For example: 0644
     * @return boolean
     * @throws \RuntimeException        If unable to chmod $remoteFile
     */
    public function permission($remoteFile, $permission = 0644)
    {
        return @Facade::fire('chmod', array($this->connection, $permission, $remoteFile));
    }

    /**
     * Get list of files/directories on FTP server.
     *
     * @param  string   $directory
     * @return array
     */
    public function allFiles($directory)
    {
        $list = @Facade::fire('nlist', array($this->connection, $directory));

        return is_array($list) ? $list : array();
    }

    /**
     * Create directory on FTP server.
     *
     * @param  string   $directory
     * @return boolean
     */
    public function makeDirectory($directory)
    {
        return @Facade::fire('mkdir', array($this->connection, $directory));
    }

    /**
     * Remove directory on FTP server.
     *
     * @param  string   $directory
     * @return boolean
     */
    public function removeDirectory($directory)
    {
        return @Facade::fire('rmdir', array($this->connection, $directory));
    }

    /**
     * Connect to FTP server.
     *
     * @return boolean
     * @throws \Orchestra\Support\Ftp\Exception If unable to connect/login
     *                                          to FTP server.
     */
    public function connect()
    {
        $host     = array_get($this->config, 'host');
        $port     = array_get($this->config, 'port');
        $user     = array_get($this->config, 'user');
        $password = array_get($this->config, 'password');
        $passive  = array_get($this->config, 'passive');
        $timeout  = array_get($this->config, 'timeout');

        if (is_null($host)) {
            return ;
        }

        $this->createConnection($host, $port, $timeout);

        if (! (@Facade::login($this->connection, $user, $password))) {
            throw new ServerException("Failed FTP login to [{$host}].");
        }

        // Set passive mode.
        @Facade::pasv($this->connection, (bool) $passive);

        // Set system type.
        $this->systemType = @Facade::systype($this->connection);

        return true;
    }

    /**
     * Create a FTP connection.
     *
     * @param  string   $host
     * @param  integer  $port
     * @param  integer  $timeout
     * @return void
     * @throws \Orchestra\Support\Ftp\ServerException   If unable to connect to FTP
     *                                                  server.
     */
    protected function createConnection($host, $port = 21, $timeout = 90)
    {
        if ($this->config['ssl'] && @Facade::isCallable('sslConnect')) {
            return $this->createSecureConnection($host, $port, $timeout);
        } elseif (! ($this->connection = @Facade::connect($host, $port, $timeout))) {
            throw new ServerException("Failed to connect to [{$host}].");
        }
    }

    /**
     * Create a secure (SSL) FTP connection.
     *
     * @param  string   $host
     * @param  integer  $port
     * @param  integer  $timeout
     * @return void
     * @throws \Orchestra\Support\Ftp\ServerException
     */
    protected function createSecureConnection($host, $port = 21, $timeout = 90)
    {
        if (! ($this->connection = @Facade::sslConnect($host, $port, $timeout))) {
            throw new ServerException(
                "Failed to connect to [{$host}] (SSL Connection)."
            );
        }
    }

    /**
     * Close FTP connection.
     *
     * @return void
     * @throws \RuntimeException If unable to close connection.
     */
    public function close()
    {
        if (! is_null($this->connection)) {
            @Facade::close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Check FTP connection status.
     *
     * @return boolean
     */
    public function connected()
    {
        return ( ! is_null($this->connection));
    }
}
