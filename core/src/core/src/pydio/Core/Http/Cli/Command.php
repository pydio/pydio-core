<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Http\Cli;

defined('AJXP_EXEC') or die('Access not allowed');
use Pydio\Core\PluginFramework\PluginsService;
use Symfony;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class Command
 * Pydio implementation of Symfony command
 * @package Pydio\Core\Http\Cli
 */
class Command extends Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this->setDefinition(new FreeDefOptions());
        $this
            ->setName('pydio')
            ->setDescription('Pydio Command Line Tool. This tool can load any action defined in the framework. Should be used to run time-consuming task in background without hogging the webserver.')
            ->addOption(
                'cli_username',
                'u',
                InputOption::VALUE_REQUIRED,
                '[Mandatory] User id or user token'
            )->addOption(
                'cli_repository_id',
                'r',
                InputOption::VALUE_REQUIRED,
                '[Mandatory] Repository id or alias, can be a comma-separated list of identifier, or "*" for all repositories of the current user.'
            )->addOption(
                'cli_action_name',
                'a',
                InputOption::VALUE_REQUIRED,
                '[Mandatory] Action name to apply. If not passed, command will interactively ask for the action, with autocompletion feature.'
            )->addOption(
                'cli_password',
                'p',
                InputOption::VALUE_OPTIONAL,
                'User Password. You can pass either a passowrd (p) or a token (t). If none of them passed, command will ask for password interactively.'
            )->addOption(
                'cli_token',
                't',
                InputOption::VALUE_OPTIONAL,
                'Encrypted Token used to replace password'
            )->addOption(
                'cli_impersonate',
                'i',
                InputOption::VALUE_OPTIONAL,
                'If authenticated user has administrative role, apply action under a different user name. Possible values are: a comma-separated list of users login, "*" means all users in the current group, "**/*" means all users from all groups recursively.'
            )->addOption(
                'cli_status_file',
                's',
                InputOption::VALUE_OPTIONAL,
                'Path to a file to write status information about the running task.'
            )->addOption(
                'cli_task_uuid',
                'k',
                InputOption::VALUE_OPTIONAL,
                'Task uuid: command will store status information in the corresponding task.'
            )
        ;
    }

    /**
     * Execture the command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $server = new CliServer("/");
        $server->registerCatchAll();

        $definitionsKeys = array_keys($this->getDefinition()->getOptions());
        $actionParameters = [];
        $pydioCliOptions = [];
        foreach ($input->getOptions() as $key => $option){
            if(in_array($key, $definitionsKeys)){
                if(strpos($key, "cli_") === 0) {
                    $shortcut = $this->getDefinition()->getOption($key)->getShortcut();
                    $pydioCliOptions[$shortcut] = $option;
                }
            }else{
                $actionParameters[$key] = $option;
            }
        }

        $helper = $this->getHelper("question");
        if(empty($pydioCliOptions["p"]) && empty($pydioCliOptions["t"])){
            // Ask password interactively
            $question = new Question('Please enter the password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
            $pydioCliOptions["p"] = $password;
        }
        if(empty($pydioCliOptions["a"])){
            $actions = PluginsService::searchManifestsWithCache("//actions/action", function($resultNodes){
                $output = [];
                /** @var \DOMElement[] $resultNodes */
                foreach($resultNodes as $node){
                    $output[] = $node->getAttribute("name");
                }
                return $output;
            });
            $question = new Question('Please type in an action to apply: ', '');
            $question->setAutocompleterValues($actions);
            $pydioCliOptions["a"] = $helper->ask($input, $output, $question);
        }

        $taskUid = $pydioCliOptions["k"];
        $request = $server->getRequest();

        $request = $request
            ->withParsedBody($actionParameters)
            ->withAttribute("api", "cli")
            ->withAttribute("cli-options", $pydioCliOptions)
            ->withAttribute("cli-output", $output)
            ->withAttribute("cli-input", $input)
            ->withAttribute("cli-command", $this);

        if(!empty($taskUid)){
            $request = $request->withAttribute("pydio-task-id", $taskUid);
        }

        $server->updateRequest($request);
        $server->listen();
    }
}