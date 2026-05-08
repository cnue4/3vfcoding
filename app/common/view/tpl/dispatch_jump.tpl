{__NOLAYOUT__}<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>跳转提示</title>
    <style type="text/css">
        *{box-sizing:border-box;margin:0;padding:0;font-family:Lantinghei SC,Open Sans,Arial,Hiragino Sans GB,Microsoft YaHei,"微软雅黑",STHeiti,WenQuanYi Micro Hei,SimSun,sans-serif;-webkit-font-smoothing:antialiased}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#edf1f4;font-weight:400;font-size:16px;-webkit-text-size-adjust:none;color:#333}
        a{outline:0;color:#3498db;text-decoration:none;cursor:pointer}
        .system-message{width:min(92vw,760px);margin:0 auto;padding:clamp(28px,4vw,44px) clamp(20px,4vw,36px);background:#fff;box-shadow:0 12px 40px hsla(0,0%,39%,.12);border-radius:20px;text-align:center}
        .system-message h1{margin:0 0 12px;color:#444;font-weight:500;font-size:clamp(30px,5vw,48px);line-height:1.25}
        .system-message .jump,.system-message .image{margin:20px 0;padding:10px 0;font-weight:400}
        .system-message .jump{font-size:14px;color:#666}
        .system-message .jump a{color:#333}
        .system-message p{font-size:14px;line-height:24px}
        .system-message .actions{display:flex;justify-content:center;flex-wrap:wrap;gap:12px;margin-top:10px}
        .system-message .btn{display:inline-flex;align-items:center;justify-content:center;min-width:138px;height:40px;padding:0 22px;border:1px solid #44a0e8;border-radius:30px;color:#44a0e8;text-align:center;font-size:16px;line-height:1;transition:all .2s ease}
        .system-message .btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
        .success .btn{border-color:#69bf4e;color:#69bf4e}
        .error .btn{border-color:#e699c6;color:#e699c6}
        .info .btn{border-color:#3498db;color:#3498db}
        .copyright p{width:100%;color:#919191;text-align:center;font-size:10px}
        .system-message .btn-grey{border-color:#bbb;color:#888}
        .clearfix:after{clear:both;display:block;visibility:hidden;height:0;content:"."}
        .system-message .image img{width:clamp(88px,18vw,150px);height:auto;max-width:100%}
        @media (max-width:768px){body{padding:16px;}.system-message{width:100%;border-radius:16px;}.system-message .actions{flex-direction:column;}.system-message .btn{width:100%;min-width:0;}}
    </style>
</head>
<body>
<?php
	$codeText = $code == 1 ? 'success' : ($code == 0 ? 'error' : 'info');
    $plainMsg = strip_tags($msg);
    $isNoPermission = strpos($plainMsg, '没有权限') !== false || strpos($plainMsg, '无权') !== false;
?>
<div class="system-message {$codeText}">
    <div class="image">
        <img src="/static/common/images/{$codeText}.svg" alt="" />
    </div>
    <h1><?php echo($plainMsg);?></h1>
    <p class="jump">
        <?php if($isNoPermission): ?>
            当前操作未授权，你可以返回上一页或取消当前弹框。
        <?php else: ?>
            页面将在 <span id="wait"><?php echo($wait);?></span> 秒后自动跳转
        <?php endif; ?>
    </p>
    <p class="actions clearfix">
        <a href="#" onClick="javascript:history.back(-1);return false;" class="btn btn-grey">返回上一页</a>
        <?php if($isNoPermission): ?>
            <a href="#" onClick="closeCurrentDialog();return false;" class="btn btn-primary">取消操作</a>
        <?php else: ?>
            <a id="href" href="{$url}" class="btn btn-primary">立即跳转</a>
        <?php endif; ?>
    </p>
</div>
<script type="text/javascript">
    function closeCurrentDialog(){
        try {
            if (window.parent && window.parent !== window && window.parent.layer) {
                var index = window.parent.layer.getFrameIndex(window.name);
                if (typeof index !== 'undefined') {
                    window.parent.layer.close(index);
                    return;
                }
            }
        } catch (e) {}
        try {
            window.close();
        } catch (e) {}
        try {
            history.back(-1);
        } catch (e) {}
    }
    (function(){
        var hrefNode = document.getElementById('href');
        var wait = document.getElementById('wait');
        if(!hrefNode || !wait){
            return;
        }
        var href = hrefNode.href;
        var interval = setInterval(function(){
            var time = parseInt(wait.innerHTML, 10) - 1;
            wait.innerHTML = time;
            if(time <= 0) {
                location.href = href;
                clearInterval(interval);
            }
        }, 1000);
    })();
</script>
</body>
</html>
