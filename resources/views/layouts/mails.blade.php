<?php 

$urlUnSubscribe = isset($urlUnListen) ? $urlUnListen : false;

?>

<div>
    @yield('content')
</div>
<table style="background-color: #ffffff; margin-top: 50px" cellspacing="0" cellpadding="0">
<tr>
    <td style='vertical-align: middle; text-align: left; width: 45px;' width='45'><img src="<?php echo $message->embed(public_path().'/img/logo-mini-bot-xsm.png'); ?>" width="32" height="35" /></td>
    <td style='vertical-align: middle; text-align: left;'>Искренне Ваш,<br>Борис Бот<br><a href='https://BorisBot.com'>BorisBot.com</a></td>
</tr> 
</table>
<?php if($urlUnSubscribe !== false){ ?>    
<p style="font-size: 9px; color: #888; margin-top:20px;">
    Отписаться от уведомлений можно <a href="<?php echo $urlUnSubscribe; ?>" style="color:#888; text-decoration: underline;">тут</a>
</p>
<?php } ?>