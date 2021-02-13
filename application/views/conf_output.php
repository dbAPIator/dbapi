<html>
<header>
    <title>Config gen</title>
    <style>
        textarea{
            width: 45%;
            height: 500px;
            background: #e6e6e6;
        }
        textarea:focus{
            background: black;
            color: #00CC00;
            font-weight: bold;
        }
    </style>
</header>
<form action="ConfigGen/mysql" method="post">
    <fieldset>
        <textarea><?=$structure?></textarea>
        <textarea><?=$connection?></textarea>
    </fieldset>
</form>
</html>