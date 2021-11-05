<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\UpwardConnector\Console\Command;

use Magento\UpwardConnector\Api\UpwardPathManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class UpwardPathCommand
 *
 * Command for setting the UPWARD yaml config path
 */
class UpwardPathCommand extends Command
{
    private const UPWARD_PATH = 'path';
    private const SCOPE_TYPE = 'scopeType';
    private const SCOPE_CODE = 'scopeCode';

    /** @var \Magento\UpwardConnector\Api\UpwardPathManagerInterface */
    private $pathManager;

    /**
     * @param \Magento\UpwardConnector\Api\UpwardPathManagerInterface $pathManager
     */
    public function __construct(
        UpwardPathManagerInterface $pathManager
    ) {
        $this->pathManager = $pathManager;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('pwa:upward:set')
            ->setDescription('Sets the path to the UPWARD yaml file');

        $this->addOption(
            self::UPWARD_PATH,
            'p',
            InputOption::VALUE_OPTIONAL,
            'UPWARD yaml path',
            ''
        );

        $this->addOption(
            self::SCOPE_TYPE,
            's',
            InputOption::VALUE_OPTIONAL,
            'Scope type <' . implode(',', $this->pathManager->getScopeTypes()) . '>'
        );

        $this->addOption(
            self::SCOPE_CODE,
            'c',
            InputOption::VALUE_OPTIONAL,
            'Scope Code (website or store view code)'
        );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $path = $input->getOption(self::UPWARD_PATH);
            $scopeType = $input->getOption(self::SCOPE_TYPE);
            $scopeCode = $input->getOption(self::SCOPE_CODE);

            if ($scopeType && !$scopeCode) {
                $output->writeln('<error>Please enter a valid scope code</error>');

                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }

            if ($scopeType) {
                $this->pathManager->setPath($path, $scopeType, $scopeCode);
            } else {
                $this->pathManager->setPath($path);
            }

            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}
