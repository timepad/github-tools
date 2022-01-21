<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Command;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Github\AuthMethod;
use Github\Client as GithubClient;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TpReleaseNotes\Github\CommitsExtrasClient;
use TpReleaseNotes\Printable\Pull;
use TpReleaseNotes\Youtrack\Client as YTClient;

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

                new InputOption('rc_id', null, InputOption::VALUE_REQUIRED, 'Number, tag or something naming the RC'),
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

        $rc_id = $input->getOption('rc_id');

        $rc_source_branch = $input->getOption('rc_source_branch');
        $rc_target_branch = $input->getOption('rc_target_branch');

        $l = function ($msg) use ($output) {
            $output->writeln($msg);
        };

        $rc_branch_name = "test_rc_$rc_id";

        /** @var YTClient $yt_client */
        $yt_client = null;

        if ($yt_host && $yt_token) {
            $output->writeln("Adding youtrack data");
            $yt_client = new YTClient($yt_host, $yt_token);

            // create YT issue
            // $rc_branch_name = ...
        }

        $outfile = "./out/rc.md";

        $gh_client = new GithubClient();
        $cache_pool = new FilesystemCachePool(new Filesystem(new Local('/tmp/github-api-cache')));
        $cache_pool->setFolder($rc_id);
        $gh_client->addCache($cache_pool);
        $gh_client->authenticate($token, AuthMethod::ACCESS_TOKEN);

        foreach ($github_repos as $repo) {
            $l("Processing $repo $rc_source_branch → $rc_target_branch");

            $gh_source_ref = $gh_client->git()->references()->show($gh_user, $repo, "heads/$rc_source_branch");
            $devmaster_sha = $gh_source_ref["object"]["sha"];

            $l("$repo $rc_source_branch sha: $devmaster_sha");

            $compare_result = $gh_client->repo()->commits()->compare($gh_user, $repo, $rc_target_branch, $devmaster_sha);
            $compare_status = $compare_result["status"]; // diverged

            $l("$repo $rc_source_branch → $rc_target_branch: $compare_status");

            if (in_array($compare_status, ["diverged", "ahead"])) {
                // create rc branch if not exists
                $pr_ref = null;

                try {
                    $l("$repo looking for branch for pr named $rc_branch_name");
                    $pr_ref = $gh_client->git()->references()->show($gh_user, $repo, "heads/$rc_branch_name");
                } catch (\Github\Exception\RuntimeException $ge) {
                    // https://stackoverflow.com/a/9513594
                    $l("$repo welp... '{$ge->getMessage()}', creating branch for pr named $rc_branch_name");
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
                    // https://docs.github.com/en/rest/reference/pulls#create-a-pull-request
                    $pr_object = $gh_client->pr()->create($gh_user, $repo, [
                        "base" => $rc_target_branch,
                        "head" => "$gh_user:$rc_branch_name",
                        "title" => "Выпущен пакет изменений $rc_id",
                        "body" => "Тут будет ссылка на ютрек",
                    ]);
                }

                $pr_number = $pr_object["number"];
                $pr_url = $pr_object["html_url"];

                $l("$repo pr: $pr_url");

                $extraClient = new CommitsExtrasClient($gh_client);

                $get_prs_recursive = function($commits, &$result, $log_context) use (&$get_prs_recursive, $gh_client, $extraClient, $gh_user, $repo, $l) {
                    foreach ($commits as $prc) {
                        $l("$log_context analyzing commit {$prc["sha"]} {$prc["commit"]["message"]}");

                        // если это мерж коммит то не мудрим и просто идем в тот PR
                        if (preg_match("!^Merge pull request #(?'pr'\d+)!siu", $prc["commit"]["message"], $matches)) {
                            $l("$log_context It's a merge commit of pr #{$matches['pr']}");
                            $prc_prs = [$gh_client->pr()->show($gh_user, $repo, $matches['pr'])];
                        } else {
                            // analyze PR contents https://docs.github.com/en/rest/reference/commits#list-pull-requests-associated-with-a-commit
                            $l("$log_context Asking github");
                            $prc_prs = $extraClient->commitPulls($gh_user, $repo, $prc["sha"], ["state" => "closed"]);
                        }

                        foreach ($prc_prs as $prc_pr) {
                            $child_pr_number = $prc_pr["number"];
                            $l("$log_context analyzing commit {$prc["sha"]} pr $child_pr_number {$prc_pr["title"]} {$prc_pr["html_url"]}");

                            if ($prc_pr["state"] != "closed") {
                                $l("$log_context analyzing commit {$prc["sha"]} pr $child_pr_number is not closed");
                                continue;
                            }

                            if (array_key_exists($child_pr_number, $result)) {
                                $l("$log_context analyzing commit {$prc["sha"]} pr $child_pr_number already analyzed");
                                continue;
                            }

                            $result[$child_pr_number] = $prc_pr;

                            $get_prs_recursive($gh_client->pullRequest()->commits($gh_user, $repo, $child_pr_number), $result, $log_context . "/$child_pr_number");

                            if (preg_match("!^revert-(?'rpr'\d+)!siu", $prc_pr["head"]["ref"], $matches)) {
                                $l("$log_context pr $child_pr_number is a revert of pr#{$matches['rpr']}, will analyze it too");

                                $get_prs_recursive($gh_client->pullRequest()->commits($gh_user, $repo, $matches['rpr']), $result, $log_context . "/{$matches['rpr']}");
                            }
                        }
                    }
                };

                $rc_prs = [];
                $get_prs_recursive($compare_result["commits"], $rc_prs, "{$repo}/$pr_number");

                $pr_body = "Релиз кандидат содержит следующие изменения в $rc_source_branch относительно $rc_target_branch:\n";

                foreach ($rc_prs as $rc_pr) {
                    $l("$repo RC PR #{$rc_pr["number"]} {$rc_pr["title"]} {$rc_pr["html_url"]}");

                    $pr_printable = Pull::createFromGHPull($rc_pr, $yt_client, null, $output);
                    $pr_body .= $pr_printable->printStingForTracker() . "\n";

                    //$pr_body .= "* #{$rc_pr["number"]}\n";
                }

                $gh_client->pr()->update($gh_user, $repo, $pr_number, [
                    "body" => $pr_body
                ]);

                $l("break");
            }

            //

            // analyze PR contents https://docs.github.com/en/rest/reference/commits#list-pull-requests-associated-with-a-commit
            // export to gh and yt
        }


        //file_put_contents($outfile, $release_notes);

    }
}