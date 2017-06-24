<?php
define('SHORTURL', 'http://SITEURL'); //Your sites URL with http://
define('SHORTFURL', true);
define('PASSWORD', md5('12345')); //change password for administration
define('FILENAME', 'db.sqlite'); // SQLite DB File

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
$actions = array(
	'stat',
	'add',
	'edit',
	'del',
	'login',
	'logout'
);

if (!defined('SHORTURL'))
{
	$url = 'http';
	if (getenv('HTTPS') !== false && getenv('HTTPS') != 'off') $url.= 's';
	$url.= '://' . getenv('HTTP_HOST') . '/';
	$path = trim(dirname(getenv('SCRIPT_NAME')) , '\/\\');
	if (!empty($path)) $url.= $path . '/';
	define('SHORTURL', $url);
}

function value($k = '', $v = '')
{
	if (!empty($k))
	{
		if (isset($_POST[$k])) $v = $_POST[$k];
		elseif (isset($_GET[$k])) $v = $_GET[$k];
	}

	return $v;
}

function arg($k = '', $v = '')
{
	global $args;
	return (!empty($k)) && (isset($args[$k])) ? $args[$k] : $v;
}

function escape($s)
{
	return htmlspecialchars((string)$s);
}

function nohtml($s)
{
	if (empty($s)) return $s;
	$p = array(
		"'<script[^>]*?>.*?</script>'si",
		"'<noscript[^>]*?>.*?</noscript>'si",
		"'<style[^>]*?>.*?</style>'si",
		"'<[\/\!]*?[^<>]*?>'si",
	);
	$r = array(
		" ",
		" ",
		" ",
		" "
	);
	$s = preg_replace($p, $r, $s);
	$s = strip_tags($s);
	return $s;
}

function mod_rewrite_exists()
{
	return ((function_exists('apache_get_modules')) && (in_array('mod_rewrite', apache_get_modules()))) || (isset($_SERVER['IIS_UrlRewriteModule'])) || (strpos(shell_exec('/usr/local/apache/bin/apachectl -l') , 'mod_rewrite') !== false);
}

function shorturl($args = array())
{
	$furl = @SHORTURL;
	if (!is_array($args))
	{
		$action = (string)$args;
		$args = empty($action) ? array() : array(
			'action' => $action
		);
	}

	if (sizeof($args) > 0)
	{
		if ((mod_rewrite_exists()) && (@SHORTFURL === true))
		{
			$furl.= implode('/', array_values($args));
		}
		elseif ((mod_rewrite_exists()) && (is_string(@SHORTFURL)))
		{
			if (isset($args['action']))
			{
				$furl.= str_replace('%action%', $args['action'], @SHORTFURL);
				unset($args['action']);
			}

			if (sizeof($args) > 0)
			{
				$furl.= '?' . http_build_query($args);
			}
		}
		elseif (isset($_SERVER['QUERY_STRING']))
		{
			$furl.= '?' . implode('/', array_values($args));
		}
		else
		{
			$furl.= '?' . http_build_query($args);
		}
	}

	return $furl;
}

function the_header($title, $menu = true)
{
	global $action;
	$title = escape($title);
	$shorturl = shorturl();
	echo '   <!doctype html><html lang="ru"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
        <link rel="stylesheet" type="text/css" href="' . $shorturl . 'style.min.css">
        <title>' . $title . '</title>
        </head>
        <body><div class="main-outer">';
	if ($menu === true)
	{
		echo '<ul id="menu">';
		echo '<li';
		if ($action == 'stat') echo ' class="select"';
		echo '><a href="' . shorturl('stat') . '"><i class="fa fa-bar-chart"></i> Статистика</a></li>';
		echo '<li';
		if ($action == 'add') echo ' class="select"';
		echo '><a href="' . shorturl('add') . '"><i class="fa fa-plus"></i> Добавить</a></li>';
		echo '<li class="floatright';
		if ($action == 'logout') echo ' select';
		echo '"><a href="' . shorturl('logout') . '"><i class="fa fa-sign-out"></i> Выйти</a></li>';
		echo '</ul>';
	}

	echo '<div class="main-inner">';
}

function the_footer()
{
	echo '</div></div></body></html>';
}

try
{
	if (!in_array("sqlite", PDO::getAvailableDrivers() , true))
	{
		throw new PDOException("Cannot work without a proper database setting up");
	}
}

catch(PDOException $e)
{
	die($e->getMessage());
}

$file_exists = file_exists(@FILENAME);
try
{
	$db = new PDO('sqlite:' . @FILENAME);
}

catch(PDOException $e)
{
	die($e->getMessage());
}

if (!$file_exists)
{
	$r = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='links'");
	if (!$r || $r->fetch() === false)
	{
		$db->exec("CREATE TABLE links (id INTEGER PRIMARY KEY AUTOINCREMENT,url_long TEXT NOT NULL DEFAULT '',url_short TEXT UNIQUE NOT NULL DEFAULT '',excerpt TEXT NOT NULL DEFAULT '',clicks INTEGER NOT NULL DEFAULT 0,pubdate INTEGER NOT NULL DEFAULT 0)");
	}
}

$action = '';
$args = array();

if (isset($_POST['action']))
{
	$action = (string)$_POST['action'];
}
elseif (isset($_GET['action']))
{
	$action = (string)$_GET['action'];
}
else
{
	if (isset($_GET['furl']))
	{
		$action = (string)$_GET['furl'];
	}
	else
	{
		$action = $_SERVER['QUERY_STRING'];
	}

	$action = trim($action, "\/\\");
	$args = preg_split("/[\\/]/", $action);
	$action = $args['0'];
}

if (empty($action)) $action = 'stat';

if (in_array($action, $actions))
{
	@session_start();
	if (isset($_SESSION['auth']))
	{
		if ($_SESSION['auth'] != @PASSWORD)
		{
			unset($_SESSION['auth']);
			$action = 'login';
		}
	}
	else $action = 'login';
}

if ($action == 'stat')
{
	the_header('Статистика'); ?>
            <table class="grid w100">
                <tr class="alignleft">
                    <th class="mob-hide">#</th>
                    <th class="w40">Длинный <span class="mob-hide">URL</span></th>
                    <th class="w20 mob-hide">Создано</th>
                    <th class="w20">Короткий <span class="mob-hide">URL</span></th>
                    <th class="w20" colspan="2">&nbsp;</th><th class="alignright">Клик<span class="mob-hide">и</span></th>
                </tr>
            <?php
	$rows = 0;
	foreach($db->query("SELECT * FROM links ORDER BY pubdate") as $link)
	{
		$rows++; ?>
                <tr class="alignleft valigntop item">
                    <td class="mob-hide"><?php echo $link['id'] ?></td>
                    <td><div class="ellipsis-outer"><div class="ellipsis-inner"><a href="<?php echo $link['url_long'] ?>" title="<?php echo escape($link['url_long']) ?>" target="_blank"><?php echo preg_replace("~^(https?:\/\/)~i", '<span class="mob-hide">$1</span>', $link['url_long']) ?></a></div></div></td>
                    <td class="mob-hide nowrap small"><?php echo date("d/m/Y H:i", $link['pubdate']) ?></td><td><div class="ellipsis-outer"><div class="ellipsis-inner"><a href="<?php echo shorturl($link['url_short']) ?>" title="<?php echo escape(shorturl($link['url_short'])) ?>" target="_blank"><?php echo preg_replace("~^(" . preg_quote(@SHORTURL) . ")~i", '<span class="mob-hide">$1</span>', shorturl($link['url_short'])) ?></a></div></div></td>
                    <td class="mob-size nowrap"><a href="<?php echo shorturl(array(
			'action' => 'del',
			'id' => $link['id']
		)) ?>"><i class="fa fa-times"></i> <span class="mob-hide">Удалить</span></a></td><td class="mob-size nowrap"><a href="<?php echo shorturl(array(
			'action' => 'edit',
			'id' => $link['id']
		)) ?>"><i class="fa fa-pencil"></i> <span class="mob-hide">Изменить</span></a></td><td class="alignright"><?php echo $link['clicks'] ?></td>
                </tr>
            <?php
		if (!empty($link['excerpt'])) echo '<tr><td class="excerpt" colspan="7">' . $link['excerpt'] . '</td></tr>';
	}

	if ($rows == 0) echo '<tr><td class="excerpt" colspan="7">Данные отсутствуют.</td></tr>' ?>
            </table><?php
	the_footer();
}
elseif ($action == 'add')
{
	$errors = array();
	if (isset($_POST['send']))
	{
		$url_long = nohtml(trim((string)value('url_long')));
		$url_short = nohtml(trim((string)value('url_short')));
		$excerpt = nohtml(trim((string)value('excerpt')));
		if (empty($url_long))
		{
			$errors[] = 'Не указан Длинный URL.';
		}
		else
		{
			if (!preg_match('~https?\:\/\/~i', $url_long))
			{
				$url_long = 'http://' . $url_long;
			}

			if (filter_var($url_long, FILTER_VALIDATE_URL) === false)
			{
				$errors[] = 'Длинный URL неправильный.';
			}
		}

		if (empty($url_short)) $url_short = substr(md5($url_long . time()) , 0, 5);
		if (in_array($url_short, $actions)) $errors[] = 'Указан системный фрагмент короткого URL "' . $url_short . '".';
		if (sizeof($errors) < 1)
		{
			$r = $db->query("SELECT url_long FROM links WHERE url_short=" . $db->quote($url_short) . " LIMIT 1");
			if ($r && $r->fetch() !== false) $errors[] = 'Короткий URL "' . escape($url_short) . '" есть.';
		}

		if (sizeof($errors) < 1)
		{
			$set_columns = array(
				'url_long',
				'url_short',
				'pubdate'
			);
			$set_values = array(
				$db->quote($url_long) ,
				$db->quote($url_short) ,
				time()
			);
			if (!empty($excerpt))
			{
				$set_columns[] = 'excerpt';
				$set_values[] = $db->quote($excerpt);
			}

			$sql = "INSERT INTO links (" . implode(",", $set_columns) . ") VALUES (" . implode(",", $set_values) . ")";
			$db->exec($sql);
			header('Location: ' . shorturl() , true, 301);
		}
	}

	the_header('Добавить'); ?>
                <form action="<?php echo shorturl('add') ?>" class="null" method="post">
                    <?php
	if (sizeof($errors) > 0) echo '<div class="errors"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>'; ?>
                    <p><label for="url_long">Длинный URL</label><br /><input class="field w100" type="text" id="url_long" name="url_long" value="<?php echo escape(value('url_long')) ?>" placeholder="http://" required autofocus></p>
                    <p><label for="url_short">Фрагмент короткого URL</label><br />
                        <input class="field w100 b" type="text" id="url_short" name="url_short" value="<?php echo escape(value('url_short')) ?>" placeholder="не обязательно"></p>
                    <p><label for="excerpt">Примечание</label><br /><textarea class="field w100" id="excerpt" name="excerpt" cols="40" rows="5" placeholder="не обязательно">
                        <?php echo escape(value('excerpt')) ?></textarea></p>
                        <p><input class="submit" type="submit" name="send" value="Добавить"></p>
                </form>
            <?php
	the_footer();
}
else
if ($action == 'edit')
{
	$id = (int)value('id', 0);
	if ($id == 0) $id = arg(1, 0);
	$r = $db->query("SELECT * FROM links WHERE id='" . $id . "'");
	if (!$r || ($link_data = $r->fetch()) === false) die('Ссылки #' . $id . ' нет.');
	$errors = array();
	if (isset($_POST['send']))
	{
		$url_long = nohtml(trim((string)value('url_long')));
		$url_short = nohtml(trim((string)value('url_short')));
		$excerpt = nohtml(trim((string)value('excerpt')));
		$clicks = (int)value('clicks');
		if (empty($url_long))
		{
			$errors[] = 'Не указан Длинный URL.';
		}
		else
		{
			if (!preg_match('~https?\:\/\/~i', $url_long))
			{
				$url_long = 'http://' . $url_long;
			}

			if (filter_var($url_long, FILTER_VALIDATE_URL) === false)
			{
				$errors[] = 'Длинный URL неправильный.';
			}
		}

		if (empty($url_short)) $url_short = substr(md5($url_long . time()) , 0, 5);
		if (in_array($url_short, $actions)) $errors[] = 'Указан системный фрагмент короткого URL "' . $url_short . '".';
		if (sizeof($errors) < 1)
		{
			$r = $db->query("SELECT id FROM links WHERE id<>" . $id . " AND url_short=" . $db->quote($url_short) . " LIMIT 1");
			if ($r && $r->fetch() !== false) $errors[] = 'Короткий URL "' . htmlspecialchars($url_short) . '" есть.';
		}

		if (sizeof($errors) < 1)
		{
			$set = array();
			if ($url_long != $link_data['url_long']) $set[] = "url_long=" . $db->quote($url_long);
			if ($url_short != $link_data['url_short']) $set[] = "url_short=" . $db->quote($url_short);
			if ($excerpt != $link_data['excerpt']) $set[] = "excerpt=" . $db->quote($excerpt);
			if ($clicks != $link_data['clicks']) $set[] = "clicks=" . $clicks;
			if (sizeof($set) > 0)
			{
				$db->exec("UPDATE links SET " . implode(",", $set) . " WHERE id=" . $id);
			}

			header('Location: ' . shorturl() , true, 301);
		}
	}

	the_header('Изменить #' . $link_data['id']); ?><form action="<?php echo shorturl(array(
		'action' => 'edit',
		'id' => $link_data['id']
	)) ?>" class="null" method="post">
            <?php
	if (sizeof($errors) > 0) echo '<div class="errors"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>'; ?><p><label for="url_long">Длинный URL</label><br /><input class="field w100" type="text" id="url_long" name="url_long" value="<?php echo escape(value('', $link_data['url_long'])) ?>" placeholder="http://" required autofocus></p><div class="p"><table class="w100"><tr><td class="w80">
            <label for="url_short">Фрагмент короткого URL</label><br /><input class="field w100 b" size="6" type="text" id="url_short" name="url_short" value="<?php echo escape(value('', $link_data['url_short'])) ?>" placeholder="не обязательно"></td><td>&nbsp;</td><td class="w20"><label for="clicks">Клики</label><br /><input class="field w100" type="number" id="clicks" name="clicks" value="<?php echo escape(value('', $link_data['clicks'])) ?>" placeholder="не обязательно"></td></tr></table></div><p><label for="excerpt">Примечание</label><br /><textarea class="field w100" id="excerpt" name="excerpt" cols="40" rows="5" placeholder="не обязательно"><?php echo escape(value('', $link_data['excerpt'])) ?></textarea></p><p><input class="submit" type="submit" name="send" value="Изменть"></p></form><?php
	the_footer();
}
elseif ($action == 'del')
{
	$id = (int)value('id', 0);
	if ($id == 0) $id = arg(1, 0);
	$db->exec("DELETE FROM links WHERE id=" . $id);
	header('Location: ' . shorturl() , true, 301);
}
elseif ($action == 'login')
{
	if (isset($_POST['password']))
	{
		$_SESSION['auth'] = (string)md5($_POST['password']);
		header('Location: ' . shorturl() , true, 301);
	}

	the_header('Войти', false); ?><form action="<?php echo shorturl('login') ?>" class="null" method="post"><p class="aligncenter"><input class="field" type="password" name="password" required autofocus> <input class="submit" type="submit" name="send" value="Войти"></p></form><?php
	the_footer();
}
elseif ($action == 'logout')
{
	unset($_SESSION['auth']);
	header('Location: ' . shorturl() , true, 301);
}
else
{
	$r = $db->query("SELECT id, url_long FROM links WHERE url_short=" . $db->quote($action) . " LIMIT 1");
	if ($r && ($link_data = $r->fetch()) !== false)
	{
		$db->exec("UPDATE links SET clicks = clicks + 1 WHERE id=" . $link_data['id']);
		header("Location: " . $link_data['url_long'], true, 301);
	}
	else
	{
		header("HTTP/1.0 404 Not Found");
		echo 'Сокращения "' . htmlspecialchars($action) . '" нет.';
	}
}
