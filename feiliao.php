<?php
require('conf.php');
$settings = json_decode(file_get_contents('tmp/settings.json'), true);

if (isset($_REQUEST['logout'])) {
  header('HTTP/1.1 401 Unauthorized');
  die('Logged out');
}

$realm = 'Restricted area';

if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

    die('Unauthorized access'); //Cancel pressed
}


// analyze the PHP_AUTH_DIGEST variable
if (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
    !isset($users[$data['username']])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

    die('Wrong Credentials!');
}
#header('X-Debug:'.$_SERVER['PHP_AUTH_DIGEST']);

// generate the valid response
$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]['pass']);
$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

if ($data['response'] != $valid_response) {
    header('HTTP/1.1 401 Unauthorized');
    die('Wrong Credentials!');
}

// ok, valid username & password
#echo 'You are logged in as: ' . $data['username'];

function auth_fail() {
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Digest realm="'.$realm.
	 '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
}

// function to parse the http auth header
function http_digest_parse($txt)
{
    // protect against missing data
    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
    $data = array();
    $keys = implode('|', array_keys($needed_parts));

    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
        unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
}

$xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
$href = [];
$where = [];
$args = [];

if (isset($users[$data['username']]['func'])) {
  $users[$data['username']]['func']();
}

if (isset($_REQUEST['name'])) {
  $where[] = '_phone = ?' ;
  $args[] = $_REQUEST['name'];
  $href['name'] = $_REQUEST['name'];
}

if (isset($_REQUEST['date'])) {
  $start = strtotime($_REQUEST['date']);
  $end = $start + 86400;
  $where[] = '_timestamp > ?' ;
  $args[] = $start * 1000;
  $where[] = '_timestamp < ?' ;
  $args[] = $end * 1000;
  $href['date'] = $_REQUEST['date'];
}

$href_a = implode(array_map(function($v, $k) {
      return $k . '=' . $v;
    }, $href, array_keys($href)), '&');

if ($xhr) {
  header('Content: text/plain');
}
else {
?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv = "content-type" content = "text/html; charset=UTF-8" />
  <meta name="author" content="ZUO Haocheng" />
  <title>Feiliao record reader</title>  
  <link rel="stylesheet" href="feiliao.css" type="text/css" media="screen" />
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.1/jquery.min.js"></script>
  <script type="text/javascript" src="feiliao.js"></script>
</head>
<body>
<div id="settings" class="minor-list"><ul><li><a href="settings.php">设置</a></li><li><a href="?logout">登出</a></li></ul></div>
<form method="get" action="<?php echo $action ?>">
<?php foreach ($href as $k => $v) {
if ($k != 'date') {
?>
<input type="hidden" name="<?php echo $k; ?>" value="<?php echo $v; ?>" />
<?php }
} ?>
<label>日期<input type="date" name="date" value="<?php
if (isset($_REQUEST['date'])) {
  echo $_REQUEST['date'];
}
else {
  echo date("Y-m-d");
}?>" /></label>
<input type="submit" value="Search" />
</form>
<?php
}

    $page = 0;
    $itemperpage = 100;
    if (isset($_REQUEST['page'])) {
      $page = 0 + $_REQUEST['page'];
    }
    $start = $page * $itemperpage;
    
    try {
      $dbh = new PDO('sqlite:data/' . $settings['db']);
      $count = $dbh->query('SELECT count(1) from messages')->fetch()[0];

      $sql = 'SELECT COUNT(1) FROM messages';
      if (!empty($where)) {
	$sql .= ' WHERE ' . implode($where, ' AND ');
      }

      $stmt = $dbh->prepare($sql);
      $stmt->execute($args);
      $count = $stmt->fetch()[0];
      if ($href_a) {
	$t = $href_a . '&';
      }
      $pager = pager($itemperpage, $count, '?' . $t);



      $sql = 'SELECT * FROM messages';
      if (!empty($where)) {
	$sql .= ' WHERE ' . implode($where, ' AND ');
      }
      $sql .= ' LIMIT ?, ?';
      array_push($args, $pager[2], $itemperpage);
      $stmt = $dbh->prepare($sql);
      $stmt->execute($args);
      
      if (!$xhr) {
	echo $pager[0];
?>
<table>
<thead>
<tr>
  <th>Sender</th>
  <th>Receiver</th>
  <th>Time</th>
  <th>Content</th>
</tr>
</thead>
<tbody>
<?php
      }
      
      foreach($stmt as $row) {
	$phone = $row['_phone'];
	if (isset($addr[$phone])) {
	  $name = $addr[$phone];
	}
	else {
	  $name = $phone;
	}
        if ($href_a) {
          $t = '&' . $href_a;
        }
	$name = '<a href="?name=' . $phone . $t . '">' . $name . '</a>';
	if ($row['_ward'] == 1) {
	  $sender = $name;
	  $receiver = '我';
	}
	else {
	  $receiver = $name;
	  $sender = '我';
	}

	if ($row['_msg_type'] == 2 || $row['_msg_type'] == 6) {
	  $f = 'image/' . $row['_msg_file_name'];
	  if (file_exists($f)) {
	    $size = getimagesize($f);
	    $content = '';
	    if ($size[0] > 408 || $size[1] > 408) {
	      $content .= '<a href="' . $f . '" target="_blank">';
	    }
	    $content .= '<img src="' . $f . '" / alt="" />';
	    if ($size[0] > 408 || $size[1] > 408) {
	      $content .= '</a>';
	    }
	  }
	  else {
	    $content = '<span class="warn">无图无真相</span>';
	  }
	}
	elseif ($row['_msg_type'] == 3) {
	  preg_match('![^/]+$!', $row['_uri'], $filename);
	  $f = $filename[0];
	  if (file_exists('audio/' . $f)) {
	    $content = '<audio controls="controls">
  <source src="audio.php?name=' . $f . '" type="audio/wav" />
  Your browser does not support the audio element.
</audio>';
	  }
	  else {
	    $content = '<span class="warn">啦啦啦</span>';
	  }	  
	}
	else {
	  $content = $row['_content'];
	}
	
	echo '<tr><td>' . $sender . '</td><td>' . $receiver . '</td><td>' . date("Y-m-d H:i:s", $row['_timestamp']/1000) . '</td><td class="content">' . $content . '</td></tr>';
      }
      $dbh = null;
      
    }
    catch (PDOException $e) {
      print "Error!: " . $e->getMessage() . "<br/>";
      die();
    }


    #      echo '!!!';
if (!$xhr) {
?>
</tbody></table>
<?php echo $pager[1] ?>
</body>
</html>
<?php
}

function pager($rpp, $count, $href, $opts = array(), $pagename = "page") {
  global $lang_functions,$add_key_shortcut;
  $prev_page_href = '';
  $next_page_href = '';
  $pages = ceil($count / $rpp);

  if (array_key_exists('page', $opts)) {
    $page = $opts['page'];
  }
  else {
    if (!array_key_exists("lastpagedefault", $opts))
      $pagedefault = 0;
    else {
      $pagedefault = floor(($count - 1) / $rpp);
      if ($pagedefault < 0)
        $pagedefault = 0;
    }

    if (isset($_GET[$pagename])) {
      $page = 0 + $_GET[$pagename];
      if ($page < 0)
        $page = $pagedefault;
    }
    else
      $page = $pagedefault;
  }

  if (isset($opts['anchor'])) {
    $surfix = '#' . $opts['anchor'];
  }
  else {
    $surfix = '';
  }

  $mp = $pages - 1;
  $pagerprev = '';
  $pagernext = '';

  //Opera (Presto) doesn't know about event.altKey
  $is_presto = strpos($_SERVER['HTTP_USER_AGENT'], 'Presto');
  $as = "&lt;&lt;&nbsp;".$lang_functions['text_prev'];
  if ($page >= 1) {
    $prev_page_href = $href . $pagename . "=" . ($page - 1) . $surfix;
    $pagerprev = "<a href=\"".htmlspecialchars($prev_page_href). "\" title=\"".($is_presto ? $lang_functions['text_shift_pageup_shortcut'] : $lang_functions['text_alt_pageup_shortcut'])."\">";
    $pagerprev .= $as;
    $pagerprev .= "</a>";
  }
  else {
    $pagerprev = "<span class=\"selected\">".$as."</span>";
  }

  $as = $lang_functions['text_next']."&nbsp;&gt;&gt;";
  if ($page < $mp && $mp >= 0) {
    $next_page_href = $href . $pagename."=" . ($page + 1) . $surfix;
    $pagernext .= "<a href=\"".htmlspecialchars($next_page_href). "\" title=\"".($is_presto ? $lang_functions['text_shift_pagedown_shortcut'] : $lang_functions['text_alt_pagedown_shortcut'])."\">";
    $pagernext .= $as;
    $pagernext .= "</a>";
  }
  else {
       $pagernext = "<span class=\"selected\">".$as."</span>";
  }

  if ($count) {
    $pagerarr = array($pagerprev);
    $dotted = 0;
    $dotspace = 3;
    $startdotspace = 2;
    $dotend = $pages - $startdotspace;
    $curdotend = $page - $dotspace;
    $curdotstart = $page + $dotspace;
    for ($i = 0; $i < $pages; $i++) {
      if (($i >= $startdotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
        if (!$dotted)
        $pagerarr[] = '<a href="#" class="pager-more">...</a>';
        $dotted = 1;
        continue;
      }
      $dotted = 0;
      $start = $i * $rpp + 1;
      $end = $start + $rpp - 1;
      if ($end > $count) {
	$end = $count;
      }
      $text = "$start&nbsp;-&nbsp;$end";
      if ($i != $page) {
	$pagerarr[] = "<a class=\"pagenumber\" href=\"".htmlspecialchars($href . $pagename . "=" . $i . $surfix)."\">$text</a>";
      }
      else {
	$pagerarr[] = "<span class=\"selected\">$text</span>";
      }
    }
    $pagerarr[] = $pagernext;
    
    $pagerstr = '<ul><li>'.join("</li><li>", $pagerarr).'</li></ul>';
    $pagertop = "<div id='pagertop' class=\"pages minor-list list-seperator\">$pagerstr";
    $pagerbottom = "<div id='pagerbottom' class=\"pages minor-list list-seperator\" style=\"margin-bottom:0.6em;\">$pagerstr";

    $links = '<link rel="start" href="' . substr($href, 0, -1) . '" />';
    if ($prev_page_href) {
      $links .= '<link rel="prev" href="' . $prev_page_href . '" />';
    }
    if ($next_page_href) {
      $links .= '<link rel="next" href="' . $next_page_href . '" />';
    }

    if (isset($opts['link']) && $opts['link'] == 'bottom') {
      $pagerbottom .= $links;
    }
    else {
      $pagertop .= $links;
    }
    $pagertop .= "</div>";
    $pagerbottom .= "</div>";
  }
  else {
    $pagertop = "<div id='pagertop' class=\"pages minor-list\"></div>\n";
    $pagerbottom = "<div id='pagerbottom' class=\"pages minor-list\"></div>\n";
  }

  $start = $page * $rpp;
  return array($pagertop, $pagerbottom, $start, $next_page_href, $start);
}
