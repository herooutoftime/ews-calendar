<?php
/**
 * Snippet 'ews'
 * Instantiates a new EWS class for further handling
 * @param array $scriptProperties The script properties
 * @param int $year The year to search for, default current year
 * @param int $month The month to search for, default current month
 * @param int $range The range of months to go forward
 * @param string $fn The function to call
 * @return mixed The output
 * @author Andreas Bilz <andreas@subsolutions.at>
 */

require_once($modx->getOption('base_path') . 'assets/libs/ews/ews.class.php');

$props['year']		= $modx->getOption('year', $scriptProperties, $_GET['year']);
$props['month']		= $modx->getOption('month', $scriptProperties, $_GET['month']);
$props['range']		= $modx->getOption('range', $scriptProperties);
$props['limit']		= $modx->getOption('limit', $scriptProperties);

$props['outerTpl']	= $modx->getOption('outerTpl', $scriptProperties, 'ol');
$props['dayTpl']	= $modx->getOption('dayTpl', $scriptProperties, 'extraCalendarDay');
$props['eventTpl']	= $modx->getOption('eventTpl', $scriptProperties, 'extraCalendarItem');
$props['headerTpl']	= $modx->getOption('headerTpl', $scriptProperties, 'extraCalendarHeader');
$props['navTpl']	= $modx->getOption('navTpl', $scriptProperties);

$props['outerAttr']	= $modx->getOption('outerAttr', $scriptProperties);
$props['dayAttr']	= $modx->getOption('dayAttr', $scriptProperties);
$props['dayClass']	= $modx->getOption('dayClass', $scriptProperties);
$props['eventAttr']	= $modx->getOption('eventAttr', $scriptProperties);

if(!$props['year']) $props['year'] = date('Y');
if(!$props['month']) $props['month'] = date('m');

$fn = $modx->getOption('fn', $scriptProperties);
$ews = new EWS($modx, $props);
$ews->$fn();