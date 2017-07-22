# AltTray Plus β
[PopTray Minus](http://server-pro.com/poptrayminus/) に IMAP を追加したオルタナ版のメールチェッカー。

ブラウザで一覧 → 新着メールがポップアップ通知 → 読んだメールはまとめて削除。

# 使い方

## 1 .poptrayrc がある場合

AltTrayPlus フォルダ内に .poptrayrc を配置して下さい。

## 2 .poptrayrc がない場合

AltTrayPlus フォルダ内に .poptrayrc を作成し、以下の例を参考に入力して下さい。

    [account0]
    name=Yourname
    host=mail.yoursever.com
    port=995
    user=yourmail@yoursever.com
    passwd="xxxxxxxx"
    protocol=POP3 SSL
  
[account○]の数字は、アカウントを追加する場合に連番になるよう加算して下さい。

passwd は、端末などで base64 エンコードする必要があります。例：`echo xxxxxxxx | base64`
