<?php
require '../autoload.php';
$Config = new Config();

header('Content-Type: application/json');

if ($Config->get('esm:agent:enabled'))
{
    Misc::agentServe(__FILE__);
    return;
}

$datas = array();

$available_protocols = array('tcp', 'udp');

$show_port = $Config->get('services:show_port');

if (count($Config->get('services:list')) > 0)
{
    foreach ($Config->get('services:list') as $service)
    {
        $host     = $service['host'];
        $port     = $service['port'];
        $name     = $service['name'];
        $protocol = isset($service['protocol']) && in_array($service['protocol'], $available_protocols) ? $service['protocol'] : 'tcp';

        if (Misc::scanPort($host, $port, $protocol))
            $status = 1;
        else
            $status = 0;

        $datas[] = array(
            'port'      => $show_port === true ? $port."/".$protocol : '',
            'name'      => $name,
            'status'    => $status,
        );
    }
}


echo json_encode($datas);