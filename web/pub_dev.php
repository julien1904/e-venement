<?php

// this check prevents access to debug front controllers that are deployed by accident to production servers.
// feel free to remove this, extend it or make something more sophisticated.
if (!in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')))
{
  die('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}

header('P3P: CP="IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA NOI COR NID CUR ADM DEV CNT BUS"');
require_once(dirname(__FILE__).'/../config/ProjectConfiguration.class.php');

$configuration = ProjectConfiguration::getApplicationConfiguration('pub', 'dev', true);
$configuration->shut();
sfContext::createInstance($configuration)->dispatch();
