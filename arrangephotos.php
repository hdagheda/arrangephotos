<?php
/**
 * This script arranges photos by timestamp.
 *
 * @copyright  2022 Heena Agheda
 */

/**
 * Returns cli script parameters.
 * @param array $longoptions array of --style options ex:('verbose'=>false)
 * @param array $shortmapping array describing mapping of short to long style options ex:('h'=>'help', 'v'=>'verbose')
 * @return array array of arrays, options, unrecognised as optionlongname=>value
 */

ini_set('xdebug.max_nesting_level', -1);
ob_start();
function cli_get_params(array $longoptions, array $shortmapping=null) {
    $shortmapping = (array)$shortmapping;
    $options      = array();
    $unrecognized = array();

    if (empty($_SERVER['argv'])) {
        // bad luck, we can continue in interactive mode ;-)
        return array($options, $unrecognized);
    }
    $rawoptions = $_SERVER['argv'];

    //remove anything after '--', options can not be there
    if (($key = array_search('--', $rawoptions)) !== false) {
        $rawoptions = array_slice($rawoptions, 0, $key);
    }

    //remove script
    unset($rawoptions[0]);
    foreach ($rawoptions as $raw) {
        if (substr($raw, 0, 2) === '--') {
            $value = substr($raw, 2);
            $parts = explode('=', $value);
            if (count($parts) == 1) {
                $key   = reset($parts);
                $value = true;
            } else {
                $key = array_shift($parts);
                $value = implode('=', $parts);
            }
            if (array_key_exists($key, $longoptions)) {
                $options[$key] = $value;
            } else {
                $unrecognized[] = $raw;
            }

        } else if (substr($raw, 0, 1) === '-') {
            $value = substr($raw, 1);
            $parts = explode('=', $value);
            if (count($parts) == 1) {
                $key = reset($parts);
                $value = true;
            } else {
                $key = array_shift($parts);
                $value = implode('=', $parts);
            }
            if (array_key_exists($key, $shortmapping)) {
                $options[$shortmapping[$key]] = $value;
            } else {
                $unrecognized[] = $raw;
            }
        } else {
            $unrecognized[] = $raw;
            continue;
        }
    }
    //apply defaults
    foreach ($longoptions as $key=>$default) {
        if (!array_key_exists($key, $options)) {
            $options[$key] = $default;
        }
    }
    // finished
    return array($options, $unrecognized);
}


function get_newname($newName) {
    if (file_exists($newName)) {
        $pathinfo = pathinfo($newName);
        if (strpos($pathinfo['filename'], '_') !== false) {
            $fileparts = explode('_', $pathinfo['filename']);
            $i = $fileparts[1]+1;
            $newName = $pathinfo['dirname'].'/'.$fileparts[0].'_'.$i.'.'.$pathinfo['extension'];
            return get_newname($newName);
        } else {
            $i = 1;
            $newName = $pathinfo['dirname'].'/'.$pathinfo['filename'].'_'.$i.'.'.$pathinfo['extension'];
            return get_newname($newName);
        }
    }
    return $newName;
}

function scanDirAndSubdir($dir, &$out = []) {
    $sun = scandir($dir);

    foreach ($sun as $a => $filename) {
        $way = realpath($dir . DIRECTORY_SEPARATOR . $filename);
        if (!is_dir($way)) {
            if (@is_array(getimagesize($way))) {
                $out[] = $way;
            }
        } else if ($filename != "." && $filename != "..") {
            scanDirAndSubdir($way, $out);
            //$out[] = $way;
        }
    }

    return $out;
}

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array(
    'sourcedir' => false,
    'destinationdir' => '',
    'help' => false,
    ), array(
        's' => 'sourcedir',
        'd' => 'destinationdir',
        'h' => 'help'
    ));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error('Unknown parameters: ', $unrecognized);
}


if ($options['help'] || !($options['sourcedir'] || $options['destinationdir'])) {
    $help = <<<EOL
Arranges photos from sourcedir and adds in destinationdir.

Options:
--sourcedir=STRING          Source directory.
--destinationdir=STRING     Destination directory.
-h, --help                  Print out this help.

Example:
\$sudo -u www-data /usr/bin/php arrangephotos.php --sourcedir=/home/user/picture/test --destinationdir=/home/user/pictures/final\n
EOL;

    echo $help;
    die;
}

// check sourcedir exist.
$dir = rtrim($options['sourcedir'], '/');
if (!empty($dir)) {
    if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
        mtrace("Source directory does not exists or not writable.");
        die;
    }
}

// check destinationdir exist.
$dir = rtrim($options['destinationdir'], '/');
if (!empty($dir)) {
    if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
        mtrace("Destination directory does not exists or not writable.");
        die;
    }
}
$sourcedir = $options['sourcedir'];
$destinationdir = $options['destinationdir'];

$allphotos = scanDirAndSubdir($sourcedir);
echo 'Total Photos to copy from(sourcedir) -'.$sourcedir.' :- '. count($allphotos).PHP_EOL;

$existingphotos =  scanDirAndSubdir($destinationdir);
echo 'Total Photos in(destinationdir)  -'.$destinationdir.' :- '. count($existingphotos).PHP_EOL;

foreach ($allphotos as $photo) {
    $ext = pathinfo($photo, PATHINFO_EXTENSION);
    if (@is_array(getimagesize($photo))) {
        $createdat = filemtime($photo);
        $year =  date("Y", $createdat);
        $month =  date("m", $createdat);
        if(!is_dir($destinationdir.'/'.$year.'/'.$month)) {
            if (!mkdir($destinationdir.'/'.$year.'/'.$month, 0777, true)) {
                die('Failed to create directories...');
            }
        }
        $newName = $destinationdir.'/'.$year.'/'.$month.'/'.$createdat.'.'.$ext;

        $newName = get_newname($newName);
        $copied = copy($photo , $newName);
        if ((!$copied)) {
            echo "Error : Not Copied: " . $photo . PHP_EOL;
        } else {
            echo "Copied Successful: " . $photo . ' => ' . $newName . PHP_EOL;
        }
    }
}

$existingphotosnow =  scanDirAndSubdir($destinationdir);

echo 'Total Photo in destination directory now :- '. count($existingphotosnow).PHP_EOL;

if (count($existingphotosnow) == (count($allphotos) + count($existingphotos))) {
    echo 'Yay! All photos are copied successfully.'.PHP_EOL;
} else {
    echo 'Ohh no not all photos are copied.'.PHP_EOL;
}

$contents = ob_get_flush();
$logdir = $destinationdir.DIRECTORY_SEPARATOR.'logs';
if (!file_exists($logdir)) {
    mkdir($logdir, 0777, true);
}
$logfile = $logdir.DIRECTORY_SEPARATOR.date('Y-m-d H:i:s').'.log';
file_put_contents($logfile, $contents);
?>