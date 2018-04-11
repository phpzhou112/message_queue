<?php

/**
 * Created by PhpStorm.
 * User: zhou
 * Date: 2018/4/10
 * Time: 17:37
 */
class MessageQueue
{
    private static $localAddr = 'tcp://0.0.0.0:12590';
    private $queue = [];
    private $sockets = [];
    private $queueObj;

    public function __construct()
    {
        $this->queueObj = new Queue();
    }

    private function connect()
    {
        $sock = stream_socket_server(self::$localAddr, $errno, $errstr);
        if (!$sock) {
            syslog(2, $errstr . $errno);
        } else {
            while (true) {
                $conn = stream_socket_accept($sock, -1);
                if (!$conn) {
                    syslog(2, 'accept failed');
                    fclose($sock);
                    exit;
                }
                stream_set_blocking($conn, 0);
                $this->sockets[] = $conn;
                while ($this->sockets) {
                    $data = '';
                    $read = $this->sockets;
                    $flag = stream_select($read, $write, $except, 0);
                    if ($flag) {
                        foreach ($read as $r) {
                            $id = array_search($r, $this->sockets);
                            $data .= fread($r, 8192);
                            if (strlen($data) == 0) {
                                echo 'no data';
                                fclose($r);
                                unset($this->sockets[$id]);
                            } else {
                                $sendData = $this->handleData($data);
                                if ($sendData) {
                                    fwrite($r, $sendData);
                                }
                            }

                        }
                    }
                }
            }
            fclose($sock);
        }
    }

    /**
     * @param $data
     * @return mixed|string
     */
    private function handleData($data)
    {
        $dataArr = explode(" ", $data);
        if ('push' == $dataArr[0] || 'PUSH' == $dataArr[0]) {
            $this->push($dataArr[1], $dataArr[2]);
            return '';
        } elseif ('pop' == $dataArr[0] || 'POP' == $dataArr[0]) {
            return $this->shift($dataArr[1]);
        }
    }

    /**
     * @param $key
     * @param $value
     */
    private function push($key, $value)
    {
        $this->queueObj->enQueue($value);
        $this->queue[$key] = $this->serializeObj($this->queueObj);
    }

    /**
     * @param $key
     * @return mixed
     */
    private function shift($key)
    {
        if ($key) {
            $str = '';
            foreach ($this->queue as $k => $value) {
                if ($key == $k) {
                    $str = $value;
                    break;
                }
            }
            if ($str) {
                $obj = $this->unSerializeObj($str);
                if (!$obj->isEmpty()) {
                    return $obj->deQueue();
                }

            }
        }
    }

    /**
     * @param $obj
     * @return string
     */
    private function serializeObj($obj)
    {
        return serialize($obj);
    }

    /**
     * @param $str
     * @return mixed
     */
    private function unSerializeObj($str)
    {
        return unserialize($str);
    }


    private function daemon()
    {
        switch (pcntl_fork()) {
            case -1:
                syslog(1, "fork failed!");
                exit(-1);
                break;
            case 1:
                exit(-1);
                break;
            case 0:
                break;
            default:
                break;
        }
        $sid = posix_setsid();
        if ($sid < 0) {
            syslog(1, "setsid failed");
            exit;
        }
        if (!chdir("/")) {
            syslog(1, "change dir failed!");
            exit;
        }
        cli_set_process_title("message queue");
        umask(0);
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
    }

    public function run()
    {
        $this->daemon();
        $this->connect();
    }
}

/**
 * Class Queue
 */
class Queue
{
    private static $list = [];

    /**
     * @param $value
     */
    public function enQueue($value)
    {
        self::$list[] = $value;
    }

    /**
     * @return mixed
     */
    public function deQueue()
    {
        $de = self::$list[0];
        array_splice(self::$list, 0, 1);
        return $de;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        if (empty(self::$list)) {
            return true;
        }
        return false;
    }
}

$message = new MessageQueue();
$message->run();


