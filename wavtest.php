#!/usr/bin/php
<?php
include 'WavInfo.class.php';

$fn=$_SERVER['argv'][1];
#print "Reading $fn\n";
$wav=new WavInfo($fn);
$d=$wav->describe();
$duration=$wav->getDuration();
print "$fn: $d, $duration seconds\n";
?>
