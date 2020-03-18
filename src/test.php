<?php

$test = $_GET['z'];
$yo = $test;
$you = 'zd';
$yo = $you . 'zeff';
$yo = $_POST['g'] . $_GET['test'];

//$yo = 'a';
//if (1 == $_POST['f']) {
//    $yo = $_GET['d'];
//    $yo = $_POST['g'];
//} elseif (4 == $_POST['f']) {
//    $yo = $_GET['d'];
//} else {
//    $yo = $_POST['g'];
//}

echo $yo;