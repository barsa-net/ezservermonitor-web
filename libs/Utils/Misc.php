<?php
$Config = new Config();

class Misc
{
    /**
     * Returns human size
     *
     * @param  float $filesize   File size
     * @param  int   $precision  Number of decimals
     * @return string            Human size
     */
    public static function getSize($filesize, $precision = 2)
    {
        $units = array('', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y');

        foreach ($units as $idUnit => $unit)
        {
            if ($filesize > 1024)
                $filesize /= 1024;
            else
                break;
        }
        
        return round($filesize, $precision).' '.$units[$idUnit].'B';
    }
    
    
    /**
     * Returns hostname
     *
     * @return  string  Hostname
     */
    public static function getHostname()
    {
        return php_uname('n');
    }


    /**
     * Returns CPU cores number
     * 
     * @return  int  Number of cores
     */
    public static function getCpuCoresNumber()
    {
        if (!($num_cores = shell_exec('/bin/grep -c ^processor /proc/cpuinfo')))
        {
            if (!($num_cores = trim(shell_exec('/usr/bin/nproc'))))
            {
                $num_cores = 1;
            }
        }

        if ((int)$num_cores <= 0)
            $num_cores = 1;

        return (int)$num_cores;
    }


    /**
     * Returns server IP
     *
     * @return string Server local IP
     */
    public static function getLanIp()
    {
        return $_SERVER['SERVER_ADDR'];
    }


    /**
     * Seconds to human readable text
     * Eg: for 36545627 seconds => 1 year, 57 days, 23 hours and 33 minutes
     * 
     * @return string Text
     */
    public static function getHumanTime($seconds)
    {
        $units = array(
            'year'   => 365*86400,
            'day'    => 86400,
            'hour'   => 3600,
            'minute' => 60,
            // 'second' => 1,
        );
     
        $parts = array();
     
        foreach ($units as $name => $divisor)
        {
            $div = floor($seconds / $divisor);
     
            if ($div == 0)
                continue;
            else
                if ($div == 1)
                    $parts[] = $div.' '.$name;
                else
                    $parts[] = $div.' '.$name.'s';
            $seconds %= $divisor;
        }
     
        $last = array_pop($parts);
     
        if (empty($parts))
            return $last;
        else
            return join(', ', $parts).' and '.$last;
    }


    /**
     * Returns a command that exists in the system among $cmds
     *
     * @param  array  $cmds             List of commands
     * @param  string $args             List of arguments (optional)
     * @param  bool   $returnWithArgs   If true, returns command with the arguments
     * @return string                   Command
     */
    public static function whichCommand($cmds, $args = '', $returnWithArgs = true)
    {
        $return = '';

        foreach ($cmds as $cmd)
        {
            if (trim(shell_exec($cmd.' 2>/dev/null '.$args)) != '')
            {
                $return = $cmd;
                
                if ($returnWithArgs)
                    $return .= $args;

                break;
            }
        }

        return $return;
    }


    /**
     * Allows to pluralize a word based on a number
     * Ex : echo 'mot'.Misc::pluralize(5); ==> prints mots
     * Ex : echo 'cheva'.Misc::pluralize(5, 'ux', 'l'); ==> prints chevaux
     * Ex : echo 'cheva'.Misc::pluralize(1, 'ux', 'l'); ==> prints cheval
     * 
     * @param  int       $nb         Number
     * @param  string    $plural     String for plural word
     * @param  string    $singular   String for singular word
     * @return string                String pluralized
     */
    public static function pluralize($nb, $plural = 's', $singular = '')
    {
        return $nb > 1 ? $plural : $singular;
    }


    /**
     * Checks if a port is open (TCP or UPD)
     *
     * @param  string   $host       Host to check
     * @param  int      $port       Port number
     * @param  string   $protocol   tcp or udp
     * @param  integer  $timeout    Timeout
     * @return bool                 True if the port is open else false
     */
    public static function scanPort($host, $port, $protocol = 'tcp', $timeout = 3)
    {
        if ($protocol == 'tcp')
        {
            $handle = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (!$handle)
            {
                return false;
            }
            else
            {
                fclose($handle);
                return true;
            }
        }
        elseif ($protocol == 'udp')
        {
            $handle = @fsockopen('udp://'.$host, $port, $errno, $errstr, $timeout);

            socket_set_timeout($handle, $timeout);

            $write = fwrite($handle, 'x00');

            if ($write === false)
            {
                return false;
            }
            else
            {
                $startTime = time();

                $header = fread($handle, 1);

                $endTime = time();

                $timeDiff = $endTime - $startTime; 
                
                fclose($handle);

                if ($timeDiff >= $timeout)
                    return true;
                else
                    return false;
            }
        }

        return false;
    }

    /**
     * Act as a reverse proxy, get a JSON from a remote endpoint
     * and return it
     *
     * @param  string          $url        Base URL
     * @param  array<array>    $params     Query arguments
     * @return string                      The JSON from the remote endpoint
     */
    public static function proxyPass($url, $params = array())
    {
        global $Config;
        if (count($params) > 0)
        {
            $query = http_build_query($params);
            $query = preg_replace('/%5B(?:\d+)%5D=/', '=', $query);
            $full_url = "$url?$query";
        }
        else
        {
            $full_url = "$url";
        }

        if (!function_exists('curl_version'))
        {
            $output = @file_get_contents($full_url);
        }
        else
        {
            $curl = curl_init();

            $setopt = array(
                CURLOPT_CONNECTTIMEOUT  => 5,
                CURLOPT_TIMEOUT         => 10,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_USERAGENT       => 'eZ Server Monitor `Web',
                CURLOPT_URL             => $full_url
                );

            if ($Config->get('esm:agent:unix_socket'))
            {
                $setopt += [CURLOPT_UNIX_SOCKET_PATH => '/var/run/agent.sock'];
            }

            curl_setopt_array($curl, $setopt);

            $output = curl_exec($curl);

            curl_close($curl);
        }

        return is_bool($output) ? '[]' : $output;
    }

    /**
     * Build the request that needs to be passed to the remote agent
     * and serve it via self::proxyPass
     *
     * @param  string   $file       It contains __FILE__ from the caller
     * @return void
     */
    public static function agentServe($file)
    {
        global $Config;

        $endpoint = basename($file, ".php");

        if ($Config->get('esm:agent:unix_socket'))
            $url = "http://localhost/".$endpoint;
        else
            $url = $Config->get('esm:agent:url')."/".$endpoint.$Config->get('esm:agent:suffix');

        $params = array();

        if ($Config->get('esm:agent:suffix') != ".php" or $Config->get('esm:agent:unix_socket'))
        {
            switch ($endpoint)
            {
                case "cpu":
                    if ($Config->get('cpu:enable_temperature'))
                        $params = array('temperature' => $Config->get('cpu:enable_temperature'));
                    break;

                case "disk":
                    if ($Config->get('disk:show_tmpfs'))
                        $params += array('tmpfs' => $Config->get('disk:show_tmpfs'));

                    if ($Config->get('disk:show_loop'))
                        $params += array('loop' => $Config->get('disk:show_loop'));

                    if ($Config->get('disk:show_filesystem'))
                        $params += array('filesystem' => $Config->get('disk:show_filesystem'));

                    if (count($Config->get('disk:ignore_mounts')) > 0)
                        $params += array('ignore' => $Config->get('disk:ignore_mounts'));
                    break;

                case "last_login":
                    $params = array('max' => $Config->get('last_login:max'));
                    break;

                case "ping":
                    if (count($Config->get('ping:hosts')) > 0)
                        $hosts = $Config->get('ping:hosts');
                    else
                        $hosts = array('google.com', 'wikipedia.org');

                    $datas = array();

                    foreach ($hosts as $host)
                    {
                        $ping_url = $url."/".$host;

                        $response = json_decode(self::proxyPass($ping_url), true);
                        $datas[] = array(
                            'host' => $response['host'],
                            'ping' => $response['ping']
                        );
                    }

                    echo json_encode($datas);
                    return;

                case "services":
                    $available_protocols = array('tcp', 'udp');
                    $datas = array();

                    if (count($Config->get('services:list')) > 0)
                    {
                        foreach ($Config->get('services:list') as $service)
                        {
                            $host     = $service['host'];
                            $port     = $service['port'];
                            $name     = $service['name'];
                            $protocol = isset($service['protocol']) && in_array($service['protocol'], $available_protocols) ? $service['protocol'] : 'tcp';

                            $service_url = $url."/".$host."/".$port."/".$protocol;

                            $response = json_decode(self::proxyPass($service_url), true);
                            $datas[] = array(
                                'port' => $Config->get('services:show_port') === true ? $response['port'] : '',
                                'name' => $name,
                                'status' => $response['status']
                            );
                        }
                    }

                    echo json_encode($datas);
                    return;
            }
        }

        echo self::proxyPass($url, $params);
        return;
    }

    /**
     * Connect to agent and on success returns agent IP, on failure returns false
     *
     * @param   string       $url       Agent base url
     * @return  string|bool             Agent local IP
     */
    public static function agentIp($url)
    {
        global $Config;
        $url = $Config->get('esm:agent:unix_socket') ? "http://localhost" : $url;
        $response = json_decode(self::proxyPass($url."/system/ip"), true);
        return empty($response) ? false : $response['ip'];
    }

    /**
     * Connect to agent and on success returns hostname of agent, on failure returns local hostname
     *
     * @param   string       $url       Agent base url
     * @return  string                  Agent hostname
     */
    public static function agentHostname($url)
    {
        global $Config;
        $url = $Config->get('esm:agent:unix_socket') ? "http://localhost" : $url;
        $response = json_decode(self::proxyPass($url."/system/hostname"), true);
        return empty($response) ? self::getHostname() : $response['hostname'];
    }
}