<?php
if (!isset($_REQUEST['name'])) {
  die;
}
$file = 'audio/' . $_REQUEST['name'];
if (file_exists($file)) {
  header('Content-type: audio/wav');
  header('Cache-control:public, max-age=31536000');
  header('Expires: ' . date('r', time() + 31536000));
  lastModified(date('r', filemtime($file)));
  if (extension_loaded('apc')) {
    $result = apc_fetch($file);
  }
  else {
    $result = false;
  }

  if ($result === false) {
    $result = shell_exec('ffmpeg -i ' . escapeshellarg($file) . ' -f wav pipe:1');
    if (extension_loaded('apc')) {
      apc_store($file, $result, 31536000);
    }
  }
  echo $result;
}

function lastModified($modifiedTime, $notModifiedExit = true) {
  $ret = false;
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $modifiedTime == $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
      if ($notModifiedExit) {
	header("HTTP/1.0 304 Not Modified");
        exit();
      }
      else {
	$ret = true;
      }
    }
    header("Last-Modified: $modifiedTime");
    return $ret;
}
