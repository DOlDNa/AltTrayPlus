# <img src="./icon.svg" alt=alt width=64 height=50> AltTray Plus 2

## 概要

ローカルホスト専用のメールリーダーです。
ブラウザ上で「メールチェック」、「内容確認」、「テキストで保存」、「添付ファイルの保存」、「メールの削除」をすることができます。
「送信」と「返信」はできません。

メール本文の HTML ダグは除去されるため、素早く安全に内容確認することができます。
また、blacklist.txt にメールアドレスを一行ずつ書くことで、スパムメールなどを自動削除することができます。

---------------------------------------


## 動作および推奨環境

* XAMPP for Linux 7.x / Docker
* Web Notifications API 対応ブラウザ


---------------------------------------


## インストール

AltTrayPlus フォルダをローカルホストのドキュメントルートに配置し、ブラウザでアクセスします。

PopTray Minus ユーザーは「[.poptrayrc がある場合](#poptrayrc-がある場合)」を、そうでない方は「[.poptrayrc がない場合](#poptrayrc-がない場合)」を参照して下さい。

### .poptrayrc がある場合

1. AltTrayPlus フォルダ内に .poptrayrc を配置

### .poptrayrc がない場合

1. http://localhost/AltTrayPlus/?config にアクセスし、フォームにアカウントを入力

---------------------------------------


## 謝辞

本ソフトウェアには、[PopTray Minus](http://server-pro.com/poptrayminus/) の設定ファイルである .poptrayrc と [jQuery](http://jquery.com/) を使用した。
また、Cog_font_awesome.svg は Dave Gandy 氏の作品であり CC BY-SA 3.0 が適用されている。
