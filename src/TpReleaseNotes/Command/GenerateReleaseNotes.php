<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Command;

use Github\Client as GithubClient;
use Github\HttpClient\CachedHttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TpReleaseNotes\LocalGit\LocalGit;
use TpReleaseNotes\Util;

class GenerateReleaseNotes extends Command {
    public function configure() {
        $this->setDefinition(
            [
                new InputOption('github_token', null, InputOption::VALUE_REQUIRED, 'Github auth token'),
                new InputOption('github_user', null, InputOption::VALUE_REQUIRED, 'Github user/org'),
                new InputOption('github_repo', null, InputOption::VALUE_REQUIRED, 'Github repo'),
                new InputOption('from_tag', null, InputOption::VALUE_OPTIONAL, 'Tag to start'),
                new InputOption('repo', null, InputOption::VALUE_REQUIRED, 'Local repo path'),
                new InputArgument('outfile', InputArgument::OPTIONAL, 'Target md file'),
            ]
        )
            ->setDescription('Генерит релизноутсы');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $token    = $input->getOption('github_token');
        $gh_user  = $input->getOption('github_user');
        $gh_repo  = $input->getOption('github_repo');
        $repo     = $input->getOption('repo');
        $outfile  = $input->getArgument('outfile');
        $from_tag = $input->getOption('from_tag');

        if (!$outfile) {
            $outfile = "./out/{$gh_user}_{$gh_repo}.md";
        }

        $client = new GithubClient(new CachedHttpClient(['cache_dir' => '/tmp/github-api-cache']));
        $client->authenticate($token, GithubClient::AUTH_HTTP_TOKEN);

        $git = new LocalGit($repo);
        $git->fetch();
        $tags = $git->tags();

        /**
         * @var Tag[]
         */
        $revTags = [];

        $stopIteration = null;

        foreach ($tags as $i => $tag) {
            if (!isset($tags[$i + 1])) {
                continue;
            }

            $prevTag = &$tags[$i + 1];

            $output->writeln("getting commits for tag {$prevTag->name}..{$tag->name}");

            $revList      = $git->rev_list("{$prevTag->ref}..{$tag->ref}");
            $revListCount = count($revList);

            $output->writeln("found {$revListCount} commits");

            foreach ($revList as $rev) {
                $revTags[$rev] = $tag;
            }

            if ($from_tag === $tag->name) {
                $stopIteration = 30;
                $output->writeln("Tag limited at {$tag->name}, will load $stopIteration more");
                continue;
            }

            if ($stopIteration !== null) {
                $stopIteration--;

                if ($stopIteration <= 0) {
                    $output->writeln("Tag limit reached");
                    break;
                }
            }
        }

        $tagNotes = [];

        $prFilter = [
            'state'     => 'closed',
            'sort'      => 'updated',
            'direction' => 'desc'
        ];

        $stopIteration = 0;

        Util::paginateAll(
            $client, 'PullRequest', 'all', [$gh_user, $gh_repo, $prFilter], function ($pull) use (&$revTags, &$tagNotes, $output, &$stopIteration) {
            if (!$pull['merged_at']) {
                $output->writeln("{$pull['number']} was not merged, skipping");

                return;
            }

            if (!isset($revTags[$pull['head']['sha']])) {
                $output->writeln("{$pull['number']} has no matching tag, probably out of bounds");
                $stopIteration++;

                if ($stopIteration > 3) {
                    $output->writeln("Stopping now");

                    return "stop";
                } else {
                    $output->writeln("Will try another PR");

                    return;
                }
            }

            $output->writeln("getting notes for pull {$pull['number']}");

            $tag = $revTags[$pull['head']['sha']];

            $output->writeln("{$pull['number']} is assigned to tag {$tag->name}");

            if (!isset($tagNotes[$tag->name])) {
                $tagNotes[$tag->name] = [
                    "date"  => $tag->date,
                    "notes" => []
                ];

            }

            $bodyLines = [];
            foreach (preg_split("#[\n\r]+#u", $pull['body']) as $bodyLine) {
                if (preg_match("#^[\\d*]\\.?\\s*\\[(new|bfx|ref)]\\[.{1,2}]#siu", $bodyLine)) {
                    $bodyLines[] = $bodyLine;
                }
            }

            $tagNotes[$tag->name]['notes'][] = implode("\n", $bodyLines);

            $cleanedTitle = preg_replace('!\\#\\d+!siu', '', $pull['title']);

            $tagNotes[$tag->name]['pulls'][] = "* $cleanedTitle (#[{$pull['number']}]({$pull['html_url']}) by @{$pull['user']['login']})"; // user

            $stopIteration = 0;
        }
        );

        uasort(
            $tagNotes, function ($a, $b) {
            if ($a == $b) {
                $r = 0;
            } else {
                $r = ($a > $b) ? 1 : -1;
            }

            return $r;
        }
        );

        $log = fopen($outfile, 'w');

        fwrite($log, "\n\n## Release notes\n\n");

        foreach ($tagNotes as $tag => $tagData) {
            if (count($tagData['notes'])) {
                if ($from_tag && ($tag < $from_tag)) {
                    $output->writeln("Tag limited at {$from_tag}, skipping notes for tar $tag");
                    continue;
                }

                $output->writeln("Writing notes for tag {$tag}");

                fwrite($log, "## Версия $tag\n");

                if ($tagData['date']) {
                    fwrite($log, "**Выпущена:** " . $tagData['date']->format('d.m.Y H:i') . " \n\n");
                }

                fwrite($log, "\n### Фичи\n\n");

                foreach ($tagData['pulls'] as $pullInfo) {
                    fwrite($log, $pullInfo . "\n");
                }

                fwrite($log, "\n### Детальные изменения\n\n");

                foreach ($tagData['notes'] as $noteBlock) {
                    fwrite($log, $noteBlock . "\n");
                }

                fwrite($log, "\n");
            }
        }

    }
}