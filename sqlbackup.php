<?php
set_time_limit(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_PARSE & ~E_CORE_ERROR & ~E_COMPILE_ERROR);

$LIB_PATH = 'vendor/';
// print_r($argv); exit;
$config['mysql']['host']="localhost";
$config['mysql']['user']="root";
$config['mysql']['pass']="";
$config['mysql']['port']="3306";
$config['mysql']['data']="test";
$config['dropbox']['sync'] = (in_array('dropbox',$argv) ? true : false);
$config['dropbox']['delete_file'] = (in_array('dropbox-delete-file',$argv) ? true : false);

require_once $LIB_PATH . 'autoload.php';

include_once dirname(__FILE__).'/config.php';
 $DBX = new Koyabu\Webapi\Dropbox(); 
//$p = $DBX->getAccessToken('AmqXcauymGgAAAAAAAAAJRvmemS8qwJAW1mXlvcxYAw'); print_r($p); exit;
$conn = new mysqli($config['mysql']['host'],$config['mysql']['user'],$config['mysql']['pass'],$config['mysql']['data']);

$BASE_DIR = 'D:/Projects/00_DB_BACKUP/'; //;__DIR__ . DIRECTORY_SEPARATOR; //dirname(__FILE__) . DIRECTORY_SEPARATOR;
//

function delete_FileDBX($dateDelete,$json,$t) {
    global $config,$DBX;
    if ($json['dbx'][$dateDelete]) {
        print_r($json['dbx'][$dateDelete]);
        if ($json['dbx'][$dateDelete]['dbx_file']) {
            $d = $DBX->delete(''.$config['dropbox']['home_dir'].'/'.$t['Database'].'/'.$json['dbx'][$dateDelete]['dbx_file']);
            echo "Deleted! \n";
            if ($d['http_code'] == '200') {
                unset($json['dbx'][$dateDelete],$json['dbx_deleted_not_found'][$dateDelete]);
            } else {
                $json['dbx_deleted_not_found'][$dateDelete] = $json['dbx'][$dateDelete];
                unset($json['dbx'][$dateDelete]);
            }
            print_r($d);
        }
    }
    return $json;
}

$monthStartDate = 1;
$weekStartDate = 0; // 0 = Sunday, 1 = Monday ... etc

// $backupDaily = ['cs_apps','csphotobooth','hwm','panada','panada_cms','panada_klinik','panada_gl'];
// $backupWeekly = ['polisi_online_v4','panada_klinik_origin'];
// $backupMonthly = ['all'];
$backupDaily = ['all'];
// $tgl = date("Y-m-d");
$g = $conn->query("show databases;");
while($t = $g->fetch_assoc()) {
    // echo $t['Database'].PHP_EOL;
    $BACKUP_PATH = "{$BASE_DIR}backup/sql/{$t['Database']}";

    if (preg_match("#^sys$|^performance_schema|^information_schema|^test|^mysql#si",$t['Database'])) goto endbackup;

    if (!file_exists($BACKUP_PATH)) {
        mkdir($BACKUP_PATH,0777,true);
    }
    if (file_exists("{$t['Database']}.sql")) { unlink("{$t['Database']}.sql"); }
    if (file_exists("{$t['Database']}.sql.bz2")) { unlink("{$t['Database']}.sql.bz2"); }
    if (file_exists("{$BACKUP_PATH}/{$t['Database']}.sql.bz2")) { unlink("{$BACKUP_PATH}/{$t['Database']}.sql.bz2"); }
    
    if (!file_exists($BACKUP_PATH.'/backup.json')) {
        file_put_contents($BACKUP_PATH.'/backup.json',json_encode([]));
    } else {
        $json = json_decode(file_get_contents($BACKUP_PATH.'/backup.json'),true);
    }

    // Backup Daily
    if (in_array($t['Database'],$backupDaily) or in_array('all',$backupDaily)) { 
        $dateDelete = date("Y-m-d",strtotime("-7 day")); //
        $json = delete_FileDBX($dateDelete,$json,$t);
        goto startbackup; 
    }

    // Backup Monthly
    if (date("d") == $monthStartDate and (in_array($t['Database'],$backupMonthly) or in_array('all',$backupMonthly))) {
        $dateDelete = date("Y-m-d",strtotime("-2 month")); //
        $json = delete_FileDBX($dateDelete,$json,$t); 
        goto startbackup; 
    }

    // Backup Weekly
    if (date("w") == $weekStartDate and (in_array($t['Database'],$backupWeekly) or in_array('all',$backupWeekly))) { 
        $dateDelete = date("Y-m-d",strtotime("-2 week")); //
        $json = delete_FileDBX($dateDelete,$json,$t); 
        goto startbackup; 
    }
    else { goto endbackup; }

    startbackup:
    
    $json['lastbackup'] = date("Y-m-d H:i:s");
    $exec = "mysqldump -uroot ". ( $config['mysql']['pass'] ? "-p{$config['mysql']['pass']}" : '')  ." {$t['Database']} > {$BASE_DIR}{$t['Database']}.sql";
    echo "Database {$t['Database']}\n{$exec}\n";
    exec($exec);
    exec("bzip2 {$BASE_DIR}{$t['Database']}.sql");
    // exec("mv {$t['Database']}.sql.bz2 {$BASE_DIR}backup/sql/{$t['Database']}.sql.bz2");
    copy("{$BASE_DIR}{$t['Database']}.sql.bz2","{$BACKUP_PATH}/{$t['Database']}.sql.bz2");
    $syncFile = "{$BACKUP_PATH}/{$t['Database']}_".date("Y-m-d").".sql.bz2";
    copy("{$BASE_DIR}{$t['Database']}.sql.bz2",$syncFile);
    unlink("{$BASE_DIR}{$t['Database']}.sql.bz2");
    
    if ($config['dropbox']['sync']) {
        $DBX_PATH = '/'.$config['dropbox']['home_dir'].'/'.$t['Database'].'/';
        $p = $DBX->upload("{$BACKUP_PATH}/{$t['Database']}_".date("Y-m-d").".sql.bz2",'overwrite',$DBX_PATH);
        if ($config['dropbox']['delete_file']) { unlink($syncFile); }
        if ($p['name']) {
            $json['dbx'][date("Y-m-d")] = [ 'dbx_path' => $p['path_display'], 'dbx_file' => $p['name'] ];
        }
    }
    $json['filename'] = "{$BACKUP_PATH}/{$t['Database']}.sql.bz2";
    $json['md5_sum'] = md5_file("{$BACKUP_PATH}/{$t['Database']}.sql.bz2");
    file_put_contents($BACKUP_PATH.'/backup.json',json_encode($json));
    print_r($p);

    endbackup:
    echo "--".PHP_EOL;
}

?>
