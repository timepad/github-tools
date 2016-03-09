<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TpReleaseNotes\LocalGit\LocalGit;

class RebindTags extends Command {
    public function configure() {
        $this->setDefinition(
            [
                new InputOption('repo', null, InputOption::VALUE_REQUIRED, 'Local repo path'),
            ]
        )
            ->setDescription('Выводит скрипт которым можно воссоздать тэги на другом репозитории');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $repo = $input->getOption('repo');
        $git  = new LocalGit($repo);
        $git->fetch();
        $tags = $git->tags();


        foreach ($tags as $i => $tag) {
            echo "git tag -d {$tag->name}\n";
            echo "GIT_COMMITTER_DATE=\"{$tag->date_raw}\" ";
            echo "git tag -a {$tag->name} {$tag->ref} -m \"{$tag->name}\"\n";
            echo "git push otp refs/tags/{$tag->name}\n\n";
        }
    }
}