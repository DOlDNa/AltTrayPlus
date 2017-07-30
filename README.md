# <img src="./icon.png" alt=alt> AltTray Plus β

[PopTray Minus](http://server-pro.com/poptrayminus/) に IMAP をプラスしたオルタナ版メールチェッカー

ブラウザからチェック → ポップアップでお知らせ → 読んだメール＆いらないメールはまとめて削除


---------------------------------------


## 使い方

### 1 .poptrayrc がある場合

* AltTrayPlus フォルダ内に .poptrayrc を配置

### 2 .poptrayrc がない場合

* AltTrayPlus フォルダ内にテキストファイルを作成
* 下記コードを参考に入力して、ファイル名を .poptrayrc にリネーム

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


## 動作および推奨環境

* XAMPP for Linux 5.6.x および 7.x
* Web Notifications API 対応ブラウザ

---------------------------------------


## 問題点

### 遅い

* 多数のアカウントを設定した場合、一覧が表示されるまでに時間が掛かる
    * 1アカウント ≒ 1秒
    
* メール削除を実行した後、再取得を経てからリダイレクトを行うため時間が掛かる
    * `imap_open` → `imap_expunge` → `imap_open` → `location.replace` → `imap_open`

### interval と black list が未実装

* interval は Refresh で代替した

* black list は迷惑メールブロックサービスの利用を推奨
