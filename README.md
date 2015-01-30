# using-ftp-sftp-with-php
Send and delete remote files with PHP using FTP/SFTP

sftp_upload.php
動作確認済み
コード内でホスト，ユーザ名，パスワードを指定
リモートのアップロード先ディレクトリとローカルのアップロード元ディレクトリを指定
アップロード先のディレクトリは事前につくっておく
　/home/junji/test を指定した場合は/testをつくっておく
　それ以下の階層にあるディレクトリはつくってくれる

挙動
　1. ローカルのアップロード元ディレクトリにあるファイル，ディレクトリは全てアップロードする
　　※$ignoreFilesで指定したファイルはアップロードの対象外となる
　2. リモートにのみ存在するファイルは削除する
　　※$ignoreFilesで指定したファイルでも，ローカルに存在すれば削除しない

ftp_upload.php
動作未確認である点以外はsftp_upload.phpと同一の内容
