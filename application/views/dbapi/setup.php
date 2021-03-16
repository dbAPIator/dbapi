<html>
<head>
    <title>Setup</title>
    <base href="<?=$beBaseUrl?>">
</head>
<body>
<form>
<button formaction="setup/project">New</button>
</form>
<ul>
    <?php foreach ($configs as $config):?>
    <li><a href="setup/project/<?=$config?>"><?=$config?></a> </li>
    <?php endforeach;?>
</ul>
</body>
</html>