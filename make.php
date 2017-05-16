<?php
/**
 * @author artyfarty
 */

date_default_timezone_set("Europe/Moscow");

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;

$console = new Application();

$console->add(new \TpReleaseNotes\Command\GenerateReleaseNotes('make_notes'));
$console->add(new \TpReleaseNotes\Command\Stats('make_stats'));

$console->run();

