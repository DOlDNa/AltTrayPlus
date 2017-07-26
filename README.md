# AltTray Plus β

[PopTray Minus](http://server-pro.com/poptrayminus/) に IMAP を追加したオルタナ版のメールチェッカー。

ブラウザで受信 → 新着メールはポップアップでお知らせ → 読んだメールはまとめて削除。


---------------------------------------


## 使い方


### 1 .poptrayrc がある場合

AltTrayPlus フォルダ内に .poptrayrc を配置


### 2 .poptrayrc がない場合

AltTrayPlus フォルダ内に .poptrayrc を作成し、以下のように入力して下さい

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

* アカウントが複数ある場合、表示に時間が掛かる
    * 1アカウント ≒ 1秒
    
* メール削除を実行した後、再取得を経てからリダイレクトを行うため時間が掛かる
    * `imap_open` → `imap_expunge` → `imap_open` → `location.replace` → `imap_open`


### interval と black list が未実装

* interval は Refresh で代替

* black list は迷惑メールブロックサービスの利用を推奨
