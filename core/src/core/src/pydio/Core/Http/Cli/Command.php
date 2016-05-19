<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <http://pyd.io/>.
 */
namespace Pydio\Core\Http\Cli;

defined('AJXP_EXEC') or die('Access not allowed');
use Pydio\Core\Http\Server;
use Symfony;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this->setDefinition(new FreeDefOptions());
        $this
            ->setName('pydio')
            ->setDescription('Pydio Command Line')
            ->addOption(
                'cli_username',
                'u',
                InputOption::VALUE_REQUIRED,
                'User id or user token'
            )->addOption(
                'cli_password',
                'p',
                InputOption::VALUE_OPTIONAL,
                'User Password'
            )->addOption(
                'cli_token',
                't',
                InputOption::VALUE_OPTIONAL,
                'Encrypted Token used to replace password'
            )->addOption(
                'cli_impersonate',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Comma separated list of users to impersonate. Only possible if authenticated user is ADMIN.'
            )->addOption(
                'cli_repository_id',
                'r',
                InputOption::VALUE_REQUIRED,
                'Repository ID or alias, can be a comma-separated list'
            )->addOption(
                'cli_action_name',
                'a',
                InputOption::VALUE_REQUIRED,
                'Action name to apply'
            )->addOption(
                'cli_status_file',
                's',
                InputOption::VALUE_OPTIONAL,
                'Path to a file to write status information about the running task'
            )->addOption(
                'cli_task_uuid',
                'k',
                InputOption::VALUE_OPTIONAL,
                'Task Uuid'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $definitionsKeys = array_keys($this->getDefinition()->getOptions());
        $actionParameters = [];
        $pydioCliOptions = [];
        foreach ($input->getOptions() as $key => $option){
            if(in_array($key, $definitionsKeys)){
                if(strpos($key, "cli_") === 0) {
                    $shortcut = $this->getDefinition()->getOption($key)->getShortcut();
                    $pydioCliOptions[$shortcut] = FreeArgvOptions::removeEqualsSign($option);
                }
            }else{
                $actionParameters[$key] = $option;
            }
        }

        if(!is_array($pydioCliOptions["r"])){
            $pydioCliOptions["r"] = [$pydioCliOptions["r"]];
        }
        $taskUid = $pydioCliOptions["k"];
        foreach ($pydioCliOptions["r"] as $repoId){
            $reqOptions = $pydioCliOptions;
            $reqOptions["r"] = $repoId;

            $server = new Server(Server::MODE_CLI);
            $request = $server->getRequest();
            $request = $request
                ->withParsedBody($actionParameters)
                ->withAttribute("api", "cli")
                ->withAttribute("cli-options", $reqOptions)
                ->withAttribute("cli-output", $output);
            if(!empty($taskUid)){
                $request = $request->withAttribute("pydio-task-id", $taskUid);
            }
            $server->updateRequest($request);
            $server->listen();
        }
    }
}