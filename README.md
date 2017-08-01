# <img src="./icon.png" alt=alt> AltTray Plus β

ブラウザからチェック → ポップアップでお知らせ → 読んだメール＆いらないメールはまとめて削除

ヘッダと本文のみをプレビューできるので「偽装メール」や「ウィルスメール」対策にも最適


---------------------------------------


## 動作および推奨環境

* XAMPP for Linux 5.6.x、7.x
* Web Notifications API 対応ブラウザ


---------------------------------------


## 使い方

PopTray Minus ユーザーは「[.poptrayrc がある場合](https://github.com/DOlDNa/AltTrayPlus/blob/master/README.md#poptrayrc-がある場合)」を、そうでない方は「[.poptrayrc がない場合](https://github.com/DOlDNa/AltTrayPlus/blob/master/README.md#poptrayrc-がない場合)」を参照して下さい。

### .poptrayrc がある場合

* AltTrayPlus フォルダ内に .poptrayrc を配置

### .poptrayrc がない場合

1. AltTrayPlus フォルダ内にテキストファイルを作成
2. 下記を参考に入力し、ファイル名を .poptrayrc にリネーム

        [account0]
        name=Yourname
        host=mail.yoursever.com
        port=995
        user=yourmail@yoursever.com
        passwd="xxxxxxxx"
        protocol=POP3 SSL

* [account○]の数字は、複数のアカウントを追加する場合に連番となります
* passwd は、端末などで base64 エンコードする必要があります
    1. 端末：`echo xxxxxxxx | base64`
    2. PHP：`<?=base64_encode('xxxxxxxx');`


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

本ソフトウェアは、[PopTray Minus](http://server-pro.com/poptrayminus/) の設定ファイルである .poptrayrc を借用し、[Bootstrap](http://v4-alpha.getbootstrap.com/) および [jQuery](http://jquery.com/) を使用した。
