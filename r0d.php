<?php
static $allow_extensions = [
    'php', 'js', 'jsx', 'vue', 'css', 'scss', 'less', 'html', 'htm', 'shtml',
    'txt', 'md', 'conf', 'ini', 'htaccess', 'htpasswd', 'gitignore', 'sql',
    'pl', 'cgi', 'asp', 'py', 'sh', 'bat', 'ps1',
    'xml', 'json', 'svg',
    // 'pas', 'c', 'cpp', 'h', // NO! Some legacy IDEs doesn't supports /n without /r.
];


// gettings arguments
if (!is_array($argv) || ($i = count($argv)) < 2) {
  echo <<<END
Usage: r0d.php [options] [filename or mask] [output filename (optionally)]
r0d.php [mask, like *.php, or * to find all files with allowed extensions (including hidden files, .htaccess, etc)] [-r (to process subdirectories, if directory names match mask)]

Options:
  -s or -r: process subdirectories
  -c:src_charset~target_charset: convert from specified charset into another. If target_charset not specified, file converted to UTF-8.
                                 WARNING! double conversion is possible if conversion is not from or into UTF-8.
  -fix-html: BETA & EXPERIMENTAL!!! Removes odd tags, converts HTML entities into their UTF-8 representation charaters.
  -i: inform about possible optimization/convertion without optimization/convertion.
END;
  exit;
}

$source_file = '';
$target_file = '';
$process_subdirectories = false;
$convert_charset = false;
$inform_only = false;
$fix_html = false;

unset($argv[0]);
foreach ($argv as $arg) {
    if (($arg[0] === '-') && ($option = strtolower(substr($arg, 1)))) {
        if ($option === 's' || $option === 'r') {
            $process_subdirectories = true;
            continue;
        }

        if (substr($option, 0, 2) === 'c:') {
            $charsets = explode('~', substr($option, 2));
            $convert_charset = strtolower($charsets[0]);
            $convert_charset_target = isset($charsets[1]) ? strtolower($charsets[1]) : 'utf-8';
            continue;
        }

        if ($option === 'i') {
            $inform_only = true;
            continue;
        }

        if ($option === 'fix-html') {
            $fix_html = true;
            continue;
        }
    }

    // non-options
    if (!$source_file)
        $source_file = $arg;
    elseif (!$target_file)
        $target_file = $arg;
}



// Check
if ((!$is_wildcard = (strpos($source_file, '*') !== false)) && (!file_exists($source_file) || is_dir($source_file))) {
    echo "File \"$source_file\" not found.\n";
    exit;
}

// TOOLS
function remove_utf8_bom($t) {
    $bom = pack('H*', 'EFBBBF');
    return preg_replace("/^$bom/", '', $t);
}

function strip_char($t, $char) {
    return preg_replace("/$char/", '', $t);
}

function r0d_file($source_file, $target_file = false) {
  global $convert_charset, $convert_charset_target, $inform_only, $fix_html;

  $data_changed = false;
  if ($data = file_get_contents($source_file)) {
    $source_size = strlen($data);

    $data = remove_utf8_bom($data);

    if (strpos($data, "\r") !== false) {
        // if file contains \r, but has no \n, let's replace all \r to \n.
        if (strpos($data, "\n") === false) {
            $data = str_replace("\r", "\n", $data);
            $data_changed = true;
        }else
            $data = strip_char($data, "\r"); // // \r = chr(13) = carriage return. (We don't want \r\n, we'd like to have only \n.)
    }

    // remove spaces and tabs before the end of each line.
    $data = preg_replace('/[ \x00\xa0\t]+(\n|$)/', "$1", $data);


    // now try to recode the charset, if required
    if ($convert_charset) {

      // already in target encoding?
      $skip_encoding = (($convert_charset_target === 'utf-8') && mb_check_encoding($data, $convert_charset_target)) ||
                       (($convert_charset === 'utf-8') && !mb_check_encoding($data, $convert_charset));

      if (!$skip_encoding && ($r = iconv($convert_charset, $convert_charset_target, $data)))
          $data = $r;
    }

    if ($fix_html)
        $data = fix_html($data);

    $target_size = strlen($data);
  }else
    $target_size = 0;

  // Result...
  if ($data_changed = ($data_changed || ($data && ($source_size != $target_size)))) {
      if ($inform_only)
          $inform_only = ' (unchanged)';
      else
          file_put_contents($target_file ? $target_file : $source_file, $data);

      $out = "$source_file: Original size: $source_size, Result size: $target_size.$inform_only\n";
  }else
      $out = "Nothing changed. Same size: $target_size.\n";
  return array($data_changed, $out);
}

function r0d_dir($dir_mask, $check_subdirs = false) {
    global $allow_extensions;

    if ($fn = glob($dir_mask))
        foreach ($fn as $f) {
            if (!is_dir($f)) {
                if (!in_array(pathinfo($f, PATHINFO_EXTENSION), $allow_extensions))
                    continue;

                $out = r0d_file($f);
                if ($out[0])
                    echo $out[1];
            }elseif ($check_subdirs) {
                // if (($f !== '.') && ($f !== '..'))
                r0d_dir($f.'/'.basename($dir_mask), $check_subdirs);
            }
        }
}

// GO!
if ($is_wildcard) {
    r0d_dir(/*getcwd().'/'.*/$source_file, $process_subdirectories);

    // AK 2023-01-18: I want to find .htaccess too, even if I looking for "*", not ".*".
    if ($source_file === '*')
        r0d_dir('.*', $process_subdirectories);

}else {
    $out = r0d_file($source_file, $target_file);
    echo $out[1];
}






// ---------------------------------------------------------------
function fix_html($s) {
/*
  static $convert_chars = [

    ];
 */

  $s = preg_replace('/&(amp|nbsp|quot);/s', ':xzx#\\1xzx#;', $s);
  $s = html_entity_decode($s); // !! this break XML's <em> tags
  $s = preg_replace('/:xzx#(.*?)xzx#;/s', '&\\1;', $s);

//  $s = str_replace('& ', '&amp; ', $s); // !! this break JavaScript's &&
//  $s = str_replace("&\n", "&amp;\n", $s);

  $s = str_ireplace('<br>', '<br />', $s);
  $s = str_ireplace('<hr>', '<hr />', $s);

  $s = preg_replace('/\s*?<br \/>/', '<br />', $s);
  $s = preg_replace('/<(strong|b|i|em|span|font|td)>(\s+)<\/\\1>/', '\\2', $s);
  $s = preg_replace('/<(strong|b|i|em|span|font|td)>(\s*?)<br \/>(\s*?)<\/\\1>/', '\\2<br />\\3', $s);

  $s = preg_replace_callback('/(<p>(.*?))(?=\s+<(\/?p|h\d)>)/s', function($m) {
            return preg_replace("/\n+/s", "\n", $m[0]);
       }, $s);

  // site-specific (GoldenHorn.com)
  // $s = preg_replace('/<(span|p|b|i|strong|em|font)([^>]*?)><\/\\1>/is', '', $s); // NOT <DIV>!! UPD. It's harmful.
  $s = preg_replace('/<td><div align="center">(.*?)<\/div><\/td>/', '<td>\\1</td>', $s);
  $s = preg_replace('/<td(.*?)class="yazilar">/', '<td\\1>', $s);
  $s = str_ireplace(' </b></p>', '</b></p>', $s);
  $s = str_ireplace('<p class="yazilar">', '<p>', $s);
  $s = str_ireplace('<li class="yazilar">', '<li>', $s);
  $s = str_replace('<span class="yazilar">', '<span>', $s);
  $s = str_ireplace('<meta HTTP-EQUIV="CONTENT-TYPE" CONTENT="text/html; charset=iso-8859-1">', '', $s);
  $s = str_ireplace('<meta HTTP-EQUIV="CONTENT-TYPE" CONTENT="text/html; charset=iso-8859-9">', '', $s);
  $s = str_replace('<font color="#FFFF99"></font>', '', $s);
  $s = str_replace('<font color="#FFFFCC"></font>', '', $s);
  $s = str_replace('<font color="#FFFF99"> </font>', '', $s);
  $s = str_replace(' <font color="#FF8000"> ', ' <font color="#FF8000">', $s);
  $s = preg_replace('/<span class="basliklar">\s*?<br \/>\s*?<\/span>/', '<br />', $s);
  $s = str_replace(' </font><br />', '</font><br />', $s);
  $s = str_replace('<p align="center" class="basliklar"><br /></p>', '', $s);
  $s = preg_replace('/<p> <\/p>/', '', $s);
  $s = preg_replace('/<p>\s*?<\/p>/s', '', $s);
  $s = str_replace('<p><br /></p>', '', $s);
  $s = str_replace('<div align="center"></div>', '', $s);
  $s = str_replace('<font color="#FFFF99" size="2"> </font>', ' ', $s);
  $s = str_replace('<h1 class="basliklar"></h1>', ' ', $s);
  $s = str_replace(' </title>', '</title>', $s);
  $s = str_replace('\'s ', '’s ', $s);
  $s = str_replace('s\' ', 's’ ', $s);
  $s = str_replace('<td><br />', '<td>', $s);
  $s = str_replace('<br /></td>', '</td>', $s);
  $s = str_replace('<td align="left" valign="top">', '<td>', $s);
  $s = str_replace('<p class="basliklar"> </p>', '', $s);
  $s = preg_replace('/<body\s+/s', '<body ', $s);

  $s = preg_replace('/\s*?>/', '>', $s);
  $s = preg_replace('/<(p|td|li)>\s+/', '<\\1>', $s);
  $s = preg_replace('/(.)\s*?<\/(p|td)>/', '\\1</\\2>', $s);

  $s = preg_replace('/\n\n\n/s', "\n\n", $s);

  return trim($s);
}
