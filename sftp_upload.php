<?php
//http://www.gigoblog.com/2013/11/14/add-the-ssh2extension=/opt/local/lib/php54/extensions/no-debug-non-zts-20100525/ssh2.so-extension-for-php-on-mac-os-x-mavericks-server/

//FTPの場合は修正
global $sshConnection, $sftpConnection, $ignoreFiles;
$sshConnection = ssh2_connect('host', 22);
$sshConnectionAuth = ssh2_auth_password($sshConnection, 'user', 'password');
$sftpConnection = ssh2_sftp($sshConnection);

//アップロード先のディレクトリは事前につくって
$serverUploadDir = '/home/junji/test';
$projectDir = '/Users/junji/test';

// /Users/junji/test/A.txt を更新しない場合
$ignoreFiles = array(
    $projectDir.'/A',
);

checkDiffsExistFiles($serverUploadDir, $projectDir);

echo "\n";

function checkDiffsExistFiles($serverPath, $localPath){
    global $sftpConnection, $ignoreFiles;
    $serverFileList = getFileListFromLsComand($serverPath, true);
    $localFileList = getFileListFromLsComand($localPath);
    $localDir = array();
    $uploadFileArr = array();
    $serverDir = array();
    foreach($localFileList as $localFile){
        if(!$localFile['is_dir']){
            $uploadFileArr[] = $localFile['name'];
            if(!in_array($localPath.'/'.$localFile['name'], $ignoreFiles)){
                uploadFileToServer($serverPath, $localPath, $localFile['name']);
            }
        }else{
            $localDir[] = $localFile['name'];
        }
    }
    foreach($serverFileList as $serverFile){
        if(!$serverFile['is_dir']){
            if(!in_array($serverFile['name'], $uploadFileArr)){
                ssh2_sftp_unlink($sftpConnection, $serverPath.'/'.$serverFile['name']);
            }
        }else{
            $serverDir[] = $serverFile['name'];
        }
    }

    $eachExistDir = array();
    $onlyOnServerDir = array();
    $onlyOnLocalDir = array();
    foreach($serverDir as $dir){
        if(in_array($dir, $localDir)){
            $eachExistDir[] = '/'.$dir;
        }else{
            $onlyOnServerDir[] = '/'.$dir;
        }
    }
    foreach($localDir as $dir){
        if(!in_array($dir, $serverDir)){
            $onlyOnLocalDir[] = '/'.$dir;
        }
    }
    foreach($eachExistDir as $dir){
        checkDiffsExistFiles($serverPath.$dir, $localPath.$dir);
    }
    foreach($onlyOnLocalDir as $dir){
        //FTPの場合は修正
        ssh2_sftp_mkdir($sftpConnection, $serverPath.$dir, 0755);
        checkDiffsExistFiles($serverPath.$dir, $localPath.$dir);
    }
    foreach($onlyOnServerDir as $dir){
        deleteDirOnServer($serverPath.$dir);
    }
}

//FTPの場合は修正
function uploadFileToServer($serverPath, $localPath, $fileName){
    global $sftpConnection;

    $resFile = fopen("ssh2.sftp://{$sftpConnection}{$serverPath}/{$fileName}", 'w');
    $srcFile = fopen($localPath.'/'.$fileName, 'r');
    stream_copy_to_stream($srcFile, $resFile);
    fclose($resFile);
    fclose($srcFile);
}
//FTPの場合は修正
function deleteDirOnServer($delDir){
    global $sftpConnection;
    //サーバ上にしかないディレクトリは削除
    $fileList = getFileListFromLsComand($delDir, true);
    foreach($fileList as $file){
        if($file['is_dir']){
            deleteDirOnServer($delDir.'/'.$file['name']);
        }else{
            ssh2_sftp_unlink($sftpConnection, $delDir.'/'.$file['name']);
        }
    }
    ssh2_sftp_rmdir($sftpConnection, $delDir);
}

function getFileListFromLsComand($dir, $isOnServer = false){
    global $sshConnection;
    $lsResultArr = array();
    $dir = str_replace(' ', '\ ', $dir);
    if($isOnServer){
        //FTPの場合は修正
        $stream = ssh2_exec($sshConnection, "ls -al {$dir}");
        stream_set_blocking($stream, true);
        $lsResultString = stream_get_contents($stream);
        $lsResultArr = explode("\n", $lsResultString);
    }else{
        exec("ls -al {$dir}", $lsResultArr);
    }
    $fileList = array();
    foreach($lsResultArr as $val){
        $fileInfo = array();
        $v = '';
        for($i=0;$i<mb_strlen($val);$i++){
            if(mb_substr($val, $i, 1) === ' '){
                if(!empty($v)){
                    $fileInfo[] = $v;
                    $v = '';
                }
                continue;
            }
            if(count($fileInfo) === 8){
                $fileInfo[] = mb_substr($val, $i);
                break;
            }else{
                $v .= mb_substr($val, $i, 1);
            }
        }
        if(count($fileInfo) !== 9 || strpos($fileInfo[8], '.') === 0){
            continue;
        }
        $fileList[] =
            array(
                'is_dir' => strpos($fileInfo[0], 'd') === 0 ? true : false,
                'name' => $fileInfo[8],
            );
    }
    return $fileList;
}
