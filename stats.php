<!DOCTYPE html>
<html>
<head>
  <meta http-equiv = "content-type" content = "text/html; charset=UTF-8" />
  <meta name="author" content="ZUO Haocheng" />
  <title>Feiliao record stats</title>  
  <link rel="stylesheet" href="feiliao.css" type="text/css" media="screen" />
</head>
<body>
<table>
<thead>
<tr>
  <th>Word</th>
  <th>Count</th>
</tr>
</thead>
<tbody>
<?php
try {
  $so = scws_new();
  $dbh = new PDO('sqlite:data.db');
#  $dbw = new PDO('mysql:host=localhost;dbname=feiliao', 'hudbt', '123456');#, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
#  $dbw->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
#  $dbw->query('SET NAMES utf8');
#  $dbw->query('DROP TABLE IF EXISTS word_stats');
#  $dbw->query("CREATE TABLE `word_stats` (`word` VARCHAR(60) NOT NULL, `count` INT(10) NOT NULL DEFAULT '0', PRIMARY KEY (`word`))");
#  $dbw->query('TRUNCATE TABLE word_stats');
#  $dbw->query('insert into word_stats values ("a", 10)');
#  $ifexists = $dbw->prepare("SELECT count(1) FROM word_stats WHERE word = ?");
#  $insert = $dbw->prepare('INSERT INTO word_stats (word, count) VALUES (?, ?)');
#  $update = $dbw->prepare('UPDATE word_stats SET count = count + 1 WHERE word = ?');

  $a = [];
  
  foreach ($dbh->query("SELECT * FROM messages") as $row) {
    $so->send_text($row['_content']);
    while ($words = $so->get_result()) {
      foreach ($words as $word) {
	$w = $word['word'];
	if (isset($a[$w])) {
	  $a[$w] += 1;
	}
	else {
	  $a[$w] = 1;
	}
      }
    }
  }

  $words = array_keys($a);
  $counts = array_values($a);
  array_multisort($counts, SORT_DESC, $words);

  for ($i=0; $i< 100; ++$i) {
    echo '<tr><td>' . $counts[$i] . '</td><td>' . $words[$i] . '</td></tr>';
  }  
  $so->close();

  $dbh = null;
#  $dbw = null;

}
catch (PDOException $e) {
  print "Error!: " . $e->getMessage() . "<br/>";
  die();
}
?>
</tbody>
</table>
</body>
</html>