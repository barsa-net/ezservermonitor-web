<?php
require '../autoload.php';
$Config = new Config();

header('Content-Type: application/json');

$datas = array();

if ($Config->get('last_login:enable'))
{
    if ($Config->get('esm:agent:enabled'))
    {
        Misc::agentServe(__FILE__);
        return;
    }

    if (!(exec('/usr/bin/lastlog --time 365 | awk \'{ printf $1";"; for (i=4; i<NF; i++) printf $i" "; print $NF; }\'', $users)))
    {
        $datas[] = array(
            'user' => 'N.A',
            'date' => 'N.A',
        );
    }
    else
    {
        $max = $Config->get('last_login:max');

        for ($i = 1; $i < count($users) && $i <= $max; $i++)
        {
            list($user, $date) = explode(';', $users[$i]);

            $datas[] = array(
                'user' => $user,
                'date' => $date,
            );
        }
    }
}

echo json_encode($datas);
