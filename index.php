<?php
header( 'Refresh: 900' );
header( 'Pragma: no-cache' );
date_default_timezone_set( 'Asia/Tokyo' );
error_reporting( E_ERROR );
$n = PHP_EOL;
$blk = $col = $total = $last_error = $notify = null;
$delete = filter_input( INPUT_POST, 'delete', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY );
function h( $str )
{
    return htmlspecialchars( $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function s( $bytes )
{
    if ( $bytes >= 1048576 )
        return ceil( $bytes / 1048576 ) . 'MB';
    elseif ( $bytes >= 1024 )
        return ceil( $bytes / 1024 ) . 'KB';
    else
        return $bytes . 'B';
}
function d( $str, $num )
{
    switch ( $num )
    {
        case 1 : return imap_qprint( imap_8bit( $str ) );
        case 3 : return imap_base64( $str );
        case 4 : return imap_qprint( $str );
        default : return $str;
    }
}
function l( $str )
{
    return preg_replace( '/(https?:\/\/[0-9a-z' . preg_quote( '!#$%&\'()*+,-./:;=?@[]_~', '/' ) . ']+)/i', '<a href="\\1" class=text-danger target=_blank>\\1</a>', $str );
}
ob_implicit_flush( true );
?>
<!doctype html>
<html lang=ja>
    <head>
        <meta charset=utf-8>
        <meta name=viewport content="width=device-width,initial-scale=1">
        <title>AltTray Plus β</title>
        <link href=css/ rel=stylesheet>
        <meta name=description content="PopTray Minus に IMAP をプラスしたオルタナティブなメールチェッカー AltTray Plus">
        <link href=icon.svg rel=icon type=image/svg+xml sizes=any>
    </head>
    <body>
        <form method=post>
            <script>var d=document,div=d.createElement("div"),f=d.getElementsByTagName("form")[0],n=Notification;f.style.display="none";div.setAttribute("id","loading-bg");d.body.appendChild(div);div.innerHTML="<d"+"iv id=loading><i>読<\/i><i>み<\/i><i>込<\/i><i>み<\/i><i>中<\/i><\/d"+"iv>";setTimeout(function(){div.innerHTML="<d"+"iv id=loading><i>タイムアウトしました<\/i><i>リロードして下さい<\/i><\/d"+"iv>"},60000)</script><?php ob_flush();echo $n?>
            <nav class="navbar navbar-inverse bg-danger fixed-top">
                <div class=form-inline>
                    <span class=navbar-brand>AltTray Plus <small><sup class="badge badge-pill badge-info">β</sup></small></span><?=file_exists( $rc = '.poptrayrc' ) && is_file( $rc ) && is_readable( $rc ) ? '
                    <span class="navbar-brand text-right"><button accesskey=d tabindex=1 class="btn btn-warning" type=submit id=del>選択したメールを削除する</button></span>' . $n : $n?>
                </div>
            </nav>
            <div class=container-fluid><?=$n;
                if ( is_readable( $rc ) )
                {
                    $ini = parse_ini_file( $rc, true );
                    for ( $i = 0, $c = count( $ini ); $i < $c; ++$i )
                    {
                        switch ( $ini['account'.$i]['protocol'] )
                        {
                            case 'POP3 SSL' : $protocol = 'pop3/ssl'; break;
                            case 'IMAP' : $protocol = 'imap/ssl'; break;
                            default : $protocol = 'pop3'; break;
                        }
                        if ( $imap = imap_open( '{' . $ini['account'.$i]['host'] . ':' . $ini['account'.$i]['port'] . '/' . $protocol . '/novalidate-cert}INBOX', $ini['account'.$i]['user'], base64_decode( $ini['account'.$i]['passwd'] ), OP_SILENT ) or $last_error = imap_last_error() )
                        {
                            $d = imap_num_msg( $imap );
                            if ( $d > 0 )
                            {
                                $notify .= 'new n("' . h( $ini['account'.$i]['name'] ) . '",{icon:"./icon.png",body:"新着メールが' . $d . '件あります。"});';
                                echo
                                '                <table class="table table-bordered table-hover table-inverse">', $n,
                                '                    <tr><th colspan=7 class="h5 bg-primary">', h( $ini['account' . $i]['name'] ), ' <small><sup class="badge badge-pill badge-info">', $d, '</sup></small></th></tr>', $n,
                                '                    <tr><th class=text-center>削除</th><th class=text-center>詳細</th><th>差出人</th><th>宛先</th><th>日付</th><th class=text-center>サイズ</th><th>件名</th></tr>', $n;
                                for ( $j = 1; $j <= $d; ++$j )
                                {
                                    if ( $delete )
                                    {
                                        for ( $h = 0, $b = count( $delete ); $h < $b; ++$h )
                                        {
                                            list( $blk, $col ) = explode( '+', $delete[$h] );
                                            if ( $i == $blk && $j == $col )
                                                imap_delete( $imap, $col );
                                        }
                                    }
                                    $headerinfo = imap_headerinfo( $imap, $j );
                                    if ( isset( $headerinfo -> subject ) )
                                    {
                                        if ( stripos( $headerinfo -> subject, '=?' ) !== false )
                                            $subject = mb_decode_mimeheader( trim( $headerinfo -> subject ) );
                                        else
                                            $subject = trim( $headerinfo -> subject );
                                        $subject = h( str_replace( array( '/', ':', '!', '?', '&' ), '-', $subject ) );
                                        if ( isset( $headerinfo -> from[0] -> personal ) )
                                            $personal = stripos( $headerinfo -> from[0] -> personal, '=?' ) !== false ? h( mb_decode_mimeheader( $headerinfo -> from[0] -> personal ) ) : h( $headerinfo -> from[0] -> personal );
                                        else
                                            $personal = h( $headerinfo -> from[0] -> mailbox ) . '@' . h( $headerinfo -> from[0] -> host );
                                        $header = str_replace( "\r\n", '&#10;', h( imap_fetchbody( $imap, $j, 0 ) ) );
                                        if ( stripos( $header, '=?' ) !== false )
                                            $header = mb_decode_mimeheader( $header );
                                        $structure = imap_fetchstructure( $imap, $j );
                                        if ( $structure -> parameters[0] -> attribute === 'CHARSET' )
                                            $charset = strtoupper( trim( $structure -> parameters[0] -> value ) );
                                        elseif ( $structure -> parts[0] -> parameters[0] -> attribute === 'CHARSET' )
                                            $charset = strtoupper( trim( $structure -> parts[0] -> parameters[0] -> value ) );
                                        else
                                            $charset = '';
                                        $body = trim( imap_fetchbody( $imap, $j, 1.2 ) );
                                        if ( ! $body )
                                            $body = trim( imap_fetchbody( $imap, $j, 1 ) );
                                        if ( isset( $structure -> encoding ) && $structure -> encoding > 0 )
                                            $body = d( $body, $structure -> encoding );
                                        if ( isset( $structure -> parts[0] -> encoding ) )
                                            $body = d( $body, $structure -> parts[0] -> encoding );
                                        $body = isset( $charset ) && $charset !== 'UTF-8' ? mb_convert_encoding( $body, 'UTF-8', $charset ) : $body;
                                        $body = l( h( $body ) );
                                        $body = str_replace( "\r\n", '&#10;', $body );
                                        $attachment = isset( $structure -> parts ) ? count( $structure -> parts )-1 : 0;
                                        echo
                                        '                    <tr id="t', $i, '-', $j, '">', $n,
                                        '                        <td class="text-center align-middle"><div class=form-check><label class=form-check-label><input type=checkbox class=form-check-input id="c', $i, '-', $j, '" value="', $i, '+', $j, '" name=delete[]></label></div></td>', $n,
                                        '                        <td class="text-center align-middle">', $n,
                                        '                            <a class=text-white data-toggle=collapse href="#col', $i, '-', $j, '" onclick="scrl(\'#t', $i, '-', $j, '\')">表示</a><br>', $n,
                                        '                            <a class=text-white href="#col', $i, '-', $j, '" onclick="$(this).attr(\'download\',\'', $subject, '.txt\').attr(\'href\',\'data:application/octet-stream,\'+encodeURIComponent($(\'#d', $i, '-', $j, '\').text()))">保存</a>', $n,
                                        '                        </td>', $n,
                                        '                        <td class=align-middle>', $personal, ' &lt;', h( $headerinfo -> from[0] -> mailbox ), '@', h( $headerinfo -> from[0] -> host ), '&gt;</td>', $n,
                                        '                        <td class=align-middle>', ( isset( $headerinfo -> toaddress ) ? stripos( $headerinfo -> toaddress, '=?' ) !== false ? h( mb_decode_mimeheader( $headerinfo -> toaddress ) ) : h( $headerinfo -> toaddress ) : 'Undisclosed-Recipients:;' ), '</td>', $n,
                                        '                        <td class=align-middle>', date( 'Y/n/j H:i:s', strtotime( $headerinfo -> Date ) ), '</td>', $n,
                                        '                        <td class="text-center align-middle">', s( $headerinfo -> Size ), '</td>', $n,
                                        '                        <td class=align-middle>', $subject, ( $attachment > 0 ? ' <small><sup class="badge badge-pill badge-info">添付x' . $attachment . '</sup></small>' : '' ), '</td>', $n,
                                        '                    </tr>', $n,
                                        '                    <tr class=collapse id="col', $i, '-', $j, '">', $n,
                                        '                        <td colspan=7 class=collapsed>', $n,
                                        '                            <table>', $n,
                                        '                                <tr>', $n,
                                        '                                    <td class=detail>', $header, '</td>', $n,
                                        '                                    <td class=detail id="d', $i, '-', $j, '">', $body, '</td>', $n,
                                        '                                </tr>', $n,
                                        '                                <tr>', $n,
                                        '                                    <td colspan=2 class=text-center>', $n,
                                        '                                        <a class="btn btn-primary btn-lg" data-toggle=collapse href="#col', $i, '-', $j, '" onclick="scrl(\'#t', $i, '-', $j, '\')">閉じる</a> ', $n,
                                        '                                        <a class="btn btn-danger btn-lg" data-toggle=collapse href="#col', $i, '-', $j, '" onclick="delc(\'#c', $i, '-', $j, '\');scrl(\'#t', $i, '-', $j, '\')">削除にチェックを入れて閉じる</a>', $n,
                                        '                                    </td>', $n,
                                        '                                </tr>', $n,
                                        '                            </table>', $n,
                                        '                        </td>', $n,
                                        '                    </tr>', $n;
                                    }
                                }
                                echo
                                '                </table>', $n;
                                $total += ( int )$d;
                            }
                            else
                                echo
                                '                <table class="table table-bordered table-hover table-inverse">', $n,
                                '                    <tr><th class="h5 bg-primary">', h( $ini['account'.$i]['name'] ), ' <small><sup class="badge badge-pill badge-info">0</sup></small></th></tr>', $n,
                                '                    <tr><td>', $last_error, '</td></tr>', $n,
                                '                </table>', $n;
                        }
                        if ( $delete )
                            imap_close( $imap, CL_EXPUNGE );
                        else
                        {
                            imap_errors();
                            imap_close( $imap );
                        }
                    }
                }
                else
                    echo
                    '                <h2 class=text-primary><img src=icon.svg alt=alt width=48> AltTray Plus の使い方</h2>', $n,
                    '                <ol>', $n,
                    '                    <li>ホーム直下にある不可視ファイルの <strong class=text-danger>.poptrayrc</strong> を <strong class=text-primary>',__DIR__,'</strong> にコピーして下さい。</li>', $n,
                    '                    <li>コピーした <strong class=text-danger>.poptrayrc</strong> のパーミッションを、755 など「読み込み可能」に変更して下さい。</li>', $n,
                    '                    <li>ブラウザのリロードボタンを押して、暫くお待ち下さい。</li>', $n,
                    '                    <li>自動チェックの間隔を変えたいときは、<strong class=text-primary>',__FILE__,'</strong> をテキストエディタで開き、『Refresh』の値を変えて下さい。デフォルトは 900 = 15分です。</li>', $n,
                    '                    <li>メールを削除するときは、各行のチェックボックスをクリックしてから <span class="btn btn-warning">選択したメールを削除する</span> を押して下さい。</li>', $n,
                    '                </ol>', $n;
                if ( $delete )
                    exit( '<script>location.replace("./")</script><meta http-equiv=refresh content="0;URL=./?d">' );
                ?>
                <footer class=text-right><small class="badge badge-pill badge-primary"><a class=text-white href=./License.html>© <?=date( 'Y' )?> AltTray Plus</a></small></footer>
            </div>
        </form><?=$total > 0 ? '
        <script>d.title="' . $total . '件受信 - AltTray Plus β";n.requestPermission(function(p){if(p==="granted"){' . $notify . '}})</script>' . $n : $n?>
        <script src=js/></script>
    </body>
</html>
