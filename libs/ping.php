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

if (count($Config->get('ping:hosts')) > 0)
    $hosts = $Config->get('ping:hosts');
else
    $hosts = array('google.com', 'wikipedia.org');

foreach ($hosts as $host)
{
    exec('/bin/ping -qc 1 '.$host.' | awk -F/ \'/^(rtt|round-trip)/ { print $5 }\'', $result);

    if (!isset($result[0]))
    {
        $result[0] = 0;
    }
    
    $datas[] = array(
        'host' => $host,
        'ping' => $result[0],
    );

    unset($result);
}

echo json_encode($datas);