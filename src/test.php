<?php

$a = $_GET['sd'];
$b = $_GET['sd'];
function test($a) {
    global $b;
    $b = $_GET['sd'];
    $a = $_GET['sd'];
    echo $a;
    echo $b;
    return $_GET['a'];
}
function test2($a) {
    echo $a;
    global $b;
    $b = $_GET['sd'];
    echo $b;
    return $_GET['a'];
}

$yo = 'a';
while (1 == $_POST['f']) {
    if (1 == $_GET['d']) {
        if ("sdv" == $_GET['d']) {
            $yo = $_GET['d'];
        }
    }
    $a = $_GET['sd'];
    $yo = test(5);
    echo $b;
}
