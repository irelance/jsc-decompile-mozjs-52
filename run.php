<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/1
 * Time: 上午10:47
 */

include './Kind.php';
include './Xdr.php';
include './Decompile.php';

$decompile = new Decompile($argv[1]);
$decompile->run();

