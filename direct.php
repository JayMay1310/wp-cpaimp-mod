<?php
/**
 * Created by PhpStorm.
 * User: sliva
 * Date: 2/21/14
 * Time: 7:27 PM
 */

$file = (IM_PLUGIN_PATH."/direct.xml");
header ("Content-Type: application/octet-stream");
header ("Accept-Ranges: bytes");
header ("Content-Length: ".filesize($file));
header ("Content-Disposition: attachment; filename=direct.xml");
readfile(IM_PLUGIN_PATH."/direct.xml");
