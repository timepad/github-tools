<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Command;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Github\AuthMethod;
use Github\Client as GithubClient;
use Github\HttpClient\CachedHttpClient;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
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

class GenerateReleaseNotes extends Command {
    public function configure() {
        $this->setDefinition(
            [
                new InputOption('github_token', null, InputOption::VALUE_REQUIRED, 'Github auth token'),
                new InputOption('github_user', null, InputOption::VALUE_REQUIRED, 'Github user/org'),
                new InputOption('github_repo', null, InputOption::VALUE_REQUIRED, 'Github repo'),
                new InputOption('from_tag', null, InputOption::VALUE_OPTIONAL, 'Tag to start'),
                new InputOption('from_rev', null, InputOption::VALUE_OPTIONAL, 'Interpret current rev as provided tag', false),
                new InputOption('filter_bad_tags', null, InputOption::VALUE_OPTIONAL, 'Filter bad tags', false),
                new InputOption('repo', null, InputOption::VALUE_REQUIRED, 'Local repo path'),
                new InputOption('title', null, InputOption::VALUE_OPTIONAL, 'Project title'),
                new InputOption('mail_to', null, InputOption::VALUE_OPTIONAL, 'Send release notes to Email'),
                new InputOption('mail_from', null, InputOption::VALUE_OPTIONAL, 'From: email', "no-reply@timepad.ru"),
                new InputOption('postmark_api', null, InputOption::VALUE_OPTIONAL, 'Postmark API key', "no-reply@timepad.ru"),
                new InputOption('pr_overiteration_limit', null, InputOption::VALUE_OPTIONAL, 'Сколько попыток найти следующий PR для тега делать', 3),
                new InputOption('pr_master_only', null, InputOption::VALUE_OPTIONAL, 'Выводить только PR в мастер'),
                new InputOption('yt_token', null, InputOption::VALUE_OPTIONAL, 'Youtrack auth token'),
                new InputOption('yt_host', null, InputOption::VALUE_OPTIONAL, 'Youtrack hostname'),
                new InputOption('yt_mark_released', null, InputOption::VALUE_OPTIONAL, 'Youtrack mark released'),
                new InputOption('zd_user', null, InputOption::VALUE_OPTIONAL, 'Zendesk auth user'),
                new InputOption('zd_token', null, InputOption::VALUE_OPTIONAL, 'Zendesk auth token'),
                new InputOption('zd_subdomain', null, InputOption::VALUE_OPTIONAL, 'Zendesk subdomain'),
                new InputOption('zd_reply', false, InputOption::VALUE_OPTIONAL, 'Zendesk autoreply'),
                new InputOption('tg_token', false, InputOption::VALUE_OPTIONAL, 'Telegram bot token'),
                new InputOption('tg_chats', false, InputOption::VALUE_OPTIONAL, 'Telegram bot chat ids (space separated)'),
                new InputOption('tg_proxy', false, InputOption::VALUE_OPTIONAL, 'Telegram proxy'),
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
        $from_rev = $input->getOption('from_rev');
        $filter_bad_tags = $input->getOption('filter_bad_tags');
        $pr_master_only = !!$input->getOption('pr_master_only');

        $yt_token   = $input->getOption('yt_token');
        $yt_host    = $input->getOption('yt_host');
        $yt_mark_released = !!$input->getOption('yt_mark_released');

        $zd_token   = $input->getOption('zd_token');
        $zd_user    = $input->getOption('zd_user');
        $zd_subdomain    = $input->getOption('zd_subdomain');
        $zd_reply   = $input->getOption('zd_reply');

        $tg_token   = $input->getOption('tg_token');
        $tg_chats   = explode(" ", $input->getOption('tg_chats'));
        $tg_proxy   = $input->getOption('tg_proxy');

        /** @var YTClient $yt_client */
        $yt_client = null;

        if ($yt_host && $yt_token) {
            $output->writeln("Adding youtrack data");
            $yt_client = new YTClient($yt_host, $yt_token);
        }

        /** @var ZendeskAPI $zd_client */
        $zd_client = null;

        if ($zd_subdomain && $zd_user && $zd_token) {
            $output->writeln("Adding zendesk data");

            try {
                $zd_client = new ZendeskAPI($zd_subdomain);
                $zd_client->setAuth('basic', ['username' => $zd_user, 'token' => $zd_token]);
            } catch (\Exception $e) {
                $output->writeln("Failed to connect to zd");
                $zd_client = null;
            }
        }

        if (!$outfile) {
            $outfile = "./out/{$gh_user}_{$gh_repo}.md";
        }

        $client = new GithubClient();
        $client->addCache(new FilesystemCachePool(new Filesystem(new Local('/tmp/github-api-cache'))));
        $client->authenticate($token, AuthMethod::ACCESS_TOKEN);

        $git = new LocalGit($repo);
        $git->fetch();
        $tags = $git->tags($filter_bad_tags);

        if ($from_rev) {
            $current_rev = $git->current_rev();
            $output->writeln("Adding virtual tag $from_tag@$current_rev");
            $nonexistent_tag = new Tag($from_tag, $current_rev, date_create()->format("Y-m-d H:i:s"));
            array_unshift($tags, $nonexistent_tag);
        }

        $weight_tag = function($tag) {
            if ($tag instanceof PrintableTag) {
                $tag = $tag->tag;
            }

            preg_match("#(?'major'\\d+)(?:\\.(?'minor'\\d+)(?:\\.(?'patch'\\d+))?)?#siu", $tag, $matches);

            $result = 0;
            foreach (['patch', 'minor', 'major'] as $i => $part) {
                $p = isset($matches[$part]) ? $matches[$part] : 0;
                $result += pow(10000, $i) * +$p;
            }

            return $result;
        };

        $compare_tags = function($a, $b) use ($weight_tag) {

            $a = $weight_tag($a);
            $b = $weight_tag($b);

            if ($a == $b) {
                return 0;
            } else {
                return ($a > $b) ? 1 : -1;
            }
        };

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

            if ($weight_tag($from_tag) == $weight_tag($tag->name)) {
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

        /** @var \TpReleaseNotes\Printable\Tag[] $processedTags */
        $processedTags = [];

        $prFilter = [
            'state'     => 'closed',
            'sort'      => 'updated',
            'direction' => 'desc'
        ];

        if ($pr_master_only) {
            $prFilter['base'] = 'master';
        }

        $stopIteration = 0;
        $stopIterationLimit = +$input->getOption('pr_overiteration_limit');

        Util::paginateAll(
            $client, 'PullRequest', 'all',
            [$gh_user, $gh_repo, $prFilter],
            function ($pull) use (&$revTags, &$processedTags, $output, &$stopIteration, $stopIterationLimit, $yt_client, $zd_client, $zd_reply, $pr_master_only) {
                if (!$pull['merged_at']) {
                    $output->writeln("{$pull['number']} was not merged, skipping");

                    return null;
                }

                if (!isset($revTags[$pull['head']['sha']])) {
                    $output->writeln("{$pull['number']} has no matching tag, probably out of bounds");
                    $stopIteration++;

                    if ($stopIteration > $stopIterationLimit) {
                        $output->writeln("Stopping now");

                        return "stop";
                    } else {
                        $output->writeln("Will try another PR");

                        return null;
                    }
                }

                $output->writeln("getting notes for pull {$pull['number']}");

                $tag = $revTags[$pull['head']['sha']];

                $output->writeln("{$pull['number']} is assigned to tag {$tag->name}");

                if (!isset($processedTags[$tag->name])) {
                    $processedTags[$tag->name] = new PrintableTag($tag->name, $tag->date);
                }

                $p_pull = PrintablePull::createFromGHPull($pull, $yt_client, $zd_client);
                $processedTags[$tag->name]->addPull($p_pull);

                $stopIteration = 0;

                return null;
        }
        );

        uksort($processedTags, $compare_tags);

        /** @var PrintableTag[] $tags_to_print */
        $tags_to_print = [];
        foreach ($processedTags as $tag_id => $tag) {
            if (count($tag->pulls)) {
                if ($from_tag && ($weight_tag($tag) < $weight_tag($from_tag))) {
                    $output->writeln("Tag limited at {$from_tag}, skipping notes for tar {$tag->tag}");
                    continue;
                }

                $tags_to_print[$tag_id] = $tag;
            }
        }


        /** @var string[] $release_notes */
        $release_notes = [];

        $tags_count = count($tags_to_print);

        if (!$tags_count) {
            $output->writeln("No release notes to write");
            exit();
        }

        $title_append = $input->hasOption('title') ? " {$input->getOption('title')}" : "";
        $truncate_minor = function($tag) {
            return Util::truncateMinorVersion($tag);
        };

        $tag_keys = array_keys($tags_to_print);
        $first_tag = $truncate_minor(array_shift($tag_keys));

        $tag_keys = array_keys($tags_to_print);
        $last_tag = $truncate_minor(array_pop($tag_keys));

        if ($tags_count == 1) {
            $subject = "Релиз{$title_append} $first_tag";
        } else {
            $subject = "Релизы{$title_append} {$first_tag}-{$last_tag}";
        }

        if ($tg_token) {
            $output->writeln("Preparing TG message");

            try {
                $bot        = new \TelegramBot\Api\BotApi($tg_token);
                if ($tg_proxy) {
                    $bot->setCurlOption(CURLOPT_PROXY, $tg_proxy);
                }

                $tg_message = [];

                $tg_message[] = "🎁 $subject\n";

                foreach ($tags_to_print as $tag => $tagData) {
                    $tg_message[] = $tagData->printSting($tags_count > 1, "tg");
                }

                $tg_message[] = "Подробнее — на почте :)";
                $tg_message[] = "Подпишитесь на канал с релизами: https://t.me/joinchat/AAAAAEkvGd8VBLjNDMWe3w";
                $tg_message[] = "Ваши роботы.";

                $tg_message_text = implode("\n", $tg_message) . "\n";

                foreach ($tg_chats as $tg_chat) {
                    $output->writeln("Sending TG message to $tg_chat");
                    try {
                        $bot->sendMessage("-{$tg_chat}", $tg_message_text);
                    } catch (\Exception $e) {
                        $output->writeln("TG to $tg_chat failed: {$e->getMessage()}");
                    }
                }
            } catch (\Exception $e) {
                $output->writeln("TG failed: {$e->getMessage()}");
            }

            $output->writeln("TG stuff done");
        }

        $release_notes[] = "# $subject";

        foreach ($tags_to_print as $tag => $tagData) {
            $output->writeln("Writing notes for tag {$tag}");

            $release_notes[] = $tagData->printSting($tags_count > 1);

        }

        $release_notes[] = "*****";
        $release_notes[] = "*Это письмо написано роботами.*  ";
        $release_notes[] = "**СЛАВА РОБОТАМ**";

        $release_notes = implode("\n", $release_notes);

        if ($yt_client && $yt_mark_released) {
            $output->writeln("Marking all Done youtrack tickets as Released");

            foreach ($tags_to_print as $tagData) {
                foreach ($tagData->pulls as $pull) {
                    foreach ($pull->yt_issues as $issue) {
                        $output->writeln("Mark released  {$issue->yt_id}");
                        $yt_client->applyCommand("Mark released", [$issue->yt_id]);
                    }
                }
            }
        }

        $mail_to = $input->getOption("mail_to");
        if ($mail_to) {
            $mail_from = $input->getOption("mail_from");
            $postmark_api = $input->getOption("postmark_api");

            if (!($mail_from && $postmark_api)) {
                $output->writeln("Incomplete email config, no mails for u");
            } else {
                $output->writeln("Sending email {$mail_from} -> $mail_to");
                $postmark = new PostmarkClient($postmark_api);

                $stylesheet = file_get_contents("resources/markdown.css");
                $transformed_md = Markdown::defaultTransform($release_notes);
                $body = "<html><head><style>{$stylesheet}</style></head><body><div class='markdown-body'>$transformed_md</div></body>";

                $postmark->sendEmail($mail_from, $mail_to, $subject, $body);
            }
        }

        file_put_contents($outfile, $release_notes);

    }
}