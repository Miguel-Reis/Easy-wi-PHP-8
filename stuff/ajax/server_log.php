<?php
 
if (!defined('AJAXINCLUDED')) {
    die('Do not access directly!');
}

if (!isset($resellerLockupID)) {
    $resellerLockupID = $reseller_id;
}

$array = array('lastLog' => 0, 'log' => '');

$query = $sql->prepare("SELECT r.`ip` AS `ftp_ip`,r.`ftpport`,u.`cname`,g.`id`,g.`newlayout`,g.`rootID`,g.`serverip`,g.`port`,g.`protected`,AES_DECRYPT(g.`ftppassword`,?) AS `dftppass`,AES_DECRYPT(g.`ppassword`,?) AS `decryptedftppass`,s.`servertemplate`,t.`binarydir`,t.`shorten` FROM `gsswitch` AS g INNER JOIN `userdata` AS u ON u.`id`=g.`userid` INNER JOIN `rserverdata` AS r ON r.`id`=g.`rootID` INNER JOIN `serverlist` AS s ON g.`serverid`=s.`id` INNER JOIN `servertypes` AS t ON s.`servertype`=t.`id` WHERE g.`id`=? AND g.`userid`=? AND g.`resellerid`=? LIMIT 1");
$query->execute(array($aeskey, $aeskey, $ui->id('id', 10, 'get'), $user_id, $resellerLockupID));
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

    if ($ui->escaped('cmd', 'post')) {

        $appServer = new AppServer($row['rootID']);
        $appServer->getAppServerDetails($ui->id('id', 10, 'get'));
        $appServer->shellCommand($ui->escaped('cmd', 'post'));
        $appServer->execute();

    } else {

        $shorten = $row['shorten'];
        $ftppass = $row['dftppass'];
        $username = ($row['newlayout'] == 'Y') ? $row['cname'] . '-' . $row['id'] : $row['cname'];

        if ($row['protected'] == 'N' and $row['servertemplate'] > 1) {
            $shorten .= '-' . $row['servertemplate'];
            $pserver = 'server/';
        } else if ($row['protected'] == 'Y') {
            $username .= '-p';
            $ftppass = $row['decryptedftppass'];
            $pserver = '';
        } else {
            $pserver = 'server/';
        }

        $ftpConnect = new EasyWiFTP($row['ftp_ip'], $row['ftpport'], $username, $ftppass);

        $downloadChrooted = $ftpConnect->removeSlashes($pserver . '/' . $shorten . '/' . $row['binarydir'] . '/screenlog.0');

        if ($ftpConnect->ftpConnection) {

            if (!$ftpConnect->downloadToTemp($downloadChrooted, 32768, false, $ui->isinteger('lastLog', 'get'))) {
                $array['error'] = 'Cannot download screenlog from ' . $downloadChrooted;
            } else {
                $array['lastLog'] = $ftpConnect->getLastFileSize();
                $array['log'] = nl2br(htmlentities($ftpConnect->getTempFileContent()));
            }

        } else {
            $array['error'] = 'Cannot connect to FTP Server ' . $row['ftp_ip'] . ':' . $row['ftpport'];
        }
    }
}

if ($query->rowCount() < 1) {
    $array['error'] = 'Error: No rootID';
}

die(json_encode($array));
