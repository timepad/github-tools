<?php
/**
 * @author artyfarty
 */

use TpReleaseNotes\LocalGit\LocalGit;

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application();

$console->add(new \TpReleaseNotes\Command\GenerateReleaseNotes('make_notes'));
$console->add(new \TpReleaseNotes\Command\Stats('make_stats'));
//$console->add(new \TpReleaseNotes\Command\RebindTags('rebind_tags'));
//$console->add(new \TpReleaseNotes\Command\DestroyTags('destroy_tags'));

$console->run();

