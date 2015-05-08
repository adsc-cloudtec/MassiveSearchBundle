<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Massive\Bundle\SearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * Command to execute a query on the configured search engine
 */
class QueryCommand extends ContainerAwareCommand
{
    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName('massive:search:query');
        $this->addArgument('query', InputArgument::REQUIRED, 'Search query');
        $this->addOption('index', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Index to search');
        $this->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Index to search');
        $this->setDescription('Search the using a given query');
        $this->setHelp(<<<EOT
The %command.name_full% command will search the configured repository and present
the results.

     %command.name_full% "This is a query string" --index=content
EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $query = $input->getArgument('query');
        $indexes = $input->getOption('index');
        $locale = $input->getOption('locale');

        $searchManager = $this->getContainer()->get('massive_search.search_manager');
        $start = microtime(true);
        $hits = $searchManager->createSearch($query)->indexes($indexes)->locale($locale)->execute();
        $timeElapsed = microtime(true) - $start;

        $table = new Table($output);
        $table->setHeaders(array('Score', 'ID', 'Title', 'Description', 'Url', 'Image', 'Class'));
        foreach ($hits as $hit) {
            $document = $hit->getDocument();
            $table->addRow(array(
                $hit->getScore(),
                $document->getId(),
                $document->getTitle(),
                $this->truncate($document->getDescription(), 50),
                $document->getUrl(),
                $document->getImageUrl(),
                $document->getClass(),
            ));
        }
        $table->render();
        $output->writeln(sprintf('%s result(s) in %fs', count($hits), $timeElapsed));
    }

    /**
     * Truncate the given string
     *
     * See: https://github.com/symfony/symfony/issues/11977
     *
     * @param string Text to truncate
     * @param integer Length
     * @param string Suffix to append
     */
    private function truncate($text, $length, $suffix = '...')
    {
        $computedLength = $length - strlen($suffix);

        return strlen($text) > $computedLength ? substr($text, 0, $computedLength) . $suffix : $text;
    }
}
