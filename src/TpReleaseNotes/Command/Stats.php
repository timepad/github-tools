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
use TpReleaseNotes\Printable\IssueStory;
use TpReleaseNotes\Util;


class Stats extends Command {
    public function configure() {
        $this
            ->setDefinition(
                [
                    new InputOption('github_token', null, InputOption::VALUE_REQUIRED, 'Github auth token'),
                    new InputOption('github_user', null, InputOption::VALUE_REQUIRED, 'Github user/org'),
                    new InputOption(
                        'github_repos', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Github repo'
                    ),
                    new InputOption('period', null, InputOption::VALUE_OPTIONAL, 'Date period', "-1month"),
                    new InputArgument('outfile', InputArgument::OPTIONAL, 'Target md file'),
                ]
            )
            ->setDescription('Генерит странную статистику по репозиторию. WIP');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $token    = $input->getOption('github_token');
        $gh_user  = $input->getOption('github_user');
        $gh_repos = $input->getOption('github_repos');
        $period   = $input->getOption('period');
        $outfile  = $input->getArgument('outfile');

        if (!$outfile) {
            $outfile = "./out/{$gh_user}_stats.md";
        }

        $log = fopen($outfile, 'w');

        fwrite($log, "\n\n## Stats\n\n");

        $client = new GithubClient(new CachedHttpClient(['cache_dir' => '/tmp/github-api-cache']));
        $client->authenticate($token, GithubClient::AUTH_HTTP_TOKEN);


        foreach ($gh_repos as $gh_repo) {
            Util::paginateAll(
                $client, "Issue\\Events", "all", [$gh_user, $gh_repo, []], function ($event) use ($period, $gh_repo) {
                IssueStory::store($event);
                $date = date_create($event["created_at"]);

                if ($date < date_create($period)) {
                    return "stop";
                }

                echo "$gh_repo: parsing event {$event['id']}\n";
            }
            );
        }

        foreach (IssueStory::getStories() as $story) {
            if ($story->isDone()) {
                fwrite($log, $story->out());
            }
        }
    }
}