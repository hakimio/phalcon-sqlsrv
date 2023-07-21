<?php

namespace Phalcon\Logger\Adapter;

use Phalcon\Logger\Exception;
use Phalcon\Logger\Formatter\FormatterInterface;
use Phalcon\Logger\Formatter\Line as LineFormatter;
use Phalcon\Logger\Item;

/**
 * Phalcon\Logger\Adapter\Database
 * Adapter to store logs in a database table.
 */
class Database extends AbstractAdapter implements AdapterInterface
{
    /**
     * Username.
     *
     * @var string
     */
    protected $username = 'guest';

    /**
     * Adapter options.
     *
     * @var array
     */
    protected $options = [];

    protected ?LineFormatter $_formatter;

    /**
     * Class constructor.
     *
     * @param array $options
     *
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        if (!isset($options['db'])) {
            throw new Exception("Parameter 'db' is required");
        }

        if (!isset($options['table'])) {
            throw new Exception("Parameter 'table' is required");
        }

        if (!empty($options['username'])) {
            $this->username = $options['username'];
        }

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     *
     * @return FormatterInterface
     */
    public function getFormatter(): FormatterInterface
    {
        if (!isset($this->_formatter)) {
            $this->_formatter = new LineFormatter();
        }

        return $this->_formatter;
    }

    /**
     * Closes the logger.
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    public function getIP()
    {
        return (getenv(HTTP_X_FORWARDED_FOR)) ? getenv(HTTP_X_FORWARDED_FOR) : getenv(REMOTE_ADDR);
    }

    public function getBrowser()
    {
        // Declare known browsers to look for
        $browsers = array('chrome', 'firefox', 'safari', 'msie', 'opera',
            'mozilla', 'seamonkey', 'konqueror', 'netscape',
            'gecko', 'navigator', 'mosaic', 'lynx', 'amaya',
            'omniweb', 'avant', 'camino', 'flock', 'aol', );

        // Find all phrases (or return empty array if none found)
        foreach ($browsers as $browser) {
            if (preg_match("#($browser)[/ ]?([0-9.]*)#", strtolower($_SERVER['HTTP_USER_AGENT']), $match)) {
                $info['name'] = $match[1];
                $info['version'] = $match[2];
                break;
            }
        }

        return "{$info['name']} ({$info['version']})";
    }

    public function process(Item $item): void
    {
        /* @var \Phalcon\Db\Adapter\Pdo\Sqlsrv $db */
        $db = $this->options['db'];

        $db->insertAsDict(
            $this->options['table'], array(
            'LogType' => $item->getLevelName(),
            'LogProcess' => $item->getContext()['process'],
            'LogContent' => $item->getMessage(),
            'LogUser' => $this->username,
            'LogDate' => $item->getDateTime()->format('Y-m-d H:i:s'),
            'LogIP' => $this->getIP(),
            'LogBrowser' => $this->getBrowser()
        ));
    }

}
