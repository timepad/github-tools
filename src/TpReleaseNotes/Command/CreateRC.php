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
use TpReleaseNotes\Github\PrAggregated;
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

    /** @var string[] */
    protected $github_repos;

    /** @var string */
    protected $gh_user;

    /**
     * @var GithubClient
     */
    protected $gh_client;

    /**
     * @var YTClient
     */
    protected $yt_client;

    protected $yt_issue = null;

    /** @var string */
    protected $rc_source_branch;

    /** @var string */
    protected $rc_target_branch;

    /**
     * @var string
     */
    protected $rc_branch_name;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $rc_id;

    public function execute(InputInterface $input, OutputInterface $output) {
        $token    = $input->getOption('github_token');
        $this->gh_user  = $input->getOption('github_user');

        /** @var string[] $repos */
        $this->github_repos = explode(',', $input->getOption('github_repos'));

        $yt_token   = $input->getOption('yt_token');
        $yt_host    = $input->getOption('yt_host');
        $yt_project = $input->getOption('yt_project');

        $this->rc_id = $input->getOption('rc_id');

        $this->rc_source_branch = $input->getOption('rc_source_branch');
        $this->rc_target_branch = $input->getOption('rc_target_branch');

        $this->output = $output;

        $l = function ($msg) { $this->log($msg); };

        $this->rc_branch_name = "test_rc_{$this->rc_id}";

        $this->yt_client = null;

        if ($yt_host && $yt_token) {
            $output->writeln("Adding youtrack data");
            $this->yt_client = new YTClient($yt_host, $yt_token);
        }

        $this->gh_client = new GithubClient();
        $cache_pool = new FilesystemCachePool(new Filesystem(new Local('/tmp/github-api-cache')));
        $cache_pool->setFolder($this->rc_id);
        $this->gh_client->addCache($cache_pool);
        $this->gh_client->authenticate($token, AuthMethod::ACCESS_TOKEN);

        $rc_prs = [];

        foreach ($this->github_repos as $repo) {
            $rc_pr = $this->createOrFindPullForRC($repo);

            if (!$rc_pr) {
                continue;
            }

            $this->populateChildPRs($rc_pr);

            $rc_prs[$repo] = $rc_pr;
        }

        foreach ($rc_prs as $repo => $rc_pr) {
            $pr_body = "Релиз кандидат содержит следующие изменения в {$this->rc_source_branch} относительно {$this->rc_target_branch}:\n";

            foreach ($rc_pr->children as $rcc_pr) {
                $l("$repo RC PR #{$rcc_pr["number"]} {$rcc_pr["title"]} {$rcc_pr["html_url"]}");

                $pr_printable = Pull::createFromGHPull($rcc_pr->pr_object, $this->yt_client, null, $output);
                $pr_body .= $pr_printable->printStingForTracker() . "\n";
            }

            $this->gh_client->pr()->update($this->gh_user, $repo, $rc_pr->number, [
                "body" => $pr_body
            ]);

            $l("break");
        }


        if ($this->yt_client) {
            if (!$this->yt_issue) {

            }
        }

    }

    protected function createOrFindPullForRC($repo) : ?PrAggregated {
        $l = function ($msg) use ($repo) { $this->log("[{$repo}] $msg"); };

        $l("processing {$this->rc_source_branch} → {$this->rc_target_branch}");

        $gh_source_ref = $this->gh_client->git()->references()->show($this->gh_user, $repo, "heads/{$this->rc_source_branch}");
        $devmaster_sha = $gh_source_ref["object"]["sha"];

        $l("{$this->rc_source_branch} sha: $devmaster_sha");

        $compare_result = $this->gh_client->repo()->commits()->compare($this->gh_user, $repo, $this->rc_target_branch, $devmaster_sha);
        $compare_status = $compare_result["status"]; // diverged

        $l("{$this->rc_source_branch} → {$this->rc_target_branch}: $compare_status");

        if (in_array($compare_status, ["diverged", "ahead"])) {
            // create rc branch if not exists
            $pr_ref = null;

            try {
                $l("looking for branch for pr named {$this->rc_branch_name}");
                $pr_ref = $this->gh_client->git()->references()->show($this->gh_user, $repo, "heads/{$this->rc_branch_name}");
            } catch (\Github\Exception\RuntimeException $ge) {
                // https://stackoverflow.com/a/9513594
                $l("welp... '{$ge->getMessage()}', creating branch for pr named {$this->rc_branch_name}");
                $pr_ref = $this->gh_client->git()->references()->create($this->gh_user, $repo, ["ref" => "refs/heads/{$this->rc_branch_name}", "sha" => $devmaster_sha]);
            }

            $pr_ref_sha = $pr_ref["object"]["sha"];
            $l("$repo branch for pr: {$this->rc_branch_name} with sha $pr_ref_sha");

            $l("$repo looking for pr {$this->rc_branch_name} → {$this->rc_target_branch}");
            $pr_candidates = $this->gh_client->pr()->all($this->gh_user, $repo, ["base" => $this->rc_target_branch, "head" => "{$this->gh_user}:{$this->rc_branch_name}"]);
            if (count($pr_candidates)) {
                $pr_object = new PrAggregated($pr_candidates[0]);

                if ($this->yt_client && !$this->yt_issue) {
                    $rc_pr_printable = Pull::createFromGHPull($pr_object->pr_object, $this->yt_client, null, $this->output);

                    if (count($rc_pr_printable->yt_issues)) {
                        $yt_issue = $rc_pr_printable->yt_issues[0]->yt_id;
                    }
                }
            } else {
                $l("$repo welp... creating pr {$this->rc_branch_name} → {$this->rc_target_branch}");
                // https://docs.github.com/en/rest/reference/pulls#create-a-pull-request
                $pr_object = new PrAggregated($this->gh_client->pr()->create($this->gh_user, $repo, [
                    "base" => $this->rc_target_branch,
                    "head" => "{$this->gh_user}:{$this->rc_branch_name}",
                    "title" => "Выпущен пакет изменений {$this->rc_id}",
                    "body" => "Тут будет ссылка на ютрек",
                ]));
            }

            $pr_object->commits = $compare_result["commits"];

            $l("$repo pr: {$pr_object->url}");

            return $pr_object;
        }

        return null;
    }

    protected function populateChildPRs(PrAggregated $rc_pr) {
        $extraClient = new CommitsExtrasClient($this->gh_client);
        $repo = $rc_pr->repo;

        $l = function ($msg) use ($repo) { $this->log("[{$repo}] $msg"); };

        $get_prs_recursive = function ($commits, &$result, $log_context) use ($repo, &$get_prs_recursive, $extraClient, $l) {
            foreach ($commits as $prc) {
                $l("$log_context analyzing commit {$prc["sha"]} {$prc["commit"]["message"]}");
                $prc_prs = [];

                // если это мерж коммит то не мудрим и просто идем в тот PR
                if (preg_match("!^Merge pull request #(?'pr'\d+)!siu", $prc["commit"]["message"], $matches)) {
                    $l("$log_context It's a merge commit of pr #{$matches['pr']}");
                    $prc_prs = [$this->gh_client->pr()->show($this->gh_user, $repo, $matches['pr'])];
                } else {
                    // analyze PR contents https://docs.github.com/en/rest/reference/commits#list-pull-requests-associated-with-a-commit
                    $l("$log_context Asking github");
                    $prc_prs = $extraClient->commitPulls($this->gh_user, $repo, $prc["sha"], ["state" => "closed"]);
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

                    $get_prs_recursive($this->gh_client->pullRequest()->commits($this->gh_user, $repo, $child_pr_number), $result, $log_context . "/$child_pr_number");

                    if (preg_match("!^revert-(?'rpr'\d+)!siu", $prc_pr["head"]["ref"], $matches)) {
                        $l("$log_context pr $child_pr_number is a revert of pr#{$matches['rpr']}, will analyze it too");

                        $get_prs_recursive($this->gh_client->pullRequest()->commits($this->gh_user, $repo, $matches['rpr']), $result, $log_context . "/{$matches['rpr']}");
                    }
                }
            }
        };

        $rc_contents_prs = [];
        $get_prs_recursive($rc_pr->commits, $rc_contents_prs, "{$rc_pr->number}");

        foreach ($rc_contents_prs as $rc_contents_pr) {
            $rc_pr->children[] = new PrAggregated($rc_contents_pr);
        }
    }

    protected function log($msg) {
        $this->output->writeln($msg);
    }
}