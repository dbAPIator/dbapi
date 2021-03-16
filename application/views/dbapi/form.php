<html>
<head>
    <title>Setup</title>
    <base href="<?=$beBaseUrl?>">

    <style>
        label{
            display: inline-block;
            width: 180px;
        }
        input{
            clear: left;
        }
    </style>
</head>
<body>
<a href="../">Back</a>
<form action="setup/project" method="post">
    <fieldset>
        <legend>DB Connection Config</legend>
        <label>Project name</label><input type="text" required name="projectname" value="<?=@$projectname;?>" <?=isset($projectname)?"disabled":"";?>><br>
        <label>Type</label><input type="text"  required name="dbdriver" value="<?=@$dbdriver;?>"><br>
        <label>Host(:port)</label><input type="text"  required name="hostname" value="<?=@$hostname;?>"><br>
        <label>User</label><input type="text" name="username"  required value="<?=@$username;?>"><br>
        <label>password</label><input type="text" name="password"  required value="<?=@$password;?>"><br>
        <label>DB/Schema name</label><input type="text" name="database"  required value="<?=@$database;?>"><br>
        <button>Save & Generate</button>
    </fieldset>
</form>
</body>
</html>