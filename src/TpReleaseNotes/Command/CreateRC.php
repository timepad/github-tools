<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Command;

use Github\Client as GithubClient;
use Github\HttpClient\CachedHttpClient;
use Michelf\MarkdownExtra as Markdown;
use Postmark\PostmarkClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TpReleaseNotes\LocalGit\LocalGit;
use TpReleaseNotes\LocalGit\Tag;
use TpReleaseNotes\Util;
use TpReleaseNotes\Printable\Tag as PrintableTag;
use TpReleaseNotes\Printable\Pull as PrintablePull;
use TpReleaseNotes\Printable\YtIssue as PrintableYtIssue;
use TpReleaseNotes\Youtrack\Client as YTClient;
use TpReleaseNotes\Printable\ZdIssue as ZdPrintable;
use Zendesk\API\HttpClient as ZendeskAPI;

class CreateRC extends Command {
    public function configure() {
        $this->setDefinition(
            [
                new InputOption('github_token', null, InputOption::VALUE_REQUIRED, 'Github auth token'),
                new InputOption('github_user', null, InputOption::VALUE_REQUIRED, 'Github user/org'),
                new InputOption('github_repos', null, InputOption::VALUE_REQUIRED, 'Repos in pairs gh_repo'),

                new InputOption('yt_token', null, InputOption::VALUE_OPTIONAL, 'Youtrack auth token'),
                new InputOption('yt_host', null, InputOption::VALUE_OPTIONAL, 'Youtrack hostname'),
                new InputOption('yt_project', null, InputOption::VALUE_OPTIONAL, 'Project to create RC ticket', "TPI"),

                new InputOption('rc_source_branch', null, InputOption::VALUE_OPTIONAL, 'RC source branch', "master-dev"),
                new InputOption('rc_target_branch', null, InputOption::VALUE_OPTIONAL, 'RC target branch', "master"),
            ]
        )
            ->setDescription('Генерит релизноутсы');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $token    = $input->getOption('github_token');
        $gh_user  = $input->getOption('github_user');

        /** @var string[] $repos */
        $github_repos = explode(',', $input->getOption('github_repos'));

        $yt_token   = $input->getOption('yt_token');
        $yt_host    = $input->getOption('yt_host');
        $yt_project = $input->getOption('yt_project');

        $rc_source_branch = $input->getOption('rc_source_branch');
        $rc_target_branch = $input->getOption('rc_target_branch');

        $l = function ($msg) use ($output) {
            $output->writeln($msg);
        };

        $rc_branch_name = "test_rc";

        /** @var YTClient $yt_client */
        $yt_client = null;

        if ($yt_host && $yt_token) {
            $output->writeln("Adding youtrack data");
            $yt_client = new YTClient($yt_host, $yt_token);

            // create YT issue
            // $rc_branch_name = ...
        }

        $outfile = "./out/rc.md";

        $gh_client = new GithubClient(new CachedHttpClient(['cache_dir' => '/tmp/github-api-cache']));
        $gh_client->authenticate($token, GithubClient::AUTH_HTTP_TOKEN);

        foreach ($github_repos as $repo) {
            $l("Processing $repo $rc_source_branch → $rc_target_branch");

            $gh_source_ref = $gh_client->git()->references()->show($gh_user, $repo, "heads/$rc_source_branch");
            $devmaster_sha = $gh_source_ref["object"]["sha"];

            $l("$repo $rc_source_branch sha: $devmaster_sha");

            $compare_result = $gh_client->repo()->commits()->compare($gh_user, $repo, $rc_target_branch, $devmaster_sha);
            $compare_status = $compare_result["status"]; // diverged

            $l("$repo $rc_source_branch → $rc_target_branch: $compare_status");

            if ($compare_status === "diverged") {
                // create rc branch if not exists
                $pr_ref = null;

                try {
                    $l("$repo looking for branch for pr named $rc_branch_name");
                    $pr_ref = $gh_client->git()->references()->show($gh_user, $repo, "heads/$rc_branch_name");
                } catch (\Github\Exception\RuntimeException $ge) {
                    // https://stackoverflow.com/a/9513594
                    $l("$repo welp... creating branch for pr named $rc_branch_name");
                    $pr_ref = $gh_client->git()->references()->create($gh_user, $repo, ["ref" => "refs/heads/$rc_branch_name", "sha" => $devmaster_sha]);
                }

                $pr_ref_sha = $pr_ref["object"]["sha"];
                $l("$repo branch for pr: $rc_branch_name with sha $pr_ref_sha");

                $l("$repo looking for pr $rc_branch_name → $rc_target_branch");
                $pr_candidates = $gh_client->pr()->all($gh_user, $repo, ["base" => $rc_target_branch, "head" => "$gh_user:$rc_branch_name"]);
                if (count($pr_candidates)) {
                    $pr_object = $pr_candidates[0];
                } else {
                    $l("$repo welp... creating pr $rc_branch_name → $rc_target_branch");
                    $pr_object = $gh_client->pr()->create($gh_user, $repo, [
                        "base" => $rc_target_branch,
                        "head" => "$gh_user:$rc_branch_name",
                        "title" => "Выпущен пакет изменений от $devmaster_sha",
                        "body" => "Тут будет ссылка на ютрек",
                    ]);
                }

                $pr_number = $pr_object["number"];
                $pr_url = $pr_object["html_url"];

                $l("$repo pr: $pr_url");
            }

            // create pr https://docs.github.com/en/rest/reference/pulls#create-a-pull-request
            // analyze PR contents https://docs.github.com/en/rest/reference/commits#list-pull-requests-associated-with-a-commit
            // export to gh and yt
        }




        //file_put_contents($outfile, $release_notes);

    }
}