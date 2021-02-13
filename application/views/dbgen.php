<html>
<header>
    <title>Config gen</title>
    <style>
        label{
            display: inline-block;
            width: 200px;
        }

    </style>
</header>
<form action="ConfigGen/mysql" method="post">
    <fieldset>
        <legend>DB Connection data</legend>
        <label for="hostname">Hostname</label><input type="text" name='hostname' id='hostname' value="localhost"><br>
        <label for="username">Username</label><input type="text" name='username' id='username' value="vsergiu"><br>
        <label for="password">Password</label><input type="text" name='password' id='password' value="parola123"><br>
        <label for="database">Database</label><input type="text" name='database' id='database'><br>
        <button type="submit">Submit</button>
    </fieldset>
</form>
</html>