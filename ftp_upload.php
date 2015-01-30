<?php
global $ftpConnection, $ignoreFiles;
$ftpConnection = ftp_connect('host', 21);
ftp_login($ftpConnection, 'user', 'pasword');

//アップロード先のディレクトリは事前につくって
$serverUploadDir = '/home/junji/test';
$projectDir = '/Users/junji/test';

// /Users/junji/test/A.txt を更新しない場合
$ignoreFiles = array(
    $projectDir.'/A',
);

checkDiffsExistFiles($serverUploadDir, $projectDir);

ftp_close($ftpConnection);
echo "\n";

function checkDiffsExistFiles($serverPath, $localPath){
    global $ftpConnection, $ignoreFiles;
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
                ssh2_sftp_unlink($ftpConnection, $serverPath.'/'.$serverFile['name']);
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
        ftp_mkdir($ftpConnection, $serverPath.$dir);
        checkDiffsExistFiles($serverPath.$dir, $localPath.$dir);
    }
    foreach($onlyOnServerDir as $dir){
        deleteDirOnServer($serverPath.$dir);
    }
}

function uploadFileToServer($serverPath, $localPath, $fileName){
    global $ftpConnection;
    $srcFile = fopen($localPath.'/'.$fileName, 'r');
    ftp_fput($ftpConnection, "{$ftpConnection}{$serverPath}/{$fileName}", $srcFile, FTP_BINARY);
    fclose($srcFile);
}

function deleteDirOnServer($delDir){
    global $ftpConnection;
    //サーバ上にしかないディレクトリは削除
    $fileList = getFileListFromLsComand($delDir, true);
    foreach($fileList as $file){
        if($file['is_dir']){
            deleteDirOnServer($delDir.'/'.$file['name']);
        }else{
            ftp_delete($ftpConnection, $delDir.'/'.$file['name']);
        }
    }
    ftp_rmdir($ftpConnection, $delDir);
}

function getFileListFromLsComand($dir, $isOnServer = false){
    global $ftpConnection;
    $lsResultArr = array();
    $dir = str_replace(' ', '\ ', $dir);
    if($isOnServer){
        $lsResultArr = ftp_rawlist($ftpConnection, $dir);
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
