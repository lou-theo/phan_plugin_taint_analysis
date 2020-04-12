<?php

//function test($a) {
//    echo $a;
//    return test($a);
//    return $a;
//}
//
//$yo = 'a';
//while (1 == $_POST['f']) {
//    if (1 == $_GET['d']) {
//        if ("sdv" == $_GET['d']) {
//            $ya = $_GET['d'];
//            $yo = $ya;
//        }
//    }
//    $a = $_GET['sd'];
//    $yo = test($_GET['d']);
//    $yo = test(3);
//    echo $yo;
//}

function f(int $n) {
    $a = 0;
    $b = 1;
    while ($n > 1) {
        $c = $a + $n;
        $a = $b;
        $b = $c;
        $n = $n - 1;
    }
    return $a;
}

function zef($p) {
    return f($p);
}

function g(int $p) {
    $q = zef($p);
    echo($q);
}

$x = $_GET['sd'];
'@phan-var int $x';
g($x);
