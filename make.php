<?php
/**
 * @author artyfarty
 */

use TpReleaseNotes\LocalGit\LocalGit;
use TpReleaseNotes\LocalGit\Tag;
use TpReleaseNotes\Util;

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application();

$console
    ->register('make')
    ->setDefinition([
        new InputOption('github_token', null, InputOption::VALUE_REQUIRED, 'Github auth token'),
        new InputOption('github_user', null, InputOption::VALUE_REQUIRED, 'Github user/org'),
        new InputOption('github_repo', null, InputOption::VALUE_REQUIRED, 'Github repo'),
        new InputOption('repo', null, InputOption::VALUE_REQUIRED, 'Local repo path'),
        new InputArgument('outfile', InputArgument::OPTIONAL, 'Target md file'),
    ])
    ->setDescription('Generates MD')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $token = $input->getOption('github_token');
        $gh_user = $input->getOption('github_user');
        $gh_repo = $input->getOption('github_repo');
        $repo = $input->getOption('repo');
        $outfile = $input->getArgument('outfile');

        if (!$outfile) {
            $outfile = "./out/{$gh_user}_{$gh_repo}.md";
        }
        
        $client = new \Github\Client(new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache')));
        $client->authenticate($token, Github\Client::AUTH_HTTP_TOKEN);
        
        $git = new LocalGit($repo);
        $tags = $git->tags();
        
        /**
         * @var Tag[]
         */
        $revTags = [];
        
        foreach ($tags as $i => $tag) {
            if (!isset($tags[$i+1])) {
                continue;
            }
        
            $prevTag = &$tags[$i+1];
        
            $output->writeln("getting commits for tag {$prevTag->name}..{$tag->name}");
        
            $revList = $git->rev_list("{$prevTag->ref}..{$tag->ref}");
            $revListCount = count($revList);
        
            $output->writeln("found {$revListCount} commits");
        
            foreach ($revList as $rev) {
                $revTags[$rev] = $tag;
            }
        }
        
        $tagNotes = [];
        
        
        Util::paginateAll($client, 'PullRequest', 'all', [$gh_user, $gh_repo, ['state' => 'closed']], function($pull) use (&$revTags, &$tagNotes, $output) {
            if (!isset($revTags[$pull['head']['sha']])) {
                $output->writeln("No tag for pull {$pull['number']}");
                return;
            }
        
            $output->writeln("getting notes for pull {$pull['number']}");
        
            $tag = $revTags[$pull['head']['sha']];
        
            $output->writeln("{$pull['number']} is assigned to tag {$tag->name}");
        
            if (!isset($tagNotes[$tag->name])) {
                $tagNotes[$tag->name] = [
                    "date" => $tag->date,
                    "notes" => []
                ];
        
            }
        
            $tagNotes[$tag->name]['notes'][] = $pull['body'];
        
            $cleanedTitle = preg_replace('!\\#\\d+!siu', '', $pull['title']);
        
            $tagNotes[$tag->name]['pulls'][] = "* $cleanedTitle (#{$pull['number']}) by @{$pull['user']['login']}"; // user
        });
        
        uasort($tagNotes, function($a, $b) {
            if ($a == $b) $r = 0;
            else $r = ($a > $b) ? 1: -1;
            return $r;
        });
        
        $log = fopen($outfile, 'w');
        
        fwrite($log, "\n\n## Release notes\n\n");
        
        foreach ($tagNotes as $tag => $tagData) {
           if (count($tagData['notes'])) {
               $output->writeln("Writing notes for tag {$tag}");
        
               fwrite($log, "## Версия $tag\n");
        
               if ($tagData['date']) {
                   fwrite($log, "**Выпущена:** " . $tagData['date']->format('d.m.Y H:i') . " \n\n");
               }
        
               fwrite($log, "### Детальные изменения\n\n");
        
               foreach ($tagData['notes'] as $noteBlock) {
                   fwrite($log, $noteBlock . "\n");
               }
        
               fwrite($log, "\n### Фичи\n\n");
        
               foreach ($tagData['pulls'] as $noteBlock) {
                   fwrite($log, $noteBlock . "\n");
               }
        
               fwrite($log, "\n");
           }
        }
    })
;

$console->run();

