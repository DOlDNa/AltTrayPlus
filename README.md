# <img src="./icon.png" alt=alt> AltTray Plus β

[PopTray Minus](http://server-pro.com/poptrayminus/) に IMAP を追加した PHP 版のメールチェッカー

ブラウザからチェック → ポップアップでお知らせ → 読んだメール＆いらないメールはまとめて削除

ヘッダーと本文のみをプレビュー表示できるので「偽装メール」や「ウィルスメール」対策にも最適


---------------------------------------


## 動作および推奨環境

* XAMPP for Linux 5.6.x、7.x
* Web Notifications API 対応ブラウザ


---------------------------------------


## 使い方
PopTray Minus ユーザーは「.poptrayrc がある場合」を、
そうでない方は「.poptrayrc がない場合」を参照して下さい。

### .poptrayrc がある場合

* AltTrayPlus フォルダ内に .poptrayrc を配置

### .poptrayrc がない場合

* AltTrayPlus フォルダ内にテキストファイルを作成
* 下記を参考に入力し、ファイル名を .poptrayrc にリネーム

        [account0]
        name=Yourname
        host=mail.yoursever.com
        port=995
        user=yourmail@yoursever.com
        passwd="xxxxxxxx"
        protocol=POP3 SSL

* [account○]の数字は、複数のアカウントを追加する場合に連番となります
* passwd は、端末などで base64 エンコードする必要があります
    * 例：`echo xxxxxxxx | base64`


---------------------------------------


## 問題点

### 遅い

* 多数のアカウントを設定した場合、一覧が表示されるまでに時間が掛かる
    * 1アカウント ≒ 1秒
    
* メール削除を実行した後、再取得を経てからリダイレクトを行うため時間が掛かる
    * `imap_open` → `imap_expunge` → `imap_open` → `location.replace` → `imap_open`

### interval と black list が未実装

* interval は `Refresh` で代替した

* black list は迷惑メールブロックサービスの利用を推奨


---------------------------------------


## 謝辞

本ソフトウェアは、PopTray Minus の設定ファイルである .poptrayrc を借用し、Bootstrap および jQuery を使用した。
