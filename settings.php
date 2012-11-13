<?php
$f = file_get_contents('tmp/settings.json');
$settings = json_decode($f, true);
?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv = "content-type" content = "text/html; charset=UTF-8" />
    <meta name="author" content="ZUO Haocheng" />
    <title>Settings - Feiliao record reader</title>  
    <link rel="stylesheet" href="feiliao.css" type="text/css" media="screen" />
  </head>
<body>
  聊天记录在 <code>/data/data/com.feinno.felio/databases/data.db</code>
<?php
    if (isset($_POST['db'])) {
      $db = $_POST['db'];
      if (file_exists('./data/' . $db)) {
	$settings['db'] = $db;

	file_put_contents('tmp/settings.json', json_encode($settings));
	?><div>Saved.</div>
<?php
      }
      else {
	?><div>Invalid input.</div>
<?php
      }
    }
?>
<form method="post" action="">
  <label>选择数据库
  <select name="db">
<?php
    $f = opendir('./data');
while ($file = readdir($f)) {
  if ($file !== '.' && $file !== '..' && preg_match('/\.db$/', $file)) {
    ?>
<option value="<?php echo $file ?>"<?php
    if ($settings['db'] === $file) {
?> selected="selected"<?php
}
?>><?php echo $file ?></option>
<?php
  }
}

?>
</select>
  </label>
  <input type="submit" value="Submit" />
</form>

</body>
</html>


