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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Http\Server;
use Symfony\Component\Console\Output\OutputInterface;

defined('AJXP_EXEC') or die('Access not allowed');


class CliMiddleware
{
    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws WorkspaceNotFoundException
     */
    public static function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        /**
         * @var OutputInterface
         */
        $output = $requestInterface->getAttribute("cli-output");
        $options = $requestInterface->getAttribute("cli-options");
        $statusFile = (!empty($options["s"]) ? $options["s"] : false);

        try {

            $responseInterface = Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

            if($responseInterface !== false && $responseInterface->getBody() && $responseInterface->getBody() instanceof SerializableResponseStream){
                // For the moment, use XML by default
                // Todo: Create A CLI Serializer for pretty printing?
                if($requestInterface->getParsedBody()["format"] == "json"){
                    $responseInterface->getBody()->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
                }
            }
            $output->writeln("Executing Action" . $requestInterface->getAttribute("action"));
            $output->writeln("----------------");
            $output->writeln("" . $responseInterface->getBody());
            $output->writeln("");

        } catch (AuthRequiredException $e){

            $output->writeln("ERROR:Authentication Failed.");
            if($statusFile !== false){
                file_put_contents($statusFile, "ERROR:Authentication Failed.");
            }

        }catch (\Exception $e){

            $output->writeln("ERROR:".$e->getMessage());
            if($statusFile !== false){
                file_put_contents($statusFile, "ERROR:Authentication Failed.");
            }

        }


    }
}