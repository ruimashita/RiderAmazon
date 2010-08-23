<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header('Content-Type: text/plain; charset=UTF-8');

$classPath = $_GET['class_path'];
include_once($classPath.'class-snoopy.php');
$snoopy = new Snoopy();


$domain = 'webservices.amazon.co.jp';
$path = '/onca/xml';
$param  = 'AssociateTag='.urlencode($_GET['AssociateTag']);
$param .= '&Keywords='.urlencode($_GET['Keywords']);
$param .= '&SearchIndex='.urlencode($_GET['SearchIndex']);
$param .= '&ItemPage='.urlencode($_GET['ItemPage']);
$param .= '&ResponseGroup='.urlencode('Request,Small,Images');
$param .= '&Service=AWSECommerceService';
$param .= '&AWSAccessKeyId=1P1KJSTVRDMR2FA0ZGG2';
$param .= '&Operation=ItemSearch';
if ( $_GET['SearchIndex'] != 'Blended' )
{
	$param .= '&Sort=salesrank';
}
$param .= '&Version=2007-10-29';

$snoopy->fetch('http://'.$domain.$path.'?'.$param);
print $snoopy->results;
?>
